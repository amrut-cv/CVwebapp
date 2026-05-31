<?php
require_once __DIR__ . '/../session_guard.php';
require_once __DIR__ . '/cc_db.php';

$member = cc_member($_SESSION['auth_email']);
if (!$member) { header('Location: /CVwebapp/index.php'); exit; }

$db        = getDB();
$rel_types = cc_rel_types();
$errors    = [];
$step      = 'upload';
$preview   = [];
$group_name = '';

// ── Parser ────────────────────────────────────────────────────────────────
function parse_whatsapp_txt(string $content): array {
    $senders = [];
    // Match both formats:
    // [DD/MM/YY, H:MM:SS AM/PM] Name: message
    // DD/MM/YY, H:MM am/pm - Name: message
    $pattern = '/(?:\[[\d\/]+,\s*[\d:]+(?:\s*[AP]M)?\]\s*|[\d\/]+,\s*[\d:]+(?:\s*[ap]m)?\s*-\s*)([^:]+):/u';
    preg_match_all($pattern, $content, $matches);
    foreach ($matches[1] as $name) {
        $name = trim($name);
        if (!$name) continue;
        if (str_starts_with($name, '~')) $name = ltrim(substr($name, 1));
        $name = trim($name);
        if (strlen($name) < 2) continue;
        $senders[$name] = ($senders[$name] ?? 0) + 1;
    }
    arsort($senders); // most active first
    return $senders;
}

function name_similarity(string $a, string $b): float {
    $a = strtolower(trim($a));
    $b = strtolower(trim($b));
    if ($a === $b) return 1.0;
    // Check if all words of the shorter name appear in the longer
    $words_a = preg_split('/\s+/', $a);
    $words_b = preg_split('/\s+/', $b);
    $shorter = count($words_a) <= count($words_b) ? $words_a : $words_b;
    $longer  = count($words_a) <= count($words_b) ? $words_b : $words_a;
    $matches = 0;
    foreach ($shorter as $w) {
        if (strlen($w) >= 3 && in_array($w, $longer)) $matches++;
    }
    return $matches / max(count($shorter), 1);
}

// ── Step 2: save ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['step'] ?? '') === 'save') {
    $rows      = json_decode($_POST['rows_json'] ?? '[]', true);
    $saved     = 0;
    $skipped   = 0;

    foreach ($rows as $i => $row) {
        if (empty($_POST["import_{$i}"])) { $skipped++; continue; }

        $full_name    = trim($_POST["name_{$i}"] ?? '') ?: $row['sender'];
        $rel_origin   = trim($_POST["rel_origin_{$i}"] ?? '');
        $rel_type     = $_POST["rel_type_{$i}"] ?? '';
        $rel_strength = $_POST["rel_strength_{$i}"] ?? '';

        $cluster_id = uuid();
        $contact_id = uuid();
        $is_phone   = (bool)($row['is_phone'] ?? false);

        // If sender is a phone number and name wasn't changed, use placeholder name
        $display_name = $full_name;
        if ($is_phone && preg_match('/^\+?[\d\s\-\(\)]{7,}$/', $display_name)) {
            $display_name = $row['sender']; // keep phone as name until edited
        }

        $db->prepare("INSERT INTO person_clusters (cluster_id,full_name,last_updated_by) VALUES (?,?,?)")
          ->execute([$cluster_id, $display_name, $member['member_id']]);

        $db->prepare("INSERT INTO contacts
            (contact_id,owner_member_id,cluster_id,space,origin_source,
             relationship_origin,relationship_type,relationship_strength)
            VALUES (?,?,?,'personal','whatsapp',?,?,?)")
          ->execute([$contact_id,$member['member_id'],$cluster_id,
                     $rel_origin?:null,$rel_type?:null,$rel_strength?:null]);

        // Save phone number if sender was a phone
        if ($is_phone) {
            $phone_num = preg_replace('/[^\d\+]/', '', $row['sender']);
            if (strlen($phone_num) >= 7) {
                $db->prepare("INSERT INTO cluster_phones (phone_id,cluster_id,phone,label,is_primary) VALUES (?,?,?,'whatsapp',1)")
                  ->execute([uuid(), $cluster_id, $phone_num]);
            }
        }

        $saved++;
    }

    $step = 'done';
    $done_saved   = $saved;
    $done_skipped = $skipped;
}

