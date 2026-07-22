<?php
require __DIR__ . '/../session_guard.php';
require_module_access('cashflow_status');
require_once __DIR__ . '/../db.php';
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

$tierCols = cf_tier_cols();
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
    .page{padding:36px 40px;max-width:1200px}
    .page-header{display:flex;justify-content:space-between;align-items:center;gap:14px;padding-bottom:20px;margin-bottom:4px;border-bottom:1px solid #e2e5ef;flex-wrap:wrap}
    .page-header .title-group{display:flex;align-items:center;gap:14px}
    .page-header .icon-badge{width:42px;height:42px;border-radius:11px;background:#1a1a2e;color:#C9972A;display:flex;align-items:center;justify-content:center;flex-shrink:0}
    .page-header h1{font-family:Georgia,serif;font-size:1.65rem;font-weight:700;line-height:1.15}
    .page-header h1 span{color:#C9972A}
    .sub{font-size:.82rem;color:#6b7280;margin:16px 0 24px}
    .btn{display:inline-flex;align-items:center;gap:6px;padding:9px 18px;border-radius:7px;font-size:.85rem;font-weight:600;cursor:pointer;text-decoration:none;border:none;font-family:inherit}
    .btn-primary{background:#1a1a2e;color:#fff}.btn-primary:hover{background:#2d2d4e}
    .sum-card{margin-bottom:24px}
    .sum-table{width:100%;border-collapse:collapse;font-size:.9rem}
    .sum-table th{text-align:right;font-size:.7rem;color:#9ca3af;text-transform:uppercase;letter-spacing:.03em;padding:4px 0;font-weight:700}
    .sum-table th:first-child{text-align:left}
    .sum-table td{padding:10px 0;text-align:right}
    .sum-table td:first-child{text-align:left;color:#6b7280;font-weight:600}
    .sum-table tr+tr td{border-top:1px solid #f1f0e8}
    .sum-table tr.sum-total td{font-weight:700;border-top:1.5px solid #e2e5ef;padding-top:12px;padding-bottom:12px}
    .sum-table tr.sum-total td:first-child{color:#1a1a2e}
    .sum-table td.muted{color:#9ca3af}
    .trend{font-size:.76rem;font-weight:600;display:inline-block;margin-top:4px}
    .trend.up{color:#16a34a}.trend.down{color:#dc2626}.trend.flat{color:#9ca3af}
    .breakdown{display:grid;grid-template-columns:repeat(3, 1fr);gap:20px}
    @media (max-width: 900px){.breakdown{grid-template-columns:1fr}}
    .card{background:#fff;border:1px solid #e2e5ef;border-radius:12px;padding:22px 26px;box-shadow:0 2px 12px rgba(0,0,0,.05)}
    .card h2{font-size:1rem;font-weight:700;margin-bottom:14px;padding-bottom:10px;border-bottom:1px solid #f1f0e8}
    .row{display:flex;justify-content:space-between;padding:5px 0;font-size:.85rem;border-top:1px solid #f1f0e8;border-radius:4px;transition:background .15s}
    .row:first-of-type{border-top:none}
    .row .k{color:#6b7280}
    .row.row-highlight{background:#e6f7ec}
    .row.row-highlight .k,.row.row-highlight>span:last-child{color:#15803d}
    .tier-num{cursor:default;border-radius:6px;transition:background .15s}
    .tier-num:hover{background:#e6f7ec}
    .empty{text-align:center;padding:64px 20px;color:#9ca3af}
    .empty h2{font-size:1.1rem;margin-bottom:12px;color:#6b7280}
  </style>
</head>
<body>
<div class="cv-layout">
  <?php require __DIR__ . '/../nav.php'; ?>
  <div class="page">
    <div class="page-header">
      <div class="title-group">
        <div class="icon-badge">
          <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
        </div>
        <h1>Cashflow <span>status</span></h1>
      </div>
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

      <div class="card sum-card">
        <table class="sum-table">
          <tr>
            <th></th>
            <th>EOM</th>
            <th>Total liquid</th>
            <th>Total</th>
          </tr>
          <tr>
            <td>Assets</td>
            <td class="tier-num" data-tier="eom_assets"><?= cf_inr($c['eom_assets']) ?></td>
            <td class="tier-num" data-tier="total_liquid_assets"><?= cf_inr($c['total_liquid_assets']) ?></td>
            <td class="tier-num" data-tier="total_assets"><?= cf_inr($c['total_assets']) ?></td>
          </tr>
          <tr>
            <td>Liabilities</td>
            <td class="tier-num" data-tier="eom_liab"><?= cf_inr($c['eom_liab']) ?></td>
            <td class="tier-num" data-tier="total_liquid_liab"><?= cf_inr($c['total_liquid_liab']) ?></td>
            <td class="tier-num" data-tier="total_liab"><?= cf_inr($c['total_liab']) ?></td>
          </tr>
          <tr class="sum-total">
            <td>Position</td>
            <td>
              <div><?= cf_inr($c['eom_position']) ?></div>
              <?= trend_html($c['eom_position'], $cp['eom_position'] ?? null) ?>
            </td>
            <td>
              <div><?= cf_inr($c['total_liquid_position']) ?></div>
              <?= trend_html($c['total_liquid_position'], $cp['total_liquid_position'] ?? null) ?>
            </td>
            <td>
              <div><?= cf_inr($c['total_position']) ?></div>
              <?= trend_html($c['total_position'], $cp['total_position'] ?? null) ?>
            </td>
          </tr>
          <tr>
            <td>Months of cash (salary only)</td>
            <td class="muted">—</td>
            <td><?= $c['months_liquid'] === null ? '—' : round($c['months_liquid'], 2) ?></td>
            <td><?= $c['months_total'] === null ? '—' : round($c['months_total'], 2) ?></td>
          </tr>
        </table>
      </div>

      <div class="breakdown">
        <div class="card">
          <h2>Assets</h2>
          <?php foreach ($fields['Assets'] as $col => $label): ?>
            <div class="row" data-col="<?= h($col) ?>"><span class="k"><?= h($label) ?></span><span><?= cf_inr($latest[$col]) ?></span></div>
          <?php endforeach ?>
        </div>
        <div class="card">
          <h2>EOM liabilities</h2>
          <?php foreach ($fields['Payroll (paid at EOM)'] as $col => $label): ?>
            <div class="row" data-col="<?= h($col) ?>"><span class="k"><?= h($label) ?></span><span><?= cf_inr($latest[$col]) ?></span></div>
          <?php endforeach ?>
        </div>
        <div class="card">
          <h2>Other liabilities</h2>
          <?php foreach ($fields['Other liabilities'] as $col => $label): ?>
            <div class="row" data-col="<?= h($col) ?>"><span class="k"><?= h($label) ?></span><span><?= cf_inr($latest[$col]) ?></span></div>
          <?php endforeach ?>
        </div>
      </div>
    <?php endif ?>
  </div>
</div>
<script>
var TIER_COLS = <?= json_encode($tierCols) ?>;
document.querySelectorAll('.tier-num').forEach(function(cell) {
  var cols = TIER_COLS[cell.dataset.tier] || [];
  var rows = cols.map(function(col) { return document.querySelector('.row[data-col="' + col + '"]'); }).filter(Boolean);
  cell.addEventListener('mouseenter', function() { rows.forEach(function(r) { r.classList.add('row-highlight'); }); });
  cell.addEventListener('mouseleave', function() { rows.forEach(function(r) { r.classList.remove('row-highlight'); }); });
});
</script>
</body>
</html>
