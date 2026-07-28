<?php
require __DIR__ . '/../session_guard.php';
require_module_access('inquiries');
require_once __DIR__ . '/../db.php';
require __DIR__ . '/helpers.php';
$stagesByPath = require __DIR__ . '/stages.php';
$pathTabs = iq_path_tabs();
$pdo = getDB();

$activePath = $_GET['path'] ?? 'hire';
if (!isset($pathTabs[$activePath])) $activePath = 'hire';
$showArchived = isset($_GET['archived']);
$stages = $stagesByPath[$activePath];

$users = $pdo->query("SELECT email, name FROM users ORDER BY name, email")->fetchAll();

$stmt = $pdo->prepare(
    "SELECT s.*,
            t.stage AS tracked_stage, t.owner AS tracking_owner, t.notes AS tracking_notes,
            t.converted_deal_id, t.archived AS tracking_archived
     FROM contact_form_submissions s
     LEFT JOIN inquiry_tracking t ON t.submission_id = s.id
     WHERE s.path = ?
     ORDER BY s.created_at DESC"
);
$stmt->execute([$activePath]);
$rows = $stmt->fetchAll();
foreach ($rows as &$r) {
    $r['eff_stage']    = $r['tracked_stage'] ?: 'New';
    $r['eff_archived'] = (bool)$r['tracking_archived'];
}
unset($r);

$activeRows   = array_values(array_filter($rows, fn($r) => !$r['eff_archived']));
$archivedRows = array_values(array_filter($rows, fn($r) => $r['eff_archived']));

$byStage = [];
foreach ($stages as $s) $byStage[$s['label']] = [];
foreach ($activeRows as $r) {
    if (isset($byStage[$r['eff_stage']])) $byStage[$r['eff_stage']][] = $r;
}

$tabCounts = [];
foreach ($pathTabs as $p => $label) {
    $c = $pdo->prepare(
        "SELECT COUNT(*) FROM contact_form_submissions s
         LEFT JOIN inquiry_tracking t ON t.submission_id = s.id
         WHERE s.path = ? AND COALESCE(t.archived,0) = 0"
    );
    $c->execute([$p]);
    $tabCounts[$p] = (int)$c->fetchColumn();
}

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_HTML5, 'UTF-8'); }

function iq_card_line($row, $path) {
    if ($path === 'hire') {
        $bits = array_filter([$row['sector'] ?? '', $row['stage'] ?? '']);
        return implode(' · ', $bits) ?: ($row['role'] ?? '');
    }
    if ($path === 'join') {
        return $row['job_role'] ?: ($row['expertise'] ?: ucfirst($row['sub_reason']));
    }
    return ucfirst($row['sub_reason'] ?? '');
}

