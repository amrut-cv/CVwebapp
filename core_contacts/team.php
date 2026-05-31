<?php
require_once __DIR__ . '/../session_guard.php';
require_once __DIR__ . '/cc_db.php';

$member = cc_member($_SESSION['auth_email']);
if (!$member) { header('Location: /CVwebapp/index.php'); exit; }

$db     = getDB();
$search = trim($_GET['q'] ?? '');

// Handle adopt
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'adopt') {
    $cluster_id = $_POST['cluster_id'] ?? '';

    // Check not already owned
    $existing = $db->prepare("SELECT contact_id FROM contacts WHERE owner_member_id=? AND cluster_id=?");
    $existing->execute([$member['member_id'], $cluster_id]);
    if (!$existing->fetch()) {
        $new_id = uuid();
        $db->prepare("INSERT INTO contacts
            (contact_id,owner_member_id,cluster_id,space,origin_source)
            VALUES (?,?,?,'personal','adopted')")
          ->execute([$new_id, $member['member_id'], $cluster_id]);
        header("Location: /CVwebapp/core_contacts/edit.php?id={$new_id}&adopted=1");
        exit;
    }
    header("Location: team.php");
    exit;
}

// Load all shared clusters with member relationship rows
$where  = ["c.space = 'shared'"];
$params = [];

if ($search !== '') {
    $where[]  = '(p.full_name LIKE ? OR p.current_role LIKE ? OR p.current_company LIKE ?)';
    $like = "%{$search}%";
    array_push($params, $like, $like, $like);
}

$sql = "
    SELECT
        p.cluster_id, p.full_name, p.current_role, p.current_company, p.city,
        p.linkedin_url, p.notes AS cluster_notes,
        c.contact_id, c.owner_member_id, c.relationship_origin,
        c.relationship_strength, c.notes AS rel_notes,
        glv.value AS rel_type_label,
        om.name AS owner_name,
        GROUP_CONCAT(DISTINCT ce.email ORDER BY ce.is_primary DESC SEPARATOR '||') AS emails,
        GROUP_CONCAT(DISTINCT cp.phone ORDER BY cp.is_primary DESC SEPARATOR '||') AS phones,
        GROUP_CONCAT(DISTINCT ct.tag ORDER BY ct.tag SEPARATOR ',') AS tags
    FROM contacts c
    JOIN person_clusters p ON p.cluster_id = c.cluster_id
    JOIN org_members om ON om.member_id = c.owner_member_id
    LEFT JOIN global_list_values glv ON glv.value_id = c.relationship_type
    LEFT JOIN cluster_emails ce ON ce.cluster_id = p.cluster_id
    LEFT JOIN cluster_phones cp ON cp.cluster_id = p.cluster_id
    LEFT JOIN contact_tags ct ON ct.cluster_id = p.cluster_id
    WHERE " . implode(' AND ', $where) . "
    GROUP BY c.contact_id
    ORDER BY p.full_name ASC, om.name ASC
";

$st = $db->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();

// Group by cluster
$clusters = [];
foreach ($rows as $row) {
    $cid = $row['cluster_id'];
    if (!isset($clusters[$cid])) {
        $clusters[$cid] = [
            'cluster_id'    => $cid,
            'full_name'     => $row['full_name'],
            'current_role'  => $row['current_role'],
            'current_company'=> $row['current_company'],
            'city'          => $row['city'],
            'linkedin_url'  => $row['linkedin_url'],
            'cluster_notes' => $row['cluster_notes'],
            'emails'        => $row['emails'] ? explode('||', $row['emails']) : [],
            'phones'        => $row['phones'] ? explode('||', $row['phones']) : [],
            'tags'          => $row['tags'] ? explode(',', $row['tags']) : [],
            'relationships' => [],
        ];
    }
    $clusters[$cid]['relationships'][] = [
        'contact_id'        => $row['contact_id'],
        'owner_member_id'   => $row['owner_member_id'],
        'owner_name'        => $row['owner_name'],
        'relationship_origin'   => $row['relationship_origin'],
        'relationship_strength' => $row['relationship_strength'],
        'rel_type_label'    => $row['rel_type_label'],
        'rel_notes'         => $row['rel_notes'],
    ];
}

// Which clusters does current member already own?
$my_clusters = $db->prepare("SELECT cluster_id FROM contacts WHERE owner_member_id=?");
$my_clusters->execute([$member['member_id']]);
$my_cluster_ids = $my_clusters->fetchAll(PDO::FETCH_COLUMN, 0);

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES|ENT_HTML5, 'UTF-8'); }

