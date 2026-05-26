<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (empty($_SESSION['auth_email']) ||
    empty($_SESSION['auth_time'])  ||
    time() - (int)$_SESSION['auth_time'] > 28800) { // 8 hours
    session_unset();
    session_destroy();
    header('Location: login.php');
    exit;
}
