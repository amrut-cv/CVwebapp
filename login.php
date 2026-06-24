<?php
session_start();
require __DIR__ . '/ses_config.php';
require __DIR__ . '/db.php';

if (!empty($_SESSION['auth_email'])) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));

    $stmt = getDB()->prepare("SELECT email, role FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        $error = 'That email is not authorised.';
    } else {
        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $_SESSION['otp_email']   = $email;
        $_SESSION['otp_code']    = $otp;
        $_SESSION['otp_expires'] = time() + 300; // 5 minutes
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
            $error = 'Failed to send OTP. Please try again.';
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
    input[type=email] {
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
  <p class="sub">Enter your CoreVoice email and we'll send you a one-time code.</p>

  <?php if ($error): ?>
    <div class="error"><?= h($error) ?></div>
  <?php endif; ?>

  <form method="POST">
    <label for="email">Email address</label>
    <input type="email" id="email" name="email"
           value="<?= h($_POST['email'] ?? '') ?>"
           placeholder="you@corevoice.in"
           required autofocus />
    <button type="submit">Send code →</button>
  </form>
</div>
</body>
</html>
