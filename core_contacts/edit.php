<?php
require_once __DIR__ . '/../session_guard.php';
require_once __DIR__ . '/cc_db.php';

$member = cc_member($_SESSION['auth_email']);
if (!$member) { header('Location: /CVwebapp/index.php'); exit; }

$db         = getDB();
$contact_id = $_GET['id'] ?? '';

$contact = $db->prepare("
    SELECT c.*, p.full_name, p.linkedin_url, p.current_role, p.current_company, p.city, p.notes AS cluster_notes
    FROM contacts c
    LEFT JOIN person_clusters p ON p.cluster_id = c.cluster_id
    WHERE c.contact_id = ? AND c.owner_member_id = ?
");
$contact->execute([$contact_id, $member['member_id']]);
$row = $contact->fetch();
if (!$row) { header('Location: index.php'); exit; }

$cluster_id = $row['cluster_id'];

$email_row = $db->prepare("SELECT email FROM cluster_emails WHERE cluster_id = ? AND is_primary = 1 LIMIT 1");
$email_row->execute([$cluster_id]);
$primary_email = $email_row->fetchColumn();

$phone_row = $db->prepare("SELECT phone FROM cluster_phones WHERE cluster_id = ? AND is_primary = 1 LIMIT 1");
$phone_row->execute([$cluster_id]);
$primary_phone = $phone_row->fetchColumn();

$edu = $db->prepare("SELECT * FROM education WHERE cluster_id = ? ORDER BY year_start DESC LIMIT 1");
$edu->execute([$cluster_id]);
$edu = $edu->fetch();

$exp = $db->prepare("SELECT * FROM experience WHERE cluster_id = ? ORDER BY year_start DESC LIMIT 1");
$exp->execute([$cluster_id]);
$exp = $exp->fetch();

$tags = $db->prepare("SELECT tag FROM contact_tags WHERE cluster_id = ?");
$tags->execute([$cluster_id]);
$existing_tags = $tags->fetchAll(PDO::FETCH_COLUMN);

$rel_types = cc_rel_types();
$tags_list  = cc_domain_tags();
$insts      = cc_institutions();
$errors     = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name       = trim($_POST['full_name'] ?? '');
    $email           = trim($_POST['email'] ?? '');
    $phone           = trim($_POST['phone'] ?? '');
    $linkedin_url    = trim($_POST['linkedin_url'] ?? '');
    $current_role    = trim($_POST['current_role'] ?? '');
    $current_company = trim($_POST['current_company'] ?? '');
    $city            = trim($_POST['city'] ?? '');
    $rel_origin      = trim($_POST['relationship_origin'] ?? '');
    $rel_type        = $_POST['relationship_type'] ?? '';
    $rel_strength    = $_POST['relationship_strength'] ?? '';
    $private_notes   = trim($_POST['private_notes'] ?? '');
    $notes           = trim($_POST['notes'] ?? '');
    $edu_inst        = trim($_POST['edu_institution'] ?? '');
    $edu_degree      = trim($_POST['edu_degree'] ?? '');
    $edu_field       = trim($_POST['edu_field'] ?? '');
    $edu_start       = trim($_POST['edu_year_start'] ?? '');
    $edu_end         = trim($_POST['edu_year_end'] ?? '');
    $exp_company     = trim($_POST['exp_company'] ?? '');
    $exp_role        = trim($_POST['exp_role'] ?? '');
    $exp_founder     = isset($_POST['exp_is_founder']) ? 1 : 0;
    $exp_investor    = isset($_POST['exp_is_investor']) ? 1 : 0;
    $exp_start       = trim($_POST['exp_year_start'] ?? '');
    $exp_end         = trim($_POST['exp_year_end'] ?? '');
    $selected_tags   = $_POST['tags'] ?? [];
    $custom_tag      = trim($_POST['custom_tag'] ?? '');

    if (!$full_name) $errors[] = 'Name is required.';

    if (empty($errors)) {
        // Update cluster
        $db->prepare("UPDATE person_clusters SET full_name=?,linkedin_url=?,current_role=?,
                      current_company=?,city=?,notes=?,last_updated_by=? WHERE cluster_id=?")
           ->execute([$full_name,$linkedin_url?:null,$current_role?:null,$current_company?:null,
                      $city?:null,$notes?:null,$member['member_id'],$cluster_id]);

        // Update primary email
        if ($email) {
            $db->prepare("DELETE FROM cluster_emails WHERE cluster_id=? AND is_primary=1")->execute([$cluster_id]);
            $db->prepare("INSERT IGNORE INTO cluster_emails (email_id,cluster_id,email,is_primary,added_by) VALUES (?,?,?,1,?)")
               ->execute([uuid(),$cluster_id,$email,$member['member_id']]);
        }
        // Update primary phone
        if ($phone) {
            $db->prepare("DELETE FROM cluster_phones WHERE cluster_id=? AND is_primary=1")->execute([$cluster_id]);
            $db->prepare("INSERT IGNORE INTO cluster_phones (phone_id,cluster_id,phone,label,is_primary,added_by) VALUES (?,?,?,'mobile',1,?)")
               ->execute([uuid(),$cluster_id,$phone,$member['member_id']]);
        }

        // Update education (replace first record)
        if ($edu_inst) {
            $notable_insts = cc_institutions();
            $is_notable = in_array($edu_inst, $notable_insts) ? 1 : 0;
            if ($edu) {
                $db->prepare("UPDATE education SET institution=?,is_notable=?,degree=?,field=?,year_start=?,year_end=? WHERE edu_id=?")
                   ->execute([$edu_inst,$is_notable,$edu_degree?:null,$edu_field?:null,
                              $edu_start?:null,$edu_end?:null,$edu['edu_id']]);
            } else {
                $db->prepare("INSERT INTO education (edu_id,cluster_id,institution,is_notable,degree,field,year_start,year_end,added_by) VALUES (?,?,?,?,?,?,?,?,?)")
                   ->execute([uuid(),$cluster_id,$edu_inst,$is_notable,$edu_degree?:null,
                              $edu_field?:null,$edu_start?:null,$edu_end?:null,$member['member_id']]);
            }
        }

        // Update experience (replace first record)
        if ($exp_company) {
            if ($exp) {
                $db->prepare("UPDATE experience SET company=?,role=?,is_founder=?,is_investor=?,year_start=?,year_end=? WHERE exp_id=?")
                   ->execute([$exp_company,$exp_role?:null,$exp_founder,$exp_investor,
                              $exp_start?:null,$exp_end?:null,$exp['exp_id']]);
            } else {
                $db->prepare("INSERT INTO experience (exp_id,cluster_id,company,role,is_founder,is_investor,year_start,year_end,added_by) VALUES (?,?,?,?,?,?,?,?,?)")
                   ->execute([uuid(),$cluster_id,$exp_company,$exp_role?:null,$exp_founder,
                              $exp_investor,$exp_start?:null,$exp_end?:null,$member['member_id']]);
            }
        }

        // Replace tags
        $db->prepare("DELETE FROM contact_tags WHERE cluster_id=?")->execute([$cluster_id]);
        $all_tags = array_filter(array_merge($selected_tags, $custom_tag ? [$custom_tag] : []));
        foreach ($all_tags as $tag) {
            $tag = strtolower(trim($tag));
            if ($tag) {
                $db->prepare("INSERT IGNORE INTO contact_tags (tag_id,cluster_id,tag,added_by) VALUES (?,?,?,?)")
                   ->execute([uuid(),$cluster_id,$tag,$member['member_id']]);
            }
        }

        // Update contact row
        $db->prepare("UPDATE contacts SET relationship_origin=?,relationship_type=?,
                      relationship_strength=?,notes=?,private_notes=? WHERE contact_id=?")
           ->execute([$rel_origin?:null,$rel_type?:null,$rel_strength?:null,
                      $notes?:null,$private_notes?:null,$contact_id]);

        header("Location: contact.php?id={$contact_id}");
        exit;
    }

    // Re-populate for re-render after error
    $row = array_merge($row, compact('full_name','current_role','current_company','city',
        'linkedin_url','rel_origin','rel_type','rel_strength','private_notes','notes'));
    $primary_email = $email;
    $primary_phone = $phone;
}

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES|ENT_HTML5, 'UTF-8'); }
function pre($field, $row, $default='') {
    return h($row[$field] ?? $_POST[$field] ?? $default);
}

