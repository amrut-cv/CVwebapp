<?php
require __DIR__ . '/session_guard.php';
require __DIR__ . '/db.php';

$pdo   = getDB();
$email = $_SESSION['auth_email'];
$user  = $pdo->prepare("SELECT id, name, role, password_hash FROM users WHERE email = ?");
$user->execute([$email]);
$user  = $user->fetch();

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = $_POST['current_password'] ?? '';
    $new     = $_POST['new_password']     ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (strlen($new) < 8) {
        $error = 'New password must be at least 8 characters.';
    } elseif ($new !== $confirm) {
        $error = 'Passwords do not match.';
    } elseif ($user['password_hash'] && !password_verify($current, $user['password_hash'])) {
        $error = 'Current password is incorrect.';
    } else {
        $hash = password_hash($new, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET password_hash = ? WHERE email = ?")->execute([$hash, $email]);
        $user['password_hash'] = $hash;
        $success = 'Password updated successfully.';
    }
}

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>My account &mdash; CoreVoice</title>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Segoe UI', system-ui, sans-serif; background: #f0f2f5; min-height: 100vh; }
    header { background: #1a1a2e; color: #fff; padding: 0 32px; height: 56px; display: flex; align-items: center; justify-content: space-between; }
    header .logo { font-weight: 700; font-size: 1rem; }
    header .logo span { color: #C9972A; }
    header a { color: rgba(255,255,255,.7); text-decoration: none; font-size: .85rem; }
    header a:hover { color: #fff; }
    .main { max-width: 480px; margin: 48px auto; padding: 0 24px; }
    .card { background: #fff; border-radius: 12px; box-shadow: 0 2px 16px rgba(0,0,0,.08); padding: 36px 40px; }
    h1 { font-size: 1.2rem; font-weight: 700; color: #1a1a2e; margin-bottom: 4px; }
    .who { font-size: .85rem; color: #6b7280; margin-bottom: 28px; }
    .role-badge { display: inline-block; padding: 2px 9px; border-radius: 20px; font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; background: #fef3c7; color: #92400e; }
    .role-badge.editor { background: #dbeafe; color: #1e40af; }
    .role-badge.viewer { background: #f3f4f6; color: #6b7280; }
    h2 { font-size: .9rem; font-weight: 700; color: #374151; margin-bottom: 18px; padding-bottom: 10px; border-bottom: 1px solid #e5e7eb; }
    label { display: block; font-size: .75rem; font-weight: 700; color: #374151; margin-bottom: 5px; margin-top: 14px; text-transform: uppercase; letter-spacing: .06em; }
    input[type=password] {
      width: 100%; padding: 10px 13px;
      border: 1.5px solid #d1d5db; border-radius: 7px;
      font-size: .92rem; color: #1a1a2e; outline: none;
      transition: border-color .15s; font-family: inherit;
    }
    input:focus { border-color: #C9972A; }
    .hint { font-size: .75rem; color: #9ca3af; margin-top: 5px; }
    .btn { margin-top: 22px; width: 100%; padding: 12px; background: #1a1a2e; color: #fff; border: none; border-radius: 7px; font-size: .9rem; font-weight: 700; cursor: pointer; font-family: inherit; }
    .btn:hover { background: #2d2d4e; }
    .alert { padding: 11px 14px; border-radius: 7px; margin-bottom: 20px; font-size: .84rem; }
    .alert-error   { background: #fef2f2; border: 1px solid #fecaca; color: #dc2626; }
    .alert-success { background: #f0fdf4; border: 1px solid #bbf7d0; color: #16a34a; }
  </style>
</head>
<body>
<header>
  <div class="logo">Core<span>Voice</span></div>
  <a href="/CVwebapp/contract_builder/">&#x2190; Back to builder</a>
</header>

<div class="main">
  <div class="card">
    <h1>My account</h1>
    <p class="who">
      <?= h($email) ?> &nbsp;
      <span class="role-badge <?= h($user['role']) ?>"><?= h($user['role']) ?></span>
    </p>

    <h2><?= $user['password_hash'] ? 'Change password' : 'Set a password' ?></h2>

    <?php if ($error): ?>
      <div class="alert alert-error"><?= h($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
      <div class="alert alert-success"><?= h($success) ?></div>
    <?php endif; ?>

    <form method="POST">
      <?php if ($user['password_hash']): ?>
      <label for="current_password">Current password</label>
      <input type="password" id="current_password" name="current_password" required />
      <?php endif; ?>

      <label for="new_password">New password</label>
      <input type="password" id="new_password" name="new_password" required />
      <div class="hint">At least 8 characters.</div>

      <label for="confirm_password">Confirm new password</label>
      <input type="password" id="confirm_password" name="confirm_password" required />

      <button class="btn" type="submit">
        <?= $user['password_hash'] ? 'Update password' : 'Set password' ?> &rarr;
      </button>
    </form>
  </div>
</div>
</body>
</html>
