<?php
require __DIR__ . '/session_guard.php';
require __DIR__ . '/db.php';

header('Content-Type: application/json');

try {
    $pdo  = getDB();
    $stmt = $pdo->prepare("SELECT id, name, updated_at, data FROM drafts WHERE email=? ORDER BY updated_at DESC");
    $stmt->execute([$_SESSION['auth_email']]);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$r) {
        $r['data'] = json_decode($r['data'], true);
    }
    echo json_encode($rows);
} catch (Exception $e) {
    error_log('CVwebapp load_drafts: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
