<?php
require __DIR__ . '/session_guard.php';
require __DIR__ . '/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); echo json_encode(['error' => 'Method not allowed']); exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$id    = (int)($input['id'] ?? 0);

if (!$id) {
    http_response_code(400); echo json_encode(['error' => 'Missing id']); exit;
}

try {
    $pdo = getDB();
    $pdo->prepare("DELETE FROM drafts WHERE id=? AND email=?")->execute([$id, $_SESSION['auth_email']]);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    error_log('CVwebapp delete_draft: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
