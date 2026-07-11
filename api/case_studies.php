<?php
require __DIR__ . '/../session_guard.php';
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json');

$pdo = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $rows = $pdo->query("SELECT id, name, description, sort_order FROM case_studies ORDER BY sort_order, id")->fetchAll();
    echo json_encode($rows);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); echo json_encode(['error' => 'Method not allowed']); exit;
}

$body   = json_decode(file_get_contents('php://input'), true);
$action = $body['action'] ?? '';

switch ($action) {
    case 'add':
        $name = trim($body['name'] ?? '');
        $desc = trim($body['description'] ?? '');
        if (!$name) { http_response_code(400); echo json_encode(['error' => 'Name required']); exit; }
        $stmt = $pdo->prepare("SELECT COALESCE(MAX(sort_order),0)+10 FROM case_studies");
        $stmt->execute();
        $order = (int)$stmt->fetchColumn();
        $stmt  = $pdo->prepare("INSERT INTO case_studies (name, description, sort_order) VALUES (?,?,?)");
        $stmt->execute([$name, $desc, $order]);
        echo json_encode(['id' => (int)$pdo->lastInsertId(), 'name' => $name, 'description' => $desc, 'sort_order' => $order]);
        break;

    case 'update':
        $id   = (int)($body['id'] ?? 0);
        $name = trim($body['name'] ?? '');
        $desc = trim($body['description'] ?? '');
        if (!$id || !$name) { http_response_code(400); echo json_encode(['error' => 'Missing fields']); exit; }
        $pdo->prepare("UPDATE case_studies SET name=?, description=? WHERE id=?")->execute([$name, $desc, $id]);
        echo json_encode(['ok' => true]);
        break;

    case 'delete':
        $id = (int)($body['id'] ?? 0);
        if (!$id) { http_response_code(400); echo json_encode(['error' => 'Missing id']); exit; }
        $pdo->prepare("DELETE FROM case_studies WHERE id=?")->execute([$id]);
        echo json_encode(['ok' => true]);
        break;

    case 'reorder':
        $ids = $body['ids'] ?? [];
        if (!is_array($ids)) { http_response_code(400); echo json_encode(['error' => 'ids must be array']); exit; }
        $stmt = $pdo->prepare("UPDATE case_studies SET sort_order=? WHERE id=?");
        foreach ($ids as $i => $id) {
            $stmt->execute([($i + 1) * 10, (int)$id]);
        }
        echo json_encode(['ok' => true]);
        break;

    default:
        http_response_code(400); echo json_encode(['error' => 'Unknown action']);
}
