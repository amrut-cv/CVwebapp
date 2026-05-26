<?php
require __DIR__ . '/session_guard.php';

$signout = '<div style="position:fixed;bottom:20px;left:20px;z-index:9999;'
    . 'font-family:\'Segoe UI\',sans-serif;font-size:.72rem;'
    . 'background:#fff;border:1px solid #e2e5ef;border-radius:6px;'
    . 'padding:6px 12px;color:#6b7280;box-shadow:0 2px 8px rgba(0,0,0,.07)">'
    . htmlspecialchars($_SESSION['auth_email'], ENT_QUOTES | ENT_HTML5, 'UTF-8')
    . '&ensp;&middot;&ensp;<a href="logout.php" style="color:#6b7280;text-decoration:underline">Sign out</a>'
    . '</div>';

$html = file_get_contents(__DIR__ . '/index.html');
echo preg_replace_callback('/(<body[^>]*>)/i', function ($m) use ($signout) {
    return $m[1] . $signout;
}, $html, 1);
