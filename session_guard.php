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

// Backfill user_role for sessions created before the users table existed
if (empty($_SESSION['user_role'])) {
    require_once __DIR__ . '/db.php';
    $stmt = getDB()->prepare("SELECT role FROM users WHERE email = ?");
    $stmt->execute([$_SESSION['auth_email']]);
    $r = $stmt->fetch();
    $_SESSION['user_role'] = $r ? $r['role'] : 'editor';
}

function current_role(): string { return $_SESSION['user_role'] ?? 'editor'; }
function is_admin(): bool { return current_role() === 'admin'; }

function has_module_access(string $moduleKey): bool {
    if (is_admin()) return true;
    require_once __DIR__ . '/db.php';
    $stmt = getDB()->prepare(
        "SELECT 1 FROM module_access ma JOIN users u ON u.id = ma.user_id
         WHERE u.email = ? AND ma.module_key = ?"
    );
    $stmt->execute([$_SESSION['auth_email'], $moduleKey]);
    return (bool)$stmt->fetch();
}

function require_module_access(string $moduleKey): void {
    if (!has_module_access($moduleKey)) {
        header('Location: /CVwebapp/index.php');
        exit;
    }
}
