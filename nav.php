<?php
// nav.php — shared left sidebar. $nav_active should be set before including ('home' or 'contracts').
if (!isset($nav_active)) $nav_active = '';
$email = htmlspecialchars($_SESSION['auth_email'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
$base  = '/CVwebapp';
?>
<style>
  .cv-layout { display: flex; min-height: 100vh; }
  .cv-nav {
    width: 220px; flex-shrink: 0;
    background: #1a1a2e; color: #fff;
    display: flex; flex-direction: column;
    position: fixed; top: 0; left: 0; bottom: 0;
    z-index: 100;
  }
  .cv-nav .nav-logo {
    font-size: 1.1rem; font-weight: 700;
    padding: 28px 24px 24px;
    border-bottom: 1px solid rgba(255,255,255,.08);
  }
  .cv-nav .nav-logo .cv    { color: #fff; }
  .cv-nav .nav-logo .voice { color: #C9972A; }
  .cv-nav nav { flex: 1; padding: 16px 0; }
  .cv-nav nav a {
    display: flex; align-items: center; gap: 10px;
    padding: 10px 24px;
    color: rgba(255,255,255,.65);
    text-decoration: none;
    font-size: .875rem;
    border-left: 3px solid transparent;
    transition: color .15s, background .15s;
  }
  .cv-nav nav a:hover { color: #fff; background: rgba(255,255,255,.06); }
  .cv-nav nav a.active {
    color: #fff; background: rgba(201,151,42,.12);
    border-left-color: #C9972A;
  }
  .cv-nav nav a svg { flex-shrink: 0; opacity: .7; }
  .cv-nav nav a.active svg { opacity: 1; }
  .cv-nav .nav-footer {
    padding: 16px 24px;
    font-size: .72rem; color: rgba(255,255,255,.4);
    border-top: 1px solid rgba(255,255,255,.08);
    line-height: 1.6;
  }
  .cv-nav .nav-footer a { color: rgba(255,255,255,.4); text-decoration: underline; }
  .cv-main { margin-left: 220px; flex: 1; min-width: 0; }
</style>

<div class="cv-nav">
  <div class="nav-logo"><span class="cv">Core</span><span class="voice">Voice</span></div>
  <nav>
    <a href="<?= $base ?>/index.php" class="<?= $nav_active === 'home' ? 'active' : '' ?>">
      <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
      Home
    </a>
    <a href="<?= $base ?>/contract_builder/" class="<?= $nav_active === 'contracts' ? 'active' : '' ?>">
      <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
      Contract Builder
    </a>
    <a href="<?= $base ?>/cashflow_status/" class="<?= $nav_active === 'cashflow' ? 'active' : '' ?>">
      <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
      Cashflow Status
    </a>
    <a href="<?= $base ?>/deal_tracker/" class="<?= $nav_active === 'deals' ? 'active' : '' ?>">
      <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/></svg>
      Deal Tracker
    </a>
    <a href="<?= $base ?>/guest_tracker/" class="<?= $nav_active === 'guests' ? 'active' : '' ?>">
      <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" y1="19" x2="12" y2="23"/><line x1="8" y1="23" x2="16" y2="23"/></svg>
      Guest Tracker
    </a>
    <a href="<?= $base ?>/network_graph/" class="<?= $nav_active === 'network' ? 'active' : '' ?>">
      <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="5" r="2.5"/><circle cx="5" cy="19" r="2.5"/><circle cx="19" cy="19" r="2.5"/><line x1="12" y1="7.5" x2="6.3" y2="17"/><line x1="12" y1="7.5" x2="17.7" y2="17"/><line x1="7.5" y1="19" x2="16.5" y2="19"/></svg>
      Network Graph
    </a>
    <?php if (function_exists('is_admin') && is_admin()): ?>
    <a href="<?= $base ?>/admin/users.php" class="<?= $nav_active === 'users' ? 'active' : '' ?>">
      <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
      Users
    </a>
    <?php endif ?>
  </nav>
  <div class="nav-footer">
    <?= $email ?><br>
    <a href="<?= $base ?>/account.php">Account</a> &middot;
    <a href="<?= $base ?>/logout.php">Sign out</a>
  </div>
</div>
<div class="cv-main">
