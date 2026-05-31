<?php
require __DIR__ . '/session_guard.php';
$nav_active = 'home';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>CoreVoice — Home</title>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'Segoe UI', system-ui, sans-serif;
      background: #f7f8fc;
      color: #1a1a2e;
    }
    .home-content {
      max-width: 720px;
      padding: 64px 48px;
    }
    .home-content h1 {
      font-family: Georgia, serif;
      font-size: 2rem;
      font-weight: 700;
      color: #1a1a2e;
      margin-bottom: 16px;
      line-height: 1.3;
    }
    .home-content h1 span { color: #C9972A; }
    .home-content p {
      font-size: 1rem;
      color: #6b7280;
      line-height: 1.7;
      margin-bottom: 48px;
    }
    .feature-card {
      background: #fff;
      border: 1px solid #e2e5ef;
      border-radius: 12px;
      padding: 28px 32px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 24px;
      text-decoration: none;
      color: inherit;
      box-shadow: 0 2px 12px rgba(0,0,0,.05);
      transition: box-shadow .15s, border-color .15s;
      max-width: 480px;
    }
    .feature-card:hover {
      box-shadow: 0 4px 24px rgba(0,0,0,.10);
      border-color: #C9972A;
    }
    .feature-card .card-text h2 {
      font-size: 1rem;
      font-weight: 700;
      margin-bottom: 4px;
    }
    .feature-card .card-text p {
      font-size: .83rem;
      color: #6b7280;
      margin: 0;
    }
    .feature-card .card-arrow {
      font-size: 1.4rem;
      color: #C9972A;
      flex-shrink: 0;
    }
  </style>
</head>
<body>
<div class="cv-layout">
  <?php require __DIR__ . '/nav.php'; ?>
  <div class="home-content">
    <h1>Welcome, <span>CVlian</span></h1>
    <p>This is the CV backend — built to help us show up sharper, move faster, and be better every day.</p>

    <a href="/CVwebapp/contracts/" class="feature-card">
      <div class="card-text">
        <h2>Contract Builder</h2>
        <p>Generate proposals and contracts for clients.</p>
      </div>
      <div class="card-arrow">→</div>
    </a>
  </div>
</div>
</body>
</html>
