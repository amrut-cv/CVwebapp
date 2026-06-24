<?php
session_start();

if (!empty($_SESSION['auth_email'])) {
    header('Location: index.php');
    exit;
}

if (empty($_SESSION['otp_email']) || empty($_SESSION['otp_code']) || empty($_SESSION['otp_expires'])) {
    header('Location: login.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $entered = trim($_POST['otp'] ?? '');

    if (time() > $_SESSION['otp_expires']) {
        session_unset();
        header('Location: login.php?expired=1');
        exit;
    }

    if (!hash_equals($_SESSION['otp_code'], $entered)) {
        $error = 'Incorrect code. Please try again.';
    } else {
        $email = $_SESSION['otp_email'];
        $role  = $_SESSION['otp_role'] ?? 'editor';
        session_unset();
        session_regenerate_id(true);
        $_SESSION['auth_email'] = $email;
        $_SESSION['auth_time']  = time();
        $_SESSION['user_role']  = $role;
        header('Location: index.php');
        exit;
    }
}

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

$email = $_SESSION['otp_email'];
$expires_in = max(0, $_SESSION['otp_expires'] - time());
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>CoreVoice — Enter code</title>
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
    input[type=text] {
      width: 100%; padding: 12px 14px;
      border: 1.5px solid #d1d5db; border-radius: 7px;
      font-size: 1.4rem; color: #1a1a2e; outline: none;
      transition: border-color .15s; margin-bottom: 20px;
      font-family: inherit; letter-spacing: .25em; text-align: center;
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
    .back { margin-top: 18px; text-align: center; font-size: .82rem; color: #6b7280; }
    .back a { color: #C9972A; text-decoration: none; }
    .timer { font-size: .78rem; color: #9ca3af; margin-bottom: 20px; }
  </style>
</head>
<body>
<div class="card">
  <div class="logo"><span class="cv">Core</span><span class="voice">Voice</span></div>
  <h1>Check your email</h1>
  <p class="sub">We sent a 6-digit code to <strong><?= h($email) ?></strong>.</p>
  <p class="timer">Code expires in <span id="countdown"><?= $expires_in ?></span>s</p>

  <?php if ($error): ?>
    <div class="error"><?= h($error) ?></div>
  <?php endif; ?>

  <form method="POST">
    <label for="otp">One-time code</label>
    <input type="text" id="otp" name="otp"
           maxlength="6" pattern="\d{6}"
           placeholder="000000"
           inputmode="numeric"
           autocomplete="one-time-code"
           required autofocus />
    <button type="submit">Verify →</button>
  </form>
  <p class="back"><a href="login.php">← Use a different email</a></p>
</div>
<script>
  let t = <?= $expires_in ?>;
  const el = document.getElementById('countdown');
  const iv = setInterval(() => {
    t--;
    if (t <= 0) { clearInterval(iv); el.textContent = '0'; }
    else el.textContent = t;
  }, 1000);
</script>
</body>
</html>