// ── Step 1: parse chat ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['step'] ?? '') === 'upload') {
    $group_name = trim($_POST['group_name'] ?? '');

    if (empty($_FILES['chat']['tmp_name'])) {
        $errors[] = 'Please select a chat .txt file.';
    } else {
        $content = file_get_contents($_FILES['chat']['tmp_name']);
        $senders = parse_whatsapp_txt($content);

        if (empty($senders)) {
            $errors[] = 'No messages found. Make sure you uploaded a WhatsApp chat export (.txt).';
        } else {
            // Load existing contact names + phones for fuzzy matching
            $existing = $db->prepare("SELECT p.full_name, c.contact_id, c.cluster_id FROM person_clusters p
                JOIN contacts c ON c.cluster_id = p.cluster_id
                WHERE c.owner_member_id = ?");
            $existing->execute([$member['member_id']]);
            $existing_contacts = $existing->fetchAll();

            // Build phone→name lookup (strip non-digits for matching)
            $phone_lookup = []; // digits_only => full_name
            $phones_q = $db->prepare("SELECT cp.phone, cp.cluster_id, p.full_name
                FROM cluster_phones cp
                JOIN contacts c ON c.cluster_id = cp.cluster_id
                JOIN person_clusters p ON p.cluster_id = cp.cluster_id
                WHERE c.owner_member_id = ?");
            $phones_q->execute([$member['member_id']]);
            foreach ($phones_q->fetchAll() as $pr) {
                $digits = preg_replace('/\D/', '', $pr['phone']);
                // Store last 10 digits for flexible matching
                $phone_lookup[substr($digits, -10)] = $pr['full_name'];
            }

            $my_name = $member['name']; // skip self

            foreach ($senders as $sender => $msg_count) {
                // Skip self
                if (stripos($sender, $my_name) !== false || strtolower($sender) === 'amrut') continue;

                $is_phone = (bool) preg_match('/^\+?[\d\s\-\(\)]{7,}$/', $sender);
                $best_match = null;
                $best_score = 0;

                if ($is_phone) {
                    // Match by phone number
                    $digits = preg_replace('/\D/', '', $sender);
                    $tail   = substr($digits, -10);
                    if (isset($phone_lookup[$tail])) {
                        $best_match = ['full_name' => $phone_lookup[$tail]];
                        $best_score = 1.0;
                    }
                } else {
                    // Fuzzy name match
                    foreach ($existing_contacts as $ec) {
                        $score = name_similarity($sender, $ec['full_name']);
                        if ($score > $best_score) {
                            $best_score = $score;
                            $best_match = $ec;
                        }
                    }
                }

                $preview[] = [
                    'sender'     => $sender,
                    'msg_count'  => $msg_count,
                    'is_phone'   => $is_phone,
                    'matched'    => $best_score >= 0.6 ? $best_match['full_name'] : null,
                    'match_score'=> $best_score,
                    'already_in' => $best_score >= 0.6,
                ];
            }

            if (empty($preview)) {
                $errors[] = 'No new contacts found.';
            } else {
                $step = 'review';
            }
        }
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
  <title>CoreContacts — Import WhatsApp Group</title>
  <style>
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
    body{font-family:'Segoe UI',system-ui,sans-serif;background:#f7f8fc;color:#1a1a2e}
    .page{padding:36px 40px;max-width:1000px}
    .back{font-size:.82rem;color:#6b7280;text-decoration:none;display:inline-flex;align-items:center;gap:4px;margin-bottom:20px}
    .back:hover{color:#1a1a2e}
    h1{font-family:Georgia,serif;font-size:1.4rem;font-weight:700;margin-bottom:6px}
    .sub{font-size:.875rem;color:#6b7280;margin-bottom:28px;line-height:1.6}
    .card{background:#fff;border:1px solid #e2e5ef;border-radius:10px;padding:28px;margin-bottom:24px}
    .steps{display:flex;gap:0;margin-bottom:32px}
    .step{display:flex;align-items:center;gap:8px;font-size:.82rem;color:#9ca3af}
    .step.active{color:#1a1a2e;font-weight:600}
    .step.done{color:#10b981}
    .step-num{width:22px;height:22px;border-radius:50%;border:2px solid currentColor;display:flex;align-items:center;justify-content:center;font-size:.7rem;font-weight:700;flex-shrink:0}
    .step-sep{width:32px;height:1px;background:#e2e5ef;margin:0 4px}
    .upload-area{border:2px dashed #d1d5db;border-radius:10px;padding:40px;text-align:center;cursor:pointer;transition:border-color .15s}
    .upload-area:hover,.upload-area.drag{border-color:#C9972A}
    .upload-area input[type=file]{display:none}
    .upload-area p{color:#6b7280;font-size:.875rem;margin-top:8px}
    .file-name{font-size:.82rem;color:#374151;margin-top:10px;font-weight:600}
    .how-to{background:#f0f9ff;border:1px solid #bae6fd;border-radius:8px;padding:16px 20px;margin-bottom:24px;font-size:.82rem;line-height:1.8;color:#0369a1}
    .how-to strong{display:block;margin-bottom:4px;color:#1a1a2e}
    .how-to ol{padding-left:20px}
    .error-box{background:#fef2f2;border:1px solid #fecaca;color:#dc2626;border-radius:7px;padding:12px 16px;margin-bottom:20px;font-size:.85rem}
    .info-box{background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:12px 16px;margin-bottom:20px;font-size:.82rem;color:#92400e}
    .btn{display:inline-flex;align-items:center;gap:6px;padding:10px 22px;border-radius:7px;font-size:.875rem;font-weight:600;cursor:pointer;text-decoration:none;border:none;font-family:inherit;transition:background .15s}
    .btn-primary{background:#1a1a2e;color:#fff}.btn-primary:hover{background:#2d2d4e}
    .btn-ghost{background:#f3f4f6;color:#374151}.btn-ghost:hover{background:#e5e7eb}
    label.field-label{display:block;font-size:.75rem;font-weight:700;color:#374151;margin-bottom:5px;text-transform:uppercase;letter-spacing:.06em}
    input[type=text].field-input{width:100%;max-width:400px;padding:10px 12px;border:1.5px solid #d1d5db;border-radius:7px;font-size:.9rem;outline:none;font-family:inherit;transition:border-color .15s}
    input[type=text].field-input:focus{border-color:#C9972A}
    .tab-bar{display:flex;gap:4px;background:#e9ebf0;border-radius:8px;padding:4px;width:fit-content;margin-bottom:20px}
    .tab-bar button{padding:7px 16px;border-radius:6px;font-size:.82rem;font-weight:600;color:#6b7280;border:none;background:none;cursor:pointer;transition:all .15s;font-family:inherit}
    .tab-bar button.active{background:#fff;color:#1a1a2e;box-shadow:0 1px 4px rgba(0,0,0,.1)}
    .review-bar{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap;gap:10px}
    .select-all{font-size:.82rem;color:#C9972A;cursor:pointer;text-decoration:underline;background:none;border:none;font-family:inherit}
    table{width:100%;border-collapse:collapse;font-size:.82rem}
    thead th{text-align:left;padding:8px 10px;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#9ca3af;border-bottom:2px solid #e2e5ef;white-space:nowrap}
    tbody tr{border-bottom:1px solid #f3f4f6}
    tbody tr.unchecked{opacity:.4}
    tbody tr.already-in{background:#f0fdf4}
    td{padding:9px 10px;vertical-align:middle}
    td input[type=text],td select{width:100%;padding:5px 8px;border:1.5px solid #d1d5db;border-radius:5px;font-size:.8rem;font-family:inherit;outline:none}
    td input[type=text]:focus,td select:focus{border-color:#C9972A}
    td input[type=checkbox]{width:16px;height:16px;cursor:pointer;accent-color:#1a1a2e}
    .match-badge{font-size:.7rem;padding:2px 7px;border-radius:8px;background:#dcfce7;color:#166534;font-weight:600;white-space:nowrap}
    .msg-count{font-size:.72rem;color:#9ca3af}
    .sticky-footer{position:sticky;bottom:0;background:#fff;border-top:1px solid #e2e5ef;padding:16px 0;margin-top:24px;display:flex;gap:12px;align-items:center}
    .done-card{text-align:center;padding:48px 32px}
    .done-card .big-num{font-size:3rem;font-weight:700;color:#1a1a2e;line-height:1}
    .done-card p{color:#6b7280;margin-top:8px;margin-bottom:24px}
    .success-icon{font-size:2.5rem;margin-bottom:12px}
  </style>
</head>
<body>
<div class="cv-layout">
  <?php require __DIR__ . '/../nav.php'; ?>
  <div class="page">
    <a href="index.php" class="back">← My Contacts</a>
    <h1>Import WhatsApp Group</h1>
    <p class="sub">Export a group chat and we'll extract every member who sent a message.</p>

    <div class="steps">
      <div class="step <?= $step === 'upload' ? 'active' : 'done' ?>">
        <div class="step-num"><?= $step !== 'upload' ? '✓' : '1' ?></div> Upload chat
      </div>
      <div class="step-sep"></div>
      <div class="step <?= $step === 'review' ? 'active' : ($step === 'done' ? 'done' : '') ?>">
        <div class="step-num"><?= $step === 'done' ? '✓' : '2' ?></div> Review
      </div>
      <div class="step-sep"></div>
      <div class="step <?= $step === 'done' ? 'active done' : '' ?>">
        <div class="step-num">3</div> Done
      </div>
    </div>

    <?php if ($errors): ?>
      <div class="error-box"><?= implode('<br>', array_map('h', $errors)) ?></div>
    <?php endif ?>

    <?php if ($step === 'upload'): ?>
      <div class="how-to">
        <strong>How to export a WhatsApp group chat:</strong>
        <ol>
          <li>Open the group in WhatsApp</li>
          <li>Tap the group name → Scroll down → <strong>Export chat</strong></li>
          <li>Choose <strong>Without media</strong></li>
          <li>Share / save the <strong>_chat.txt</strong> file and upload it here</li>
        </ol>
      </div>

      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="step" value="upload"/>
        <div class="card">
          <div style="margin-bottom:20px">
            <label class="field-label" for="group_name">Group name <span style="font-weight:400;text-transform:none">(used as relationship origin)</span></label>
            <input type="text" id="group_name" name="group_name" class="field-input"
                   placeholder="e.g. IITM Founders Network" value="<?= h($_POST['group_name'] ?? '') ?>"/>
          </div>
          <div class="upload-area" id="drop-zone">
            <label for="chat-input">
              <svg width="32" height="32" fill="none" stroke="#9ca3af" stroke-width="1.5" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
              <p>Drag & drop your <strong>_chat.txt</strong> file here, or click to browse</p>
              <div class="file-name" id="file-name"></div>
            </label>
            <input type="file" id="chat-input" name="chat" accept=".txt" required/>
          </div>
        </div>
        <div style="display:flex;gap:12px">
          <button type="submit" class="btn btn-primary">Parse chat →</button>
          <a href="index.php" class="btn btn-ghost">Cancel</a>
        </div>
      </form>

    <?php elseif ($step === 'review'): ?>
      <?php
        $new_contacts     = array_filter($preview, fn($r) => !$r['already_in']);
        $matched_contacts = array_filter($preview, fn($r) =>  $r['already_in']);
        $rel_type_alumni  = ''; // no default — user picks per contact
      ?>

      <div class="info-box">
        <strong><?= count($matched_contacts) ?></strong> people already in your contacts (shown in green — unchecked by default) ·
        <strong><?= count($new_contacts) ?></strong> new contacts to import
      </div>

      <form method="POST">
        <input type="hidden" name="step" value="save"/>
        <input type="hidden" name="rows_json" value="<?= h(json_encode($preview)) ?>"/>

        <div class="review-bar">
          <div class="tab-bar">
            <button type="button" class="active" onclick="filterRows('all', this)">All (<?= count($preview) ?>)</button>
            <button type="button" onclick="filterRows('new', this)">New only (<?= count($new_contacts) ?>)</button>
            <button type="button" onclick="filterRows('matched', this)">Already in contacts (<?= count($matched_contacts) ?>)</button>
          </div>
          <div style="display:flex;gap:12px">
            <button type="button" class="select-all" onclick="setAll(true)">Select all</button>
            <button type="button" class="select-all" style="color:#9ca3af" onclick="setAll(false)">Deselect all</button>
          </div>
        </div>

        <div class="card" style="padding:0;overflow:auto;max-height:65vh">
          <table>
            <thead>
              <tr>
                <th style="width:36px"></th>
                <th>Name / Phone in WhatsApp</th>
                <th style="width:80px">Messages</th>
                <th style="width:180px">Match in your contacts</th>
                <th style="width:160px">How you know them</th>
                <th style="width:120px">Type</th>
                <th style="width:110px">Strength</th>
              </tr>
            </thead>
            <tbody id="review-body">
              <?php foreach ($preview as $i => $row): ?>
                <tr id="row-<?= $i ?>" class="<?= $row['already_in'] ? 'already-in' : '' ?>"
                    data-status="<?= $row['already_in'] ? 'matched' : 'new' ?>">
                  <td style="text-align:center">
                    <input type="checkbox" name="import_<?= $i ?>" value="1"
                           <?= $row['already_in'] ? '' : 'checked' ?>
                           onchange="toggleRow(<?= $i ?>, this.checked)"/>
                  </td>
                  <td>
                    <?php if ($row['is_phone'] ?? false): ?>
                      <div style="font-size:.72rem;color:#9ca3af;margin-bottom:3px">📱 Not saved in WhatsApp — enter name:</div>
                    <?php endif ?>
                    <input type="text" name="name_<?= $i ?>"
                           value="<?= h(($row['is_phone'] ?? false) ? '' : $row['sender']) ?>"
                           placeholder="<?= ($row['is_phone'] ?? false) ? h($row['sender']) : '' ?>"/>
                  </td>
                  <td class="msg-count" style="text-align:center"><?= $row['msg_count'] ?></td>
                  <td>
                    <?php if ($row['matched']): ?>
                      <span class="match-badge">✓ <?= h($row['matched']) ?></span>
                    <?php else: ?>
                      <span style="color:#d1d5db;font-size:.78rem">—</span>
                    <?php endif ?>
                  </td>
                  <td>
                    <input type="text" name="rel_origin_<?= $i ?>"
                           value="<?= h($group_name ? "WhatsApp group: {$group_name}" : '') ?>"/>
                  </td>
                  <td>
                    <select name="rel_type_<?= $i ?>">
                      <option value="">—</option>
                      <?php foreach ($rel_types as $rt): ?>
                        <option value="<?= h($rt['value_id']) ?>"
                          <?= $rt['value_id'] === $rel_type_alumni ? 'selected' : '' ?>>
                          <?= h($rt['value']) ?>
                        </option>
                      <?php endforeach ?>
                    </select>
                  </td>
                  <td>
                    <select name="rel_strength_<?= $i ?>">
                      <option value="">—</option>
                      <option value="close">Close</option>
                      <option value="acquaintance" selected>Acquaintance</option>
                      <option value="distant">Distant</option>
                    </select>
                  </td>
                </tr>
              <?php endforeach ?>
            </tbody>
          </table>
        </div>

        <div class="sticky-footer">
          <button type="submit" class="btn btn-primary">Import selected →</button>
          <a href="import_whatsapp.php" class="btn btn-ghost">Start over</a>
          <span id="selected-count" style="font-size:.82rem;color:#9ca3af;margin-left:auto"></span>
        </div>
      </form>

    <?php elseif ($step === 'done'): ?>
      <div class="card done-card">
        <div class="success-icon">✓</div>
        <div class="big-num"><?= $done_saved ?></div>
        <p>contacts imported<?= $done_skipped ? " · {$done_skipped} skipped" : '' ?></p>
        <div style="display:flex;gap:12px;justify-content:center">
          <a href="index.php" class="btn btn-primary">View my contacts</a>
          <a href="import_whatsapp.php" class="btn btn-ghost">Import another group</a>
        </div>
      </div>
    <?php endif ?>
  </div>
</div>
<script>
function toggleRow(i, checked) {
  document.getElementById('row-' + i).classList.toggle('unchecked', !checked);
  updateCount();
}
function setAll(checked) {
  document.querySelectorAll('#review-body tr:not([style*="display:none"]) input[type=checkbox]').forEach(cb => {
    cb.checked = checked;
    toggleRow(cb.name.replace('import_',''), checked);
  });
}
function filterRows(type, btn) {
  document.querySelectorAll('.tab-bar button').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  document.querySelectorAll('#review-body tr').forEach(tr => {
    const status = tr.dataset.status;
    tr.style.display = (type === 'all' || tr.dataset.status === type) ? '' : 'none';
  });
  updateCount();
}
function updateCount() {
  const visible  = [...document.querySelectorAll('#review-body tr:not([style*="display:none"])')];
  const checked  = visible.filter(tr => tr.querySelector('input[type=checkbox]')?.checked).length;
  const el = document.getElementById('selected-count');
  if (el) el.textContent = checked + ' of ' + visible.length + ' selected';
}
const zone = document.getElementById('drop-zone');
if (zone) {
  zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('drag'); });
  zone.addEventListener('dragleave', () => zone.classList.remove('drag'));
  zone.addEventListener('drop', e => {
    e.preventDefault(); zone.classList.remove('drag');
    const f = e.dataTransfer.files[0];
    if (f) { document.getElementById('chat-input').files = e.dataTransfer.files; document.getElementById('file-name').textContent = f.name; }
  });
  document.getElementById('chat-input')?.addEventListener('change', e => {
    document.getElementById('file-name').textContent = e.target.files[0]?.name || '';
  });
}
updateCount();
</script>
</body>
</html>
