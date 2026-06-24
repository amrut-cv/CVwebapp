<?php
require __DIR__ . '/../session_guard.php';
require __DIR__ . '/../db.php';

header('Content-Type: application/json');

$email = $_SESSION['auth_email'];
$pdo   = getDB();

// GET — list this user's contracts
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->prepare(
        "SELECT id, client_name AS name, status, updated_at
         FROM contracts WHERE owner_email = ? ORDER BY updated_at DESC"
    );
    $stmt->execute([$email]);
    echo json_encode($stmt->fetchAll());
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); echo json_encode(['error' => 'Method not allowed']); exit;
}

$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) { http_response_code(400); echo json_encode(['error' => 'Bad request']); exit; }

$action = $body['action'] ?? '';

if ($action === 'save') {
    $id   = isset($body['id']) ? (int)$body['id'] : 0;
    $name = trim((string)($body['name'] ?? '')) ?: 'Untitled';
    $data = json_encode($body['data'] ?? [], JSON_UNESCAPED_UNICODE);

    if ($id) {
        $check = $pdo->prepare("SELECT id FROM contracts WHERE id=? AND owner_email=?");
        $check->execute([$id, $email]);
        if ($check->fetch()) {
            $pdo->prepare("UPDATE contracts SET client_name=?, data=? WHERE id=?")
                ->execute([$name, $data, $id]);
        } else {
            $id = 0; // ownership fail or deleted — fall through to insert
        }
    }
    if (!$id) {
        $stmt = $pdo->prepare(
            "INSERT INTO contracts (owner_email, client_name, data) VALUES (?,?,?)"
        );
        $stmt->execute([$email, $name, $data]);
        $id = (int)$pdo->lastInsertId();
    }

    $row = $pdo->prepare("SELECT id, client_name AS name, status, updated_at FROM contracts WHERE id=?");
    $row->execute([$id]);
    $r = $row->fetch();
    echo json_encode(['ok' => true, 'id' => $r['id'], 'name' => $r['name'], 'updated_at' => $r['updated_at']]);
    exit;
}

if ($action === 'load') {
    $id = (int)($body['id'] ?? 0);
    if (!$id) { http_response_code(400); echo json_encode(['error' => 'Missing id']); exit; }
    $stmt = $pdo->prepare(
        "SELECT id, client_name AS name, status, data, updated_at FROM contracts WHERE id=? AND owner_email=?"
    );
    $stmt->execute([$id, $email]);
    $r = $stmt->fetch();
    if (!$r) { http_response_code(404); echo json_encode(['error' => 'Not found']); exit; }
    $r['data'] = json_decode($r['data'], true);
    echo json_encode(['ok' => true, 'contract' => $r]);
    exit;
}

if ($action === 'delete') {
    $id = (int)($body['id'] ?? 0);
    if (!$id) { http_response_code(400); echo json_encode(['error' => 'Missing id']); exit; }
    $pdo->prepare("DELETE FROM contracts WHERE id=? AND owner_email=?")->execute([$id, $email]);
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(400); echo json_encode(['error' => 'Unknown action']);
