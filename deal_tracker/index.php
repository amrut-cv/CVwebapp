<?php
require __DIR__ . '/../session_guard.php';
require_module_access('deal_tracker');
require __DIR__ . '/../db.php';
require __DIR__ . '/helpers.php';
$stages = require __DIR__ . '/stages.php';
$pdo = getDB();

$engagementTypes = $pdo->query("SELECT id, label, category FROM engagement_types ORDER BY sort_order, id")->fetchAll();
$users = $pdo->query("SELECT email, name FROM users ORDER BY name, email")->fetchAll();
$contracts = $pdo->query("SELECT id, client_name, updated_at FROM contracts ORDER BY updated_at DESC")->fetchAll();
$deals = $pdo->query(
    "SELECT d.*, et.label AS eng_label
     FROM deals d
     LEFT JOIN engagement_types et ON et.id = d.engagement_type_id
     ORDER BY d.updated_at DESC"
)->fetchAll();

$dealContractRows = $pdo->query(
    "SELECT dc.deal_id, c.id, c.client_name
     FROM deal_contracts dc
     JOIN contracts c ON c.id = dc.contract_id
     ORDER BY c.updated_at DESC"
)->fetchAll();
$contractsByDeal = [];
foreach ($dealContractRows as $r) {
    $contractsByDeal[$r['deal_id']][] = ['id' => (int)$r['id'], 'client_name' => $r['client_name']];
}
foreach ($deals as &$d) {
    $d['contracts'] = $contractsByDeal[$d['id']] ?? [];
}
unset($d);

$activeDeals   = array_values(array_filter($deals, fn($d) => !$d['archived']));
$archivedDeals = array_values(array_filter($deals, fn($d) => $d['archived']));
$showArchived  = isset($_GET['archived']);

$byStage = [];
foreach ($stages as $s) $byStage[$s['label']] = [];
foreach ($activeDeals as $d) {
    if (isset($byStage[$d['stage']])) $byStage[$d['stage']][] = $d;
}

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_HTML5, 'UTF-8'); }

