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

function normalize_phone(string $p): string {
    // Remove formatting chars but keep +
    return preg_replace('/[^\d+]/', '', $p);
}

function parse_google_csv(string $tmp_path): array {
    $fp = fopen($tmp_path, 'r');
    $header = fgetcsv($fp);
    if (!$header) return [];
    $col = array_flip(array_map('trim', $header));

    $contacts = [];
    while (($row = fgetcsv($fp)) !== false) {
        // Pad short rows
        while (count($row) < count($header)) $row[] = '';

        $first  = trim($row[$col['First Name']] ?? '');
        $middle = trim($row[$col['Middle Name']] ?? '');
        $last   = trim($row[$col['Last Name']]   ?? '');
        $nickname = trim($row[$col['Nickname']]  ?? '');
        $file_as  = trim($row[$col['File As']]   ?? '');

        // Build best name
        $full_name = trim("$first $middle $last");
        if (!$full_name) $full_name = $nickname ?: $file_as;
        if (!$full_name) continue;

        $company = trim($row[$col['Organization Name']]  ?? '');
        $title   = trim($row[$col['Organization Title']] ?? '');

        // Phones (up to 3)
        $phones = [];
        for ($i = 1; $i <= 3; $i++) {
            $lk = "Phone $i - Label";
            $vk = "Phone $i - Value";
            if (!isset($col[$lk]) || !isset($col[$vk])) break;
            $num   = trim($row[$col[$vk]]);
            $label = strtolower(trim($row[$col[$lk]]));
            if (!$num) continue;
            // Normalize label
            if (str_contains($label, 'whatsapp')) $label = 'whatsapp';
            elseif (str_contains($label, 'work'))  $label = 'work';
            elseif (str_contains($label, 'mobile') || str_contains($label, 'cell')) $label = 'mobile';
            else $label = 'mobile';
            $phones[] = ['number' => $num, 'label' => $label];
        }

        // Emails (up to 2)
        $emails = [];
        for ($i = 1; $i <= 2; $i++) {
            $vk = "E-mail $i - Value";
            if (!isset($col[$vk])) break;
            $em = trim($row[$col[$vk]]);
            if ($em && filter_var($em, FILTER_VALIDATE_EMAIL)) $emails[] = $em;
        }

        if (!$phones && !$emails) continue;

        $contacts[] = [
            'full_name'     => $full_name,
            'company'       => $company,
            'title'         => $title,
            'phones'        => $phones,
            'emails'        => $emails,
            'primary_phone' => $phones[0]['number'] ?? '',
            'primary_email' => $emails[0] ?? '',
        ];
    }
    fclose($fp);
    return $contacts;
}

