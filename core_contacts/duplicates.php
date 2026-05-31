<?php
require_once __DIR__ . '/../session_guard.php';
require_once __DIR__ . '/cc_db.php';

$member = cc_member($_SESSION['auth_email']);
if (!$member) { header('Location: /CVwebapp/index.php'); exit; }

$db  = getDB();
$mid = $member['member_id'];

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES|ENT_HTML5, 'UTF-8'); }
function uuid() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0,0xffff),mt_rand(0,0xffff),mt_rand(0,0xffff),
        mt_rand(0,0x0fff)|0x4000,mt_rand(0,0x3fff)|0x8000,
        mt_rand(0,0xffff),mt_rand(0,0xffff),mt_rand(0,0xffff));
}

// ── SCAN ─────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'scan') {
    // Clear existing pending pairs for this member
    $db->prepare("DELETE dl FROM duplicate_links dl
        JOIN contacts ca ON ca.contact_id = dl.contact_id_a
        WHERE ca.owner_member_id = ? AND dl.status = 'pending'")
      ->execute([$mid]);

    $pairs = []; // key = "minId|maxId" => match_reason

    // 1. Email matches
    $email_matches = $db->prepare("
        SELECT c1.contact_id AS id_a, c2.contact_id AS id_b
        FROM contacts c1
        JOIN contacts c2 ON c2.owner_member_id = c1.owner_member_id
            AND c2.cluster_id != c1.cluster_id AND c2.contact_id > c1.contact_id
        JOIN cluster_emails e1 ON e1.cluster_id = c1.cluster_id
        JOIN cluster_emails e2 ON e2.cluster_id = c2.cluster_id
            AND LOWER(e2.email) = LOWER(e1.email)
        WHERE c1.owner_member_id = ?
    ");
    $email_matches->execute([$mid]);
    foreach ($email_matches->fetchAll() as $r) {
        $key = min($r['id_a'],$r['id_b']).'|'.max($r['id_a'],$r['id_b']);
        $pairs[$key] = 'email';
    }

    // 2. Phone matches (last 10 digits)
    $phone_matches = $db->prepare("
        SELECT c1.contact_id AS id_a, c2.contact_id AS id_b
        FROM contacts c1
        JOIN contacts c2 ON c2.owner_member_id = c1.owner_member_id
            AND c2.cluster_id != c1.cluster_id AND c2.contact_id > c1.contact_id
        JOIN cluster_phones p1 ON p1.cluster_id = c1.cluster_id
        JOIN cluster_phones p2 ON p2.cluster_id = c2.cluster_id
            AND RIGHT(REGEXP_REPLACE(p2.phone,'[^0-9]',''),10)
              = RIGHT(REGEXP_REPLACE(p1.phone,'[^0-9]',''),10)
            AND LENGTH(REGEXP_REPLACE(p1.phone,'[^0-9]','')) >= 7
        WHERE c1.owner_member_id = ?
    ");
    $phone_matches->execute([$mid]);
    foreach ($phone_matches->fetchAll() as $r) {
        $key = min($r['id_a'],$r['id_b']).'|'.max($r['id_a'],$r['id_b']);
        if (!isset($pairs[$key])) $pairs[$key] = 'phone';
    }

    // 3. Name matches (word overlap >= 0.7) — PHP-side
    $all_contacts = $db->prepare("
        SELECT c.contact_id, c.cluster_id, p.full_name
        FROM contacts c JOIN person_clusters p ON p.cluster_id = c.cluster_id
        WHERE c.owner_member_id = ? AND p.full_name IS NOT NULL AND p.full_name != ''
    ");
    $all_contacts->execute([$mid]);
    $contacts_list = $all_contacts->fetchAll();

    // Build inverted word index
    $word_index = []; // word => [contact_idx, ...]
    foreach ($contacts_list as $idx => $c) {
        $words = preg_split('/\s+/', strtolower(trim($c['full_name'])));
        foreach ($words as $w) {
            if (strlen($w) >= 4) $word_index[$w][] = $idx;
        }
    }

    // Find candidate pairs sharing at least one word
    $checked = [];
    foreach ($word_index as $w => $idxs) {
        if (count($idxs) < 2) continue;
        $n = count($idxs);
        for ($i = 0; $i < $n; $i++) {
            for ($j = $i+1; $j < $n; $j++) {
                $ia = $idxs[$i]; $ib = $idxs[$j];
                $pair_key = min($ia,$ib).'_'.max($ia,$ib);
                if (isset($checked[$pair_key])) continue;
                $checked[$pair_key] = true;

                $ca = $contacts_list[$ia];
                $cb = $contacts_list[$ib];
                if ($ca['cluster_id'] === $cb['cluster_id']) continue;

                // Compute word overlap
                $wa = preg_split('/\s+/', strtolower(trim($ca['full_name'])));
                $wb = preg_split('/\s+/', strtolower(trim($cb['full_name'])));
                $wa = array_filter($wa, fn($x) => strlen($x) >= 3);
                $wb = array_filter($wb, fn($x) => strlen($x) >= 3);
                if (empty($wa) || empty($wb)) continue;
                $common = count(array_intersect($wa, $wb));
                $score = $common / max(count($wa), count($wb));

                if ($score >= 0.7) {
                    $key = min($ca['contact_id'],$cb['contact_id']).'|'.max($ca['contact_id'],$cb['contact_id']);
                    if (!isset($pairs[$key])) $pairs[$key] = 'name';
                }
            }
        }
    }

    // Insert all pairs
    $ins = $db->prepare("INSERT IGNORE INTO duplicate_links
        (link_id, contact_id_a, contact_id_b, status, match_reason)
        VALUES (?, ?, ?, 'pending', ?)");
    foreach ($pairs as $key => $reason) {
        [$id_a, $id_b] = explode('|', $key);
        $ins->execute([uuid(), $id_a, $id_b, $reason]);
    }

    $scan_count = count($pairs);
    header('Location: duplicates.php?scanned='.$scan_count);
    exit;
}

// ── DISMISS ──────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'dismiss') {
    $link_id = $_POST['link_id'] ?? '';
    $db->prepare("UPDATE duplicate_links SET status='dismissed' WHERE link_id=?")->execute([$link_id]);
    header('Location: duplicates.php');
    exit;
}

// ── MERGE ────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'merge') {
    $link_id   = $_POST['link_id']   ?? '';
    $winner_id = $_POST['winner_id'] ?? ''; // cluster_id to keep
    $loser_id  = $_POST['loser_id']  ?? ''; // cluster_id to absorb

    if ($winner_id && $loser_id && $winner_id !== $loser_id) {
        // Verify both clusters belong to this member
        $check = $db->prepare("SELECT COUNT(*) FROM contacts
            WHERE cluster_id IN (?,?) AND owner_member_id = ?");
        $check->execute([$winner_id, $loser_id, $mid]);
        if ($check->fetchColumn() >= 2) {

            // Move emails (skip duplicates)
            $db->prepare("UPDATE IGNORE cluster_emails SET cluster_id=? WHERE cluster_id=?")
              ->execute([$winner_id, $loser_id]);
            $db->prepare("DELETE FROM cluster_emails WHERE cluster_id=?")->execute([$loser_id]);

            // Move phones (skip duplicates)
            $db->prepare("UPDATE IGNORE cluster_phones SET cluster_id=? WHERE cluster_id=?")
              ->execute([$winner_id, $loser_id]);
            $db->prepare("DELETE FROM cluster_phones WHERE cluster_id=?")->execute([$loser_id]);

            // Move tags (skip duplicates)
            $db->prepare("UPDATE IGNORE contact_tags SET cluster_id=? WHERE cluster_id=?")
              ->execute([$winner_id, $loser_id]);
            $db->prepare("DELETE FROM contact_tags WHERE cluster_id=?")->execute([$loser_id]);

            // Move education + experience
            $db->prepare("UPDATE education SET cluster_id=? WHERE cluster_id=?")
              ->execute([$winner_id, $loser_id]);
            $db->prepare("UPDATE experience SET cluster_id=? WHERE cluster_id=?")
              ->execute([$winner_id, $loser_id]);

            // Merge cluster fields (fill winner's nulls from loser)
            $db->prepare("UPDATE person_clusters w
                JOIN person_clusters l ON l.cluster_id=?
                SET w.linkedin_url    = COALESCE(w.linkedin_url, l.linkedin_url),
                    w.current_role    = COALESCE(w.current_role, l.current_role),
                    w.current_company = COALESCE(w.current_company, l.current_company),
                    w.city            = COALESCE(w.city, l.city),
                    w.notes           = CASE WHEN w.notes IS NULL THEN l.notes
                                             WHEN l.notes IS NULL THEN w.notes
                                             ELSE CONCAT(w.notes, '\n---\n', l.notes) END
                WHERE w.cluster_id=?")->execute([$loser_id, $winner_id]);

            // Redirect all contacts on loser cluster to winner
            $db->prepare("UPDATE contacts SET cluster_id=? WHERE cluster_id=?")
              ->execute([$winner_id, $loser_id]);

            // Delete loser cluster
            $db->prepare("DELETE FROM person_clusters WHERE cluster_id=?")->execute([$loser_id]);

            // Mark link as confirmed
            $db->prepare("UPDATE duplicate_links SET status='confirmed', merged_cluster_id=? WHERE link_id=?")
              ->execute([$winner_id, $link_id]);

            // Also dismiss other pending links that involved either cluster
            // (find contact_ids that were on loser cluster — now all on winner)
            $db->prepare("UPDATE duplicate_links SET status='confirmed', merged_cluster_id=?
                WHERE link_id != ? AND status='pending'
                AND (contact_id_a IN (SELECT contact_id FROM contacts WHERE cluster_id=?)
                  OR contact_id_b IN (SELECT contact_id FROM contacts WHERE cluster_id=?))")
              ->execute([$winner_id, $link_id, $winner_id, $winner_id]);
        }
    }
    header('Location: duplicates.php?merged=1');
    exit;
}

// ── LOAD PENDING PAIRS ───────────────────────────────────────────────────────
$pending = $db->prepare("
    SELECT dl.link_id, dl.match_reason,
           pa.full_name AS name_a, pa.current_role AS role_a, pa.current_company AS company_a,
           pa.linkedin_url AS li_a, pa.cluster_id AS cluster_a,
           ca.contact_id AS contact_a, ca.origin_source AS src_a,
           pb.full_name AS name_b, pb.current_role AS role_b, pb.current_company AS company_b,
           pb.linkedin_url AS li_b, pb.cluster_id AS cluster_b,
           cb.contact_id AS contact_b, cb.origin_source AS src_b,
           ea.email AS email_a, eb.email AS email_b,
           pha.phone AS phone_a, phb.phone AS phone_b
    FROM duplicate_links dl
    JOIN contacts ca ON ca.contact_id = dl.contact_id_a
    JOIN contacts cb ON cb.contact_id = dl.contact_id_b
    JOIN person_clusters pa ON pa.cluster_id = ca.cluster_id
    JOIN person_clusters pb ON pb.cluster_id = cb.cluster_id
    LEFT JOIN cluster_emails ea ON ea.cluster_id = ca.cluster_id AND ea.is_primary = 1
    LEFT JOIN cluster_emails eb ON eb.cluster_id = cb.cluster_id AND eb.is_primary = 1
    LEFT JOIN cluster_phones pha ON pha.cluster_id = ca.cluster_id AND pha.is_primary = 1
    LEFT JOIN cluster_phones phb ON phb.cluster_id = cb.cluster_id AND phb.is_primary = 1
    WHERE ca.owner_member_id = ? AND dl.status = 'pending'
    ORDER BY dl.match_reason ASC, pa.full_name ASC
    LIMIT 200
");
$pending->execute([$mid]);
$pairs = $pending->fetchAll();

$total_pending = $db->prepare("SELECT COUNT(*) FROM duplicate_links dl
    JOIN contacts ca ON ca.contact_id = dl.contact_id_a
    WHERE ca.owner_member_id = ? AND dl.status = 'pending'");
$total_pending->execute([$mid]);
$total = $total_pending->fetchColumn();

$nav_active = 'contacts_dupes';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>CoreContacts — Duplicates</title>
  <style>
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
    body{font-family:'Segoe UI',system-ui,sans-serif;background:#f7f8fc;color:#1a1a2e}
    .page{padding:36px 40px;max-width:1100px}
    .page-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:28px;gap:16px;flex-wrap:wrap}
    .page-header h1{font-family:Georgia,serif;font-size:1.5rem;font-weight:700}
    .page-header h1 span{color:#C9972A}
    .btn{display:inline-flex;align-items:center;gap:6px;padding:9px 18px;border-radius:7px;font-size:.875rem;font-weight:600;cursor:pointer;text-decoration:none;border:none;font-family:inherit;transition:background .15s}
    .btn-primary{background:#1a1a2e;color:#fff}.btn-primary:hover{background:#2d2d4e}
    .btn-ghost{background:#f3f4f6;color:#374151}.btn-ghost:hover{background:#e5e7eb}
    .btn-danger{background:#fef2f2;color:#dc2626;border:1px solid #fecaca}.btn-danger:hover{background:#fee2e2}
    .btn-merge{background:#f0fdf4;color:#166534;border:1px solid #bbf7d0}.btn-merge:hover{background:#dcfce7}
    .notice{border-radius:8px;padding:14px 18px;margin-bottom:24px;font-size:.875rem}
    .notice-info{background:#eff6ff;border:1px solid #bfdbfe;color:#1e40af}
    .notice-success{background:#f0fdf4;border:1px solid #bbf7d0;color:#166534}
    .empty{text-align:center;padding:64px 20px;color:#9ca3af}
    .empty h2{font-size:1.1rem;margin-bottom:8px;color:#6b7280}
    .section-label{font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#9ca3af;margin-bottom:12px}
    .pair-card{background:#fff;border:1px solid #e2e5ef;border-radius:10px;margin-bottom:16px;overflow:hidden}
    .pair-header{display:flex;align-items:center;gap:10px;padding:12px 16px;border-bottom:1px solid #f3f4f6;background:#fafafa}
    .reason-badge{font-size:.7rem;padding:2px 8px;border-radius:10px;font-weight:700}
    .reason-email{background:#dbeafe;color:#1d4ed8}
    .reason-phone{background:#dcfce7;color:#166534}
    .reason-name{background:#fef3c7;color:#92400e}
    .pair-body{display:grid;grid-template-columns:1fr 40px 1fr;align-items:start}
    .side{padding:16px}
    .side-name{font-weight:700;font-size:.95rem;margin-bottom:3px}
    .side-role{font-size:.8rem;color:#6b7280;margin-bottom:8px}
    .side-detail{font-size:.78rem;color:#374151;margin-bottom:3px}
    .side-detail span{color:#9ca3af}
    .side-source{font-size:.7rem;padding:2px 7px;border-radius:8px;background:#f3f4f6;color:#6b7280;display:inline-block;margin-top:6px}
    .vs{display:flex;align-items:center;justify-content:center;color:#d1d5db;font-size:.85rem;font-weight:700;padding-top:20px}
    .pair-actions{display:flex;gap:8px;align-items:center;padding:12px 16px;border-top:1px solid #f3f4f6;flex-wrap:wrap}
    .pair-actions .spacer{flex:1}
    .stats-bar{display:flex;gap:24px;margin-bottom:24px;flex-wrap:wrap}
    .stat{background:#fff;border:1px solid #e2e5ef;border-radius:8px;padding:14px 20px;min-width:120px}
    .stat-num{font-size:1.5rem;font-weight:700;color:#1a1a2e}
    .stat-label{font-size:.75rem;color:#9ca3af;margin-top:2px}
    .filter-bar{display:flex;gap:4px;background:#e9ebf0;border-radius:8px;padding:4px;width:fit-content;margin-bottom:20px}
    .filter-bar button{padding:6px 14px;border-radius:6px;font-size:.8rem;font-weight:600;color:#6b7280;border:none;background:none;cursor:pointer;font-family:inherit}
    .filter-bar button.active{background:#fff;color:#1a1a2e;box-shadow:0 1px 4px rgba(0,0,0,.1)}
  </style>
</head>
<body>
<div class="cv-layout">
  <?php require __DIR__ . '/../nav.php'; ?>
  <div class="page">
    <div class="page-header">
      <h1>Core<span>Contacts</span> — Duplicates</h1>
      <form method="POST">
        <input type="hidden" name="action" value="scan"/>
        <button type="submit" class="btn btn-primary">
          <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
          Scan for duplicates
        </button>
      </form>
    </div>

    <?php if (isset($_GET['scanned'])): ?>
      <div class="notice notice-success">Found <strong><?= (int)$_GET['scanned'] ?></strong> potential duplicate pairs. Review them below.</div>
    <?php endif ?>
    <?php if (isset($_GET['merged'])): ?>
      <div class="notice notice-success">Contacts merged successfully.</div>
    <?php endif ?>

    <?php if ($total > 0): ?>
      <div class="stats-bar">
        <div class="stat"><div class="stat-num"><?= $total ?></div><div class="stat-label">Pending pairs</div></div>
      </div>

      <div class="filter-bar">
        <button type="button" class="active" onclick="filterPairs('all',this)">All (<?= $total ?>)</button>
        <button type="button" onclick="filterPairs('email',this)">Same email</button>
        <button type="button" onclick="filterPairs('phone',this)">Same phone</button>
        <button type="button" onclick="filterPairs('name',this)">Similar name</button>
      </div>

      <?php foreach ($pairs as $pair): ?>
        <?php
          // Determine "richer" cluster as default winner (more non-null fields)
          $score_a = (int)!!$pair['email_a'] + (int)!!$pair['phone_a'] + (int)!!$pair['role_a'] + (int)!!$pair['li_a'];
          $score_b = (int)!!$pair['email_b'] + (int)!!$pair['phone_b'] + (int)!!$pair['role_b'] + (int)!!$pair['li_b'];
          $winner  = $score_a >= $score_b ? 'a' : 'b';
        ?>
        <div class="pair-card" data-reason="<?= h($pair['match_reason']) ?>">
          <div class="pair-header">
            <span class="reason-badge reason-<?= h($pair['match_reason']) ?>">
              <?= $pair['match_reason'] === 'email' ? '✉ Same email' : ($pair['match_reason'] === 'phone' ? '📱 Same phone' : '👤 Similar name') ?>
            </span>
            <span style="font-size:.78rem;color:#9ca3af">link_id: <?= h(substr($pair['link_id'],0,8)) ?>…</span>
          </div>
          <div class="pair-body">
            <div class="side">
              <div class="side-name"><?= h($pair['name_a'] ?: '(no name)') ?></div>
              <div class="side-role"><?= h(implode(' · ', array_filter([$pair['role_a'],$pair['company_a']]))) ?></div>
              <?php if ($pair['email_a']): ?><div class="side-detail"><span>Email:</span> <?= h($pair['email_a']) ?></div><?php endif ?>
              <?php if ($pair['phone_a']): ?><div class="side-detail"><span>Phone:</span> <?= h($pair['phone_a']) ?></div><?php endif ?>
              <?php if ($pair['li_a']): ?><div class="side-detail"><span>LinkedIn:</span> ✓</div><?php endif ?>
              <span class="side-source"><?= h($pair['src_a']) ?></span>
            </div>
            <div class="vs">vs</div>
            <div class="side">
              <div class="side-name"><?= h($pair['name_b'] ?: '(no name)') ?></div>
              <div class="side-role"><?= h(implode(' · ', array_filter([$pair['role_b'],$pair['company_b']]))) ?></div>
              <?php if ($pair['email_b']): ?><div class="side-detail"><span>Email:</span> <?= h($pair['email_b']) ?></div><?php endif ?>
              <?php if ($pair['phone_b']): ?><div class="side-detail"><span>Phone:</span> <?= h($pair['phone_b']) ?></div><?php endif ?>
              <?php if ($pair['li_b']): ?><div class="side-detail"><span>LinkedIn:</span> ✓</div><?php endif ?>
              <span class="side-source"><?= h($pair['src_b']) ?></span>
            </div>
          </div>
          <div class="pair-actions">
            <!-- Merge keeping A -->
            <form method="POST" style="display:inline">
              <input type="hidden" name="action" value="merge"/>
              <input type="hidden" name="link_id" value="<?= h($pair['link_id']) ?>"/>
              <input type="hidden" name="winner_id" value="<?= h($pair['cluster_a']) ?>"/>
              <input type="hidden" name="loser_id"  value="<?= h($pair['cluster_b']) ?>"/>
              <button type="submit" class="btn btn-merge" style="font-size:.78rem;padding:6px 12px">
                ← Keep <strong><?= h(explode(' ',$pair['name_a'])[0]) ?></strong>
              </button>
            </form>
            <!-- Merge keeping B -->
            <form method="POST" style="display:inline">
              <input type="hidden" name="action" value="merge"/>
              <input type="hidden" name="link_id" value="<?= h($pair['link_id']) ?>"/>
              <input type="hidden" name="winner_id" value="<?= h($pair['cluster_b']) ?>"/>
              <input type="hidden" name="loser_id"  value="<?= h($pair['cluster_a']) ?>"/>
              <button type="submit" class="btn btn-merge" style="font-size:.78rem;padding:6px 12px">
                Keep <strong><?= h(explode(' ',$pair['name_b'])[0]) ?></strong> →
              </button>
            </form>
            <div class="spacer"></div>
            <a href="contact.php?id=<?= h($pair['contact_a']) ?>" target="_blank" class="btn btn-ghost" style="font-size:.75rem;padding:5px 10px">View A</a>
            <a href="contact.php?id=<?= h($pair['contact_b']) ?>" target="_blank" class="btn btn-ghost" style="font-size:.75rem;padding:5px 10px">View B</a>
            <!-- Dismiss -->
            <form method="POST" style="display:inline">
              <input type="hidden" name="action" value="dismiss"/>
              <input type="hidden" name="link_id" value="<?= h($pair['link_id']) ?>"/>
              <button type="submit" class="btn btn-danger" style="font-size:.78rem;padding:6px 12px">Not a duplicate</button>
            </form>
          </div>
        </div>
      <?php endforeach ?>

      <?php if ($total > 200): ?>
        <p style="text-align:center;color:#9ca3af;font-size:.82rem;padding:16px">Showing first 200 of <?= $total ?> pairs. Merge or dismiss some to see more.</p>
      <?php endif ?>

    <?php else: ?>
      <div class="empty">
        <h2>No duplicate pairs found</h2>
        <p>Click "Scan for duplicates" to check your <?= number_format(5000) ?> contacts for matches.</p>
      </div>
    <?php endif ?>
  </div>
</div>
<script>
function filterPairs(reason, btn) {
  document.querySelectorAll('.filter-bar button').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  document.querySelectorAll('.pair-card').forEach(card => {
    card.style.display = (reason === 'all' || card.dataset.reason === reason) ? '' : 'none';
  });
}
</script>
</body>
</html>
