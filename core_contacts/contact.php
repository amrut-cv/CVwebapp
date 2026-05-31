<?php
require_once __DIR__ . '/../session_guard.php';
require_once __DIR__ . '/cc_db.php';

$member = cc_member($_SESSION['auth_email']);
if (!$member) { header('Location: /CVwebapp/index.php'); exit; }

$db         = getDB();
$contact_id = $_GET['id'] ?? '';
$added      = isset($_GET['added']);

// Load contact + cluster
$contact = $db->prepare("
    SELECT c.*, p.full_name, p.linkedin_url, p.current_role, p.current_company,
           p.city, p.notes AS cluster_notes, p.last_updated_at,
           glv.value AS relationship_type_label
    FROM contacts c
    LEFT JOIN person_clusters p ON p.cluster_id = c.cluster_id
    LEFT JOIN global_list_values glv ON glv.value_id = c.relationship_type
    WHERE c.contact_id = ? AND c.owner_member_id = ?
");
$contact->execute([$contact_id, $member['member_id']]);
$row = $contact->fetch();
if (!$row) { header('Location: index.php'); exit; }

$cluster_id = $row['cluster_id'];

$emails = $db->prepare("SELECT * FROM cluster_emails WHERE cluster_id = ? ORDER BY is_primary DESC");
$emails->execute([$cluster_id]);
$emails = $emails->fetchAll();

$phones = $db->prepare("SELECT * FROM cluster_phones WHERE cluster_id = ? ORDER BY is_primary DESC");
$phones->execute([$cluster_id]);
$phones = $phones->fetchAll();

$education = $db->prepare("SELECT * FROM education WHERE cluster_id = ? ORDER BY year_start DESC");
$education->execute([$cluster_id]);
$education = $education->fetchAll();

$experience = $db->prepare("SELECT * FROM experience WHERE cluster_id = ? ORDER BY year_start DESC");
$experience->execute([$cluster_id]);
$experience = $experience->fetchAll();

$tags = $db->prepare("SELECT tag FROM contact_tags WHERE cluster_id = ? ORDER BY tag");
$tags->execute([$cluster_id]);
$tags = $tags->fetchAll(PDO::FETCH_COLUMN);

// Handle share action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'share' && $row['space'] === 'personal') {
        // Create cluster if not yet present (it should be, but guard)
        $db->prepare("UPDATE contacts SET space='shared' WHERE contact_id=?")
           ->execute([$contact_id]);
        header("Location: contact.php?id={$contact_id}&shared=1");
        exit;
    }
}

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES|ENT_HTML5, 'UTF-8'); }

