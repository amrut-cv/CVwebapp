<?php
require __DIR__ . '/../session_guard.php';
require_module_access('guest_tracker');
require __DIR__ . '/../db.php';
$stages = require __DIR__ . '/stages.php';
$pdo = getDB();

$guests = $pdo->query("SELECT * FROM guests ORDER BY updated_at DESC")->fetchAll();

$activeGuests   = array_values(array_filter($guests, fn($g) => !$g['archived']));
$archivedGuests = array_values(array_filter($guests, fn($g) => $g['archived']));
$showArchived   = isset($_GET['archived']);

$byStage = [];
foreach ($stages as $s) $byStage[$s['label']] = [];
foreach ($activeGuests as $g) {
    if (isset($byStage[$g['stage']])) $byStage[$g['stage']][] = $g;
}

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_HTML5, 'UTF-8'); }

function guest_summary(array $g): string {
    if ($g['recording_date']) {
        return ($g['recording_date_confirmed'] ? 'Recording ' : 'Tentative ') . date('j M', strtotime($g['recording_date']));
    }
    return $g['company_title'] ?: '—';
}

$nav_active = 'guests';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Guest Tracker — CoreVoice</title>
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

    .board-scroll{overflow-x:auto;padding-bottom:16px}
    .board{display:flex;gap:12px;min-width:max-content}
    .col{width:230px;flex-shrink:0}
    .col-head{display:flex;justify-content:space-between;align-items:center;padding:8px 12px;border-radius:8px;font-size:.76rem;font-weight:700;margin-bottom:10px;text-transform:uppercase;letter-spacing:.02em}
    .tone-neutral{background:#eef0f5;color:#4b5563}
    .tone-success{background:#dcfce7;color:#166534}
    .tone-danger{background:#fee2e2;color:#991b1b}
    .tone-warning{background:#fef3c7;color:#92400e}
    .col-cards{min-height:50px;border-radius:8px;transition:background .1s}
    .col-cards.drag-over{background:#fdf6e8}
    .guest-card{background:#fff;border:1px solid #e2e5ef;border-radius:8px;padding:10px 12px;margin-bottom:8px;cursor:pointer;box-shadow:0 1px 4px rgba(0,0,0,.04)}
    .guest-card:hover{border-color:#C9972A}
    .guest-card.dragging{opacity:.35}
    .guest-card .gname{font-weight:700;font-size:.85rem;margin-bottom:4px}
    .guest-card .gval{font-size:.78rem;color:#6b7280;margin-bottom:6px}
    .gtags{display:flex;gap:4px;flex-wrap:wrap}
    .gtag{font-size:.65rem;padding:2px 7px;border-radius:10px;background:#f3f4f6;color:#374151}
    .col-add{width:100%;text-align:left;background:none;border:1.5px dashed #d1d5db;border-radius:8px;padding:8px 10px;font-size:.78rem;color:#9ca3af;cursor:pointer;font-family:inherit}
    .col-add:hover{border-color:#C9972A;color:#C9972A}
    .archive-list{display:flex;flex-direction:column;gap:8px;max-width:680px}
    .archive-row{display:flex;align-items:center;justify-content:space-between;gap:16px;background:#fff;border:1px solid #e2e5ef;border-radius:8px;padding:12px 16px}
    .archive-row .gname{font-weight:700;font-size:.85rem;margin-bottom:4px}
    .archive-row .gval{font-size:.78rem;color:#6b7280;margin-bottom:6px}
    .archive-empty{color:#9ca3af;padding:24px 0}

    .modal-overlay{display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.4);z-index:200;align-items:center;justify-content:center;padding:20px}
    .modal-overlay.open{display:flex}
    .modal-box{background:#fff;border-radius:12px;padding:28px 32px;width:620px;max-width:100%;max-height:88vh;overflow-y:auto}
    .modal-title{font-size:1.1rem;font-weight:700;margin-bottom:18px}
    .frow{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px}
    .frow.full{grid-template-columns:1fr}
    .field label{font-size:.78rem;color:#6b7280;display:block;margin-bottom:5px;font-weight:600}
    .field input,.field select,.field textarea{width:100%;padding:8px 10px;border:1.5px solid #d1d5db;border-radius:7px;font-size:.85rem;font-family:inherit;outline:none}
    .field input:focus,.field select:focus,.field textarea:focus{border-color:#C9972A}
    .field textarea{min-height:64px;resize:vertical}
    .field-check{display:flex;align-items:center;gap:8px;margin-top:8px;font-size:.82rem;color:#374151}
    .field-check input{width:auto}
    .modal-actions{display:flex;justify-content:space-between;align-items:center;margin-top:8px}
  </style>
</head>
<body>
<div class="cv-layout">
  <?php require __DIR__ . '/../nav.php'; ?>
  <div class="page">
    <div class="page-header">
      <div class="title-group">
        <div class="icon-badge">
          <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" y1="19" x2="12" y2="23"/><line x1="8" y1="23" x2="16" y2="23"/></svg>
        </div>
        <h1>Guest <span>tracker</span></h1>
      </div>
      <button class="btn btn-primary" onclick="openAddModal()">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Add guest
      </button>
    </div>

    <div class="tab-bar">
      <a href="index.php" class="<?= !$showArchived ? 'active' : '' ?>">Board</a>
      <a href="index.php?archived=1" class="<?= $showArchived ? 'active' : '' ?>">Archived (<?= count($archivedGuests) ?>)</a>
    </div>

    <?php if ($showArchived): ?>
      <div class="archive-list">
        <?php foreach ($archivedGuests as $g): ?>
          <div class="archive-row">
            <div style="flex:1;cursor:pointer" onclick="openEditModal(<?= (int)$g['id'] ?>)">
              <div class="gname"><?= h($g['guest_name']) ?></div>
              <div class="gval"><?= h(guest_summary($g)) ?></div>
              <div class="gtags">
                <span class="gtag"><?= h($g['stage']) ?></span>
                <span class="gtag"><?= h($g['source']) ?></span>
              </div>
            </div>
            <button class="btn btn-secondary" onclick="event.stopPropagation();unarchiveGuest(<?= (int)$g['id'] ?>)">Unarchive</button>
          </div>
        <?php endforeach ?>
        <?php if (!$archivedGuests): ?><div class="archive-empty">No archived guests.</div><?php endif ?>
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
                <?php foreach ($byStage[$label] as $g): ?>
                  <div class="guest-card" draggable="true" data-id="<?= (int)$g['id'] ?>"
                       ondragstart="onDragStart(event, this)" ondragend="this.classList.remove('dragging')"
                       onclick="openEditModal(<?= (int)$g['id'] ?>)">
                    <div class="gname"><?= h($g['guest_name']) ?></div>
                    <div class="gval"><?= h(guest_summary($g)) ?></div>
                    <div class="gtags">
                      <span class="gtag"><?= h($g['source']) ?></span>
                    </div>
                  </div>
                <?php endforeach ?>
              </div>
              <button class="col-add" style="margin-top:4px" onclick="openAddModal('<?= h($label) ?>')">+ Add</button>
            </div>
          <?php endforeach ?>
        </div>
      </div>
    <?php endif ?>
  </div>
</div>

<div class="modal-overlay" id="modalOverlay">
  <div class="modal-box">
    <div class="modal-title" id="modalTitle">Add guest</div>
    <form id="guestForm" onsubmit="event.preventDefault();saveGuest()">
      <input type="hidden" id="fId">
      <input type="hidden" id="fArchived" value="0">
      <div class="frow">
        <div class="field">
          <label>Guest name</label>
          <input id="fName" required>
        </div>
        <div class="field">
          <label>Company / title</label>
          <input id="fCompany" placeholder="e.g. Founder, Acme">
        </div>
      </div>
      <div class="frow full">
        <div class="field">
          <label>Bio / angle</label>
          <textarea id="fBio" placeholder="One line on who they are and why they're a good fit"></textarea>
        </div>
      </div>
      <div class="frow">
        <div class="field">
          <label>Email</label>
          <input id="fEmail" type="email">
        </div>
        <div class="field">
          <label>Phone</label>
          <input id="fPhone">
        </div>
      </div>
      <div class="frow">
        <div class="field">
          <label>LinkedIn / Twitter</label>
          <input id="fSocial" placeholder="Profile URL">
        </div>
        <div class="field">
          <label>Source</label>
          <select id="fSource">
            <option value="Cold outreach">Cold outreach</option>
            <option value="Referral">Referral</option>
            <option value="Inbound">Inbound</option>
          </select>
        </div>
      </div>
      <div class="frow full">
        <div class="field">
          <label>Episode topic</label>
          <textarea id="fTopic" placeholder="What the episode will cover"></textarea>
        </div>
      </div>
      <div class="frow">
        <div class="field">
          <label>Recording date</label>
          <input id="fRecDate" type="date">
          <label class="field-check"><input id="fRecConfirmed" type="checkbox"> Confirmed</label>
        </div>
        <div class="field">
          <label>Release date</label>
          <input id="fRelDate" type="date">
          <label class="field-check"><input id="fRelConfirmed" type="checkbox"> Confirmed</label>
        </div>
      </div>
      <div class="frow full">
        <div class="field">
          <label>Episode link</label>
          <input id="fEpLink" placeholder="Published URL, once live">
        </div>
      </div>
      <div class="frow">
        <div class="field">
          <label>Stage</label>
          <select id="fStage">
            <?php foreach ($stages as $s): ?>
              <option value="<?= h($s['label']) ?>"><?= h($s['label']) ?></option>
            <?php endforeach ?>
          </select>
        </div>
      </div>
      <div class="frow full">
        <div class="field">
          <label>Notes</label>
          <textarea id="fNotes"></textarea>
        </div>
      </div>

      <div class="modal-actions">
        <div style="display:flex;gap:10px">
          <button type="button" class="btn btn-danger" id="deleteBtn" onclick="deleteGuest()" style="display:none">Delete</button>
          <button type="button" class="btn btn-secondary" id="archiveBtn" onclick="toggleArchive()" style="display:none">Archive</button>
        </div>
        <div style="display:flex;gap:10px;margin-left:auto">
          <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
          <button type="submit" class="btn btn-primary">Save</button>
        </div>
      </div>
    </form>
  </div>
</div>

<script>
const GUESTS = <?= json_encode($guests, JSON_UNESCAPED_UNICODE) ?>;
const API = '/CVwebapp/api/guests.php';

function openModal() { document.getElementById('modalOverlay').classList.add('open'); }
function closeModal() { document.getElementById('modalOverlay').classList.remove('open'); }

function openAddModal(stage) {
  document.getElementById('guestForm').reset();
  document.getElementById('fId').value = '';
  if (stage) document.getElementById('fStage').value = stage;
  document.getElementById('modalTitle').textContent = 'Add guest';
  document.getElementById('deleteBtn').style.display = 'none';
  document.getElementById('archiveBtn').style.display = 'none';
  document.getElementById('fArchived').value = '0';
  openModal();
}

function openEditModal(id) {
  const g = GUESTS.find(x => x.id === id);
  if (!g) return;
  document.getElementById('fId').value = g.id;
  document.getElementById('fArchived').value = g.archived ? '1' : '0';
  document.getElementById('fName').value = g.guest_name || '';
  document.getElementById('fCompany').value = g.company_title || '';
  document.getElementById('fBio').value = g.bio || '';
  document.getElementById('fEmail').value = g.email || '';
  document.getElementById('fPhone').value = g.phone || '';
  document.getElementById('fSocial').value = g.social_link || '';
  document.getElementById('fSource').value = g.source || 'Cold outreach';
  document.getElementById('fTopic').value = g.episode_topic || '';
  document.getElementById('fRecDate').value = g.recording_date || '';
  document.getElementById('fRecConfirmed').checked = !!g.recording_date_confirmed;
  document.getElementById('fRelDate').value = g.release_date || '';
  document.getElementById('fRelConfirmed').checked = !!g.release_date_confirmed;
  document.getElementById('fEpLink').value = g.episode_link || '';
  document.getElementById('fStage').value = g.stage;
  document.getElementById('fNotes').value = g.notes || '';
  document.getElementById('modalTitle').textContent = 'Edit guest';
  document.getElementById('deleteBtn').style.display = '';
  const archiveBtn = document.getElementById('archiveBtn');
  archiveBtn.style.display = '';
  archiveBtn.textContent = g.archived ? 'Unarchive' : 'Archive';
  openModal();
}

async function saveGuest() {
  const id = document.getElementById('fId').value;
  const body = {
    action: id ? 'update' : 'add',
    id: id || undefined,
    guest_name: document.getElementById('fName').value.trim(),
    company_title: document.getElementById('fCompany').value.trim(),
    bio: document.getElementById('fBio').value.trim(),
    email: document.getElementById('fEmail').value.trim(),
    phone: document.getElementById('fPhone').value.trim(),
    social_link: document.getElementById('fSocial').value.trim(),
    source: document.getElementById('fSource').value,
    episode_topic: document.getElementById('fTopic').value.trim(),
    recording_date: document.getElementById('fRecDate').value,
    recording_date_confirmed: document.getElementById('fRecConfirmed').checked,
    release_date: document.getElementById('fRelDate').value,
    release_date_confirmed: document.getElementById('fRelConfirmed').checked,
    episode_link: document.getElementById('fEpLink').value.trim(),
    stage: document.getElementById('fStage').value,
    notes: document.getElementById('fNotes').value.trim(),
  };
  if (!body.guest_name) { alert('Guest name is required'); return; }
  const r = await fetch(API, {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(body)});
  const j = await r.json();
  if (j.ok || j.id) { location.reload(); } else { alert(j.error || 'Save failed'); }
}

async function deleteGuest() {
  const id = document.getElementById('fId').value;
  if (!id || !confirm('Delete this guest?')) return;
  await fetch(API, {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({action: 'delete', id})});
  location.reload();
}

async function toggleArchive() {
  const id = document.getElementById('fId').value;
  if (!id) return;
  const archived = document.getElementById('fArchived').value === '1' ? 0 : 1;
  await fetch(API, {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({action: 'archive', id, archived})});
  location.reload();
}

async function unarchiveGuest(id) {
  await fetch(API, {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({action: 'archive', id, archived: 0})});
  location.reload();
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
  const card = document.querySelector('.guest-card[data-id="' + dragId + '"]');
  if (!card) return;
  colCards.appendChild(card);
  refreshCounts();
  const stage = colCards.dataset.stage;
  await fetch(API, {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({action: 'move_stage', id: dragId, stage})});
  const g = GUESTS.find(x => String(x.id) === String(dragId));
  if (g) g.stage = stage;
}

function refreshCounts() {
  document.querySelectorAll('.col').forEach(col => {
    const count = col.querySelectorAll('.guest-card').length;
    const countEl = col.querySelector('.col-head span:last-child');
    if (countEl) countEl.textContent = count;
  });
}
</script>
</body>
</html>
