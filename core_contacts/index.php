<?php
require_once __DIR__ . '/../session_guard.php';
require_once __DIR__ . '/cc_db.php';

$member = cc_member($_SESSION['auth_email']);
if (!$member) { header('Location: /CVwebapp/index.php'); exit; }

$db = getDB();

$search    = trim($_GET['q'] ?? '');
$space     = 'personal';
$show_junk = isset($_GET['junk']);

// ── AJAX: junk a single contact ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'junk_one') {
    header('Content-Type: application/json');
    $cid = $_POST['contact_id'] ?? '';
    $stmt = $db->prepare("UPDATE contacts SET is_junk=1 WHERE contact_id=? AND owner_member_id=?");
    $ok = $stmt->execute([$cid, $member['member_id']]) && $stmt->rowCount() > 0;
    echo json_encode(['ok' => $ok]);
    exit;
}

// ── AJAX: permanently delete from junk ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_one') {
    header('Content-Type: application/json');
    $cid = $_POST['contact_id'] ?? '';
    $db->prepare("DELETE FROM duplicate_links WHERE contact_id_a=? OR contact_id_b=?")->execute([$cid, $cid]);
    $stmt = $db->prepare("DELETE FROM contacts WHERE contact_id=? AND owner_member_id=? AND is_junk=1");
    $ok = $stmt->execute([$cid, $member['member_id']]) && $stmt->rowCount() > 0;
    echo json_encode(['ok' => $ok]);
    exit;
}
$where  = ['c.owner_member_id = ?', $show_junk ? 'c.is_junk = 1' : 'c.is_junk = 0'];
$params = [$member['member_id']];

if ($search !== '') {
    $where[]  = '(p.full_name LIKE ? OR p.current_role LIKE ? OR p.current_company LIKE ?)';
    $like = "%{$search}%";
    $params[] = $like; $params[] = $like; $params[] = $like;
}

$where_sql = implode(' AND ', $where);

$contacts = $db->prepare("
    SELECT c.contact_id, c.space, c.relationship_strength, c.relationship_origin,
           c.added_at, c.origin_source,
           p.full_name, p.current_role, p.current_company, p.city, p.cluster_id,
           glv.value AS relationship_type_label,
           GROUP_CONCAT(DISTINCT ce.email ORDER BY ce.is_primary DESC SEPARATOR ', ') AS emails,
           GROUP_CONCAT(DISTINCT ct.tag ORDER BY ct.tag SEPARATOR ',') AS tags
    FROM contacts c
    LEFT JOIN person_clusters p ON p.cluster_id = c.cluster_id
    LEFT JOIN global_list_values glv ON glv.value_id = c.relationship_type
    LEFT JOIN cluster_emails ce ON ce.cluster_id = c.cluster_id
    LEFT JOIN contact_tags ct ON ct.cluster_id = c.cluster_id
    WHERE {$where_sql}
    GROUP BY c.contact_id
    ORDER BY p.full_name ASC
");
$contacts->execute($params);
$rows = $contacts->fetchAll();

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES|ENT_HTML5, 'UTF-8'); }

