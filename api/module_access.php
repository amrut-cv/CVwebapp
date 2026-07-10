<?php
require __DIR__ . '/../session_guard.php';
require __DIR__ . '/../db.php';

header('Content-Type: application/json');

if (!is_admin()) {
    http_response_code(403); echo json_encode(['error' => 'Admin only']); exit;
}

$pdo = getDB();
$modules = require __DIR__ . '/../modules.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); echo json_encode(['error' => 'Method not allowed']); exit;
}

$body       = json_decode(file_get_contents('php://input'), true);
$action     = $body['action'] ?? '';
$userId     = (int)($body['user_id'] ?? 0);
$moduleKey  = $body['module_key'] ?? '';

if (!$userId || !isset($modules[$moduleKey])) {
    http_response_code(400); echo json_encode(['error' => 'Missing or invalid fields']); exit;
}

if ($action === 'grant') {
    $pdo->prepare("INSERT IGNORE INTO module_access (user_id, module_key) VALUES (?,?)")
        ->execute([$userId, $moduleKey]);
    echo json_encode(['ok' => true]);
    exit;
}

if ($action === 'revoke') {
    $pdo->prepare("DELETE FROM module_access WHERE user_id = ? AND module_key = ?")
        ->execute([$userId, $moduleKey]);
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(400); echo json_encode(['error' => 'Unknown action']);
