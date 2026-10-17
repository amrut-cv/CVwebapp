<?php
require __DIR__ . '/../session_guard.php';
require_module_access('profitability_planner');
$nav_active = 'profitability_planner';
$v = max(filemtime(__DIR__ . '/app.js'), filemtime(__DIR__ . '/calc.js'), filemtime(__DIR__ . '/planner.css'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Profitability planner — CoreVoice</title>
  <link rel="stylesheet" href="planner.css?v=<?= $v ?>"/>
</head>
<body>
<div class="cv-layout">
  <?php require __DIR__ . '/../nav.php'; ?>
  <div class="page">
    <div id="app"><p class="loading">Loading planner…</p></div>
  </div>
</div>
<script src="calc.js?v=<?= $v ?>"></script>
<script src="app.js?v=<?= $v ?>"></script>
</body>
</html>
