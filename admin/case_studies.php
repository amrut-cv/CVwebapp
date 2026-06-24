<?php
require __DIR__ . '/../session_guard.php';
require __DIR__ . '/../db.php';

$pdo  = getDB();
$rows = $pdo->query("SELECT id, name, description, sort_order FROM case_studies ORDER BY sort_order, id")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Case Studies — CoreVoice</title>
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
    header .nav-links { display: flex; gap: 20px; align-items: center; }
    header a { color: rgba(255,255,255,.7); text-decoration: none; font-size: .85rem; }
    header a:hover { color: #fff; }

    .main { max-width: 780px; margin: 40px auto; width: 100%; padding: 0 24px; }
    .page-title { font-size: 1.3rem; font-weight: 700; margin-bottom: 4px; }
    .page-sub { font-size: .84rem; color: var(--muted); margin-bottom: 28px; }

    .item-list { list-style: none; margin-bottom: 12px; }
    .item-row {
      display: flex; align-items: flex-start; gap: 12px;
      padding: 14px 16px; border: 1.5px solid var(--border);
      border-radius: 10px; margin-bottom: 10px; background: var(--white);
      cursor: grab; transition: box-shadow .15s, border-color .15s;
    }
    .item-row:active { cursor: grabbing; }
    .item-row.drag-over { border-color: var(--accent); box-shadow: 0 0 0 2px var(--accent-light); }
    .drag-handle { color: var(--muted); font-size: 14px; cursor: grab; padding-top: 3px; flex-shrink: 0; }

    .item-fields { flex: 1; display: flex; flex-direction: column; gap: 6px; }
    .item-name {
      font-size: .95rem; font-weight: 600; padding: 3px 0; outline: none;
      border: none; border-bottom: 1.5px solid transparent; font-family: inherit;
      color: var(--text); background: transparent; width: 100%;
      transition: border-color .15s;
    }
    .item-name:focus { border-bottom-color: var(--accent); color: var(--accent); }
    .item-desc {
      font-size: .83rem; color: var(--muted); padding: 3px 0; outline: none;
      border: none; border-bottom: 1.5px solid transparent; font-family: inherit;
      background: transparent; width: 100%; resize: none; line-height: 1.5;
      transition: border-color .15s;
    }
    .item-desc:focus { border-bottom-color: var(--border); color: var(--text); }

    .item-actions { display: flex; flex-direction: column; gap: 6px; align-items: flex-end; flex-shrink: 0; }
    .item-save { display: none; padding: 4px 12px; border: 1.5px solid var(--accent); border-radius: 6px; background: var(--accent-light); color: var(--accent); font-size: .78rem; font-weight: 600; cursor: pointer; font-family: inherit; white-space: nowrap; }
    .item-save.visible { display: inline-block; }
    .item-delete { border: none; background: none; color: var(--muted); cursor: pointer; font-size: 18px; line-height: 1; padding: 2px 4px; border-radius: 4px; }
    .item-delete:hover { color: var(--accent); background: var(--accent-light); }

    .add-section { background: var(--white); border: 1.5px solid var(--border); border-radius: 10px; padding: 20px; }
    .add-section h3 { font-size: .9rem; font-weight: 700; margin-bottom: 14px; color: var(--muted); text-transform: uppercase; letter-spacing: .05em; }
    .add-field { width: 100%; border: 1.5px solid var(--border); border-radius: 8px; padding: 9px 14px; font-size: .88rem; outline: none; font-family: inherit; color: var(--text); background: var(--bg); margin-bottom: 8px; }
    .add-field:focus { border-color: var(--accent); background: var(--white); }
    textarea.add-field { resize: vertical; min-height: 72px; }
    .add-btn { padding: 9px 22px; background: var(--accent); color: #fff; border: none; border-radius: 8px; font-size: .85rem; font-weight: 600; cursor: pointer; font-family: inherit; }
    .add-btn:hover { opacity: .9; }

    .empty { padding: 32px; text-align: center; color: var(--muted); font-size: .9rem; border: 1.5px dashed var(--border); border-radius: 10px; margin-bottom: 24px; }

    .toast { position: fixed; bottom: 24px; right: 24px; background: var(--brand); color: #fff; padding: 10px 18px; border-radius: 8px; font-size: .85rem; opacity: 0; transition: opacity .3s; pointer-events: none; z-index: 999; }
    .toast.show { opacity: 1; }
  </style>
</head>
<body>
<header>
  <div class="logo">CoreVoice — Case Studies</div>
  <div class="nav-links">
    <a href="/CVwebapp/admin/lists.php">Lists</a>
    <a href="/CVwebapp/admin/users.php">Users</a>
    <a href="/CVwebapp/contract_builder/">&#x2190; Back to builder</a>
  </div>
</header>

<div class="main">
  <div class="page-title">Case Studies</div>
  <div class="page-sub">Drag to reorder &middot; Click a field to edit &middot; Press Enter or click Save</div>

  <?php if (empty($rows)): ?>
  <div class="empty">No case studies yet. Add your first one below.</div>
  <?php endif; ?>

  <ul class="item-list" id="itemList">
    <?php foreach ($rows as $item): ?>
    <li class="item-row" data-id="<?= $item['id'] ?>" draggable="true">
      <span class="drag-handle" title="Drag to reorder">&#x2807;</span>
      <div class="item-fields">
        <input class="item-name" value="<?= htmlspecialchars($item['name']) ?>"
          data-orig-name="<?= htmlspecialchars($item['name']) ?>"
          placeholder="Client / project name"
          oninput="markDirty(this.closest('.item-row'))"
          onkeydown="if(event.key==='Enter'){event.preventDefault();saveItem(this.closest('.item-row'))}" />
        <textarea class="item-desc" rows="2"
          data-orig-desc="<?= htmlspecialchars($item['description']) ?>"
          placeholder="One-paragraph description of the engagement"
          oninput="markDirty(this.closest('.item-row'))"><?= htmlspecialchars($item['description']) ?></textarea>
      </div>
      <div class="item-actions">
        <button class="item-save" onclick="saveItem(this.closest('.item-row'))">Save</button>
        <button class="item-delete" title="Delete" onclick="deleteItem(<?= $item['id'] ?>, this)">&#xd7;</button>
      </div>
    </li>
    <?php endforeach; ?>
  </ul>

  <div class="add-section">
    <h3>Add new case study</h3>
    <input  class="add-field" id="addName" placeholder="Client / project name" onkeydown="if(event.key==='Enter'){document.getElementById('addDesc').focus()}" />
    <textarea class="add-field" id="addDesc" placeholder="One-paragraph description of the engagement" rows="3"></textarea>
    <button class="add-btn" onclick="addItem()">+ Add case study</button>
  </div>
</div>

<div class="toast" id="toast"></div>

<script>
let dragSrc = null;

// Drag to reorder
document.getElementById('itemList').addEventListener('dragstart', function(e) {
  dragSrc = e.target.closest('.item-row');
  if (!dragSrc) return;
  dragSrc.style.opacity = '.4';
  e.dataTransfer.effectAllowed = 'move';
});
document.getElementById('itemList').addEventListener('dragend', function() {
  if (dragSrc) dragSrc.style.opacity = '';
  document.querySelectorAll('.item-row').forEach(function(r) { r.classList.remove('drag-over'); });
});
document.getElementById('itemList').addEventListener('dragover', function(e) {
  e.preventDefault();
  var row = e.target.closest('.item-row');
  if (row) { document.querySelectorAll('.item-row').forEach(function(r) { r.classList.remove('drag-over'); }); row.classList.add('drag-over'); }
});
document.getElementById('itemList').addEventListener('drop', function(e) {
  e.preventDefault();
  var row = e.target.closest('.item-row');
  if (row) row.classList.remove('drag-over');
  if (dragSrc && row && dragSrc !== row) {
    row.parentNode.insertBefore(dragSrc, row.nextSibling);
    saveOrder();
  }
});

function markDirty(row) {
  row.querySelector('.item-save').classList.add('visible');
}

async function saveItem(row) {
  var id   = parseInt(row.dataset.id);
  var name = row.querySelector('.item-name').value.trim();
  var desc = row.querySelector('.item-desc').value.trim();
  if (!name) return;
  var r = await api({action: 'update', id: id, name: name, description: desc});
  if (r.ok) {
    row.querySelector('.item-name').dataset.origName = name;
    row.querySelector('.item-desc').dataset.origDesc = desc;
    row.querySelector('.item-save').classList.remove('visible');
    toast('Saved');
  }
}

async function deleteItem(id, btn) {
  if (!confirm('Delete this case study?')) return;
  var r = await api({action: 'delete', id: id});
  if (r.ok) {
    btn.closest('.item-row').remove();
    if (!document.querySelector('.item-row')) {
      var ul = document.getElementById('itemList');
      ul.insertAdjacentHTML('beforebegin', '<div class="empty" id="emptyMsg">No case studies yet. Add your first one below.</div>');
    }
    toast('Deleted');
  }
}

async function addItem() {
  var nameEl = document.getElementById('addName');
  var descEl = document.getElementById('addDesc');
  var name = nameEl.value.trim();
  var desc = descEl.value.trim();
  if (!name) { nameEl.focus(); return; }
  var r = await api({action: 'add', name: name, description: desc});
  if (r.id) {
    document.getElementById('emptyMsg') && document.getElementById('emptyMsg').remove();
    document.getElementById('itemList').appendChild(makeRow(r));
    nameEl.value = '';
    descEl.value = '';
    nameEl.focus();
    toast('Added');
  }
}

function makeRow(item) {
  var li = document.createElement('li');
  li.className = 'item-row';
  li.dataset.id = item.id;
  li.draggable = true;
  li.innerHTML =
    '<span class="drag-handle" title="Drag to reorder">&#x2807;</span>' +
    '<div class="item-fields">' +
      '<input class="item-name" value="' + esc(item.name) + '" data-orig-name="' + esc(item.name) + '" placeholder="Client / project name"' +
        ' oninput="markDirty(this.closest(\'.item-row\'))"' +
        ' onkeydown="if(event.key===\'Enter\'){event.preventDefault();saveItem(this.closest(\'.item-row\'))}" />' +
      '<textarea class="item-desc" rows="2" data-orig-desc="' + esc(item.description) + '" placeholder="One-paragraph description"' +
        ' oninput="markDirty(this.closest(\'.item-row\'))">' + esc(item.description) + '</textarea>' +
    '</div>' +
    '<div class="item-actions">' +
      '<button class="item-save" onclick="saveItem(this.closest(\'.item-row\'))">Save</button>' +
      '<button class="item-delete" title="Delete" onclick="deleteItem(' + item.id + ', this)">&#xd7;</button>' +
    '</div>';
  return li;
}

function esc(s) { return String(s).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

async function saveOrder() {
  var ids = Array.from(document.querySelectorAll('#itemList .item-row')).map(function(r) { return parseInt(r.dataset.id); });
  await api({action: 'reorder', ids: ids});
  toast('Order saved');
}

async function api(body) {
  var r = await fetch('/CVwebapp/api/case_studies.php', {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(body)});
  return r.json();
}

var toastTimer;
function toast(msg) {
  var el = document.getElementById('toast');
  el.textContent = msg;
  el.classList.add('show');
  clearTimeout(toastTimer);
  toastTimer = setTimeout(function() { el.classList.remove('show'); }, 2000);
}
</script>
</body>
</html>