$nav_active = 'contacts_personal';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Edit — <?= h($row['full_name']) ?></title>
  <style>
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
    body{font-family:'Segoe UI',system-ui,sans-serif;background:#f7f8fc;color:#1a1a2e}
    .page{padding:36px 40px;max-width:760px}
    .back{font-size:.82rem;color:#6b7280;text-decoration:none;display:inline-flex;align-items:center;gap:4px;margin-bottom:20px}
    .back:hover{color:#1a1a2e}
    h1{font-family:Georgia,serif;font-size:1.4rem;font-weight:700;margin-bottom:28px}
    .section{background:#fff;border:1px solid #e2e5ef;border-radius:10px;padding:24px;margin-bottom:20px}
    .section-title{font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#9ca3af;margin-bottom:18px}
    .field-row{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px}
    .field-row.full{grid-template-columns:1fr}
    .field{margin-bottom:0}
    label{display:block;font-size:.75rem;font-weight:700;color:#374151;margin-bottom:5px;text-transform:uppercase;letter-spacing:.06em}
    input[type=text],input[type=email],input[type=url],input[type=number],select,textarea{
      width:100%;padding:10px 12px;border:1.5px solid #d1d5db;border-radius:7px;
      font-size:.9rem;color:#1a1a2e;outline:none;font-family:inherit;background:#fff;transition:border-color .15s}
    input:focus,select:focus,textarea:focus{border-color:#C9972A}
    textarea{resize:vertical;min-height:80px}
    .checkbox-row{display:flex;align-items:center;gap:8px;margin-bottom:8px;font-size:.875rem}
    .tags-grid{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:12px}
    .tag-check{display:none}
    .tag-label{padding:4px 12px;border-radius:20px;border:1.5px solid #d1d5db;font-size:.78rem;cursor:pointer;transition:all .15s;user-select:none}
    .tag-check:checked + .tag-label{border-color:#C9972A;background:#fff8ec;color:#92400e;font-weight:600}
    .error-box{background:#fef2f2;border:1px solid #fecaca;color:#dc2626;border-radius:7px;padding:12px 16px;margin-bottom:20px;font-size:.85rem}
    .actions{display:flex;gap:12px;margin-top:8px}
    .btn{display:inline-flex;align-items:center;gap:6px;padding:10px 22px;border-radius:7px;font-size:.875rem;font-weight:600;cursor:pointer;text-decoration:none;border:none;font-family:inherit;transition:background .15s}
    .btn-primary{background:#1a1a2e;color:#fff}.btn-primary:hover{background:#2d2d4e}
    .btn-ghost{background:#f3f4f6;color:#374151}.btn-ghost:hover{background:#e5e7eb}
    .hint{font-size:.75rem;color:#9ca3af;margin-top:4px}
  </style>
</head>
<body>
<div class="cv-layout">
  <?php require __DIR__ . '/../nav.php'; ?>
  <div class="page">
    <a href="contact.php?id=<?= h($contact_id) ?>" class="back">← <?= h($row['full_name']) ?></a>
    <h1>Edit Contact</h1>

    <?php if ($errors): ?>
      <div class="error-box"><?= implode('<br>', array_map('h', $errors)) ?></div>
    <?php endif ?>

    <form method="POST">
      <div class="section">
        <div class="section-title">Basic Info</div>
        <div class="field-row full">
          <div class="field">
            <label>Full Name *</label>
            <input type="text" name="full_name" value="<?= h($row['full_name']) ?>" required autofocus/>
          </div>
        </div>
        <div class="field-row">
          <div class="field"><label>Current Role</label><input type="text" name="current_role" value="<?= h($row['current_role']) ?>"/></div>
          <div class="field"><label>Current Company</label><input type="text" name="current_company" value="<?= h($row['current_company']) ?>"/></div>
        </div>
        <div class="field-row">
          <div class="field"><label>City</label><input type="text" name="city" value="<?= h($row['city']) ?>"/></div>
          <div class="field"><label>LinkedIn URL</label><input type="url" name="linkedin_url" value="<?= h($row['linkedin_url']) ?>"/></div>
        </div>
      </div>

      <div class="section">
        <div class="section-title">Contact Details</div>
        <div class="field-row">
          <div class="field"><label>Email</label><input type="email" name="email" value="<?= h($primary_email) ?>"/></div>
          <div class="field"><label>Phone / WhatsApp</label><input type="text" name="phone" value="<?= h($primary_phone) ?>"/></div>
        </div>
      </div>

      <div class="section">
        <div class="section-title">Your Relationship</div>
        <div class="field-row full">
          <div class="field"><label>How do you know them?</label><input type="text" name="relationship_origin" value="<?= h($row['relationship_origin']) ?>"/></div>
        </div>
        <div class="field-row">
          <div class="field">
            <label>Relationship Type</label>
            <select name="relationship_type">
              <option value="">— select —</option>
              <?php foreach ($rel_types as $rt): ?>
                <option value="<?= h($rt['value_id']) ?>" <?= $row['relationship_type'] === $rt['value_id'] ? 'selected' : '' ?>><?= h($rt['value']) ?></option>
              <?php endforeach ?>
            </select>
          </div>
          <div class="field">
            <label>Relationship Strength</label>
            <select name="relationship_strength">
              <option value="">— select —</option>
              <?php foreach (['close','acquaintance','distant'] as $s): ?>
                <option value="<?= $s ?>" <?= $row['relationship_strength'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
              <?php endforeach ?>
            </select>
          </div>
        </div>
        <div class="field-row">
          <div class="field"><label>Notes (shared)</label><textarea name="notes"><?= h($row['notes'] ?? $row['cluster_notes'] ?? '') ?></textarea></div>
          <div class="field"><label>Private Notes</label><textarea name="private_notes"><?= h($row['private_notes']) ?></textarea></div>
        </div>
      </div>

      <div class="section">
        <div class="section-title">Education</div>
        <div class="field-row">
          <div class="field">
            <label>Institution</label>
            <input type="text" name="edu_institution" value="<?= h($edu['institution'] ?? '') ?>" list="inst-list"/>
            <datalist id="inst-list"><?php foreach ($insts as $i): ?><option value="<?= h($i) ?>"><?php endforeach ?></datalist>
          </div>
          <div class="field"><label>Degree</label><input type="text" name="edu_degree" value="<?= h($edu['degree'] ?? '') ?>"/></div>
        </div>
        <div class="field-row">
          <div class="field"><label>Field</label><input type="text" name="edu_field" value="<?= h($edu['field'] ?? '') ?>"/></div>
          <div class="field" style="display:grid;grid-template-columns:1fr 1fr;gap:10px;align-items:end">
            <div><label>From</label><input type="number" name="edu_year_start" value="<?= h($edu['year_start'] ?? '') ?>" min="1950" max="2030"/></div>
            <div><label>To</label><input type="number" name="edu_year_end" value="<?= h($edu['year_end'] ?? '') ?>" min="1950" max="2030"/></div>
          </div>
        </div>
      </div>

      <div class="section">
        <div class="section-title">Key Experience</div>
        <div class="field-row">
          <div class="field"><label>Company</label><input type="text" name="exp_company" value="<?= h($exp['company'] ?? '') ?>"/></div>
          <div class="field"><label>Role</label><input type="text" name="exp_role" value="<?= h($exp['role'] ?? '') ?>"/></div>
        </div>
        <div class="field-row">
          <div class="field" style="display:grid;grid-template-columns:1fr 1fr;gap:10px;align-items:end">
            <div><label>From</label><input type="number" name="exp_year_start" value="<?= h($exp['year_start'] ?? '') ?>" min="1950" max="2030"/></div>
            <div><label>To</label><input type="number" name="exp_year_end" value="<?= h($exp['year_end'] ?? '') ?>" min="1950" max="2030"/></div>
          </div>
          <div class="field" style="display:flex;flex-direction:column;justify-content:flex-end;gap:8px">
            <label class="checkbox-row" style="text-transform:none;letter-spacing:0;font-size:.875rem">
              <input type="checkbox" name="exp_is_founder" <?= ($exp['is_founder'] ?? 0) ? 'checked' : '' ?>/> Founder / Co-founder
            </label>
            <label class="checkbox-row" style="text-transform:none;letter-spacing:0;font-size:.875rem">
              <input type="checkbox" name="exp_is_investor" <?= ($exp['is_investor'] ?? 0) ? 'checked' : '' ?>/> Investor role
            </label>
          </div>
        </div>
      </div>

      <div class="section">
        <div class="section-title">Domain Tags</div>
        <div class="tags-grid">
          <?php foreach ($tags_list as $tag): ?>
            <input class="tag-check" type="checkbox" id="etag_<?= h($tag) ?>" name="tags[]" value="<?= h($tag) ?>"
                   <?= in_array($tag, $existing_tags) ? 'checked' : '' ?>/>
            <label class="tag-label" for="etag_<?= h($tag) ?>"><?= h($tag) ?></label>
          <?php endforeach ?>
        </div>
        <label>Custom tag</label>
        <input type="text" name="custom_tag" placeholder="e.g. robotics" style="max-width:240px"/>
        <p class="hint">Existing custom tags are preserved. Add more here.</p>
      </div>

      <div class="actions">
        <button type="submit" class="btn btn-primary">Save changes</button>
        <a href="contact.php?id=<?= h($contact_id) ?>" class="btn btn-ghost">Cancel</a>
      </div>
    </form>
  </div>
</div>
</body>
</html>
