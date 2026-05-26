<?php
session_start();

const ALLOWED = [
    'amrut@corevoice.in',
    'subhasmita@corevoice.in',
    'nikhil@corevoice.in',
    'piyush@corevoice.in',
];

const APP_PASSWORD = 'ausdf23ucasd';

if (!empty($_SESSION['auth_email'])) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $pass  = $_POST['password'] ?? '';

    if (!in_array($email, ALLOWED, true) || $pass !== APP_PASSWORD) {
        $error = 'Invalid email or password.';
    } else {
        session_regenerate_id(true);
        $_SESSION['auth_email'] = $email;
        $_SESSION['auth_time']  = time();
        header('Location: index.php');
        exit;
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
  <title>CoreVoice — Sign in</title>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'Segoe UI', system-ui, sans-serif;
      background: #f0f2f5;
      min-height: 100vh;
      display: flex; align-items: center; justify-content: center;
      padding: 24px;
    }
    .card {
      background: #fff;
      border-radius: 12px;
      box-shadow: 0 4px 32px rgba(0,0,0,.10);
      width: 100%; max-width: 400px;
      padding: 44px 40px 40px;
    }
    .logo { font-size: 1.2rem; font-weight: 700; margin-bottom: 36px; }
    .logo .cv    { color: #1a1a2e; }
    .logo .voice { color: #C9972A; }
    h1 {
      font-family: Georgia, serif;
      font-size: 1.4rem; font-weight: 700;
      color: #1a1a2e; margin-bottom: 8px;
    }
    .sub { font-size: .85rem; color: #6b7280; margin-bottom: 28px; line-height: 1.6; }
    .error {
      font-size: .84rem; padding: 11px 14px;
      border-radius: 6px; margin-bottom: 20px;
      background: #fef2f2; border: 1px solid #fecaca; color: #dc2626;
    }
    label {
      display: block; font-size: .75rem; font-weight: 700;
      color: #374151; margin-bottom: 6px;
      text-transform: uppercase; letter-spacing: .07em;
    }
    input[type=email],
    input[type=password] {
      width: 100%; padding: 12px 14px;
      border: 1.5px solid #d1d5db; border-radius: 7px;
      font-size: .95rem; color: #1a1a2e; outline: none;
      transition: border-color .15s; margin-bottom: 20px;
      font-family: inherit;
    }
    input:focus { border-color: #C9972A; }
    button[type=submit] {
      width: 100%; padding: 13px;
      background: #1a1a2e; color: #fff;
      border: none; border-radius: 7px;
      font-size: .9rem; font-weight: 700;
      cursor: pointer; transition: background .15s; font-family: inherit;
    }
    button[type=submit]:hover { background: #2d2d4e; }
  </style>
</head>
<body>
<div class="card">
  <div class="logo"><span class="cv">Core</span><span class="voice">Voice</span></div>
  <h1>Sign in</h1>
  <p class="sub">Enter your CoreVoice email and the shared password.</p>

  <?php if ($error): ?>
    <div class="error"><?= h($error) ?></div>
  <?php endif; ?>

  <form method="POST">
    <label for="email">Email address</label>
    <input type="email" id="email" name="email"
           value="<?= h($_POST['email'] ?? '') ?>"
           placeholder="you@corevoice.in"
           required autofocus />
    <label for="password">Password</label>
    <input type="password" id="password" name="password" required />
    <button type="submit">Sign in →</button>
  </form>
</div>
</body>
</html>
