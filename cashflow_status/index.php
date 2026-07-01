<?php
require __DIR__ . '/../session_guard.php';
require __DIR__ . '/../db.php';
require __DIR__ . '/helpers.php';
$fields = require __DIR__ . '/fields.php';
$db = getDB();

$rows   = $db->query("SELECT * FROM cashflow_entries ORDER BY entry_date DESC LIMIT 2")->fetchAll();
$latest = $rows[0] ?? null;
$prev   = $rows[1] ?? null;
$c      = cf_calc($latest);
$cp     = cf_calc($prev);

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_HTML5, 'UTF-8'); }

function trend_html($now, $before) {
    if ($now === null || $before === null) return '';
    $delta = $now - $before;
    if (abs($delta) < 0.5) return '<span class="trend flat">no change</span>';
    $up  = $delta > 0;
    return '<span class="trend ' . ($up ? 'up' : 'down') . '">'
         . ($up ? '&#9650; ' : '&#9660; ') . cf_inr(abs($delta)) . '</span>';
}

$nav_active = 'cashflow';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Cashflow status — CoreVoice</title>
  <style>
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
    body{font-family:'Segoe UI',system-ui,sans-serif;background:#f7f8fc;color:#1a1a2e}
    .page{padding:36px 40px;max-width:900px}
    .page-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;flex-wrap:wrap;gap:12px}
    .page-header h1{font-family:Georgia,serif;font-size:1.5rem;font-weight:700}
    .page-header h1 span{color:#C9972A}
    .sub{font-size:.82rem;color:#6b7280;margin-bottom:24px}
    .btn{display:inline-flex;align-items:center;gap:6px;padding:9px 18px;border-radius:7px;font-size:.85rem;font-weight:600;cursor:pointer;text-decoration:none;border:none;font-family:inherit}
    .btn-primary{background:#1a1a2e;color:#fff}.btn-primary:hover{background:#2d2d4e}
    .grid3{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:14px}
    .grid2{display:grid;grid-template-columns:repeat(2,1fr);gap:14px;margin-bottom:24px}
    .stat{background:#fff;border:1px solid #e2e5ef;border-radius:12px;padding:18px 20px;box-shadow:0 2px 12px rgba(0,0,0,.05)}
    .stat .label{font-size:.72rem;color:#9ca3af;text-transform:uppercase;letter-spacing:.03em;margin-bottom:6px}
    .stat .value{font-size:1.4rem;font-weight:700}
    .trend{font-size:.78rem;font-weight:600;display:inline-block;margin-top:6px}
    .trend.up{color:#16a34a}.trend.down{color:#dc2626}.trend.flat{color:#9ca3af}
    .breakdown{display:grid;grid-template-columns:1fr 1fr;gap:20px}
    .card{background:#fff;border:1px solid #e2e5ef;border-radius:12px;padding:22px 26px;box-shadow:0 2px 12px rgba(0,0,0,.05)}
    .card h2{font-size:.95rem;font-weight:700;margin-bottom:12px}
    .sec{font-size:.7rem;color:#9ca3af;text-transform:uppercase;letter-spacing:.03em;font-weight:700;margin:14px 0 4px}
    .sec:first-of-type{margin-top:0}
    .row{display:flex;justify-content:space-between;padding:5px 0;font-size:.85rem;border-top:1px solid #f1f0e8}
    .row:first-of-type{border-top:none}
    .row.total{font-weight:700;border-top:1.5px solid #e2e5ef;margin-top:4px}
    .row .k{color:#6b7280}
    .row.total .k{color:#1a1a2e}
    .empty{text-align:center;padding:64px 20px;color:#9ca3af}
    .empty h2{font-size:1.1rem;margin-bottom:12px;color:#6b7280}
  </style>
</head>
<body>
<div class="cv-layout">
  <?php require __DIR__ . '/../nav.php'; ?>
  <div class="page">
    <div class="page-header">
      <h1>Cashflow <span>status</span></h1>
      <a href="entry.php" class="btn btn-primary">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Add / update entry
      </a>
    </div>

    <?php if (!$latest): ?>
      <div class="empty">
        <h2>No cashflow data yet</h2>
        <a href="entry.php" class="btn btn-primary">Add first entry</a>
      </div>
    <?php else: ?>
      <?php
        $daysAgo = (int)((strtotime(date('Y-m-d')) - strtotime($latest['entry_date'])) / 86400);
      ?>
      <p class="sub">
        As of <?= h(date('j M Y', strtotime($latest['entry_date']))) ?>
        <?= $daysAgo > 0 ? '(' . $daysAgo . ' day' . ($daysAgo === 1 ? '' : 's') . ' ago)' : '(today)' ?>
        <?php if ($prev): ?>
          &middot; compared to <?= h(date('j M Y', strtotime($prev['entry_date']))) ?>
        <?php endif ?>
      </p>

      <div class="grid3">
        <div class="stat">
          <div class="label">Est EOM cash position</div>
          <div class="value"><?= cf_inr($c['eom_position']) ?></div>
          <?= trend_html($c['eom_position'], $cp['eom_position'] ?? null) ?>
        </div>
        <div class="stat">
          <div class="label">Total liquid position</div>
          <div class="value"><?= cf_inr($c['total_liquid_position']) ?></div>
          <?= trend_html($c['total_liquid_position'], $cp['total_liquid_position'] ?? null) ?>
        </div>
        <div class="stat">
          <div class="label">Total cash position</div>
          <div class="value"><?= cf_inr($c['total_position']) ?></div>
          <?= trend_html($c['total_position'], $cp['total_position'] ?? null) ?>
        </div>
      </div>

      <div class="grid2">
        <div class="stat">
          <div class="label">Months of cash (salary only) &mdash; liquid</div>
          <div class="value"><?= $c['months_liquid'] === null ? '—' : round($c['months_liquid'], 2) ?></div>
        </div>
        <div class="stat">
          <div class="label">Months of cash (salary only) &mdash; total</div>
          <div class="value"><?= $c['months_total'] === null ? '—' : round($c['months_total'], 2) ?></div>
        </div>
      </div>

      <div class="breakdown">
        <div class="card">
          <h2>Assets</h2>
          <?php foreach ($fields['Assets'] as $col => $label): ?>
            <div class="row"><span class="k"><?= h($label) ?></span><span><?= cf_inr($latest[$col]) ?></span></div>
          <?php endforeach ?>
          <div class="row total"><span class="k">EOM liquid assets</span><span><?= cf_inr($c['eom_assets']) ?></span></div>
          <div class="row total"><span class="k">Total liquid assets</span><span><?= cf_inr($c['total_liquid_assets']) ?></span></div>
          <div class="row total"><span class="k">Total assets</span><span><?= cf_inr($c['total_assets']) ?></span></div>
        </div>
        <div class="card">
          <h2>Liabilities</h2>
          <div class="sec">Payroll (paid at EOM)</div>
          <?php foreach ($fields['Payroll (paid at EOM)'] as $col => $label): ?>
            <div class="row"><span class="k"><?= h($label) ?></span><span><?= cf_inr($latest[$col]) ?></span></div>
          <?php endforeach ?>
          <div class="sec">Other liabilities</div>
          <?php foreach ($fields['Other liabilities'] as $col => $label): ?>
            <div class="row"><span class="k"><?= h($label) ?></span><span><?= cf_inr($latest[$col]) ?></span></div>
          <?php endforeach ?>
          <div class="row total"><span class="k">EOM liquid liabilities</span><span><?= cf_inr($c['eom_liab']) ?></span></div>
          <div class="row total"><span class="k">Total liquid liabilities</span><span><?= cf_inr($c['total_liquid_liab']) ?></span></div>
          <div class="row total"><span class="k">Total liabilities</span><span><?= cf_inr($c['total_liab']) ?></span></div>
        </div>
      </div>
    <?php endif ?>
  </div>
</div>
</body>
</html>
