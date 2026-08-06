<?php
require __DIR__ . '/../session_guard.php';
require_module_access('cashflow_status');
require_once __DIR__ . '/../db.php';
require __DIR__ . '/helpers.php';
$db = getDB();

$rows = $db->query("SELECT entry_date, status, filled_by_email, updated_at FROM cashflow_entries ORDER BY entry_date DESC")->fetchAll();

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_HTML5, 'UTF-8'); }

$nav_active = 'cashflow';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Cashflow entries — CoreVoice</title>
  <style>
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
    body{font-family:'Segoe UI',system-ui,sans-serif;background:#f7f8fc;color:#1a1a2e}
    .page{padding:36px 40px;max-width:900px}
    .page-header{display:flex;justify-content:space-between;align-items:center;gap:14px;padding-bottom:20px;margin-bottom:20px;border-bottom:1px solid #e2e5ef;flex-wrap:wrap}
    .page-header .title-group{display:flex;align-items:center;gap:14px}
    .page-header .icon-badge{width:42px;height:42px;border-radius:11px;background:#1a1a2e;color:#C9972A;display:flex;align-items:center;justify-content:center;flex-shrink:0}
    .page-header h1{font-family:Georgia,serif;font-size:1.65rem;font-weight:700;line-height:1.15}
    .page-header h1 span{color:#C9972A}
    .btn{display:inline-flex;align-items:center;gap:6px;padding:9px 18px;border-radius:7px;font-size:.85rem;font-weight:600;cursor:pointer;text-decoration:none;border:none;font-family:inherit}
    .btn-primary{background:#1a1a2e;color:#fff}.btn-primary:hover{background:#2d2d4e}
    .btn-ghost{background:#fff;border:1.5px solid #d1d5db;color:#374151;padding:6px 14px;font-size:.8rem}
    .btn-ghost:hover{border-color:#C9972A;color:#C9972A}
    .btn-danger{background:#fff;border:1.5px solid #fca5a5;color:#b91c1c;padding:6px 14px;font-size:.8rem}
    .btn-danger:hover{background:#fef2f2}
    .card{background:#fff;border:1px solid #e2e5ef;border-radius:12px;padding:10px 24px;box-shadow:0 2px 12px rgba(0,0,0,.05)}
    table{width:100%;border-collapse:collapse;font-size:.85rem}
    th{text-align:left;font-size:.7rem;color:#9ca3af;text-transform:uppercase;letter-spacing:.03em;padding:12px 8px;font-weight:700}
    td{padding:12px 8px;border-top:1px solid #f1f0e8;vertical-align:middle}
    tr:first-of-type td{border-top:none}
    .badge{display:inline-block;font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.03em;padding:3px 9px;border-radius:20px}
    .badge-complete{background:#e6f7ec;color:#15803d}
    .badge-draft{background:#fdf6e8;color:#a5720f}
    .row-actions{display:flex;gap:8px}
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
        <h1>Cashflow <span>entries</span></h1>
      </div>
      <a href="entry.php" class="btn btn-primary">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Add / update entry
      </a>
    </div>

    <?php if (!$rows): ?>
      <div class="empty">
        <h2>No cashflow data yet</h2>
        <a href="entry.php" class="btn btn-primary">Add first entry</a>
      </div>
    <?php else: ?>
      <div class="card">
        <table>
          <tr>
            <th>Date</th>
            <th>Status</th>
            <th>Filled by</th>
            <th>Updated</th>
            <th></th>
          </tr>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><?= h(date('j M Y', strtotime($r['entry_date']))) ?></td>
              <td><span class="badge badge-<?= $r['status'] ?>"><?= $r['status'] === 'complete' ? 'Complete' : 'Draft' ?></span></td>
              <td><?= h($r['filled_by_email']) ?></td>
              <td><?= h(date('j M Y, g:i a', strtotime($r['updated_at']))) ?></td>
              <td>
                <div class="row-actions">
                  <a class="btn btn-ghost" href="entry.php?date=<?= h($r['entry_date']) ?>"><?= $r['status'] === 'complete' ? 'View' : 'Edit' ?></a>
                  <?php if ($r['status'] !== 'complete'): ?>
                    <form method="POST" action="entry.php" onsubmit="return confirm('Delete the <?= h(date('j M Y', strtotime($r['entry_date']))) ?> entry entirely? This cannot be undone.')">
                      <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                      <input type="hidden" name="date" value="<?= h($r['entry_date']) ?>">
                      <input type="hidden" name="action" value="delete">
                      <button type="submit" class="btn btn-danger">Delete</button>
                    </form>
                  <?php endif ?>
                </div>
              </td>
            </tr>
          <?php endforeach ?>
        </table>
      </div>
    <?php endif ?>
  </div>
</div>
</body>
</html>
