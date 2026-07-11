<?php
session_start();
require __DIR__ . '/ses_config.php';
require_once __DIR__ . '/db.php';

if (!empty($_SESSION['auth_email'])) {
    header('Location: index.php');
    exit;
}

$error  = '';
$method = $_POST['method'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));

    $stmt = getDB()->prepare("SELECT email, role, password_hash FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        $error = 'That email is not authorised.';
    } elseif ($method === 'otp') {
        // OTP path
        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $_SESSION['otp_email']   = $email;
        $_SESSION['otp_code']    = $otp;
        $_SESSION['otp_expires'] = time() + 300;
        $_SESSION['otp_role']    = $user['role'];

        $sent = ses_send(
            $email,
            'Your CoreVoice sign-in code',
            "Your one-time sign-in code is: {$otp}\n\nThis code expires in 5 minutes.\n\nIf you didn't request this, ignore this email."
        );
        if ($sent) {
            header('Location: verify.php');
            exit;
        } else {
            $error = 'Failed to send email. Please try again.';
        }
    } else {
        // Password path
        $password = $_POST['password'] ?? '';
        if (!$user['password_hash']) {
            $error = 'No password set yet. Use "Email me a code" to sign in, then set a password from your account page.';
        } elseif (!password_verify($password, $user['password_hash'])) {
            $error = 'Incorrect password.';
        } else {
            session_regenerate_id(true);
            $_SESSION['auth_email'] = $user['email'];
            $_SESSION['auth_time']  = time();
            $_SESSION['user_role']  = $user['role'];
            header('Location: index.php');
            exit;
        }
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
  <title>CoreVoice &mdash; Sign in</title>
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
    h1 { font-family: Georgia, serif; font-size: 1.4rem; font-weight: 700; color: #1a1a2e; margin-bottom: 8px; }
    .sub { font-size: .85rem; color: #6b7280; margin-bottom: 28px; line-height: 1.6; }
    .error { font-size: .84rem; padding: 11px 14px; border-radius: 6px; margin-bottom: 20px; background: #fef2f2; border: 1px solid #fecaca; color: #dc2626; }
    label { display: block; font-size: .75rem; font-weight: 700; color: #374151; margin-bottom: 6px; text-transform: uppercase; letter-spacing: .07em; }
    input[type=email], input[type=password] {
      width: 100%; padding: 12px 14px;
      border: 1.5px solid #d1d5db; border-radius: 7px;
      font-size: .95rem; color: #1a1a2e; outline: none;
      transition: border-color .15s; margin-bottom: 18px;
      font-family: inherit;
    }
    input:focus { border-color: #C9972A; }
    .btn-primary {
      width: 100%; padding: 13px;
      background: #1a1a2e; color: #fff;
      border: none; border-radius: 7px;
      font-size: .9rem; font-weight: 700;
      cursor: pointer; transition: background .15s; font-family: inherit;
      margin-bottom: 12px;
    }
    .btn-primary:hover { background: #2d2d4e; }
    .divider { display: flex; align-items: center; gap: 10px; margin: 4px 0 12px; color: #9ca3af; font-size: .8rem; }
    .divider::before, .divider::after { content: ''; flex: 1; height: 1px; background: #e5e7eb; }
    .btn-otp {
      width: 100%; padding: 12px;
      background: none; color: #1a1a2e;
      border: 1.5px solid #d1d5db; border-radius: 7px;
      font-size: .88rem; font-weight: 600;
      cursor: pointer; transition: border-color .15s, color .15s; font-family: inherit;
    }
    .btn-otp:hover { border-color: #C9972A; color: #C9972A; }
    <?php if (isset($_GET['expired'])): ?>
    .expired { font-size: .84rem; padding: 11px 14px; border-radius: 6px; margin-bottom: 20px; background: #fff7ed; border: 1px solid #fed7aa; color: #c2410c; }
    <?php endif; ?>
  </style>
</head>
<body>
<div class="card">
  <div class="logo"><span class="cv">Core</span><span class="voice">Voice</span></div>
  <h1>Sign in</h1>
  <p class="sub">Use your password, or get a one-time code by email.</p>

  <?php if (isset($_GET['expired'])): ?>
    <div class="expired">Your code expired. Please sign in again.</div>
  <?php endif; ?>

  <?php if ($error): ?>
    <div class="error"><?= h($error) ?></div>
  <?php endif; ?>

  <form method="POST" id="loginForm">
    <input type="hidden" name="method" id="methodField" value="password" />
    <label for="email">Email address</label>
    <input type="email" id="email" name="email"
           value="<?= h($_POST['email'] ?? '') ?>"
           placeholder="you@corevoice.in"
           required autofocus />
    <div id="pwSection">
      <label for="password">Password</label>
      <input type="password" id="password" name="password"
             placeholder="&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;" />
    </div>
    <button type="submit" class="btn-primary" id="btnPassword">Sign in &rarr;</button>
    <div class="divider">or</div>
    <button type="button" class="btn-otp" onclick="submitOtp()">Email me a one-time code</button>
  </form>
</div>
<script>
function submitOtp() {
  document.getElementById('methodField').value = 'otp';
  document.getElementById('password').removeAttribute('required');
  document.getElementById('loginForm').submit();
}
</script>
</body>
</html>
