<?php
require __DIR__ . '/../session_guard.php';

if (!is_admin()) {
    header('Location: /CVwebapp/contract_builder/');
    exit;
}

require __DIR__ . '/../db.php';
$pdo  = getDB();
$rows = $pdo->query("SELECT id, email, name, role, password_hash, created_at FROM users ORDER BY role, email")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Users &mdash; CoreVoice</title>
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

    table { width: 100%; border-collapse: collapse; background: var(--white); border-radius: var(--radius); box-shadow: var(--shadow); overflow: hidden; margin-bottom: 28px; }
    thead { background: var(--brand); color: #fff; }
    th, td { padding: 12px 16px; text-align: left; font-size: .88rem; }
    th { font-weight: 600; font-size: .78rem; text-transform: uppercase; letter-spacing: .06em; }
    tr:not(:last-child) td { border-bottom: 1px solid var(--border); }
    tbody tr:hover { background: #f9fafd; }

    .role-badge { display: inline-block; padding: 2px 9px; border-radius: 20px; font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; }
    .role-admin  { background: #fef3c7; color: #92400e; }
    .role-editor { background: #dbeafe; color: #1e40af; }
    .role-viewer { background: #f3f4f6; color: #6b7280; }

    .td-actions { display: flex; gap: 6px; }
    .btn-edit { padding: 4px 12px; border: 1.5px solid var(--border); border-radius: 6px; background: none; color: var(--text); font-size: .78rem; cursor: pointer; font-family: inherit; }
    .btn-edit:hover { border-color: var(--accent); color: var(--accent); }
    .btn-del { padding: 4px 12px; border: 1.5px solid #fecaca; border-radius: 6px; background: none; color: #dc2626; font-size: .78rem; cursor: pointer; font-family: inherit; }
    .btn-del:hover { background: #fef2f2; }

    .add-section { background: var(--white); border: 1.5px solid var(--border); border-radius: var(--radius); padding: 22px; box-shadow: var(--shadow); }
    .add-section h3 { font-size: .9rem; font-weight: 700; margin-bottom: 16px; color: var(--muted); text-transform: uppercase; letter-spacing: .05em; }
    .add-row { display: flex; gap: 10px; flex-wrap: wrap; align-items: flex-end; }
    .add-field { border: 1.5px solid var(--border); border-radius: 8px; padding: 9px 14px; font-size: .88rem; outline: none; font-family: inherit; color: var(--text); background: var(--bg); }
    .add-field:focus { border-color: var(--accent); background: var(--white); }
    .add-field-email { flex: 1; min-width: 200px; }
    .add-field-name  { flex: 1; min-width: 140px; }
    select.add-field { min-width: 120px; }
    .add-btn { padding: 9px 22px; background: var(--accent); color: #fff; border: none; border-radius: 8px; font-size: .85rem; font-weight: 600; cursor: pointer; font-family: inherit; white-space: nowrap; }
    .add-btn:hover { opacity: .9; }
    .field-err { font-size: .78rem; color: #dc2626; margin-top: 6px; display: none; }

    /* Edit modal */
    .overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.4); z-index: 100; align-items: center; justify-content: center; }
    .overlay.open { display: flex; }
    .modal { background: var(--white); border-radius: 12px; padding: 28px 32px; width: 100%; max-width: 420px; box-shadow: 0 8px 40px rgba(0,0,0,.18); }
    .modal h2 { font-size: 1rem; font-weight: 700; margin-bottom: 18px; }
    .modal label { display: block; font-size: .75rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: var(--muted); margin-bottom: 5px; margin-top: 14px; }
    .modal input, .modal select { width: 100%; padding: 9px 12px; border: 1.5px solid var(--border); border-radius: 7px; font-size: .88rem; font-family: inherit; color: var(--text); background: var(--bg); outline: none; }
    .modal input:focus, .modal select:focus { border-color: var(--accent); background: var(--white); }
    .modal-footer { display: flex; gap: 10px; margin-top: 22px; justify-content: flex-end; }
    .btn-cancel { padding: 9px 18px; border: 1.5px solid var(--border); border-radius: 7px; background: none; font-size: .85rem; cursor: pointer; font-family: inherit; }
    .btn-save { padding: 9px 22px; background: var(--brand); color: #fff; border: none; border-radius: 7px; font-size: .85rem; font-weight: 600; cursor: pointer; font-family: inherit; }

    .toast { position: fixed; bottom: 24px; right: 24px; background: var(--brand); color: #fff; padding: 10px 18px; border-radius: 8px; font-size: .85rem; opacity: 0; transition: opacity .3s; pointer-events: none; z-index: 999; }
    .toast.show { opacity: 1; }
  </style>
</head>
<body>
<header>
  <div class="logo">CoreVoice &mdash; Users</div>
  <div class="nav-links">
    <a href="/CVwebapp/admin/case_studies.php">Case Studies</a>
    <a href="/CVwebapp/admin/lists.php">Lists</a>
    <a href="/CVwebapp/contract_builder/">&#x2190; Back to builder</a>
  </div>
</header>

<div class="main">
  <div class="page-title">Users</div>
  <div class="page-sub">Manage who can log in and their role. Only admins can access this page.</div>

  <table id="userTable">
    <thead>
      <tr>
        <th>Email</th>
        <th>Name</th>
        <th>Role</th>
        <th>Password</th>
        <th>Added</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $u): ?>
      <tr data-id="<?= $u['id'] ?>" data-email="<?= htmlspecialchars($u['email']) ?>" data-name="<?= htmlspecialchars($u['name']) ?>" data-role="<?= $u['role'] ?>">
        <td><?= htmlspecialchars($u['email']) ?></td>
        <td><?= htmlspecialchars($u['name'] ?: '&mdash;') ?></td>
        <td><span class="role-badge role-<?= $u['role'] ?>"><?= $u['role'] ?></span></td>
        <td>
          <?php if ($u['password_hash']): ?>
            <span style="color:#16a34a;font-size:.78rem;">&#10003; set</span>
          <?php else: ?>
            <span style="color:#dc2626;font-size:.78rem;">&#9888; not set</span>
          <?php endif; ?>
        </td>
        <td><?= date('d M Y', strtotime($u['created_at'])) ?></td>
        <td>
          <div class="td-actions">
            <button class="btn-edit" onclick="openEdit(this.closest('tr'))">Edit</button>
            <button class="btn-edit" onclick="openSetPw(<?= $u['id'] ?>, '<?= htmlspecialchars($u['email']) ?>')">Set pw</button>
            <button class="btn-del"  onclick="deleteUser(<?= $u['id'] ?>, this.closest('tr'))">Remove</button>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <div class="add-section">
    <h3>Add new user</h3>
    <div class="add-row">
      <input class="add-field add-field-email" id="addEmail" type="email" placeholder="email@corevoice.in" />
      <input class="add-field add-field-name"  id="addName"  type="text"  placeholder="Display name (optional)" />
      <select class="add-field" id="addRole">
        <option value="editor">Editor</option>
        <option value="viewer">Viewer</option>
        <option value="admin">Admin</option>
      </select>
      <button class="add-btn" onclick="addUser()">+ Add user</button>
    </div>
    <div class="field-err" id="addErr"></div>
  </div>
</div>

<!-- Edit modal -->
<div class="overlay" id="editOverlay" onclick="if(event.target===this)closeEdit()">
  <div class="modal">
    <h2>Edit user</h2>
    <label>Email</label>
    <input type="text" id="editEmail" disabled style="color:var(--muted);" />
    <label>Display name</label>
    <input type="text" id="editName" placeholder="Optional" />
    <label>Role</label>
    <select id="editRole">
      <option value="admin">Admin</option>
      <option value="editor">Editor</option>
      <option value="viewer">Viewer</option>
    </select>
    <div class="modal-footer">
      <button class="btn-cancel" onclick="closeEdit()">Cancel</button>
      <button class="btn-save"   onclick="saveEdit()">Save</button>
    </div>
  </div>
</div>

<!-- Set password modal -->
<div class="overlay" id="pwOverlay" onclick="if(event.target===this)closePw()">
  <div class="modal">
    <h2>Set password</h2>
    <label>User</label>
    <input type="text" id="pwEmail" disabled style="color:var(--muted);" />
    <label>New password</label>
    <input type="password" id="pwInput" placeholder="Min 8 characters" />
    <label>Confirm password</label>
    <input type="password" id="pwConfirm" placeholder="Repeat password" />
    <div style="font-size:.75rem;color:#dc2626;margin-top:6px;display:none;" id="pwErr"></div>
    <div class="modal-footer">
      <button class="btn-cancel" onclick="closePw()">Cancel</button>
      <button class="btn-save"   onclick="savePw()">Set password</button>
    </div>
  </div>
</div>

<div class="toast" id="toast"></div>

<script>
var editingId = null;
var pwUserId  = null;

function openEdit(row) {
  editingId = parseInt(row.dataset.id);
  document.getElementById('editEmail').value = row.dataset.email;
  document.getElementById('editName').value  = row.dataset.name;
  document.getElementById('editRole').value  = row.dataset.role;
  document.getElementById('editOverlay').classList.add('open');
}
function closeEdit() {
  document.getElementById('editOverlay').classList.remove('open');
  editingId = null;
}

async function saveEdit() {
  if (!editingId) return;
  var name = document.getElementById('editName').value.trim();
  var role = document.getElementById('editRole').value;
  var r = await api({action: 'update', id: editingId, name: name, role: role});
  if (r.ok) {
    var row = document.querySelector('#userTable tr[data-id="' + editingId + '"]');
    if (row) {
      row.dataset.name = name;
      row.dataset.role = role;
      row.cells[1].textContent = name || '—';
      row.cells[2].innerHTML = '<span class="role-badge role-' + role + '">' + role + '</span>';
    }
    closeEdit();
    toast('Saved');
  } else {
    toast(r.error || 'Save failed');
  }
}

async function addUser() {
  var email = document.getElementById('addEmail').value.trim();
  var name  = document.getElementById('addName').value.trim();
  var role  = document.getElementById('addRole').value;
  var errEl = document.getElementById('addErr');
  errEl.style.display = 'none';
  if (!email) { errEl.textContent = 'Email is required'; errEl.style.display = 'block'; return; }
  var r = await api({action: 'add', email: email, name: name, role: role});
  if (r.ok) {
    var tbody = document.querySelector('#userTable tbody');
    var tr = document.createElement('tr');
    tr.dataset.id    = r.id;
    tr.dataset.email = email;
    tr.dataset.name  = name;
    tr.dataset.role  = role;
    tr.innerHTML =
      '<td>' + esc(email) + '</td>' +
      '<td>' + (name ? esc(name) : '—') + '</td>' +
      '<td><span class="role-badge role-' + role + '">' + role + '</span></td>' +
      '<td><span style="color:#dc2626;font-size:.78rem;">&#9888; not set</span></td>' +
      '<td>Today</td>' +
      '<td><div class="td-actions">' +
        '<button class="btn-edit" onclick="openEdit(this.closest(\'tr\'))">Edit</button>' +
        '<button class="btn-edit" onclick="openSetPw(' + r.id + ', \'' + esc(email) + '\')">Set pw</button>' +
        '<button class="btn-del" onclick="deleteUser(' + r.id + ', this.closest(\'tr\'))">Remove</button>' +
      '</div></td>';
    tbody.appendChild(tr);
    document.getElementById('addEmail').value = '';
    document.getElementById('addName').value  = '';
    document.getElementById('addRole').value  = 'editor';
    toast('User added');
  } else {
    errEl.textContent = r.error || 'Failed to add';
    errEl.style.display = 'block';
  }
}

async function deleteUser(id, row) {
  if (!confirm('Remove this user? They will lose access to the app.')) return;
  var r = await api({action: 'delete', id: id});
  if (r.ok) { row.remove(); toast('Removed'); }
  else toast(r.error || 'Remove failed');
}

async function api(body) {
  var r = await fetch('/CVwebapp/api/users.php', {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(body)});
  return r.json();
}

function esc(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

var toastTimer;
function toast(msg) {
  var el = document.getElementById('toast');
  el.textContent = msg;
  el.classList.add('show');
  clearTimeout(toastTimer);
  toastTimer = setTimeout(function() { el.classList.remove('show'); }, 2500);
}

function openSetPw(id, email) {
  pwUserId = id;
  document.getElementById('pwEmail').value   = email;
  document.getElementById('pwInput').value   = '';
  document.getElementById('pwConfirm').value = '';
  document.getElementById('pwErr').style.display = 'none';
  document.getElementById('pwOverlay').classList.add('open');
}
function closePw() {
  document.getElementById('pwOverlay').classList.remove('open');
  pwUserId = null;
}
async function savePw() {
  var pw  = document.getElementById('pwInput').value;
  var pw2 = document.getElementById('pwConfirm').value;
  var err = document.getElementById('pwErr');
  err.style.display = 'none';
  if (pw.length < 8) { err.textContent = 'Password must be at least 8 characters'; err.style.display = 'block'; return; }
  if (pw !== pw2)    { err.textContent = 'Passwords do not match'; err.style.display = 'block'; return; }
  var r = await api({action: 'set_password', id: pwUserId, password: pw});
  if (r.ok) {
    // Update the password status cell in the table
    var row = document.querySelector('#userTable tr[data-id="' + pwUserId + '"]');
    if (row) row.cells[3].innerHTML = '<span style="color:#16a34a;font-size:.78rem;">&#10003; set</span>';
    closePw();
    toast('Password set');
  } else {
    err.textContent = r.error || 'Failed';
    err.style.display = 'block';
  }
}
</script>
</body>
</html>
