<?php
require_once __DIR__ . '/../session_guard.php';
require_once __DIR__ . '/cc_db.php';

$member = cc_member($_SESSION['auth_email']);
if (!$member) { header('Location: /CVwebapp/index.php'); exit; }

$db = getDB();

// ── Bulk delete ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'bulk_delete') {
    $ids = $_POST['ids'] ?? [];
    if ($ids) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        // Only delete contacts owned by this member
        $del = $db->prepare("SELECT c.contact_id, c.cluster_id FROM contacts c
            WHERE c.contact_id IN ($placeholders) AND c.owner_member_id = ?");
        $del->execute([...$ids, $member['member_id']]);
        $to_delete = $del->fetchAll();

        foreach ($to_delete as $row) {
            // Delete duplicate_links refs first
            $db->prepare("DELETE FROM duplicate_links WHERE contact_id_a=? OR contact_id_b=?")
              ->execute([$row['contact_id'], $row['contact_id']]);
            // Delete the contact
            $db->prepare("DELETE FROM contacts WHERE contact_id=?")->execute([$row['contact_id']]);
            // Delete the cluster if no other contacts reference it
            $still_used = $db->prepare("SELECT COUNT(*) FROM contacts WHERE cluster_id=?");
            $still_used->execute([$row['cluster_id']]);
            if ($still_used->fetchColumn() == 0) {
                foreach (['cluster_emails','cluster_phones','contact_tags','education','experience'] as $t) {
                    $db->prepare("DELETE FROM $t WHERE cluster_id=?")->execute([$row['cluster_id']]);
                }
                $db->prepare("DELETE FROM person_clusters WHERE cluster_id=?")->execute([$row['cluster_id']]);
            }
        }
    }
    header('Location: index.php' . ($search ? '?q='.urlencode($search) : ''));
    exit;
}

$search = trim($_GET['q'] ?? '');
$space  = 'personal';

$where  = ['c.owner_member_id = ?'];
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
    .contact-card{position:relative}
    .card-check{position:absolute;top:10px;right:10px;width:18px;height:18px;cursor:pointer;accent-color:#1a1a2e;display:none;z-index:2}
    .select-mode .card-check{display:block}
    .select-mode .contact-card{padding-right:36px}
    .select-mode .contact-card.selected{border-color:#1a1a2e;background:#f8f9ff}
    .bulk-bar{position:fixed;bottom:0;left:220px;right:0;background:#1a1a2e;color:#fff;padding:14px 40px;display:flex;align-items:center;gap:16px;z-index:200;transform:translateY(100%);transition:transform .2s}
    .bulk-bar.visible{transform:translateY(0)}
    .bulk-bar .count{color:rgba(255,255,255,.7);font-size:.875rem;margin-left:0}
    .btn-select-mode{background:#f3f4f6;color:#374151;border:none}.btn-select-mode:hover{background:#e5e7eb}
    .btn-select-mode.active{background:#1a1a2e;color:#fff}
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
        <button id="select-btn" onclick="toggleSelectMode()" class="btn btn-select-mode">Select</button>
        <a href="add.php" class="btn btn-primary">
          <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          Add contact
        </a>
      </div>
    </div>

    <div class="tab-bar">
      <a href="index.php" class="active">My Contacts</a>
      <a href="team.php">Team View</a>
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
        <h2><?= $search ? 'No contacts match your search' : ($space === 'personal' ? 'No contacts yet' : 'No shared contacts yet') ?></h2>
        <p><?= $search ? '' : ($space === 'personal' ? 'Add your first contact to get started.' : 'Share contacts from your personal space to see them here.') ?></p>
      </div>
    <?php else: ?>
      <form method="POST" id="bulk-form">
        <input type="hidden" name="action" value="bulk_delete"/>
      <div class="contact-grid" id="contact-grid">
        <?php foreach ($rows as $row): ?>
          <a href="contact.php?id=<?= h($row['contact_id']) ?>" class="contact-card" data-id="<?= h($row['contact_id']) ?>">
            <input type="checkbox" class="card-check" name="ids[]" value="<?= h($row['contact_id']) ?>" onclick="handleCheck(event, this)"/>
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
        <?php endforeach ?>
      </div>
      </form>
    <?php endif ?>
  </div>
</div>
<div class="bulk-bar" id="bulk-bar">
  <span class="count" id="bulk-count">0 selected</span>
  <button type="button" onclick="selectAll()" style="background:rgba(255,255,255,.1);color:#fff;border:none;padding:7px 14px;border-radius:6px;cursor:pointer;font-size:.82rem">Select all</button>
  <button type="button" onclick="deselectAll()" style="background:rgba(255,255,255,.1);color:#fff;border:none;padding:7px 14px;border-radius:6px;cursor:pointer;font-size:.82rem">Deselect all</button>
  <div style="flex:1"></div>
  <button type="button" onclick="confirmDelete()" style="background:#ef4444;color:#fff;border:none;padding:9px 20px;border-radius:7px;cursor:pointer;font-size:.875rem;font-weight:700">Delete selected</button>
  <button type="button" onclick="toggleSelectMode()" style="background:rgba(255,255,255,.1);color:#fff;border:none;padding:9px 16px;border-radius:7px;cursor:pointer;font-size:.875rem">Cancel</button>
</div>

<script>
document.addEventListener('click', e => {
  const wrap = document.getElementById('import-menu-wrap');
  const dd   = document.getElementById('import-dropdown');
  if (wrap && dd && !wrap.contains(e.target)) dd.style.display = 'none';
  if (dd && wrap && wrap.contains(e.target) && dd.classList.contains('open')) {
    dd.style.display = dd.style.display === 'none' ? 'block' : 'none';
    dd.classList.remove('open');
  }
});

let selectMode = false;

function toggleSelectMode() {
  selectMode = !selectMode;
  document.getElementById('contact-grid').classList.toggle('select-mode', selectMode);
  document.getElementById('select-btn').classList.toggle('active', selectMode);
  if (!selectMode) { deselectAll(); document.getElementById('bulk-bar').classList.remove('visible'); }
}

function handleCheck(e, cb) {
  e.preventDefault();
  e.stopPropagation();
  cb.checked = !cb.checked;
  cb.closest('.contact-card').classList.toggle('selected', cb.checked);
  updateBulkBar();
}

function updateBulkBar() {
  const checked = document.querySelectorAll('.card-check:checked').length;
  document.getElementById('bulk-count').textContent = checked + ' selected';
  document.getElementById('bulk-bar').classList.toggle('visible', checked > 0);
}

function selectAll() {
  document.querySelectorAll('.card-check').forEach(cb => {
    cb.checked = true; cb.closest('.contact-card').classList.add('selected');
  });
  updateBulkBar();
}

function deselectAll() {
  document.querySelectorAll('.card-check').forEach(cb => {
    cb.checked = false; cb.closest('.contact-card').classList.remove('selected');
  });
  updateBulkBar();
}

function confirmDelete() {
  const n = document.querySelectorAll('.card-check:checked').length;
  if (!n) return;
  if (confirm(`Delete ${n} contact${n>1?'s':''}? This cannot be undone.`)) {
    document.getElementById('bulk-form').submit();
  }
}
</script>
</body>
</html>
