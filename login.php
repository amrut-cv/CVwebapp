<?php
session_start();

if (!file_exists(__DIR__ . '/db_config.php')) {
    die('<pre style="font-family:monospace;padding:32px;color:#dc2626">db_config.php not found.<br>Run on server: cp db_config.example.php db_config.php<br>Then set DB_PASS to your MySQL password.</pre>');
}
require __DIR__ . '/db_config.php';

const ALLOWED_EMAILS = [
    'amrut@corevoice.in',
    'subhasmita@corevoice.in',
    'nikhil@corevoice.in',
    'piyush@corevoice.in',
];

// ── DB: connect + auto-create table ─────────────────────────
function db(): PDO {
    static $pdo;
    if ($pdo) return $pdo;
    $pdo = new PDO('mysql:host=' . DB_HOST . ';charset=utf8mb4', DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `" . DB_NAME . "`");
    $pdo->exec("CREATE TABLE IF NOT EXISTS `otp_tokens` (
        `id`         INT AUTO_INCREMENT PRIMARY KEY,
        `email`      VARCHAR(255) NOT NULL,
        `otp`        CHAR(6)      NOT NULL,
        `expires_at` DATETIME     NOT NULL,
        `used`       TINYINT(1)   DEFAULT 0,
        `created_at` TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_email` (`email`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    // clean up old tokens
    $pdo->exec("DELETE FROM otp_tokens WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)");
    return $pdo;
}

// ── Send OTP email ───────────────────────────────────────────
function sendOtpEmail(string $to, string $otp): void {
    $subject = 'Your CoreVoice login code: ' . $otp;
    $body    = '<!DOCTYPE html><html><body style="margin:0;padding:32px;font-family:\'Segoe UI\',sans-serif;background:#f7f8fc">'
        . '<div style="max-width:420px;margin:0 auto;background:#fff;border:1px solid #e2e5ef;border-radius:10px;padding:36px">'
        . '<div style="font-size:1.1rem;font-weight:700;margin-bottom:28px">'
        . '<span style="color:#1a1a2e">Core</span><span style="color:#C9972A">Voice</span>'
        . '</div>'
        . '<p style="margin:0 0 8px;font-size:.85rem;color:#6b7280;text-transform:uppercase;letter-spacing:.08em;font-weight:600">Your login code</p>'
        . '<div style="font-size:2.2rem;font-weight:700;letter-spacing:.3em;color:#1a1a2e;padding:18px 24px;background:#f4f4f8;border-radius:6px;display:inline-block;margin-bottom:24px">'
        . $otp
        . '</div>'
        . '<p style="margin:0;color:#9ca3af;font-size:.8rem;line-height:1.6">Expires in 10 minutes.<br>If you didn\'t request this, you can ignore it.</p>'
        . '</div></body></html>';
    $headers = implode("\r\n", [
        'From: CoreVoice <noreply@corevoice.in>',
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'X-Mailer: CoreVoice-Auth',
    ]);
    mail($to, $subject, $body, $headers);
}

// ── Already signed in → go to app ───────────────────────────
if (!empty($_SESSION['auth_email'])) {
    header('Location: index.php');
    exit;
}

$step  = 'email';
$email = '';
$error = '';

// ── Handle POST ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'send_otp') {
        $email = strtolower(trim($_POST['email'] ?? ''));
        if (!in_array($email, ALLOWED_EMAILS, true)) {
            $error = 'This email address is not authorised.';
        } else {
            try {
                $pdo = db();
                // rate-limit: max 3 requests per email per 5 minutes
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM otp_tokens WHERE email=? AND created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)");
                $stmt->execute([$email]);
                if ((int)$stmt->fetchColumn() >= 3) {
                    $error = 'Too many attempts. Please wait a few minutes before trying again.';
                } else {
                    $otp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                    $pdo->prepare("INSERT INTO otp_tokens (email, otp, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE))")
                        ->execute([$email, $otp]);
                    sendOtpEmail($email, $otp);
                    $_SESSION['pending_email'] = $email;
                    $step = 'otp';
                }
            } catch (Exception $e) {
                error_log('CVwebapp login send_otp: ' . $e->getMessage());
                $error = 'Database error: ' . $e->getMessage();
            }
        }

    } elseif ($action === 'verify_otp') {
        $email = $_SESSION['pending_email'] ?? '';
        $otp   = preg_replace('/\D/', '', $_POST['otp'] ?? '');
        $step  = 'otp';
        if (!$email || !in_array($email, ALLOWED_EMAILS, true)) {
            $step = 'email';
            $error = 'Session expired. Please start again.';
        } elseif (strlen($otp) !== 6) {
            $error = 'Please enter the 6-digit code sent to your email.';
        } else {
            try {
                $pdo  = db();
                $stmt = $pdo->prepare("SELECT id FROM otp_tokens WHERE email=? AND otp=? AND expires_at>NOW() AND used=0 ORDER BY id DESC LIMIT 1");
                $stmt->execute([$email, $otp]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$row) {
                    $error = 'Incorrect or expired code. Please try again.';
                } else {
                    $pdo->prepare("UPDATE otp_tokens SET used=1 WHERE id=?")->execute([$row['id']]);
                    session_regenerate_id(true);
                    $_SESSION['auth_email'] = $email;
                    $_SESSION['auth_time']  = time();
                    unset($_SESSION['pending_email']);
                    header('Location: index.php');
                    exit;
                }
            } catch (Exception $e) {
                error_log('CVwebapp login verify_otp: ' . $e->getMessage());
                $error = 'Database error: ' . $e->getMessage();
            }
        }

    } elseif ($action === 'resend_otp') {
        // Treat as a fresh send_otp for the pending email
        $email = $_SESSION['pending_email'] ?? '';
        if ($email && in_array($email, ALLOWED_EMAILS, true)) {
            $_POST['email']   = $email;
            $_POST['action']  = 'send_otp';
            // recurse by redirect-to-self with email pre-filled
            header('Location: login.php');
            exit;
        }
        $step = 'email';
    }

} elseif (!empty($_SESSION['pending_email'])) {
    $email = $_SESSION['pending_email'];
    $step  = 'otp';
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
    .logo {
      font-size: 1.2rem; font-weight: 700; margin-bottom: 36px;
    }
    .logo .cv    { color: #1a1a2e; }
    .logo .voice { color: #C9972A; }
    h1 {
      font-family: Georgia, serif;
      font-size: 1.4rem; font-weight: 700;
      color: #1a1a2e; margin-bottom: 8px;
    }
    .sub {
      font-size: .85rem; color: #6b7280;
      margin-bottom: 28px; line-height: 1.6;
    }
    .notice {
      font-size: .84rem; padding: 11px 14px;
      border-radius: 6px; margin-bottom: 20px; line-height: 1.5;
    }
    .notice.error   { background: #fef2f2; border: 1px solid #fecaca; color: #dc2626; }
    .notice.success { background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; }
    label {
      display: block; font-size: .75rem; font-weight: 700;
      color: #374151; margin-bottom: 6px;
      text-transform: uppercase; letter-spacing: .07em;
    }
    input[type=email],
    input[type=text] {
      width: 100%; padding: 12px 14px;
      border: 1.5px solid #d1d5db; border-radius: 7px;
      font-size: .95rem; color: #1a1a2e; outline: none;
      transition: border-color .15s;
      margin-bottom: 20px;
    }
    input:focus { border-color: #C9972A; }
    input.otp-input {
      font-size: 1.6rem; font-weight: 700;
      letter-spacing: .3em; text-align: center;
    }
    .btn-primary {
      width: 100%; padding: 13px;
      background: #1a1a2e; color: #fff;
      border: none; border-radius: 7px;
      font-size: .9rem; font-weight: 700;
      cursor: pointer; transition: background .15s;
      font-family: inherit;
    }
    .btn-primary:hover { background: #2d2d4e; }
    .btn-link {
      background: none; border: none;
      width: 100%; margin-top: 14px; padding: 6px;
      font-size: .8rem; color: #9ca3af;
      cursor: pointer; text-align: center;
      font-family: inherit;
    }
    .btn-link:hover { color: #6b7280; }
  </style>
</head>
<body>
<div class="card">
  <div class="logo"><span class="cv">Core</span><span class="voice">Voice</span></div>

  <?php if ($step === 'email'): ?>

    <h1>Sign in</h1>
    <p class="sub">Enter your CoreVoice email and we'll send you a one-time login code.</p>

    <?php if ($error): ?>
      <div class="notice error"><?= h($error) ?></div>
    <?php endif; ?>

    <form method="POST">
      <input type="hidden" name="action" value="send_otp" />
      <label for="email">Email address</label>
      <input type="email" id="email" name="email"
             value="<?= h($email) ?>"
             placeholder="you@corevoice.in"
             required autofocus />
      <button type="submit" class="btn-primary">Send login code →</button>
    </form>

  <?php else: ?>

    <h1>Enter your code</h1>
    <div class="notice success">Code sent to <?= h($email) ?></div>

    <?php if ($error): ?>
      <div class="notice error"><?= h($error) ?></div>
    <?php endif; ?>

    <form method="POST">
      <input type="hidden" name="action" value="verify_otp" />
      <label for="otp">6-digit code</label>
      <input type="text" id="otp" name="otp"
             class="otp-input"
             maxlength="6" pattern="\d{6}"
             placeholder="000000"
             inputmode="numeric"
             autocomplete="one-time-code"
             required autofocus />
      <button type="submit" class="btn-primary">Verify →</button>
    </form>

    <form method="POST">
      <input type="hidden" name="action" value="send_otp" />
      <input type="hidden" name="email" value="<?= h($email) ?>" />
      <button type="submit" class="btn-link">Resend code</button>
    </form>

  <?php endif; ?>
</div>
</body>
</html>