$nav_active = 'inquiries';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Inquiries — CoreVoice</title>
  <style>
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
    body{font-family:'Segoe UI',system-ui,sans-serif;background:#f7f8fc;color:#1a1a2e}
    .page{padding:36px 40px;max-width:1500px}
    .page-header{display:flex;justify-content:space-between;align-items:center;gap:14px;padding-bottom:20px;margin-bottom:20px;border-bottom:1px solid #e2e5ef;flex-wrap:wrap}
    .title-group{display:flex;align-items:center;gap:14px}
    .icon-badge{width:42px;height:42px;border-radius:11px;background:#1a1a2e;color:#C9972A;display:flex;align-items:center;justify-content:center;flex-shrink:0}
    .page-header h1{font-family:Georgia,serif;font-size:1.65rem;font-weight:700;line-height:1.15}
    .page-header h1 span{color:#C9972A}
    .btn{display:inline-flex;align-items:center;gap:6px;padding:9px 18px;border-radius:7px;font-size:.85rem;font-weight:600;cursor:pointer;text-decoration:none;border:none;font-family:inherit}
    .btn-primary{background:#1a1a2e;color:#fff}.btn-primary:hover{background:#2d2d4e}
    .btn-secondary{background:#fff;border:1.5px solid #d1d5db;color:#374151}
    .btn-danger{background:#fee2e2;color:#991b1b;border:none}

    .tab-bar{display:flex;gap:4px;background:#e9ebf0;border-radius:8px;padding:4px;margin-bottom:20px;width:fit-content}
    .tab-bar a{padding:7px 18px;border-radius:6px;font-size:.85rem;font-weight:600;color:#6b7280;text-decoration:none}
    .tab-bar a.active{background:#fff;color:#1a1a2e;box-shadow:0 1px 4px rgba(0,0,0,.1)}
    .tab-bar-2{margin-bottom:16px}

    .board-scroll{overflow-x:auto;padding-bottom:16px}
    .board{display:flex;gap:12px;min-width:max-content}
    .col{width:250px;flex-shrink:0}
    .col-head{display:flex;justify-content:space-between;align-items:center;padding:8px 12px;border-radius:8px;font-size:.76rem;font-weight:700;margin-bottom:10px;text-transform:uppercase;letter-spacing:.02em}
    .tone-neutral{background:#eef0f5;color:#4b5563}
    .tone-success{background:#dcfce7;color:#166534}
    .tone-danger{background:#fee2e2;color:#991b1b}
    .tone-warning{background:#fef3c7;color:#92400e}
    .col-cards{min-height:50px;border-radius:8px;transition:background .1s}
    .col-cards.drag-over{background:#fdf6e8}
    .iq-card{position:relative;background:#fff;border:1px solid #e2e5ef;border-radius:8px;padding:10px 12px;margin-bottom:8px;cursor:pointer;box-shadow:0 1px 4px rgba(0,0,0,.04)}
    .iq-card:hover{border-color:#C9972A}
    .iq-card.dragging{opacity:.35}
    .iq-card .iname{font-weight:700;font-size:.85rem;margin-bottom:4px}
    .iq-card .iline{font-size:.78rem;color:#6b7280;margin-bottom:6px}
    .itags{display:flex;gap:4px;flex-wrap:wrap}
    .itag{font-size:.65rem;padding:2px 7px;border-radius:10px;background:#f3f4f6;color:#374151}
    .junk-quick{position:absolute;top:8px;right:8px;background:none;border:none;color:#d1d5db;cursor:pointer;padding:2px;line-height:0}
    .junk-quick:hover{color:#dc2626}

    .modal-overlay{display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.4);z-index:200;align-items:center;justify-content:center;padding:20px}
    .modal-overlay.open{display:flex}
    .modal-box{background:#fff;border-radius:12px;padding:28px 32px;width:640px;max-width:100%;max-height:88vh;overflow-y:auto}
    .modal-title{font-size:1.1rem;font-weight:700;margin-bottom:4px}
    .modal-sub{font-size:.78rem;color:#9ca3af;margin-bottom:18px}
    .detail-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px 20px;margin-bottom:16px;font-size:.85rem}
    .detail-grid .full{grid-column:1/-1}
    .detail-grid .k{color:#9ca3af;font-size:.7rem;text-transform:uppercase;letter-spacing:.03em;margin-bottom:2px}
    .detail-grid .v{color:#1a1a2e;white-space:pre-wrap;word-break:break-word}
    .detail-grid .v a{color:#92400e;text-decoration:none}
    .detail-grid .v a:hover{text-decoration:underline}
    .frow{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px}
    .field label{font-size:.78rem;color:#6b7280;display:block;margin-bottom:5px;font-weight:600}
    .field input,.field select,.field textarea{width:100%;padding:8px 10px;border:1.5px solid #d1d5db;border-radius:7px;font-size:.85rem;font-family:inherit;outline:none}
    .field input:focus,.field select:focus,.field textarea:focus{border-color:#C9972A}
    .field textarea{min-height:64px;resize:vertical}
    .modal-actions{display:flex;justify-content:space-between;align-items:center;margin-top:8px;flex-wrap:wrap;gap:10px}
  </style>
</head>
<body>
<div class="cv-layout">
  <?php require __DIR__ . '/../nav.php'; ?>
  <div class="page">
    <div class="page-header">
      <div class="title-group">
        <div class="icon-badge">
          <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
        </div>
        <h1>Inquiries</h1>
      </div>
    </div>

    <div class="tab-bar">
      <?php foreach ($pathTabs as $p => $label): ?>
        <a href="?path=<?= h($p) ?>" class="<?= ($activePath === $p && !$showArchived) ? 'active' : '' ?>"><?= h($label) ?> (<?= $tabCounts[$p] ?>)</a>
      <?php endforeach ?>
    </div>
    <div class="tab-bar tab-bar-2">
      <a href="?path=<?= h($activePath) ?>" class="<?= !$showArchived ? 'active' : '' ?>">Board</a>
      <a href="?path=<?= h($activePath) ?>&archived=1" class="<?= $showArchived ? 'active' : '' ?>">Archived (<?= count($archivedRows) ?>)</a>
    </div>

    <?php if ($showArchived): ?>
      <div class="archive-list">
        <?php foreach ($archivedRows as $r): ?>
          <div class="iq-card" style="max-width:680px" onclick="openModal(<?= (int)$r['id'] ?>)">
            <div class="iname"><?= h($r['name']) ?></div>
            <div class="iline"><?= h(iq_card_line($r, $activePath)) ?></div>
            <div class="itags">
              <span class="itag"><?= h($r['eff_stage']) ?></span>
              <span class="itag"><?= h(date('j M Y', strtotime($r['created_at']))) ?></span>
            </div>
          </div>
        <?php endforeach ?>
        <?php if (!$archivedRows): ?><div style="color:#9ca3af;padding:24px 0">No archived inquiries.</div><?php endif ?>
      </div>
    <?php else: ?>
      <div class="board-scroll">
        <div class="board">
          <?php foreach ($stages as $s): $label = $s['label']; ?>
            <div class="col">
              <div class="col-head tone-<?= h($s['tone']) ?>">
                <span><?= h($label) ?></span>
                <span><?= count($byStage[$label]) ?></span>
              </div>
              <div class="col-cards" data-stage="<?= h($label) ?>"
                   ondragover="event.preventDefault();this.classList.add('drag-over')"
                   ondragleave="this.classList.remove('drag-over')"
                   ondrop="onDropCard(event, this)">
                <?php foreach ($byStage[$label] as $r): ?>
                  <div class="iq-card" draggable="true" data-id="<?= (int)$r['id'] ?>"
                       ondragstart="onDragStart(event, this)" ondragend="this.classList.remove('dragging')"
                       onclick="openModal(<?= (int)$r['id'] ?>)">
                    <button class="junk-quick" title="Mark as junk" onclick="event.stopPropagation();quickJunk(<?= (int)$r['id'] ?>, this)">&times;</button>
                    <div class="iname"><?= h($r['name']) ?></div>
                    <div class="iline"><?= h(iq_card_line($r, $activePath)) ?></div>
                    <div class="itags">
                      <?php if ($r['tracking_owner']): ?><span class="itag"><?= h($r['tracking_owner']) ?></span><?php endif ?>
                      <span class="itag"><?= h(date('j M', strtotime($r['created_at']))) ?></span>
                    </div>
                  </div>
                <?php endforeach ?>
              </div>
            </div>
          <?php endforeach ?>
        </div>
      </div>
    <?php endif ?>
  </div>
</div>

<div class="modal-overlay" id="modalOverlay">
  <div class="modal-box" id="modalBox"></div>
</div>

<script>
const SUBMISSIONS = <?= json_encode($rows, JSON_UNESCAPED_UNICODE) ?>;
const USERS = <?= json_encode($users, JSON_UNESCAPED_UNICODE) ?>;
const ACTIVE_PATH = <?= json_encode($activePath) ?>;
const API = '/CVwebapp/api/inquiries.php';

const FIELD_LABELS = {
  hire: [['role','Role'],['linkedin','LinkedIn'],['heard','Heard about us'],['heard_detail','Heard detail'],
         ['website','Website'],['sector','Sector'],['stage','Funding stage'],['needs','Needs'],['problem','Problem'],['notes','Their notes']],
  join: [['linkedin','LinkedIn'],['mobile','Mobile'],['job_role','Role applied for'],['expertise','Expertise'],
         ['availability','Availability'],['resume_link','Resume'],['portfolio_link','Portfolio'],['video_link','Video intro'],['extra','Extra']],
  hi:   [['message','Message']]
};

function esc(s) { const d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; }
function escAttr(s) { return esc(s).replace(/"/g, '&quot;').replace(/'/g, '&#39;'); }
function toHref(url) { return /^https?:\/\//i.test(url) ? url : 'https://' + url; }

const LINK_FIELDS = ['linkedin', 'website', 'resume_link', 'portfolio_link', 'video_link'];

function openModal(id) {
  const r = SUBMISSIONS.find(x => x.id === id);
  if (!r) return;
  const fields = FIELD_LABELS[ACTIVE_PATH] || [];
  let detailHtml = '';
  fields.forEach(([col, label]) => {
    if (!r[col]) return;
    const valueHtml = LINK_FIELDS.includes(col)
      ? '<a href="' + escAttr(toHref(r[col])) + '" target="_blank" rel="noopener noreferrer">' + esc(r[col]) + '</a>'
      : esc(r[col]);
    detailHtml += '<div class="' + (col === 'problem' || col === 'message' || col === 'extra' ? 'full' : '') + '">' +
      '<div class="k">' + esc(label) + '</div><div class="v">' + valueHtml + '</div></div>';
  });

  const ownerOptions = ['<option value="">— Unassigned —</option>'].concat(
    USERS.map(u => '<option value="' + esc(u.email) + '"' + (r.tracking_owner === u.email ? ' selected' : '') + '>' + esc(u.name || u.email) + '</option>')
  ).join('');

  let actionsHtml = '<button type="button" class="btn btn-secondary" onclick="closeModal()">Close</button>';
  if (ACTIVE_PATH === 'hire' && !r.converted_deal_id) {
    actionsHtml = '<button type="button" class="btn btn-primary" onclick="pushToDeal(' + r.id + ')">Push to Deals</button>' + actionsHtml;
  }
  if (r.converted_deal_id) {
    actionsHtml = '<a href="../deal_tracker/" class="btn btn-secondary" target="_blank">View in Deal Tracker</a>' + actionsHtml;
  }

  document.getElementById('modalBox').innerHTML =
    '<div class="modal-title">' + esc(r.name) + '</div>' +
    '<div class="modal-sub">' + esc(r.email) + (r.mobile ? ' &middot; ' + esc(r.mobile) : '') +
      ' &middot; submitted ' + esc(new Date(r.created_at).toDateString()) + '</div>' +
    (r.company ? '<div class="detail-grid"><div class="full"><div class="k">Company</div><div class="v">' + esc(r.company) + '</div></div></div>' : '') +
    '<div class="detail-grid">' + detailHtml + '</div>' +
    '<div class="frow">' +
      '<div class="field"><label>Owner</label><select id="fOwner">' + ownerOptions + '</select></div>' +
      '<div class="field"><label>Stage</label><select id="fStage"></select></div>' +
    '</div>' +
    '<div class="frow" style="grid-template-columns:1fr">' +
      '<div class="field"><label>Internal notes</label><textarea id="fNotes">' + esc(r.tracking_notes || '') + '</textarea></div>' +
    '</div>' +
    '<div class="modal-actions">' +
      '<button type="button" class="btn btn-danger" onclick="saveAndArchive(' + r.id + ', ' + (r.eff_archived ? 'false' : 'true') + ')">' + (r.eff_archived ? 'Unarchive' : 'Archive') + '</button>' +
      '<div style="display:flex;gap:10px;margin-left:auto">' +
        '<button type="button" class="btn btn-secondary" onclick="saveDetails(' + r.id + ')">Save</button>' +
        actionsHtml +
      '</div>' +
    '</div>';

  const stageSel = document.getElementById('fStage');
  (window.STAGE_LIST || []).forEach(s => {
    const opt = document.createElement('option');
    opt.value = s; opt.textContent = s;
    if (s === r.eff_stage) opt.selected = true;
    stageSel.appendChild(opt);
  });

  document.getElementById('modalOverlay').classList.add('open');
}

function closeModal() {
  document.getElementById('modalOverlay').classList.remove('open');
}

async function saveDetails(id) {
  const owner = document.getElementById('fOwner').value;
  const stage = document.getElementById('fStage').value;
  const notes = document.getElementById('fNotes').value;
  await fetch(API, {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({action: 'update', submission_id: id, owner, notes})});
  await fetch(API, {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({action: 'move_stage', submission_id: id, stage})});
  location.reload();
}

async function saveAndArchive(id, archived) {
  await fetch(API, {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({action: 'archive', submission_id: id, archived})});
  location.reload();
}

async function quickJunk(id, btn) {
  await fetch(API, {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({action: 'move_stage', submission_id: id, stage: 'Junk'})});
  location.reload();
}

async function pushToDeal(id) {
  if (!confirm('Push this inquiry to Deal Tracker as a new deal?')) return;
  const r = await fetch(API, {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({action: 'push_to_deal', submission_id: id})});
  const j = await r.json();
  if (j.ok) { location.reload(); } else { alert(j.error || 'Failed'); }
}

let dragId = null;
function onDragStart(e, card) {
  dragId = card.dataset.id;
  card.classList.add('dragging');
  e.dataTransfer.effectAllowed = 'move';
}
async function onDropCard(e, colCards) {
  e.preventDefault();
  colCards.classList.remove('drag-over');
  const card = document.querySelector('.iq-card[data-id="' + dragId + '"]');
  if (!card) return;
  colCards.appendChild(card);
  refreshCounts();
  const stage = colCards.dataset.stage;
  await fetch(API, {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({action: 'move_stage', submission_id: dragId, stage})});
}

function refreshCounts() {
  document.querySelectorAll('.col').forEach(col => {
    const count = col.querySelectorAll('.iq-card').length;
    const countEl = col.querySelector('.col-head span:last-child');
    if (countEl) countEl.textContent = count;
  });
}

window.STAGE_LIST = <?= json_encode(array_column($stages, 'label')) ?>;
</script>
</body>
</html>