$nav_active = 'contacts_personal';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title><?= h($row['full_name']) ?> — CoreContacts</title>
  <style>
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
    body{font-family:'Segoe UI',system-ui,sans-serif;background:#f7f8fc;color:#1a1a2e}
    .page{padding:36px 40px;max-width:800px}
    .back{font-size:.82rem;color:#6b7280;text-decoration:none;display:inline-flex;align-items:center;gap:4px;margin-bottom:20px}
    .back:hover{color:#1a1a2e}
    .contact-header{display:flex;align-items:flex-start;justify-content:space-between;gap:20px;margin-bottom:28px;flex-wrap:wrap}
    .contact-header h1{font-family:Georgia,serif;font-size:1.6rem;font-weight:700;margin-bottom:4px}
    .contact-header .role{font-size:.95rem;color:#6b7280}
    .badge-row{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px}
    .badge{font-size:.72rem;padding:4px 10px;border-radius:12px;font-weight:600}
    .badge-personal{background:#e0f2fe;color:#0369a1}
    .badge-shared{background:#dcfce7;color:#166534}
    .badge-strength-close{background:#fef3c7;color:#92400e}
    .badge-strength-acquaintance{background:#f3f4f6;color:#374151}
    .badge-strength-distant{background:#f3f4f6;color:#9ca3af}
    .badge-type{background:#f3f4f6;color:#374151}
    .actions{display:flex;gap:10px;flex-wrap:wrap}
    .btn{display:inline-flex;align-items:center;gap:6px;padding:9px 16px;border-radius:7px;font-size:.82rem;font-weight:600;cursor:pointer;text-decoration:none;border:none;font-family:inherit;transition:background .15s}
    .btn-primary{background:#1a1a2e;color:#fff}.btn-primary:hover{background:#2d2d4e}
    .btn-success{background:#166534;color:#fff}.btn-success:hover{background:#14532d}
    .btn-ghost{background:#f3f4f6;color:#374151}.btn-ghost:hover{background:#e5e7eb}
    .grid{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px}
    .section{background:#fff;border:1px solid #e2e5ef;border-radius:10px;padding:22px}
    .section.full{grid-column:1/-1}
    .section-title{font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#9ca3af;margin-bottom:14px}
    .kv{margin-bottom:10px}
    .kv label{font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#9ca3af;display:block;margin-bottom:2px}
    .kv p{font-size:.9rem;color:#1a1a2e}
    .kv a{color:#C9972A;font-size:.9rem;word-break:break-all}
    .tag{font-size:.72rem;padding:3px 9px;border-radius:10px;background:#f3f4f6;color:#374151;display:inline-block;margin:2px}
    .edu-row,.exp-row{padding:10px 0;border-bottom:1px solid #f3f4f6}
    .edu-row:last-child,.exp-row:last-child{border-bottom:none;padding-bottom:0}
    .edu-row strong,.exp-row strong{font-size:.9rem;display:block;margin-bottom:2px}
    .edu-row span,.exp-row span{font-size:.8rem;color:#6b7280}
    .notable{color:#C9972A;font-size:.7rem;font-weight:700;margin-left:6px}
    .founder-badge{font-size:.68rem;padding:1px 6px;border-radius:8px;background:#fef3c7;color:#92400e;margin-left:4px;font-weight:700}
    .investor-badge{font-size:.68rem;padding:1px 6px;border-radius:8px;background:#e0f2fe;color:#0369a1;margin-left:4px;font-weight:700}
    .notes-box{font-size:.875rem;color:#374151;line-height:1.6;white-space:pre-wrap}
    .private-notes-box{font-size:.875rem;color:#374151;line-height:1.6;white-space:pre-wrap;background:#fffbeb;border-radius:6px;padding:10px}
    .alert{border-radius:7px;padding:11px 16px;margin-bottom:20px;font-size:.85rem}
    .alert-success{background:#dcfce7;border:1px solid #bbf7d0;color:#166534}
    .empty-state{color:#9ca3af;font-size:.85rem;font-style:italic}
  </style>
</head>
<body>
<div class="cv-layout">
  <?php require __DIR__ . '/../nav.php'; ?>
  <div class="page">
    <a href="index.php" class="back">← My Contacts</a>

    <?php if ($added): ?>
      <div class="alert alert-success">Contact added successfully.</div>
    <?php endif ?>
    <?php if (isset($_GET['shared'])): ?>
      <div class="alert alert-success">Contact shared with the team.</div>
    <?php endif ?>

    <div class="contact-header">
      <div>
        <h1><?= h($row['full_name']) ?></h1>
        <div class="role"><?= h(implode(' · ', array_filter([$row['current_role'], $row['current_company'], $row['city']]))) ?></div>
        <div class="badge-row">
          <span class="badge badge-<?= $row['space'] ?>"><?= $row['space'] === 'personal' ? 'Personal' : 'Shared with team' ?></span>
          <?php if ($row['relationship_strength']): ?>
            <span class="badge badge-strength-<?= $row['relationship_strength'] ?>"><?= ucfirst($row['relationship_strength']) ?></span>
          <?php endif ?>
          <?php if ($row['relationship_type_label']): ?>
            <span class="badge badge-type"><?= h($row['relationship_type_label']) ?></span>
          <?php endif ?>
        </div>
      </div>
      <div class="actions">
        <a href="edit.php?id=<?= h($contact_id) ?>" class="btn btn-ghost">Edit</a>
        <?php if ($row['space'] === 'personal'): ?>
          <form method="POST" style="display:contents">
            <input type="hidden" name="action" value="share"/>
            <button type="submit" class="btn btn-success">Share with team →</button>
          </form>
        <?php endif ?>
      </div>
    </div>

    <div class="grid">
      <!-- Contact Info -->
      <div class="section">
        <div class="section-title">Contact Info</div>
        <?php if ($emails): ?>
          <div class="kv">
            <label>Email</label>
            <?php foreach ($emails as $e): ?>
              <p><a href="mailto:<?= h($e['email']) ?>"><?= h($e['email']) ?></a><?= $e['label'] ? ' <span style="color:#9ca3af;font-size:.75rem">('.$e['label'].')</span>' : '' ?></p>
            <?php endforeach ?>
          </div>
        <?php endif ?>
        <?php if ($phones): ?>
          <div class="kv">
            <label>Phone</label>
            <?php foreach ($phones as $p): ?>
              <p><?= h($p['phone']) ?><?= $p['label'] ? ' <span style="color:#9ca3af;font-size:.75rem">('.$p['label'].')</span>' : '' ?></p>
            <?php endforeach ?>
          </div>
        <?php endif ?>
        <?php if ($row['linkedin_url']): ?>
          <div class="kv">
            <label>LinkedIn</label>
            <a href="<?= h($row['linkedin_url']) ?>" target="_blank" rel="noopener"><?= h($row['linkedin_url']) ?></a>
          </div>
        <?php endif ?>
        <?php if (!$emails && !$phones && !$row['linkedin_url']): ?>
          <p class="empty-state">No contact details yet.</p>
        <?php endif ?>
      </div>

      <!-- Relationship -->
      <div class="section">
        <div class="section-title">Your Relationship</div>
        <?php if ($row['relationship_origin']): ?>
          <div class="kv">
            <label>How you know them</label>
            <p><?= h($row['relationship_origin']) ?></p>
          </div>
        <?php endif ?>
        <div class="kv">
          <label>Added</label>
          <p><?= date('j M Y', strtotime($row['added_at'])) ?></p>
        </div>
        <?php if ($row['last_interacted_at']): ?>
          <div class="kv">
            <label>Last interaction</label>
            <p><?= date('j M Y', strtotime($row['last_interacted_at'])) ?></p>
          </div>
        <?php endif ?>
      </div>

      <!-- Education -->
      <div class="section">
        <div class="section-title">Education</div>
        <?php if ($education): ?>
          <?php foreach ($education as $edu): ?>
            <div class="edu-row">
              <strong>
                <?= h($edu['institution']) ?>
                <?php if ($edu['is_notable']): ?><span class="notable">★ Notable</span><?php endif ?>
              </strong>
              <span><?= h(implode(', ', array_filter([$edu['degree'], $edu['field']]))) ?>
                <?php if ($edu['year_start'] || $edu['year_end']): ?>
                  · <?= $edu['year_start'] ?>–<?= $edu['year_end'] ?: 'present' ?>
                <?php endif ?>
              </span>
            </div>
          <?php endforeach ?>
        <?php else: ?>
          <p class="empty-state">No education added.</p>
        <?php endif ?>
      </div>

      <!-- Experience -->
      <div class="section">
        <div class="section-title">Experience</div>
        <?php if ($experience): ?>
          <?php foreach ($experience as $exp): ?>
            <div class="exp-row">
              <strong>
                <?= h($exp['company']) ?>
                <?php if ($exp['is_founder']): ?><span class="founder-badge">FOUNDER</span><?php endif ?>
                <?php if ($exp['is_investor']): ?><span class="investor-badge">INVESTOR</span><?php endif ?>
              </strong>
              <span><?= h($exp['role'] ?? '') ?>
                <?php if ($exp['year_start'] || $exp['year_end']): ?>
                  · <?= $exp['year_start'] ?>–<?= $exp['year_end'] ?: 'present' ?>
                <?php endif ?>
              </span>
            </div>
          <?php endforeach ?>
        <?php else: ?>
          <p class="empty-state">No experience added.</p>
        <?php endif ?>
      </div>

      <!-- Tags -->
      <?php if ($tags): ?>
        <div class="section full">
          <div class="section-title">Domain Tags</div>
          <?php foreach ($tags as $tag): ?>
            <span class="tag"><?= h($tag) ?></span>
          <?php endforeach ?>
        </div>
      <?php endif ?>

      <!-- Notes -->
      <?php if ($row['notes']): ?>
        <div class="section full">
          <div class="section-title">Notes <span style="font-size:.7rem;font-weight:400;text-transform:none">(shared with team)</span></div>
          <div class="notes-box"><?= h($row['notes']) ?></div>
        </div>
      <?php endif ?>

      <?php if ($row['private_notes']): ?>
        <div class="section full">
          <div class="section-title">Private Notes <span style="font-size:.7rem;font-weight:400;text-transform:none">(only you can see this)</span></div>
          <div class="private-notes-box"><?= h($row['private_notes']) ?></div>
        </div>
      <?php endif ?>
    </div>
  </div>
</div>
</body>
</html>
