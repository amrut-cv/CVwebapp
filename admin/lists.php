<?php
require __DIR__ . '/../session_guard.php';
require_once __DIR__ . '/../db.php';

$pdo = getDB();
$rows = $pdo->query("SELECT id, list_key, label, sort_order FROM list_items ORDER BY list_key, sort_order, id")->fetchAll();
$lists = [];
foreach ($rows as $r) {
    $lists[$r['list_key']][] = $r;
}
$engagementTypes = $pdo->query("SELECT * FROM engagement_types ORDER BY sort_order, id")->fetchAll();

$listMeta = [
    'scope_strategy'     => ['group' => 'Scope of work', 'label' => 'Strategy — Figure out...'],
    'scope_content'      => ['group' => 'Scope of work', 'label' => 'Content — Make these...'],
    'scope_ops'          => ['group' => 'Scope of work', 'label' => 'Marketing Ops — Setup / Manage...'],
    'brief_sales'        => ['group' => 'Business brief', 'label' => 'Sales triggers'],
    'brief_messaging'    => ['group' => 'Business brief', 'label' => 'Messaging / Positioning triggers'],
    'brief_mkt_strategy' => ['group' => 'Business brief', 'label' => 'Marketing strategy triggers'],
    'brief_structure'    => ['group' => 'Business brief', 'label' => 'Existing marketing structure'],
    'brief_engagement'   => ['group' => 'Business brief', 'label' => 'About the engagement'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Manage Lists — CoreVoice</title>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
      --brand: #1a1a2e; --accent: #e94560; --accent-light: #fde8ec;
      --bg: #f7f8fc; --white: #ffffff; --border: #e2e5ef;
      --text: #1c1c2e; --muted: #6b7280; --radius: 10px;
      --shadow: 0 2px 16px rgba(0,0,0,.08);
    }
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; display: flex; flex-direction: column; }

    header { background: var(--brand); color: #fff; padding: 0 32px; height: 56px; display: flex; align-items: center; justify-content: space-between; }
    header .logo { font-weight: 700; font-size: 1rem; letter-spacing: .04em; }
    header a { color: rgba(255,255,255,.7); text-decoration: none; font-size: .85rem; }
    header a:hover { color: #fff; }

    .layout { display: flex; flex: 1; max-width: 1100px; margin: 32px auto; width: 100%; gap: 24px; padding: 0 24px; }

    /* ── Sidebar ── */
    .sidebar { width: 240px; flex-shrink: 0; }
    .sidebar-group { margin-bottom: 20px; }
    .sidebar-group-label { font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; color: var(--muted); padding: 0 10px; margin-bottom: 6px; }
    .sidebar-item { display: block; padding: 9px 14px; border-radius: 8px; cursor: pointer; font-size: .85rem; color: var(--muted); border: none; background: none; width: 100%; text-align: left; font-family: inherit; transition: background .15s, color .15s; }
    .sidebar-item:hover { background: var(--white); color: var(--text); }
    .sidebar-item.active { background: var(--accent-light); color: var(--accent); font-weight: 600; }

    /* ── Panel ── */
    .panel { flex: 1; background: var(--white); border-radius: var(--radius); box-shadow: var(--shadow); padding: 28px 32px; }
    .panel-title { font-size: 1.1rem; font-weight: 700; margin-bottom: 4px; }
    .panel-sub { font-size: .82rem; color: var(--muted); margin-bottom: 24px; }

    /* ── List items ── */
    .item-list { list-style: none; }
    .item-row {
      display: flex; align-items: center; gap: 10px;
      padding: 9px 12px; border: 1.5px solid var(--border);
      border-radius: 8px; margin-bottom: 8px;
      background: var(--white); cursor: grab;
      transition: box-shadow .15s, border-color .15s;
    }
    .item-row:active { cursor: grabbing; }
    .item-row.drag-over { border-color: var(--accent); box-shadow: 0 0 0 2px var(--accent-light); }
    .drag-handle { color: var(--muted); font-size: 14px; cursor: grab; flex-shrink: 0; }
    .item-label { flex: 1; font-size: .9rem; padding: 2px 0; outline: none; border: none; font-family: inherit; color: var(--text); background: transparent; }
    .item-label:focus { color: var(--accent); }
    .item-save { display: none; padding: 3px 10px; border: 1.5px solid var(--accent); border-radius: 6px; background: var(--accent-light); color: var(--accent); font-size: .78rem; font-weight: 600; cursor: pointer; font-family: inherit; }
    .item-save.visible { display: inline-block; }
    .item-delete { border: none; background: none; color: var(--muted); cursor: pointer; font-size: 17px; line-height: 1; padding: 2px 4px; border-radius: 4px; flex-shrink: 0; }
    .item-delete:hover { color: var(--accent); background: var(--accent-light); }

    /* ── Engagement type cards ── */
    .eng-row { display: block; cursor: grab; padding: 14px; }
    .eng-row:active { cursor: grabbing; }
    .eng-top { display: flex; gap: 8px; margin-bottom: 8px; align-items: center; }
    .eng-top .drag-handle { flex-shrink: 0; }
    .eng-field { border: 1.5px solid var(--border); border-radius: 7px; padding: 7px 10px; font-size: .85rem; font-family: inherit; color: var(--text); background: var(--bg); outline: none; }
    .eng-field:focus { border-color: var(--accent); background: var(--white); }
    .eng-label-input { flex: 2; font-weight: 600; }
    .eng-category-input { flex: 1; }
    .eng-duration-input { flex: 1; }
    .eng-textarea { width: 100%; min-height: 52px; resize: vertical; margin-bottom: 8px; line-height: 1.4; }
    .eng-textarea-label { font-size: .68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--muted); margin-bottom: 4px; display: block; }
    .eng-actions { display: flex; justify-content: space-between; align-items: center; margin-top: 4px; }
    .eng-key { font-size: .72rem; color: var(--muted); font-family: monospace; }
    .eng-save { padding: 5px 14px; border: 1.5px solid var(--accent); border-radius: 6px; background: var(--accent-light); color: var(--accent); font-size: .78rem; font-weight: 600; cursor: pointer; font-family: inherit; }
    .eng-save.visible { display: inline-block; } .eng-save:not(.visible) { display: none; }

    /* ── Add new ── */
    .add-row { display: flex; gap: 8px; margin-top: 16px; }
    .add-input { flex: 1; border: 1.5px solid var(--border); border-radius: 8px; padding: 9px 14px; font-size: .88rem; outline: none; font-family: inherit; color: var(--text); background: var(--bg); }
    .add-input:focus { border-color: var(--accent); background: var(--white); }
    .add-btn { padding: 9px 18px; background: var(--accent); color: #fff; border: none; border-radius: 8px; font-size: .85rem; font-weight: 600; cursor: pointer; font-family: inherit; white-space: nowrap; }
    .add-btn:hover { opacity: .9; }

    .toast { position: fixed; bottom: 24px; right: 24px; background: var(--brand); color: #fff; padding: 10px 18px; border-radius: 8px; font-size: .85rem; opacity: 0; transition: opacity .3s; pointer-events: none; z-index: 999; }
    .toast.show { opacity: 1; }
  </style>
</head>
<body>
<header>
  <div class="logo">CoreVoice — Lists</div>
  <div style="display:flex;gap:20px;align-items:center;">
    <a href="/CVwebapp/admin/case_studies.php">Case Studies</a>
    <a href="/CVwebapp/admin/users.php">Users</a>
    <a href="/CVwebapp/contract_builder/">&#x2190; Back to builder</a>
  </div>
</header>

<div class="layout">
  <nav class="sidebar">
    <?php
    $groups = [];
    foreach ($listMeta as $key => $meta) {
        $groups[$meta['group']][$key] = $meta['label'];
    }
    $first = true;
    foreach ($groups as $group => $items): ?>
    <div class="sidebar-group">
      <div class="sidebar-group-label"><?= htmlspecialchars($group) ?></div>
      <?php foreach ($items as $key => $label): ?>
      <button class="sidebar-item <?= $first ? 'active' : '' ?>" onclick="showList('<?= $key ?>', this)"><?= htmlspecialchars($label) ?></button>
      <?php $first = false; endforeach; ?>
    </div>
    <?php endforeach; ?>
    <div class="sidebar-group">
      <div class="sidebar-group-label">Contract Builder</div>
      <button class="sidebar-item" onclick="showEngagementTypes(this)">Engagement Types</button>
    </div>
  </nav>

  <div class="panel" id="panel">
    <div id="simplePanel">
      <div class="panel-title" id="panelTitle"></div>
      <div class="panel-sub">Drag to reorder · Click a label to rename · Press Enter or click Save</div>
      <ul class="item-list" id="itemList"></ul>
      <div class="add-row">
        <input class="add-input" id="addInput" placeholder="New item…" onkeydown="if(event.key==='Enter')addItem()" />
        <button class="add-btn" onclick="addItem()">+ Add</button>
      </div>
    </div>

    <div id="engPanel" style="display:none">
      <div class="panel-title">Engagement Types</div>
      <div class="panel-sub">Drag to reorder · Edit any field · Save each card individually</div>
      <ul class="item-list" id="engList"></ul>
      <button class="add-btn" onclick="addEngType()" style="margin-top:8px">+ Add engagement type</button>
    </div>
  </div>
</div>

<div class="toast" id="toast"></div>

<script>
const LISTS = <?= json_encode($lists, JSON_UNESCAPED_UNICODE) ?>;
const META  = <?= json_encode($listMeta, JSON_UNESCAPED_UNICODE) ?>;
const ENG_TYPES = <?= json_encode($engagementTypes, JSON_UNESCAPED_UNICODE) ?>;

let currentKey = '<?= array_key_first($listMeta) ?>';
let dragSrc = null;

function showList(key, btn) {
  currentKey = key;
  document.querySelectorAll('.sidebar-item').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  document.getElementById('simplePanel').style.display = '';
  document.getElementById('engPanel').style.display = 'none';
  document.getElementById('panelTitle').textContent = META[key].label;
  renderList();
  document.getElementById('addInput').value = '';
  document.getElementById('addInput').focus();
}

function showEngagementTypes(btn) {
  document.querySelectorAll('.sidebar-item').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  document.getElementById('simplePanel').style.display = 'none';
  document.getElementById('engPanel').style.display = '';
  renderEngList();
}

function renderList() {
  const ul = document.getElementById('itemList');
  const items = LISTS[currentKey] || [];
  ul.innerHTML = '';
  items.forEach(item => ul.appendChild(makeRow(item)));
}

function makeRow(item) {
  const li = document.createElement('li');
  li.className = 'item-row';
  li.dataset.id = item.id;
  li.draggable = true;
  li.innerHTML = `
    <span class="drag-handle" title="Drag to reorder">⠿</span>
    <input class="item-label" value="${esc(item.label)}" data-orig="${esc(item.label)}"
      oninput="toggleSave(this)"
      onkeydown="if(event.key==='Enter'){event.preventDefault();saveLabel(this)}"
      onblur="if(this.value.trim()!==this.dataset.orig)saveLabel(this)"
    />
    <button class="item-save" onclick="saveLabel(this.previousElementSibling)">Save</button>
    <button class="item-delete" title="Delete" onclick="deleteItem(${item.id}, this)">×</button>
  `;
  li.addEventListener('dragstart', e => { dragSrc = li; li.style.opacity = '.4'; e.dataTransfer.effectAllowed = 'move'; });
  li.addEventListener('dragend', () => { li.style.opacity = ''; document.querySelectorAll('.item-row').forEach(r => r.classList.remove('drag-over')); });
  li.addEventListener('dragover', e => { e.preventDefault(); e.dataTransfer.dropEffect = 'move'; li.classList.add('drag-over'); });
  li.addEventListener('dragleave', () => li.classList.remove('drag-over'));
  li.addEventListener('drop', e => {
    e.preventDefault();
    li.classList.remove('drag-over');
    if (dragSrc && dragSrc !== li) {
      const ul = li.parentNode;
      ul.insertBefore(dragSrc, li.nextSibling);
      saveOrder();
    }
  });
  return li;
}

function esc(s) { return String(s).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;'); }

function toggleSave(input) {
  const btn = input.nextElementSibling;
  btn.classList.toggle('visible', input.value.trim() !== input.dataset.orig);
}

async function saveLabel(input) {
  const val = input.value.trim();
  if (!val || val === input.dataset.orig) return;
  const id = parseInt(input.closest('.item-row').dataset.id);
  const r = await api({action:'update', id, label: val});
  if (r.ok) {
    input.dataset.orig = val;
    input.nextElementSibling.classList.remove('visible');
    const item = (LISTS[currentKey]||[]).find(i => i.id === id);
    if (item) item.label = val;
    toast('Saved');
  }
}

async function deleteItem(id, btn) {
  if (!confirm('Delete this item?')) return;
  const r = await api({action:'delete', id});
  if (r.ok) {
    btn.closest('.item-row').remove();
    LISTS[currentKey] = (LISTS[currentKey]||[]).filter(i => i.id !== id);
    toast('Deleted');
  }
}

async function addItem() {
  const input = document.getElementById('addInput');
  const label = input.value.trim();
  if (!label) return;
  const r = await api({action:'add', list_key: currentKey, label});
  if (r.id) {
    if (!LISTS[currentKey]) LISTS[currentKey] = [];
    LISTS[currentKey].push({id: r.id, label, sort_order: r.sort_order});
    document.getElementById('itemList').appendChild(makeRow({id: r.id, label, sort_order: r.sort_order}));
    input.value = '';
    input.focus();
    toast('Added');
  }
}

async function saveOrder() {
  const ids = [...document.querySelectorAll('#itemList .item-row')].map(r => parseInt(r.dataset.id));
  await api({action:'reorder', ids});
  toast('Order saved');
}

async function api(body) {
  const r = await fetch('/CVwebapp/api/lists.php', {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(body)});
  return r.json();
}

// ── Engagement types ────────────────────────────────────────────────────────
function renderEngList() {
  const ul = document.getElementById('engList');
  ul.innerHTML = '';
  ENG_TYPES.forEach(et => ul.appendChild(makeEngRow(et)));
}

function makeEngRow(et) {
  const li = document.createElement('li');
  li.className = 'item-row eng-row';
  li.dataset.id = et.id;
  li.draggable = true;
  li.innerHTML = `
    <div class="eng-top">
      <span class="drag-handle" title="Drag to reorder">⠿</span>
      <input class="eng-field eng-label-input" data-f="label" value="${esc(et.label)}" oninput="engToggleSave(this)" placeholder="Label" />
      <input class="eng-field eng-category-input" data-f="category" value="${esc(et.category)}" oninput="engToggleSave(this)" placeholder="Category (e.g. Retainership)" />
      <input class="eng-field eng-duration-input" data-f="duration_tag" value="${esc(et.duration_tag || '')}" oninput="engToggleSave(this)" placeholder="Duration tag (optional)" />
    </div>
    <label class="eng-textarea-label">Card description (shown when picking the type)</label>
    <textarea class="eng-field eng-textarea" data-f="card_description" oninput="engToggleSave(this)">${esc(et.card_description)}</textarea>
    <label class="eng-textarea-label">Document description (used in generated proposal/contract)</label>
    <textarea class="eng-field eng-textarea" data-f="doc_description" oninput="engToggleSave(this)">${esc(et.doc_description)}</textarea>
    <label class="eng-textarea-label">Rationale (why we recommend this type, used in generated proposal)</label>
    <textarea class="eng-field eng-textarea" data-f="rationale" oninput="engToggleSave(this)">${esc(et.rationale)}</textarea>
    <div class="eng-actions">
      <span class="eng-key">key: ${esc(et.type_key)}</span>
      <div style="display:flex;gap:8px">
        <button class="eng-save" onclick="saveEngType(${et.id}, this)">Save</button>
        <button class="item-delete" title="Delete" onclick="deleteEngType(${et.id}, this)">×</button>
      </div>
    </div>
  `;
  li.addEventListener('dragstart', e => { dragSrc = li; li.style.opacity = '.4'; e.dataTransfer.effectAllowed = 'move'; });
  li.addEventListener('dragend', () => { li.style.opacity = ''; document.querySelectorAll('.eng-row').forEach(r => r.classList.remove('drag-over')); });
  li.addEventListener('dragover', e => { e.preventDefault(); e.dataTransfer.dropEffect = 'move'; li.classList.add('drag-over'); });
  li.addEventListener('dragleave', () => li.classList.remove('drag-over'));
  li.addEventListener('drop', e => {
    e.preventDefault();
    li.classList.remove('drag-over');
    if (dragSrc && dragSrc !== li) {
      li.parentNode.insertBefore(dragSrc, li.nextSibling);
      saveEngOrder();
    }
  });
  return li;
}

function engToggleSave(field) {
  field.closest('.eng-row').querySelector('.eng-save').classList.add('visible');
}

async function saveEngType(id, btn) {
  const row = btn.closest('.eng-row');
  const body = {action: 'update', id};
  row.querySelectorAll('[data-f]').forEach(f => body[f.dataset.f] = f.value.trim());
  const r = await api2(body);
  if (r.ok) {
    btn.classList.remove('visible');
    const et = ENG_TYPES.find(e => e.id === id);
    if (et) Object.assign(et, body);
    toast('Saved');
  }
}

async function deleteEngType(id, btn) {
  if (!confirm('Delete this engagement type? Any past contracts using it will keep showing its key as a fallback label.')) return;
  const r = await api2({action: 'delete', id});
  if (r.ok) {
    btn.closest('.eng-row').remove();
    const idx = ENG_TYPES.findIndex(e => e.id === id);
    if (idx > -1) ENG_TYPES.splice(idx, 1);
    toast('Deleted');
  }
}

async function addEngType() {
  const r = await api2({action: 'add', label: 'New engagement type', category: '', duration_tag: '', card_description: '', doc_description: '', rationale: ''});
  if (r.id) {
    const et = {id: r.id, type_key: r.type_key, label: 'New engagement type', category: '', duration_tag: '', card_description: '', doc_description: '', rationale: '', sort_order: r.sort_order};
    ENG_TYPES.push(et);
    document.getElementById('engList').appendChild(makeEngRow(et));
    toast('Added');
  }
}

async function saveEngOrder() {
  const ids = [...document.querySelectorAll('#engList .eng-row')].map(r => parseInt(r.dataset.id));
  await api2({action: 'reorder', ids});
  toast('Order saved');
}

async function api2(body) {
  const r = await fetch('/CVwebapp/api/engagement_types.php', {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(body)});
  return r.json();
}

let toastTimer;
function toast(msg) {
  const el = document.getElementById('toast');
  el.textContent = msg;
  el.classList.add('show');
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => el.classList.remove('show'), 2000);
}

// Init: show first list
document.querySelector('.sidebar-item').click();
</script>
</body>
</html>
