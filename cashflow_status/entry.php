<?php
require __DIR__ . '/../session_guard.php';
require_module_access('cashflow_status');
require_once __DIR__ . '/../db.php';
require __DIR__ . '/helpers.php';
$fields = require __DIR__ . '/fields.php';
$db = getDB();

$today = date('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cols = [];
    $vals = [];
    foreach ($fields as $items) {
        foreach ($items as $col => $label) {
            $cols[] = $col;
            $vals[] = (float)str_replace(',', '', $_POST[$col] ?? 0);
        }
    }
    $colList         = implode(',', $cols);
    $placeholderList = implode(',', array_fill(0, count($cols), '?'));
    $updateList      = implode(',', array_map(fn($c) => "$c = VALUES($c)", $cols));
    $stmt = $db->prepare(
        "INSERT INTO cashflow_entries (entry_date, $colList, filled_by_email) VALUES (?, $placeholderList, ?)
         ON DUPLICATE KEY UPDATE $updateList, filled_by_email = VALUES(filled_by_email)"
    );
    $stmt->execute([$today, ...$vals, $_SESSION['auth_email']]);
    header('Location: index.php');
    exit;
}

$todayStmt = $db->prepare("SELECT * FROM cashflow_entries WHERE entry_date = ?");
$todayStmt->execute([$today]);
$todayEntry = $todayStmt->fetch();

$lastStmt = $db->prepare("SELECT * FROM cashflow_entries WHERE entry_date < ? ORDER BY entry_date DESC LIMIT 1");
$lastStmt->execute([$today]);
$lastEntry = $lastStmt->fetch();

$daysAgo = $lastEntry ? (int)((strtotime($today) - strtotime($lastEntry['entry_date'])) / 86400) : null;

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_HTML5, 'UTF-8'); }

$nav_active = 'cashflow';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Cashflow entry — CoreVoice</title>
  <style>
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
    body{font-family:'Segoe UI',system-ui,sans-serif;background:#f7f8fc;color:#1a1a2e}
    .page{padding:36px 40px;max-width:760px}
    .page-header{display:flex;align-items:center;gap:14px;padding-bottom:20px;margin-bottom:4px;border-bottom:1px solid #e2e5ef}
    .page-header .icon-badge{width:42px;height:42px;border-radius:11px;background:#1a1a2e;color:#C9972A;display:flex;align-items:center;justify-content:center;flex-shrink:0}
    .page-header h1{font-family:Georgia,serif;font-size:1.65rem;font-weight:700;line-height:1.15}
    .page-header h1 span{color:#C9972A}
    .sub{font-size:.82rem;color:#6b7280;margin:16px 0 24px}
    .card{background:#fff;border:1px solid #e2e5ef;border-radius:12px;padding:24px 28px;box-shadow:0 2px 12px rgba(0,0,0,.05)}
    .sec-cell{padding:20px 0 10px;border-top:none}
    .sec{display:inline-block;background:#f3f4f8;color:#1a1a2e;font-weight:700;font-size:.72rem;text-transform:uppercase;letter-spacing:.04em;padding:5px 12px;border-radius:6px}
    table{width:100%;border-collapse:collapse;font-size:.85rem}
    th{text-align:right;font-size:.7rem;color:#9ca3af;text-transform:uppercase;padding:4px 0;font-weight:700}
    th:first-child{text-align:left}
    td{padding:7px 0;border-top:1px solid #f1f0e8}
    tr:first-of-type td{border-top:none}
    .last-val{text-align:right;color:#9ca3af;width:120px}
    .in-cell{text-align:right;width:150px}
    input.money{width:130px;height:34px;text-align:right;padding:0 10px;border:1.5px solid #d1d5db;border-radius:7px;font-size:.85rem;font-family:inherit;outline:none}
    input.money:focus{border-color:#C9972A}
    .actions{display:flex;justify-content:space-between;margin-top:20px}
    .btn{display:inline-flex;align-items:center;gap:6px;padding:10px 20px;border-radius:7px;font-size:.875rem;font-weight:600;cursor:pointer;text-decoration:none;border:none;font-family:inherit}
    .btn-primary{background:#1a1a2e;color:#fff}.btn-primary:hover{background:#2d2d4e}
    .btn-secondary{background:#fff;border:1.5px solid #d1d5db;color:#374151}
    .btn-secondary:hover{border-color:#C9972A;color:#C9972A}
    .last-val{position:relative}
    .copy-btn{border:none;background:#f3f4f8;color:#6b7280;width:20px;height:20px;border-radius:5px;font-size:.72rem;line-height:1;cursor:pointer;margin-left:6px;vertical-align:middle}
    .copy-btn:hover{background:#fdf6e8;color:#C9972A}
  </style>
</head>
<body>
<div class="cv-layout">
  <?php require __DIR__ . '/../nav.php'; ?>
  <div class="page">
    <div class="page-header">
      <div class="icon-badge">
        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
      </div>
      <h1>Cashflow <span>entry</span></h1>
    </div>
    <p class="sub">
      Entry for <?= h(date('j M Y', strtotime($today))) ?>
      <?php if ($lastEntry): ?>
        &middot; last filled <?= h(date('j M Y', strtotime($lastEntry['entry_date']))) ?>
        (<?= $daysAgo === 0 ? 'today' : $daysAgo . ' day' . ($daysAgo === 1 ? '' : 's') . ' ago' ?>)
      <?php else: ?>
        &middot; no previous entries yet
      <?php endif ?>
    </p>
    <form method="POST" class="card">
      <table>
        <tr>
          <th>Field</th>
          <th class="last-val"><?= $lastEntry ? h(date('j M', strtotime($lastEntry['entry_date']))) : 'Last filled' ?></th>
          <th class="in-cell">Today</th>
        </tr>
        <?php foreach ($fields as $section => $items): ?>
          <tr><td colspan="3" class="sec-cell"><span class="sec"><?= h($section) ?></span></td></tr>
          <?php foreach ($items as $col => $label): ?>
            <tr>
              <td><?= h($label) ?></td>
              <td class="last-val">
                <span class="lv-text"><?= $lastEntry ? cf_digits($lastEntry[$col]) : '—' ?></span>
                <?php if ($lastEntry): ?>
                  <button type="button" class="copy-btn" onclick="copyRow(this)" title="Copy to today">&#8594;</button>
                <?php endif ?>
              </td>
              <td class="in-cell">
                <input class="money" name="<?= h($col) ?>"
                       value="<?= h(cf_digits($todayEntry[$col] ?? $lastEntry[$col] ?? 0)) ?>">
              </td>
            </tr>
          <?php endforeach ?>
        <?php endforeach ?>
      </table>
      <div class="actions">
        <button type="button" class="btn btn-secondary" onclick="clearAll()">Clear all</button>
        <button type="submit" class="btn btn-primary">
          <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
          Save today's entry
        </button>
      </div>
    </form>
  </div>
</div>
<script>
function clearAll() {
  document.querySelectorAll('input.money').forEach(function(i) { i.value = ''; });
}
function copyRow(btn) {
  var tr = btn.closest('tr');
  var val = tr.querySelector('.lv-text').textContent.trim();
  if (val === '—') return;
  tr.querySelector('.money').value = val;
}
</script>
</body>
</html>
