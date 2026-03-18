<?php
require_once __DIR__ . '/session.php';
?>

<!DOCTYPE html>
<html lang="nl">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Planning</title>

  <!-- IMPORTANT: relative path (works with php -S) -->
  <link rel="icon" type="image/x-icon" href="favicon.ico">
  <link rel="stylesheet" href="css/planning.css" />
</head>
<body>

<header class="planning-header-bar">
  <div class="left">
    <h1 class="planning-title">
      <img src="img/logo.svg" class="planning-logo" alt="Planning">
      Planning
    </h1>

    <select id="projectleider">
      <option value="__all__">Alle projectleiders</option>
    </select>

    <button id="refreshBtn" class="refresh-btn">Refresh</button>
    
    <div id="legend" class="planning-legend"></div>

    <span id="status" class="status inline"></span>
    
  </div>

  <div class="right">
    <label for="startDate">Start</label>
    <input id="startDate" type="date" />
    <span id="rangeText" class="range"></span>
    <button onclick="window.location.href='logout.php'" class="logout-btn">Logout</button>
  </div>
</header>

<div id="planning-grid" class="planning-grid"></div>

<!-- IMPORTANT: relative path (works with php -S) -->
<script src="js/planning-api.js"></script>
<script src="js/planning-ui.js"></script>
</body>
</html>