$nav_active = 'contacts_team';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>CoreContacts — Team View</title>
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
    .toolbar{display:flex;gap:12px;margin-bottom:20px;align-items:center}
    .search-box{flex:1;min-width:200px;max-width:360px;position:relative}
    .search-box input{width:100%;padding:9px 12px 9px 36px;border:1.5px solid #d1d5db;border-radius:7px;font-size:.875rem;outline:none;font-family:inherit;background:#fff}
    .search-box input:focus{border-color:#C9972A}
    .search-box svg{position:absolute;left:10px;top:50%;transform:translateY(-50%);color:#9ca3af}
    .count{font-size:.82rem;color:#9ca3af;margin-left:auto}
    .cluster-card{background:#fff;border:1px solid #e2e5ef;border-radius:12px;margin-bottom:20px;overflow:hidden}
    .cluster-head{padding:20px 24px;display:flex;align-items:flex-start;justify-content:space-between;gap:16px;border-bottom:1px solid #f3f4f6;flex-wrap:wrap}
    .cluster-name{font-weight:700;font-size:1.05rem;margin-bottom:3px}
    .cluster-role{font-size:.85rem;color:#6b7280}
    .cluster-meta{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px}
    .tag{font-size:.7rem;padding:2px 8px;border-radius:10px;background:#f3f4f6;color:#374151}
    .contact-detail{font-size:.78rem;color:#9ca3af;margin-top:6px}
    .contact-detail a{color:#C9972A;text-decoration:none}
    .adopt-btn{flex-shrink:0;display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:7px;font-size:.8rem;font-weight:600;cursor:pointer;border:none;font-family:inherit;transition:background .15s}
    .adopt-btn-new{background:#1a1a2e;color:#fff}.adopt-btn-new:hover{background:#2d2d4e}
    .adopt-btn-mine{background:#f3f4f6;color:#9ca3af;cursor:default}
    .rel-rows{padding:0 24px}
    .rel-row{display:flex;align-items:flex-start;gap:14px;padding:14px 0;border-bottom:1px solid #f9fafb}
    .rel-row:last-child{border-bottom:none}
    .rel-avatar{width:30px;height:30px;border-radius:50%;background:#1a1a2e;color:#fff;display:flex;align-items:center;justify-content:center;font-size:.75rem;font-weight:700;flex-shrink:0}
    .rel-body{flex:1;min-width:0}
    .rel-owner{font-size:.82rem;font-weight:700;margin-bottom:3px}
    .rel-context{font-size:.82rem;color:#6b7280}
    .rel-badges{display:flex;gap:6px;flex-wrap:wrap;margin-top:5px}
    .badge{font-size:.68rem;padding:2px 8px;border-radius:10px;font-weight:600}
    .badge-close{background:#fef3c7;color:#92400e}
    .badge-acquaintance{background:#f3f4f6;color:#374151}
    .badge-distant{background:#f3f4f6;color:#9ca3af}
    .badge-type{background:#e0f2fe;color:#0369a1}
    .rel-notes{font-size:.78rem;color:#374151;margin-top:5px;font-style:italic}
    .cluster-notes{padding:12px 24px 14px;background:#fffbeb;border-top:1px solid #fef3c7;font-size:.82rem;color:#374151}
    .empty{text-align:center;padding:64px 20px;color:#9ca3af}
    .empty h2{font-size:1.1rem;margin-bottom:8px;color:#6b7280}
  </style>
</head>
<body>
<div class="cv-layout">
  <?php require __DIR__ . '/../nav.php'; ?>
  <div class="page">
    <div class="page-header">
      <h1>Core<span>Contacts</span></h1>
    </div>

    <div class="tab-bar">
      <a href="index.php">My Contacts</a>
      <a href="team.php" class="active">Team View</a>
    </div>

    <div class="toolbar">
      <form method="GET" style="display:contents">
        <div class="search-box">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          <input type="text" name="q" value="<?= h($search) ?>" placeholder="Search shared contacts…" autofocus/>
        </div>
      </form>
      <span class="count"><?= count($clusters) ?> contact<?= count($clusters) !== 1 ? 's' : '' ?></span>
    </div>

    <?php if (empty($clusters)): ?>
      <div class="empty">
        <h2><?= $search ? 'No contacts match your search' : 'No shared contacts yet' ?></h2>
        <p><?= $search ? '' : 'Share contacts from your personal space to see them here.' ?></p>
      </div>
    <?php else: ?>
      <?php foreach ($clusters as $cl): ?>
        <?php $already_mine = in_array($cl['cluster_id'], $my_cluster_ids); ?>
        <div class="cluster-card">
          <div class="cluster-head">
            <div>
              <div class="cluster-name"><?= h($cl['full_name']) ?></div>
              <div class="cluster-role"><?= h(implode(' · ', array_filter([$cl['current_role'], $cl['current_company'], $cl['city']]))) ?></div>
              <?php if ($cl['tags']): ?>
                <div class="cluster-meta">
                  <?php foreach ($cl['tags'] as $tag): ?>
                    <span class="tag"><?= h($tag) ?></span>
                  <?php endforeach ?>
                </div>
              <?php endif ?>
              <div class="contact-detail">
                <?php if ($cl['emails']): ?>
                  <?= h($cl['emails'][0]) ?>
                <?php endif ?>
                <?php if ($cl['phones']): ?>
                  <?= ($cl['emails'] ? ' · ' : '') . h($cl['phones'][0]) ?>
                <?php endif ?>
                <?php if ($cl['linkedin_url']): ?>
                  · <a href="<?= h($cl['linkedin_url']) ?>" target="_blank" rel="noopener">LinkedIn</a>
                <?php endif ?>
              </div>
            </div>
            <?php if ($already_mine): ?>
              <span class="adopt-btn adopt-btn-mine">✓ In my contacts</span>
            <?php else: ?>
              <form method="POST">
                <input type="hidden" name="action" value="adopt"/>
                <input type="hidden" name="cluster_id" value="<?= h($cl['cluster_id']) ?>"/>
                <button type="submit" class="adopt-btn adopt-btn-new">+ Add to my contacts</button>
              </form>
            <?php endif ?>
          </div>

          <div class="rel-rows">
            <?php foreach ($cl['relationships'] as $rel): ?>
              <div class="rel-row">
                <div class="rel-avatar"><?= h(mb_substr($rel['owner_name'], 0, 1)) ?></div>
                <div class="rel-body">
                  <div class="rel-owner"><?= h($rel['owner_name']) ?></div>
                  <?php if ($rel['relationship_origin']): ?>
                    <div class="rel-context"><?= h($rel['relationship_origin']) ?></div>
                  <?php endif ?>
                  <div class="rel-badges">
                    <?php if ($rel['relationship_strength']): ?>
                      <span class="badge badge-<?= $rel['relationship_strength'] ?>"><?= ucfirst($rel['relationship_strength']) ?></span>
                    <?php endif ?>
                    <?php if ($rel['rel_type_label']): ?>
                      <span class="badge badge-type"><?= h($rel['rel_type_label']) ?></span>
                    <?php endif ?>
                  </div>
                  <?php if ($rel['rel_notes']): ?>
                    <div class="rel-notes"><?= h($rel['rel_notes']) ?></div>
                  <?php endif ?>
                </div>
              </div>
            <?php endforeach ?>
          </div>

          <?php if ($cl['cluster_notes']): ?>
            <div class="cluster-notes"><strong>Team note:</strong> <?= h($cl['cluster_notes']) ?></div>
          <?php endif ?>
        </div>
      <?php endforeach ?>
    <?php endif ?>
  </div>
</div>
</body>
</html>