$nav_active = 'deals';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Deal Tracker — CoreVoice</title>
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
    .deal-card{background:#fff;border:1px solid #e2e5ef;border-radius:8px;padding:10px 12px;margin-bottom:8px;cursor:pointer;box-shadow:0 1px 4px rgba(0,0,0,.04)}
    .deal-card:hover{border-color:#C9972A}
    .deal-card.dragging{opacity:.35}
    .deal-card .dname{font-weight:700;font-size:.85rem;margin-bottom:4px}
    .deal-card .dval{font-size:.78rem;color:#6b7280;margin-bottom:6px}
    .dtags{display:flex;gap:4px;flex-wrap:wrap}
    .dtag{font-size:.65rem;padding:2px 7px;border-radius:10px;background:#f3f4f6;color:#374151}
    .dtag-link{background:#fdf6e8;color:#92400e;text-decoration:none}
    .dtag-link:hover{text-decoration:underline}
    .col-add{width:100%;text-align:left;background:none;border:1.5px dashed #d1d5db;border-radius:8px;padding:8px 10px;font-size:.78rem;color:#9ca3af;cursor:pointer;font-family:inherit}
    .col-add:hover{border-color:#C9972A;color:#C9972A}

    .tab-bar{display:flex;gap:4px;background:#e9ebf0;border-radius:8px;padding:4px;margin-bottom:20px;width:fit-content}
    .tab-bar a{padding:7px 18px;border-radius:6px;font-size:.85rem;font-weight:600;color:#6b7280;text-decoration:none}
    .tab-bar a.active{background:#fff;color:#1a1a2e;box-shadow:0 1px 4px rgba(0,0,0,.1)}
    .stage-toggles{display:flex;gap:16px;margin-bottom:16px}
    .stage-toggle{border:none;background:none;color:#C9972A;font-size:.82rem;font-weight:600;cursor:pointer;font-family:inherit;padding:0}
    .stage-toggle:hover{text-decoration:underline}
    .col[data-group="early"], .col[data-group="late"] { display: none; }
    .board.show-early .col[data-group="early"] { display: block; }
    .board.show-late .col[data-group="late"] { display: block; }
    .archive-list{display:flex;flex-direction:column;gap:8px;max-width:680px}
    .archive-row{display:flex;align-items:center;justify-content:space-between;gap:16px;background:#fff;border:1px solid #e2e5ef;border-radius:8px;padding:12px 16px}
    .archive-row .dname{font-weight:700;font-size:.85rem;margin-bottom:4px}
    .archive-row .dval{font-size:.78rem;color:#6b7280;margin-bottom:6px}
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
    .val-none{font-size:.82rem;color:#9ca3af;margin-bottom:14px}
    .contract-links{display:flex;flex-direction:column;gap:6px;margin-bottom:10px}
    .contract-links:empty{display:none}
    .contract-link-row{display:flex;align-items:center;justify-content:space-between;gap:10px;background:#fdf6e8;border:1px solid #f3e6c8;border-radius:7px;padding:7px 12px}
    .contract-link-row a{font-size:.82rem;color:#92400e;text-decoration:none}
    .contract-link-row a:hover{text-decoration:underline}
    .contract-link-remove{border:none;background:none;color:#9ca3af;cursor:pointer;font-size:15px;line-height:1;padding:2px 4px}
    .contract-link-remove:hover{color:#dc2626}
    .toggle-row{display:flex;gap:8px;margin-bottom:14px}
    .toggle-btn{border:1.5px solid #d1d5db;border-radius:7px;padding:7px 16px;font-size:.82rem;background:#fff;cursor:pointer;font-family:inherit}
    .toggle-btn.active{border-color:#C9972A;background:#fdf6e8;color:#92400e}
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
          <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/></svg>
        </div>
        <h1>Deal <span>tracker</span></h1>
      </div>
      <button class="btn btn-primary" onclick="openAddModal()">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Add deal
      </button>
    </div>

    <div class="tab-bar">
      <a href="index.php" class="<?= !$showArchived ? 'active' : '' ?>">Board</a>
      <a href="index.php?archived=1" class="<?= $showArchived ? 'active' : '' ?>">Archived (<?= count($archivedDeals) ?>)</a>
    </div>

    <?php if (!$showArchived): ?>
      <div class="stage-toggles">
        <button type="button" class="stage-toggle" id="toggleEarly" onclick="toggleStageGroup('early', this)">Show 1. Contact</button>
        <button type="button" class="stage-toggle" id="toggleLate" onclick="toggleStageGroup('late', this)">Show 5c&ndash;7b</button>
      </div>
    <?php endif ?>

    <?php if ($showArchived): ?>
      <div class="archive-list">
        <?php foreach ($archivedDeals as $d): ?>
          <div class="archive-row">
            <div style="flex:1;cursor:pointer" onclick="openEditModal(<?= (int)$d['id'] ?>)">
              <div class="dname"><?= h($d['deal_name']) ?></div>
              <div class="dval"><?= h(dt_value_text($d)) ?></div>
              <div class="dtags">
                <span class="dtag"><?= h($d['stage']) ?></span>
                <?php if ($d['eng_label']): ?><span class="dtag"><?= h($d['eng_label']) ?></span><?php endif ?>
                <span class="dtag"><?= h($d['source']) ?></span>
                <?php foreach ($d['contracts'] as $dc): ?>
                  <a class="dtag dtag-link" href="../contract_builder/?id=<?= (int)$dc['id'] ?>" target="_blank" onclick="event.stopPropagation()">Contract: <?= h($dc['client_name']) ?></a>
                <?php endforeach ?>
              </div>
            </div>
            <button class="btn btn-secondary" onclick="event.stopPropagation();unarchiveDeal(<?= (int)$d['id'] ?>)">Unarchive</button>
          </div>
        <?php endforeach ?>
        <?php if (!$archivedDeals): ?><div class="archive-empty">No archived deals.</div><?php endif ?>
      </div>
    <?php else: ?>
      <div class="board-scroll">
        <div class="board">
          <?php foreach ($stages as $s): $label = $s['label']; ?>
            <div class="col" data-group="<?= h($s['group']) ?>">
              <div class="col-head tone-<?= h($s['tone']) ?>">
                <span><?= h($label) ?></span>
                <span><?= count($byStage[$label]) ?></span>
              </div>
              <div class="col-cards" data-stage="<?= h($label) ?>"
                   ondragover="event.preventDefault();this.classList.add('drag-over')"
                   ondragleave="this.classList.remove('drag-over')"
                   ondrop="onDropCard(event, this)">
                <?php foreach ($byStage[$label] as $d): ?>
                  <div class="deal-card" draggable="true" data-id="<?= (int)$d['id'] ?>"
                       ondragstart="onDragStart(event, this)" ondragend="this.classList.remove('dragging')"
                       onclick="openEditModal(<?= (int)$d['id'] ?>)">
                    <div class="dname"><?= h($d['deal_name']) ?></div>
                    <div class="dval"><?= h(dt_value_text($d)) ?></div>
                    <div class="dtags">
                      <?php if ($d['eng_label']): ?><span class="dtag"><?= h($d['eng_label']) ?></span><?php endif ?>
                      <span class="dtag"><?= h($d['source']) ?></span>
                      <?php foreach ($d['contracts'] as $dc): ?>
                        <a class="dtag dtag-link" href="../contract_builder/?id=<?= (int)$dc['id'] ?>" target="_blank" onclick="event.stopPropagation()">Contract: <?= h($dc['client_name']) ?></a>
                      <?php endforeach ?>
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
    <div class="modal-title" id="modalTitle">Add deal</div>
    <form id="dealForm" onsubmit="event.preventDefault();saveDeal()">
      <input type="hidden" id="fId">
      <input type="hidden" id="fArchived" value="0">
      <div class="frow full">
        <div class="field">
          <label>Deal name</label>
          <input id="fName" required>
        </div>
      </div>
      <div class="frow full">
        <div class="field">
          <label>Linked contracts</label>
          <div class="contract-links" id="contractLinksList"></div>
          <div class="val-none" id="contractLinksNone" style="display:none">Save the deal first, then you can link contracts.</div>
          <div id="contractLinksAdd" style="display:flex;gap:8px">
            <select id="fAddContract" style="flex:1">
              <option value="">Select a contract to link&hellip;</option>
              <?php foreach ($contracts as $c): ?>
                <option value="<?= (int)$c['id'] ?>" data-name="<?= h($c['client_name']) ?>"><?= h($c['client_name']) ?> (<?= h(date('j M Y', strtotime($c['updated_at']))) ?>)</option>
              <?php endforeach ?>
            </select>
            <button type="button" class="btn btn-secondary" onclick="addContractLink()">Link</button>
          </div>
        </div>
      </div>
      <div class="frow">
        <div class="field">
          <label>Engagement type</label>
          <select id="fEngType" onchange="updateValueVisibility()">
            <option value="">— Not decided yet —</option>
            <?php foreach ($engagementTypes as $et): ?>
              <option value="<?= (int)$et['id'] ?>"><?= h($et['label']) ?></option>
            <?php endforeach ?>
          </select>
        </div>
        <div class="field">
          <label>Stage</label>
          <select id="fStage">
            <?php foreach ($stages as $s): ?>
              <option value="<?= h($s['label']) ?>"><?= h($s['label']) ?></option>
            <?php endforeach ?>
          </select>
        </div>
      </div>

      <div class="val-none" id="valNone">Select an engagement type to enter a value.</div>
      <div class="toggle-row" id="valToggle" style="display:none">
        <button type="button" class="toggle-btn" data-mode="monthly" onclick="setCustomMode('monthly')">Monthly</button>
        <button type="button" class="toggle-btn" data-mode="project" onclick="setCustomMode('project')">Per-project</button>
      </div>
      <div class="frow" id="valMonthly" style="display:none">
        <div class="field">
          <label>Monthly value (₹)</label>
          <input id="fMonthly" placeholder="e.g. 150000">
        </div>
        <div class="field">
          <label>Expected months</label>
          <input id="fMonths" placeholder="e.g. 6">
        </div>
      </div>
      <div class="frow full" id="valProject" style="display:none">
        <div class="field">
          <label>Project billing value (₹)</label>
          <input id="fProject" placeholder="e.g. 500000">
        </div>
      </div>

      <div class="frow">
        <div class="field">
          <label>Main contact</label>
          <input id="fContact">
        </div>
        <div class="field">
          <label>Deal owner</label>
          <select id="fOwner">
            <option value="">— Unassigned —</option>
            <?php foreach ($users as $u): ?>
              <option value="<?= h($u['email']) ?>"><?= h($u['name'] ?: $u['email']) ?></option>
            <?php endforeach ?>
          </select>
        </div>
      </div>
      <div class="frow">
        <div class="field">
          <label>Phone number</label>
          <input id="fPhone">
        </div>
        <div class="field">
          <label>Email address</label>
          <input id="fEmail" type="email">
        </div>
      </div>
      <div class="frow full">
        <div class="field">
          <label>Notes</label>
          <textarea id="fNotes"></textarea>
        </div>
      </div>
      <div class="frow">
        <div class="field">
          <label>Source</label>
          <select id="fSource">
            <option value="Outbound">Outbound</option>
            <option value="Inbound">Inbound</option>
          </select>
        </div>
      </div>

      <div class="modal-actions">
        <div style="display:flex;gap:10px">
          <button type="button" class="btn btn-danger" id="deleteBtn" onclick="deleteDeal()" style="display:none">Delete</button>
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
const ENGAGEMENT_TYPES = <?= json_encode($engagementTypes, JSON_UNESCAPED_UNICODE) ?>;
const DEALS = <?= json_encode($deals, JSON_UNESCAPED_UNICODE) ?>;
const STAGES = <?= json_encode(array_column($stages, 'label'), JSON_UNESCAPED_UNICODE) ?>;
const API = '/CVwebapp/api/deals.php';

let customMode = 'monthly';

function currentCategory() {
  const id = document.getElementById('fEngType').value;
  if (!id) return null;
  const et = ENGAGEMENT_TYPES.find(e => String(e.id) === String(id));
  return et ? et.category : null;
}

function updateValueVisibility() {
  const cat = currentCategory();
  document.getElementById('valNone').style.display = cat ? 'none' : '';
  document.getElementById('valToggle').style.display = (cat === 'Custom') ? 'flex' : 'none';
  let showMonthly = cat === 'Retainership' || (cat === 'Custom' && customMode === 'monthly');
  let showProject = cat === 'Project' || (cat === 'Custom' && customMode === 'project');
  document.getElementById('valMonthly').style.display = showMonthly ? 'grid' : 'none';
  document.getElementById('valProject').style.display = showProject ? 'grid' : 'none';
  document.querySelectorAll('.toggle-btn').forEach(b => b.classList.toggle('active', b.dataset.mode === customMode));
}

function setCustomMode(mode) {
  customMode = mode;
  updateValueVisibility();
}

function openModal() { document.getElementById('modalOverlay').classList.add('open'); }
function closeModal() { document.getElementById('modalOverlay').classList.remove('open'); }

function openAddModal(stage) {
  document.getElementById('dealForm').reset();
  document.getElementById('fId').value = '';
  if (stage) document.getElementById('fStage').value = stage;
  customMode = 'monthly';
  updateValueVisibility();
  document.getElementById('modalTitle').textContent = 'Add deal';
  document.getElementById('deleteBtn').style.display = 'none';
  document.getElementById('archiveBtn').style.display = 'none';
  document.getElementById('fArchived').value = '0';
  renderContractLinks(null);
  openModal();
}

function openEditModal(id) {
  const d = DEALS.find(x => x.id === id);
  if (!d) return;
  document.getElementById('fId').value = d.id;
  document.getElementById('fArchived').value = d.archived ? '1' : '0';
  document.getElementById('fName').value = d.deal_name || '';
  renderContractLinks(d);
  document.getElementById('fEngType').value = d.engagement_type_id || '';
  document.getElementById('fStage').value = d.stage;
  document.getElementById('fMonthly').value = d.monthly_value ?? '';
  document.getElementById('fMonths').value = d.expected_months ?? '';
  document.getElementById('fProject').value = d.project_value ?? '';
  document.getElementById('fContact').value = d.main_contact || '';
  document.getElementById('fPhone').value = d.phone_number || '';
  document.getElementById('fEmail').value = d.email_address || '';
  document.getElementById('fNotes').value = d.next_steps || '';
  document.getElementById('fOwner').value = d.deal_owner || '';
  document.getElementById('fSource').value = d.source || 'Outbound';
  customMode = (d.project_value !== null && d.monthly_value === null) ? 'project' : 'monthly';
  updateValueVisibility();
  document.getElementById('modalTitle').textContent = 'Edit deal';
  document.getElementById('deleteBtn').style.display = '';
  const archiveBtn = document.getElementById('archiveBtn');
  archiveBtn.style.display = '';
  archiveBtn.textContent = d.archived ? 'Unarchive' : 'Archive';
  openModal();
}

function renderContractLinks(d) {
  const list = document.getElementById('contractLinksList');
  const none = document.getElementById('contractLinksNone');
  const add  = document.getElementById('contractLinksAdd');
  list.innerHTML = '';
  if (!d) {
    none.style.display = '';
    add.style.display = 'none';
    return;
  }
  none.style.display = 'none';
  add.style.display = 'flex';
  (d.contracts || []).forEach(c => {
    const row = document.createElement('div');
    row.className = 'contract-link-row';
    row.innerHTML =
      '<a href="../contract_builder/?id=' + c.id + '" target="_blank">' + escHtml(c.client_name) + '</a>' +
      '<button type="button" class="contract-link-remove" title="Unlink" onclick="removeContractLink(' + c.id + ')">&times;</button>';
    list.appendChild(row);
  });
}

function escHtml(s) {
  const div = document.createElement('div');
  div.textContent = s || '';
  return div.innerHTML;
}

async function addContractLink() {
  const dealId = document.getElementById('fId').value;
  const contractId = document.getElementById('fAddContract').value;
  if (!dealId || !contractId) return;
  const r = await fetch(API, {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({action: 'link_contract', deal_id: dealId, contract_id: contractId})});
  const j = await r.json();
  if (j.ok) {
    const d = DEALS.find(x => String(x.id) === String(dealId));
    const opt = document.getElementById('fAddContract').selectedOptions[0];
    if (d) {
      d.contracts = d.contracts || [];
      if (!d.contracts.some(c => String(c.id) === String(contractId))) {
        d.contracts.push({id: parseInt(contractId), client_name: opt.dataset.name});
      }
      renderContractLinks(d);
    }
    document.getElementById('fAddContract').value = '';
  }
}

async function removeContractLink(contractId) {
  const dealId = document.getElementById('fId').value;
  if (!dealId) return;
  const r = await fetch(API, {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({action: 'unlink_contract', deal_id: dealId, contract_id: contractId})});
  const j = await r.json();
  if (j.ok) {
    const d = DEALS.find(x => String(x.id) === String(dealId));
    if (d) {
      d.contracts = (d.contracts || []).filter(c => String(c.id) !== String(contractId));
      renderContractLinks(d);
    }
  }
}

async function saveDeal() {
  const cat = currentCategory();
  const showMonthly = cat === 'Retainership' || (cat === 'Custom' && customMode === 'monthly');
  const showProject = cat === 'Project' || (cat === 'Custom' && customMode === 'project');
  const id = document.getElementById('fId').value;
  const body = {
    action: id ? 'update' : 'add',
    id: id || undefined,
    deal_name: document.getElementById('fName').value.trim(),
    engagement_type_id: document.getElementById('fEngType').value || null,
    stage: document.getElementById('fStage').value,
    main_contact: document.getElementById('fContact').value.trim(),
    phone_number: document.getElementById('fPhone').value.trim(),
    email_address: document.getElementById('fEmail').value.trim(),
    next_steps: document.getElementById('fNotes').value.trim(),
    deal_owner: document.getElementById('fOwner').value,
    source: document.getElementById('fSource').value,
    monthly_value: showMonthly ? document.getElementById('fMonthly').value : null,
    expected_months: showMonthly ? document.getElementById('fMonths').value : null,
    project_value: showProject ? document.getElementById('fProject').value : null,
  };
  if (!body.deal_name) { alert('Deal name is required'); return; }
  const r = await fetch(API, {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(body)});
  const j = await r.json();
  if (j.ok || j.id) { location.reload(); } else { alert(j.error || 'Save failed'); }
}

async function deleteDeal() {
  const id = document.getElementById('fId').value;
  if (!id || !confirm('Delete this deal?')) return;
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

async function unarchiveDeal(id) {
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
  const card = document.querySelector('.deal-card[data-id="' + dragId + '"]');
  if (!card) return;
  colCards.appendChild(card);
  refreshCounts();
  const stage = colCards.dataset.stage;
  await fetch(API, {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({action: 'move_stage', id: dragId, stage})});
  const d = DEALS.find(x => String(x.id) === String(dragId));
  if (d) d.stage = stage;
}

function refreshCounts() {
  document.querySelectorAll('.col').forEach(col => {
    const count = col.querySelectorAll('.deal-card').length;
    const countEl = col.querySelector('.col-head span:last-child');
    if (countEl) countEl.textContent = count;
  });
}

const STAGE_TOGGLE_LABELS = { early: '1. Contact', late: '5c–7b' };
function toggleStageGroup(group, btn) {
  const board = document.querySelector('.board');
  if (!board) return;
  const showing = board.classList.toggle('show-' + group);
  btn.textContent = (showing ? 'Hide ' : 'Show ') + STAGE_TOGGLE_LABELS[group];
}
</script>
</body>
</html>
