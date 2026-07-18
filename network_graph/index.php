<?php
require __DIR__ . '/../session_guard.php';
require_module_access('network_graph');
$nav_active = 'network';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Network graph — CoreVoice</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="cv-layout">
  <?php require __DIR__ . '/../nav.php'; ?>
  <div class="page">
    <div class="page-header">
      <div class="icon-badge">
        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="5" r="2.5"/><circle cx="5" cy="19" r="2.5"/><circle cx="19" cy="19" r="2.5"/><line x1="12" y1="7.5" x2="6.3" y2="17"/><line x1="12" y1="7.5" x2="17.7" y2="17"/><line x1="7.5" y1="19" x2="16.5" y2="19"/></svg>
      </div>
      <h1>Network <span>graph</span></h1>
    </div>
    <p class="sub">Click any node to make it the center. Toggle between ego and full-network views.</p>
    <div id="network-graph-root"></div>
  </div>
</div>
<script src="data.js"></script>
<script src="graph.js"></script>
</body>
</html>
