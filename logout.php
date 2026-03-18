<?php
require_once __DIR__ . '/session.php';
session_destroy();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Logging out...</title>
</head>
<body>
    <p>Je bent uitgelogd...</p>
    <p>Je mag dit venster sluiten.</p>
    <script>window.close();</script>
</body>
</html>
