<?php
require_once __DIR__ . '/session.php';

header('Content-Type: application/json; charset=utf-8');

// CHECK.
if (!isset($_SESSION['fm_token'])) 
{
  http_response_code(401);
  echo json_encode(['ok' => false, 'error' => 'Not logged in' ]);
  exit;
}

$cfg = require __DIR__ . '/../../config.php';
require __DIR__ . '/../../fmclient.php';

$fm = new fmclient($cfg);

// Date range from query params (YYYY-MM-DD)
$fromISO = $_GET['from'] ?? null;
$toISO   = $_GET['to']   ?? null;

if (!$fromISO || !$toISO)
{
  echo json_encode(['ok' => false, 'error' => 'Missing from/to parameters']);
  exit;
}

// FileMaker expects MM/DD/YYYY
$from = date('m/d/Y', strtotime($fromISO));
$to   = date('m/d/Y', strtotime($toISO));

// _dossiersdata: type, datum_van, datum_tot, _k2_dossier_ID
try 
{
  function firstName($name)
  {
    if (!$name) return null;
    $parts = explode(' ', trim($name));
    return end($parts);
  }

  $query = 
    [
      [
        'datum_van' => "{$from}..{$to}",
        'flag_hou_rekening_voor_planning' => 1
      ], 
      [
        'datum_tot' => "{$from}..{$to}",
        'flag_hou_rekening_voor_planning' => 1
      ], 
      [
        'datum_van' => "..{$from}", 'datum_tot' => "{$to}..",
        'flag_hou_rekening_voor_planning' => 1
      ]
    ];

  $sort = [['fieldName' => '_k2_dossier_ID', 'sortOrder' => 'ascend'], ['fieldName' => 'datum_van',      'sortOrder' => 'ascend']];
  $records = $fm->find($_SESSION['fm_token'],'_dossiersdata', $query, $sort, 5000);

  // group by dossier ID.
  $planning = [];
  $dossierFirstDate = [];

  foreach ($records as $r)
  {
    $fd = $r['fieldData'] ?? [];

    $dossierId = $fd['_k2_dossier_ID'] ?? null;
    if (!$dossierId) continue;

    $van = $fd['datum_van'] ?? null;
    $tot = $fd['datum_tot'] ?? null;

    if (!$van || !$tot) continue;

    // Event opslaan
    $planning[$dossierId][] = ['type' => $fd['type'] ?? null, 'datum_van' => $van, 'datum_tot' => $tot];

    // Eerste datum bepalen (binnen overlap-logica)
    $eventStart = max( strtotime($van), strtotime($from));

    if (!isset($dossierFirstDate[$dossierId]) || $eventStart < $dossierFirstDate[$dossierId]) 
    {
     $dossierFirstDate[$dossierId] = $eventStart;
    }
  }

  uksort($planning, function ($a, $b) use ($dossierFirstDate) 
  { 
    $da = $dossierFirstDate[$a] ?? PHP_INT_MAX;
    $db = $dossierFirstDate[$b] ?? PHP_INT_MAX;
    return $da <=> $db;
  });

  // Dossiers_form_detail: dossiernaam, _k2_dossierStatus_ID, projectleider1_ae, projectleider2_ae
  $dossierIds = array_keys($planning);
  $dossierInfo = [];

  if ($dossierIds)
  {
    $dossierQuery = [];

    foreach ($dossierIds as $id) 
    {
      $dossierQuery[] = ['_k1_dossier_ID' => (string)$id ];
    }
    $dossierRecords = $fm->find($_SESSION['fm_token'],'Dossiers_form_detail',$dossierQuery, [], 5000);

    foreach ($dossierRecords as $r)
    {
      $fd = $r['fieldData'] ?? [];

      $id = $fd['_k1_dossier_ID'] ?? null;
      if (!$id) continue;

      $status = (int)($fd['dossiers_dossierStatussen::volg'] ?? 0);

      // status filter: > 6 = uitsluiten
      if ($status > 6)
      {
        unset($planning[$id]);
        continue;
      }

      $full1 = $fd['projectleider1_ae'] ?? null;
      $full2 = $fd['projectleider2_ae'] ?? null;

      $first1 = firstName($full1);
      $first2 = firstName($full2);

      $projectleidersFull = array_values(array_filter([$full1, $full2]));
      $projectleidersFirst = array_values(array_filter([$first1, $first2]));

      $dossierInfo[$id] = 
      [
        'dossiernaam' => $fd['dossiernaam'] ?? null,
        'status' => $fd['dossiers_dossierStatussen::volg'] ?? null,

        // volledige naam (dropdown + filtering)
        'projectleiders' => $projectleidersFull,

        // enkel voornamen (UI)
        'projectleiders_first' => $projectleidersFirst,
        'projectleiders_text' => implode(', ', $projectleidersFirst)
      ];
    }
  }

  // RESPONSE
  echo json_encode(['ok' => true, 'month' => '2026-01', 'count' => count($planning), 'planning' => $planning, 'dossiers' => $dossierInfo], JSON_PRETTY_PRINT);
}
catch (Exception $e)
{
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
