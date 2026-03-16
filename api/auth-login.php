<?php
session_start();

header('Content-Type: application/json; charset=utf-8');

$cfg = require __DIR__ . '/../config.php';
require __DIR__ . '/../fmclient.php';

// Alleen POST toelaten
if ($_SERVER['REQUEST_METHOD'] !== 'POST') 
{
  http_response_code(405);
  echo json_encode(['ok' => false,'error' => 'Method not allowed']);
  exit;
}

// Basis validatie
if (empty($_POST['username']) || empty($_POST['password'])) 
{
  http_response_code(400);
  echo json_encode(['ok' => false,'error' => 'Missing username or password']);
  exit;
}

try 
{
  $fm = new fmclient($cfg);

  // FileMaker login
  $token = $fm->login($_POST['username'], $_POST['password'] );

  // Token + user in session
  $_SESSION['fm_token'] = $token;
  $_SESSION['fm_user']  = $_POST['username'];

  echo json_encode(['ok' => true,'user' => $_POST['username'] ]);
} 
catch (Exception $e) 
{
  http_response_code(401);
  echo json_encode(['ok' => false,'error' => 'Login failed']);
}