// ── Step 2: save ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['step'] ?? '') === 'save') {
    $rows    = json_decode($_POST['rows_json'] ?? '[]', true);
    $saved   = 0;
    $skipped = 0;

    foreach ($rows as $i => $row) {
        if (empty($_POST["import_{$i}"])) { $skipped++; continue; }

        $rel_origin   = trim($_POST["rel_origin_{$i}"] ?? '');
        $rel_type     = $_POST["rel_type_{$i}"] ?? '';
        $rel_strength = $_POST["rel_strength_{$i}"] ?? '';

        // Dedup by phone or email
        $skip = false;
        foreach ($row['phones'] as $ph) {
            $norm = normalize_phone($ph['number']);
            $dup  = $db->prepare("SELECT cp.cluster_id FROM cluster_phones cp
                JOIN contacts c ON c.cluster_id = cp.cluster_id
                WHERE REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(cp.phone,' ',''),'-',''),'(',')',')',''),'.','' ) = ?
                AND c.owner_member_id = ?");
            // simpler: just match raw
            $dup = $db->prepare("SELECT cp.cluster_id FROM cluster_phones cp
                JOIN contacts c ON c.cluster_id = cp.cluster_id
                WHERE cp.phone = ? AND c.owner_member_id = ?");
            $dup->execute([$ph['number'], $member['member_id']]);
            if ($dup->fetch()) { $skip = true; break; }
        }
        if (!$skip) {
            foreach ($row['emails'] as $em) {
                $dup = $db->prepare("SELECT ce.cluster_id FROM cluster_emails ce
                    JOIN contacts c ON c.cluster_id = ce.cluster_id
                    WHERE ce.email = ? AND c.owner_member_id = ?");
                $dup->execute([$em, $member['member_id']]);
                if ($dup->fetch()) { $skip = true; break; }
            }
        }
        if ($skip) { $skipped++; continue; }

        $cluster_id = uuid();
        $contact_id = uuid();

        $db->prepare("INSERT INTO person_clusters
            (cluster_id,full_name,current_role,current_company,last_updated_by)
            VALUES (?,?,?,?,?)")
          ->execute([$cluster_id, $row['full_name'],
                     $row['title']   ?: null,
                     $row['company'] ?: null,
                     $member['member_id']]);

        foreach ($row['phones'] as $j => $ph) {
            $db->prepare("INSERT IGNORE INTO cluster_phones
                (phone_id,cluster_id,phone,label,is_primary,added_by) VALUES (?,?,?,?,?,?)")
              ->execute([uuid(),$cluster_id,$ph['number'],$ph['label'],$j===0?1:0,$member['member_id']]);
        }
        foreach ($row['emails'] as $j => $em) {
            $db->prepare("INSERT IGNORE INTO cluster_emails
                (email_id,cluster_id,email,is_primary,added_by) VALUES (?,?,?,?,?)")
              ->execute([uuid(),$cluster_id,$em,$j===0?1:0,$member['member_id']]);
        }

        $db->prepare("INSERT INTO contacts
            (contact_id,owner_member_id,cluster_id,space,origin_source,
             relationship_origin,relationship_type,relationship_strength)
            VALUES (?,?,?,'personal','manual',?,?,?)")
          ->execute([$contact_id,$member['member_id'],$cluster_id,
                     $rel_origin?:null,$rel_type?:null,$rel_strength?:null]);

        $saved++;
    }

    $step = 'done';
    $done_saved   = $saved;
    $done_skipped = $skipped;
}

// ── Step 1: parse CSV ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['step'] ?? '') === 'upload') {
    if (empty($_FILES['csv']['tmp_name'])) {
        $errors[] = 'Please select a CSV file.';
    } else {
        $preview = parse_google_csv($_FILES['csv']['tmp_name']);
        if (empty($preview)) {
            $errors[] = 'No contacts found. Make sure you exported in Google CSV format.';
        } else {
            $step = 'review';
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
  <title>CoreContacts — Import Google Contacts</title>
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
    .upload-area input{display:none}
    .upload-area p{color:#6b7280;font-size:.875rem;margin-top:8px}
    .file-name{font-size:.82rem;color:#374151;margin-top:10px;font-weight:600}
    .how-to{background:#f0f9ff;border:1px solid #bae6fd;border-radius:8px;padding:16px 20px;margin-bottom:24px;font-size:.82rem;line-height:1.8;color:#0369a1}
    .how-to strong{display:block;margin-bottom:4px;color:#1a1a2e}
    .how-to ol{padding-left:20px}
    .error-box{background:#fef2f2;border:1px solid #fecaca;color:#dc2626;border-radius:7px;padding:12px 16px;margin-bottom:20px;font-size:.85rem}
    .btn{display:inline-flex;align-items:center;gap:6px;padding:10px 22px;border-radius:7px;font-size:.875rem;font-weight:600;cursor:pointer;text-decoration:none;border:none;font-family:inherit;transition:background .15s}
    .btn-primary{background:#1a1a2e;color:#fff}.btn-primary:hover{background:#2d2d4e}
    .btn-ghost{background:#f3f4f6;color:#374151}.btn-ghost:hover{background:#e5e7eb}
    .review-bar{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap;gap:10px}
    .review-bar .count{font-size:.875rem;color:#6b7280}
    .select-all{font-size:.82rem;color:#C9972A;cursor:pointer;text-decoration:underline;background:none;border:none;font-family:inherit}
    table{width:100%;border-collapse:collapse;font-size:.82rem}
    thead th{text-align:left;padding:8px 10px;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#9ca3af;border-bottom:2px solid #e2e5ef;white-space:nowrap}
    tbody tr{border-bottom:1px solid #f3f4f6}
    tbody tr.unchecked{opacity:.4}
    td{padding:9px 10px;vertical-align:top}
    td input[type=text],td select{width:100%;padding:5px 8px;border:1.5px solid #d1d5db;border-radius:5px;font-size:.8rem;font-family:inherit;outline:none}
    td input[type=text]:focus,td select:focus{border-color:#C9972A}
    td input[type=checkbox]{width:16px;height:16px;cursor:pointer;accent-color:#1a1a2e}
    .name-cell{font-weight:600}
    .meta-cell{color:#6b7280;font-size:.78rem;margin-top:2px}
    .sticky-footer{position:sticky;bottom:0;background:#fff;border-top:1px solid #e2e5ef;padding:16px 0;margin-top:24px;display:flex;gap:12px;align-items:center}
    .done-card{text-align:center;padding:48px 32px}
    .done-card .big-num{font-size:3rem;font-weight:700;color:#1a1a2e;line-height:1}
    .done-card p{color:#6b7280;margin-top:8px;margin-bottom:24px}
    .success-icon{font-size:2.5rem;margin-bottom:12px}
    .info-box{background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:12px 16px;margin-bottom:20px;font-size:.82rem;color:#92400e}
  </style>
</head>
<body>
<div class="cv-layout">
  <?php require __DIR__ . '/../nav.php'; ?>
  <div class="page">
    <a href="index.php" class="back">← My Contacts</a>
    <h1>Import Google Contacts</h1>
    <p class="sub">Upload the CSV exported from Google Contacts or contacts.google.com.</p>

    <div class="steps">
      <div class="step <?= $step === 'upload' ? 'active' : 'done' ?>">
        <div class="step-num"><?= $step !== 'upload' ? '✓' : '1' ?></div> Upload CSV
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
        <strong>How to export from Google Contacts:</strong>
        <ol>
          <li>Go to <strong>contacts.google.com</strong></li>
          <li>Click <strong>Export</strong> in the left sidebar</li>
          <li>Choose <strong>Google CSV</strong> format → Export</li>
          <li>Upload the downloaded file here</li>
        </ol>
      </div>

      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="step" value="upload"/>
        <div class="card">
          <div class="upload-area" id="drop-zone">
            <label for="csv-input">
              <svg width="32" height="32" fill="none" stroke="#9ca3af" stroke-width="1.5" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
              <p>Drag & drop your Google Contacts CSV here, or click to browse</p>
              <div class="file-name" id="file-name"></div>
            </label>
            <input type="file" id="csv-input" name="csv" accept=".csv" required/>
          </div>
        </div>
        <div style="display:flex;gap:12px">
          <button type="submit" class="btn btn-primary">Parse CSV →</button>
          <a href="index.php" class="btn btn-ghost">Cancel</a>
        </div>
      </form>

    <?php elseif ($step === 'review'): ?>
      <div class="info-box">
        Tip: Deselect contacts that aren't relevant to your professional network (drivers, services, family) before importing. You can also import everything now and clean up later.
      </div>

      <form method="POST">
        <input type="hidden" name="step" value="save"/>
        <input type="hidden" name="rows_json" value="<?= h(json_encode($preview)) ?>"/>

        <div class="review-bar">
          <span class="count"><?= count($preview) ?> contacts found</span>
          <div style="display:flex;gap:12px">
            <button type="button" class="select-all" onclick="setAll(true)">Select all</button>
            <button type="button" class="select-all" style="color:#9ca3af" onclick="setAll(false)">Deselect all</button>
          </div>
        </div>

        <div class="card" style="padding:0;overflow:auto;max-height:70vh">
          <table>
            <thead>
              <tr>
                <th style="width:36px"></th>
                <th>Name</th>
                <th>Company / Role</th>
                <th>Contact</th>
                <th style="width:150px">How you know them</th>
                <th style="width:120px">Type</th>
                <th style="width:110px">Strength</th>
              </tr>
            </thead>
            <tbody id="review-body">
              <?php foreach ($preview as $i => $row): ?>
                <tr id="row-<?= $i ?>">
                  <td style="text-align:center;padding-top:12px">
                    <input type="checkbox" name="import_<?= $i ?>" value="1" checked
                           onchange="toggleRow(<?= $i ?>, this.checked)"/>
                  </td>
                  <td><div class="name-cell"><?= h($row['full_name']) ?></div></td>
                  <td>
                    <?php if ($row['title'] || $row['company']): ?>
                      <div class="meta-cell"><?= h(implode(' · ', array_filter([$row['title'], $row['company']]))) ?></div>
                    <?php endif ?>
                  </td>
                  <td>
                    <?php foreach ($row['phones'] as $ph): ?>
                      <div class="meta-cell"><?= h($ph['number']) ?> <span style="color:#c4c9d4;font-size:.72rem"><?= $ph['label'] ?></span></div>
                    <?php endforeach ?>
                    <?php foreach ($row['emails'] as $em): ?>
                      <div class="meta-cell" style="font-size:.75rem"><?= h($em) ?></div>
                    <?php endforeach ?>
                  </td>
                  <td><input type="text" name="rel_origin_<?= $i ?>" placeholder="optional"/></td>
                  <td>
                    <select name="rel_type_<?= $i ?>">
                      <option value="">—</option>
                      <?php foreach ($rel_types as $rt): ?>
                        <option value="<?= h($rt['value_id']) ?>"><?= h($rt['value']) ?></option>
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
          <a href="import_google.php" class="btn btn-ghost">Start over</a>
          <span id="selected-count" style="font-size:.82rem;color:#9ca3af;margin-left:auto"></span>
        </div>
      </form>

    <?php elseif ($step === 'done'): ?>
      <div class="card done-card">
        <div class="success-icon">✓</div>
        <div class="big-num"><?= $done_saved ?></div>
        <p>contacts imported<?= $done_skipped ? " · {$done_skipped} skipped (already in your contacts)" : '' ?></p>
        <div style="display:flex;gap:12px;justify-content:center">
          <a href="index.php" class="btn btn-primary">View my contacts</a>
          <a href="import_google.php" class="btn btn-ghost">Import another file</a>
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
  document.querySelectorAll('#review-body input[type=checkbox]').forEach(cb => {
    cb.checked = checked;
    toggleRow(cb.name.replace('import_',''), checked);
  });
}
function updateCount() {
  const total   = document.querySelectorAll('#review-body input[type=checkbox]').length;
  const checked = document.querySelectorAll('#review-body input[type=checkbox]:checked').length;
  const el = document.getElementById('selected-count');
  if (el) el.textContent = checked + ' of ' + total + ' selected';
}
const zone = document.getElementById('drop-zone');
if (zone) {
  zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('drag'); });
  zone.addEventListener('dragleave', () => zone.classList.remove('drag'));
  zone.addEventListener('drop', e => {
    e.preventDefault(); zone.classList.remove('drag');
    const f = e.dataTransfer.files[0];
    if (f) { document.getElementById('csv-input').files = e.dataTransfer.files; document.getElementById('file-name').textContent = f.name; }
  });
  document.getElementById('csv-input')?.addEventListener('change', e => {
    document.getElementById('file-name').textContent = e.target.files[0]?.name || '';
  });
}
updateCount();
</script>
</body>
</html>
