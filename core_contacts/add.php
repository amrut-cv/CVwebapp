<?php
require_once __DIR__ . '/../session_guard.php';
require_once __DIR__ . '/cc_db.php';

$member = cc_member($_SESSION['auth_email']);
if (!$member) { header('Location: /CVwebapp/index.php'); exit; }

$db       = getDB();
$rel_types = cc_rel_types();
$tags_list = cc_domain_tags();
$insts     = cc_institutions();
$errors    = [];
$success   = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name    = trim($_POST['full_name'] ?? '');
    $email        = trim($_POST['email'] ?? '');
    $phone        = trim($_POST['phone'] ?? '');
    $linkedin_url = trim($_POST['linkedin_url'] ?? '');
    $current_role = trim($_POST['current_role'] ?? '');
    $current_company = trim($_POST['current_company'] ?? '');
    $city         = trim($_POST['city'] ?? '');
    $rel_origin   = trim($_POST['relationship_origin'] ?? '');
    $rel_type     = $_POST['relationship_type'] ?? '';
    $rel_strength = $_POST['relationship_strength'] ?? '';
    $private_notes = trim($_POST['private_notes'] ?? '');
    $notes        = trim($_POST['notes'] ?? '');
    $edu_inst     = trim($_POST['edu_institution'] ?? '');
    $edu_degree   = trim($_POST['edu_degree'] ?? '');
    $edu_field    = trim($_POST['edu_field'] ?? '');
    $edu_start    = trim($_POST['edu_year_start'] ?? '');
    $edu_end      = trim($_POST['edu_year_end'] ?? '');
    $exp_company  = trim($_POST['exp_company'] ?? '');
    $exp_role     = trim($_POST['exp_role'] ?? '');
    $exp_founder  = isset($_POST['exp_is_founder']) ? 1 : 0;
    $exp_investor = isset($_POST['exp_is_investor']) ? 1 : 0;
    $exp_start    = trim($_POST['exp_year_start'] ?? '');
    $exp_end      = trim($_POST['exp_year_end'] ?? '');
    $selected_tags = $_POST['tags'] ?? [];
    $custom_tag   = trim($_POST['custom_tag'] ?? '');

    if (!$full_name) $errors[] = 'Name is required.';
    if (!$email && !$phone && !$linkedin_url) $errors[] = 'At least one of email, phone, or LinkedIn URL is required.';

    if (empty($errors)) {
        $cluster_id = uuid();
        $contact_id = uuid();

        $db->prepare("INSERT INTO person_clusters
            (cluster_id,full_name,linkedin_url,current_role,current_company,city,notes,last_updated_by)
            VALUES (?,?,?,?,?,?,?,?)")
          ->execute([$cluster_id,$full_name,$linkedin_url?:null,$current_role?:null,
                     $current_company?:null,$city?:null,$notes?:null,$member['member_id']]);

        if ($email) {
            $db->prepare("INSERT INTO cluster_emails (email_id,cluster_id,email,is_primary,added_by)
                VALUES (?,?,?,1,?)")
              ->execute([uuid(),$cluster_id,$email,$member['member_id']]);
        }
        if ($phone) {
            $db->prepare("INSERT INTO cluster_phones (phone_id,cluster_id,phone,label,is_primary,added_by)
                VALUES (?,?,?,'mobile',1,?)")
              ->execute([uuid(),$cluster_id,$phone,$member['member_id']]);
        }

        // Education
        if ($edu_inst) {
            $notable_insts = cc_institutions();
            $is_notable = in_array($edu_inst, $notable_insts) ? 1 : 0;
            $db->prepare("INSERT INTO education
                (edu_id,cluster_id,institution,is_notable,degree,field,year_start,year_end,added_by)
                VALUES (?,?,?,?,?,?,?,?,?)")
              ->execute([uuid(),$cluster_id,$edu_inst,$is_notable,
                         $edu_degree?:null,$edu_field?:null,
                         $edu_start?:null,$edu_end?:null,$member['member_id']]);
        }

        // Experience
        if ($exp_company) {
            $db->prepare("INSERT INTO experience
                (exp_id,cluster_id,company,role,is_founder,is_investor,year_start,year_end,added_by)
                VALUES (?,?,?,?,?,?,?,?,?)")
              ->execute([uuid(),$cluster_id,$exp_company,$exp_role?:null,
                         $exp_founder,$exp_investor,
                         $exp_start?:null,$exp_end?:null,$member['member_id']]);
        }

        // Tags
        $all_tags = array_filter(array_merge($selected_tags, $custom_tag ? [$custom_tag] : []));
        foreach ($all_tags as $tag) {
            $tag = strtolower(trim($tag));
            if ($tag) {
                $db->prepare("INSERT IGNORE INTO contact_tags (tag_id,cluster_id,tag,added_by) VALUES (?,?,?,?)")
                  ->execute([uuid(),$cluster_id,$tag,$member['member_id']]);
            }
        }

        $db->prepare("INSERT INTO contacts
            (contact_id,owner_member_id,cluster_id,space,origin_source,
             relationship_origin,relationship_type,relationship_strength,notes,private_notes)
            VALUES (?,?,'personal','manual',?,?,?,?,?)")
          ->execute([$contact_id,$member['member_id'],
                     $cluster_id,
                     $rel_origin?:null,
                     $rel_type?:null,
                     $rel_strength?:null,
                     $notes?:null,
                     $private_notes?:null]);

        // Fix: update cluster_id on contact row
        $db->prepare("UPDATE contacts SET cluster_id=? WHERE contact_id=?")
          ->execute([$cluster_id,$contact_id]);

        header("Location: contact.php?id={$contact_id}&added=1");
        exit;
    }
}

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES|ENT_HTML5, 'UTF-8'); }
function val($k) { return h($_POST[$k] ?? ''); }

$nav_active = 'contacts_personal';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>CoreContacts — Add Contact</title>
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
    <a href="index.php" class="back">← My Contacts</a>
    <h1>Add Contact</h1>

    <?php if ($errors): ?>
      <div class="error-box"><?= implode('<br>', array_map('h', $errors)) ?></div>
    <?php endif ?>

    <form method="POST">
      <!-- Basic Info -->
      <div class="section">
        <div class="section-title">Basic Info</div>
        <div class="field-row full">
          <div class="field">
            <label for="full_name">Full Name *</label>
            <input type="text" id="full_name" name="full_name" value="<?= val('full_name') ?>" required autofocus/>
          </div>
        </div>
        <div class="field-row">
          <div class="field">
            <label for="current_role">Current Role</label>
            <input type="text" id="current_role" name="current_role" value="<?= val('current_role') ?>" placeholder="e.g. CTO"/>
          </div>
          <div class="field">
            <label for="current_company">Current Company</label>
            <input type="text" id="current_company" name="current_company" value="<?= val('current_company') ?>" placeholder="e.g. Acme Corp"/>
          </div>
        </div>
        <div class="field-row">
          <div class="field">
            <label for="city">City</label>
            <input type="text" id="city" name="city" value="<?= val('city') ?>" placeholder="e.g. Bangalore"/>
          </div>
          <div class="field">
            <label for="linkedin_url">LinkedIn URL</label>
            <input type="url" id="linkedin_url" name="linkedin_url" value="<?= val('linkedin_url') ?>" placeholder="https://linkedin.com/in/…"/>
          </div>
        </div>
      </div>

      <!-- Contact Details -->
      <div class="section">
        <div class="section-title">Contact Details <span style="font-weight:400;text-transform:none;font-size:.8rem;letter-spacing:0">(at least one required)</span></div>
        <div class="field-row">
          <div class="field">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="<?= val('email') ?>"/>
          </div>
          <div class="field">
            <label for="phone">Phone / WhatsApp</label>
            <input type="text" id="phone" name="phone" value="<?= val('phone') ?>" placeholder="+91 …"/>
          </div>
        </div>
      </div>

      <!-- Relationship -->
      <div class="section">
        <div class="section-title">Your Relationship</div>
        <div class="field-row full">
          <div class="field">
            <label for="relationship_origin">How do you know them?</label>
            <input type="text" id="relationship_origin" name="relationship_origin" value="<?= val('relationship_origin') ?>" placeholder="e.g. Overlapped at IITM 2001–2005"/>
          </div>
        </div>
        <div class="field-row">
          <div class="field">
            <label for="relationship_type">Relationship Type</label>
            <select id="relationship_type" name="relationship_type">
              <option value="">— select —</option>
              <?php foreach ($rel_types as $rt): ?>
                <option value="<?= h($rt['value_id']) ?>" <?= val('relationship_type') === $rt['value_id'] ? 'selected' : '' ?>><?= h($rt['value']) ?></option>
              <?php endforeach ?>
            </select>
          </div>
          <div class="field">
            <label for="relationship_strength">Relationship Strength</label>
            <select id="relationship_strength" name="relationship_strength">
              <option value="">— select —</option>
              <option value="close" <?= val('relationship_strength') === 'close' ? 'selected' : '' ?>>Close</option>
              <option value="acquaintance" <?= val('relationship_strength') === 'acquaintance' ? 'selected' : '' ?>>Acquaintance</option>
              <option value="distant" <?= val('relationship_strength') === 'distant' ? 'selected' : '' ?>>Distant</option>
            </select>
          </div>
        </div>
        <div class="field-row">
          <div class="field">
            <label for="notes">Notes <span style="font-weight:400;text-transform:none">(shared with team when contact is shared)</span></label>
            <textarea id="notes" name="notes"><?= val('notes') ?></textarea>
          </div>
          <div class="field">
            <label for="private_notes">Private Notes <span style="font-weight:400;text-transform:none">(never shared)</span></label>
            <textarea id="private_notes" name="private_notes"><?= val('private_notes') ?></textarea>
          </div>
        </div>
      </div>

      <!-- Education -->
      <div class="section">
        <div class="section-title">Education</div>
        <div class="field-row">
          <div class="field">
            <label for="edu_institution">Institution</label>
            <input type="text" id="edu_institution" name="edu_institution" value="<?= val('edu_institution') ?>"
                   list="inst-list" placeholder="e.g. IIT Madras"/>
            <datalist id="inst-list">
              <?php foreach ($insts as $i): ?><option value="<?= h($i) ?>"><?php endforeach ?>
            </datalist>
          </div>
          <div class="field">
            <label for="edu_degree">Degree</label>
            <input type="text" id="edu_degree" name="edu_degree" value="<?= val('edu_degree') ?>" placeholder="e.g. B.Tech"/>
          </div>
        </div>
        <div class="field-row">
          <div class="field">
            <label for="edu_field">Field</label>
            <input type="text" id="edu_field" name="edu_field" value="<?= val('edu_field') ?>" placeholder="e.g. Computer Science"/>
          </div>
          <div class="field" style="display:grid;grid-template-columns:1fr 1fr;gap:10px;align-items:end">
            <div>
              <label for="edu_year_start">From</label>
              <input type="number" id="edu_year_start" name="edu_year_start" value="<?= val('edu_year_start') ?>" placeholder="2001" min="1950" max="2030"/>
            </div>
            <div>
              <label for="edu_year_end">To</label>
              <input type="number" id="edu_year_end" name="edu_year_end" value="<?= val('edu_year_end') ?>" placeholder="2005" min="1950" max="2030"/>
            </div>
          </div>
        </div>
      </div>

      <!-- Experience -->
      <div class="section">
        <div class="section-title">Key Experience</div>
        <div class="field-row">
          <div class="field">
            <label for="exp_company">Company</label>
            <input type="text" id="exp_company" name="exp_company" value="<?= val('exp_company') ?>"/>
          </div>
          <div class="field">
            <label for="exp_role">Role</label>
            <input type="text" id="exp_role" name="exp_role" value="<?= val('exp_role') ?>"/>
          </div>
        </div>
        <div class="field-row">
          <div class="field" style="display:grid;grid-template-columns:1fr 1fr;gap:10px;align-items:end">
            <div>
              <label for="exp_year_start">From</label>
              <input type="number" id="exp_year_start" name="exp_year_start" value="<?= val('exp_year_start') ?>" placeholder="2015" min="1950" max="2030"/>
            </div>
            <div>
              <label for="exp_year_end">To <span style="font-weight:400">(blank = current)</span></label>
              <input type="number" id="exp_year_end" name="exp_year_end" value="<?= val('exp_year_end') ?>" placeholder="present" min="1950" max="2030"/>
            </div>
          </div>
          <div class="field" style="display:flex;flex-direction:column;justify-content:flex-end;gap:8px">
            <label class="checkbox-row" style="text-transform:none;letter-spacing:0;font-size:.875rem">
              <input type="checkbox" name="exp_is_founder" <?= isset($_POST['exp_is_founder']) ? 'checked' : '' ?>/>
              Founder / Co-founder
            </label>
            <label class="checkbox-row" style="text-transform:none;letter-spacing:0;font-size:.875rem">
              <input type="checkbox" name="exp_is_investor" <?= isset($_POST['exp_is_investor']) ? 'checked' : '' ?>/>
              Investor role
            </label>
          </div>
        </div>
      </div>

      <!-- Tags -->
      <div class="section">
        <div class="section-title">Domain Tags</div>
        <div class="tags-grid">
          <?php foreach ($tags_list as $tag): ?>
            <input class="tag-check" type="checkbox" id="tag_<?= h($tag) ?>" name="tags[]" value="<?= h($tag) ?>"
                   <?= in_array($tag, $_POST['tags'] ?? []) ? 'checked' : '' ?>/>
            <label class="tag-label" for="tag_<?= h($tag) ?>"><?= h($tag) ?></label>
          <?php endforeach ?>
        </div>
        <label for="custom_tag">Custom tag</label>
        <input type="text" id="custom_tag" name="custom_tag" value="<?= val('custom_tag') ?>" placeholder="e.g. robotics" style="max-width:240px"/>
        <p class="hint">Lowercase, hyphenated. e.g. embedded-engineer</p>
      </div>

      <div class="actions">
        <button type="submit" class="btn btn-primary">Save contact</button>
        <a href="index.php" class="btn btn-ghost">Cancel</a>
      </div>
    </form>
  </div>
</div>
</body>
</html>
