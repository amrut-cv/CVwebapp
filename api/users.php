<?php
require __DIR__ . '/../session_guard.php';
require __DIR__ . '/../db.php';

header('Content-Type: application/json');

if (!is_admin()) {
    http_response_code(403); echo json_encode(['error' => 'Admin only']); exit;
}

$pdo = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $rows = $pdo->query("SELECT id, email, name, role, created_at FROM users ORDER BY role, email")->fetchAll();
    echo json_encode($rows);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); echo json_encode(['error' => 'Method not allowed']); exit;
}

$body   = json_decode(file_get_contents('php://input'), true);
$action = $body['action'] ?? '';

if ($action === 'add') {
    $email = strtolower(trim($body['email'] ?? ''));
    $name  = trim($body['name'] ?? '');
    $role  = in_array($body['role'] ?? '', ['admin','editor','viewer']) ? $body['role'] : 'editor';
    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400); echo json_encode(['error' => 'Invalid email']); exit;
    }
    try {
        $pdo->prepare("INSERT INTO users (email, name, role) VALUES (?,?,?)")->execute([$email, $name, $role]);
        echo json_encode(['ok' => true, 'id' => (int)$pdo->lastInsertId()]);
    } catch (PDOException $e) {
        http_response_code(409); echo json_encode(['error' => 'Email already exists']);
    }
    exit;
}

if ($action === 'update') {
    $id   = (int)($body['id'] ?? 0);
    $name = trim($body['name'] ?? '');
    $role = in_array($body['role'] ?? '', ['admin','editor','viewer']) ? $body['role'] : 'editor';
    if (!$id) { http_response_code(400); echo json_encode(['error' => 'Missing id']); exit; }

    // Prevent stripping the last admin
    if ($role !== 'admin') {
        $cur = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $cur->execute([$id]);
        $cur = $cur->fetch();
        if ($cur && $cur['role'] === 'admin') {
            $adminCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
            if ($adminCount <= 1) {
                http_response_code(400); echo json_encode(['error' => 'Cannot demote the last admin']); exit;
            }
        }
    }

    $pdo->prepare("UPDATE users SET name = ?, role = ? WHERE id = ?")->execute([$name, $role, $id]);
    echo json_encode(['ok' => true]);
    exit;
}

if ($action === 'delete') {
    $id = (int)($body['id'] ?? 0);
    if (!$id) { http_response_code(400); echo json_encode(['error' => 'Missing id']); exit; }

    $cur = $pdo->prepare("SELECT role, email FROM users WHERE id = ?");
    $cur->execute([$id]);
    $cur = $cur->fetch();
    if ($cur && $cur['role'] === 'admin') {
        $adminCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
        if ($adminCount <= 1) {
            http_response_code(400); echo json_encode(['error' => 'Cannot delete the last admin']); exit;
        }
    }
    // Prevent self-delete
    if ($cur && $cur['email'] === $_SESSION['auth_email']) {
        http_response_code(400); echo json_encode(['error' => 'Cannot delete yourself']); exit;
    }

    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(400); echo json_encode(['error' => 'Unknown action']);