$nav_active = 'contacts_personal';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>CoreContacts — Personal Space</title>
  <style>
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
    body{font-family:'Segoe UI',system-ui,sans-serif;background:#f7f8fc;color:#1a1a2e}
    .page{padding:36px 40px;max-width:1100px}
    .page-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:28px;gap:16px;flex-wrap:wrap}
    .page-header h1{font-family:Georgia,serif;font-size:1.5rem;font-weight:700}
    .page-header h1 span{color:#C9972A}
    .tab-bar{display:flex;gap:4px;background:#e9ebf0;border-radius:8px;padding:4px;margin-bottom:24px;width:fit-content}
    .tab-bar a{padding:7px 18px;border-radius:6px;font-size:.85rem;font-weight:600;color:#6b7280;text-decoration:none;transition:all .15s}
    .tab-bar a.active{background:#fff;color:#1a1a2e;box-shadow:0 1px 4px rgba(0,0,0,.1)}
    .toolbar{display:flex;gap:12px;margin-bottom:20px;align-items:center;flex-wrap:wrap}
    .search-box{flex:1;min-width:200px;max-width:360px;position:relative}
    .search-box input{width:100%;padding:9px 12px 9px 36px;border:1.5px solid #d1d5db;border-radius:7px;font-size:.875rem;outline:none;font-family:inherit;background:#fff}
    .search-box input:focus{border-color:#C9972A}
    .search-box svg{position:absolute;left:10px;top:50%;transform:translateY(-50%);color:#9ca3af}
    .btn{display:inline-flex;align-items:center;gap:6px;padding:9px 18px;border-radius:7px;font-size:.875rem;font-weight:600;cursor:pointer;text-decoration:none;border:none;font-family:inherit;transition:background .15s}
    .btn-primary{background:#1a1a2e;color:#fff}.btn-primary:hover{background:#2d2d4e}
    .contact-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:16px}
    .contact-card{background:#fff;border:1px solid #e2e5ef;border-radius:10px;padding:20px;text-decoration:none;color:inherit;display:block;transition:box-shadow .15s,border-color .15s}
    .contact-card:hover{box-shadow:0 4px 20px rgba(0,0,0,.08);border-color:#C9972A}
    .card-name{font-weight:700;font-size:1rem;margin-bottom:3px}
    .card-role{font-size:.82rem;color:#6b7280;margin-bottom:10px}
    .card-meta{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px}
    .badge{font-size:.72rem;padding:3px 8px;border-radius:12px;font-weight:600}
    .badge-space-personal{background:#e0f2fe;color:#0369a1}
    .badge-space-shared{background:#dcfce7;color:#166534}
    .badge-strength-close{background:#fef3c7;color:#92400e}
    .badge-strength-acquaintance{background:#f3f4f6;color:#374151}
    .badge-strength-distant{background:#f3f4f6;color:#9ca3af}
    .card-tags{display:flex;gap:4px;flex-wrap:wrap}
    .tag{font-size:.7rem;padding:2px 7px;border-radius:10px;background:#f3f4f6;color:#374151}
    .empty{text-align:center;padding:64px 20px;color:#9ca3af}
    .empty h2{font-size:1.1rem;margin-bottom:8px;color:#6b7280}
    .count{font-size:.82rem;color:#9ca3af;margin-left:auto}
    .card-wrap{position:relative}
    .contact-card{display:block;padding-right:36px}
    .junk-btn{position:absolute;top:10px;right:10px;background:none;border:none;cursor:pointer;padding:4px;border-radius:5px;color:#d1d5db;transition:color .15s,background .15s;line-height:0}
    .junk-btn:hover{color:#ef4444;background:#fee2e2}
    .card-wrap.fading{opacity:0;transition:opacity .25s}
  </style>
</head>
<body>
<div class="cv-layout">
  <?php require __DIR__ . '/../nav.php'; ?>
  <div class="page">
    <div class="page-header">
      <h1>Core<span>Contacts</span></h1>
      <div style="display:flex;gap:10px">
        <div style="position:relative" id="import-menu-wrap">
          <button onclick="document.getElementById('import-dropdown').classList.toggle('open')" class="btn btn-ghost" type="button">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
            Import ▾
          </button>
          <div id="import-dropdown" style="display:none;position:absolute;top:calc(100% + 6px);right:0;background:#fff;border:1px solid #e2e5ef;border-radius:8px;box-shadow:0 4px 20px rgba(0,0,0,.1);min-width:180px;z-index:50;overflow:hidden">
            <a href="import_google.php"   style="display:block;padding:11px 16px;font-size:.85rem;color:#1a1a2e;text-decoration:none;border-bottom:1px solid #f3f4f6" onmouseover="this.style.background='#f7f8fc'" onmouseout="this.style.background=''">Google Contacts CSV</a>
            <a href="import_linkedin.php" style="display:block;padding:11px 16px;font-size:.85rem;color:#1a1a2e;text-decoration:none;border-bottom:1px solid #f3f4f6" onmouseover="this.style.background='#f7f8fc'" onmouseout="this.style.background=''">LinkedIn CSV</a>
            <a href="import_vcf.php"      style="display:block;padding:11px 16px;font-size:.85rem;color:#1a1a2e;text-decoration:none;border-bottom:1px solid #f3f4f6" onmouseover="this.style.background='#f7f8fc'" onmouseout="this.style.background=''">Phone contacts (.vcf)</a>
            <a href="import_whatsapp.php" style="display:block;padding:11px 16px;font-size:.85rem;color:#1a1a2e;text-decoration:none" onmouseover="this.style.background='#f7f8fc'" onmouseout="this.style.background=''">WhatsApp group chat</a>
          </div>
        </div>
        <a href="add.php" class="btn btn-primary">
          <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          Add contact
        </a>
      </div>
    </div>

    <div class="tab-bar">
      <a href="index.php" class="<?= !$show_junk ? 'active' : '' ?>">My Contacts</a>
      <a href="team.php">Team View</a>
      <a href="index.php?junk=1" class="<?= $show_junk ? 'active' : '' ?>">Junk</a>
    </div>

    <div class="toolbar">
      <form method="GET" style="display:contents">
        <input type="hidden" name="space" value="<?= h($space) ?>"/>
        <div class="search-box">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          <input type="text" name="q" value="<?= h($search) ?>" placeholder="Search by name, role, company…" autofocus/>
        </div>
      </form>
      <span class="count"><?= count($rows) ?> contact<?= count($rows) !== 1 ? 's' : '' ?></span>
    </div>

    <?php if (empty($rows)): ?>
      <div class="empty">
        <h2><?= $search ? 'No contacts match your search' : ($show_junk ? 'Junk is empty' : 'No contacts yet') ?></h2>
        <p><?= $search || $show_junk ? '' : 'Add your first contact to get started.' ?></p>
      </div>
    <?php else: ?>
      <div class="contact-grid">
        <?php foreach ($rows as $row): ?>
          <div class="card-wrap">
          <a href="contact.php?id=<?= h($row['contact_id']) ?>" class="contact-card" data-id="<?= h($row['contact_id']) ?>">
            <div class="card-name"><?= h($row['full_name'] ?: '(no name)') ?></div>
            <div class="card-role">
              <?= h(implode(' · ', array_filter([$row['current_role'], $row['current_company']]))) ?>
            </div>
            <div class="card-meta">
              <span class="badge badge-space-<?= $row['space'] ?>"><?= $row['space'] ?></span>
              <?php if ($row['relationship_strength']): ?>
                <span class="badge badge-strength-<?= $row['relationship_strength'] ?>"><?= $row['relationship_strength'] ?></span>
              <?php endif ?>
              <?php if ($row['relationship_type_label']): ?>
                <span class="badge" style="background:#f3f4f6;color:#374151"><?= h($row['relationship_type_label']) ?></span>
              <?php endif ?>
            </div>
            <?php if ($row['tags']): ?>
              <div class="card-tags">
                <?php foreach (explode(',', $row['tags']) as $tag): ?>
                  <span class="tag"><?= h($tag) ?></span>
                <?php endforeach ?>
              </div>
            <?php endif ?>
          </a>
            <button class="junk-btn" title="<?= $show_junk ? 'Delete permanently' : 'Move to junk' ?>"
                    onclick="junkCard(this, '<?= h($row['contact_id']) ?>', <?= $show_junk ? 'true' : 'false' ?>)">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M9.5 11l.5 6"/><path d="M14.5 11l-.5 6"/></svg>
            </button>
          </div>
        <?php endforeach ?>
      </div>
    <?php endif ?>
  </div>
</div>
<script>
document.addEventListener('click', e => {
  const wrap = document.getElementById('import-menu-wrap');
  const dd   = document.getElementById('import-dropdown');
  if (wrap && dd && !wrap.contains(e.target)) dd.style.display = 'none';
});

function junkCard(btn, contactId, isDelete) {
  const wrap = btn.closest('.card-wrap');
  const action = isDelete ? 'delete_one' : 'junk_one';
  const fd = new FormData();
  fd.append('action', action);
  fd.append('contact_id', contactId);
  fetch('index.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      if (data.ok) {
        wrap.classList.add('fading');
        setTimeout(() => wrap.remove(), 260);
      }
    });
}
</script>
</body>
</html>
