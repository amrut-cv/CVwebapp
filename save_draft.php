<?php
require __DIR__ . '/session_guard.php';
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); echo json_encode(['error' => 'Method not allowed']); exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400); echo json_encode(['error' => 'Invalid JSON']); exit;
}

$email = $_SESSION['auth_email'];
$id    = isset($input['id']) ? (int)$input['id'] : null;
$name  = trim($input['name'] ?? 'Untitled') ?: 'Untitled';
$data  = json_encode($input['data'] ?? []);

try {
    $pdo = getDB();
    if ($id) {
        $stmt = $pdo->prepare("UPDATE drafts SET name=?, data=?, updated_at=NOW() WHERE id=?");
        $stmt->execute([$name, $data, $id]);
        echo json_encode(['success' => true, 'id' => $id]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO drafts (email, name, data) VALUES (?, ?, ?)");
        $stmt->execute([$email, $name, $data]);
        echo json_encode(['success' => true, 'id' => (int)$pdo->lastInsertId()]);
    }
} catch (Exception $e) {
    error_log('CVwebapp save_draft: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
