<?php
require __DIR__ . '/../session_guard.php';
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json');

$pdo = getDB();

// ── GET: return items for one or all list keys ────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $key = $_GET['key'] ?? 'all';
    if ($key === 'all') {
        $rows = $pdo->query("SELECT id, list_key, label, sort_order FROM list_items ORDER BY list_key, sort_order, id")->fetchAll();
        $out = [];
        foreach ($rows as $r) {
            $out[$r['list_key']][] = ['id' => $r['id'], 'label' => $r['label'], 'sort_order' => $r['sort_order']];
        }
        echo json_encode($out);
    } else {
        $stmt = $pdo->prepare("SELECT id, label, sort_order FROM list_items WHERE list_key = ? ORDER BY sort_order, id");
        $stmt->execute([$key]);
        echo json_encode($stmt->fetchAll());
    }
    exit;
}

// ── POST: add / update / delete / reorder ────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); echo json_encode(['error' => 'Method not allowed']); exit;
}

$body = json_decode(file_get_contents('php://input'), true);
$action = $body['action'] ?? '';

switch ($action) {
    case 'add':
        $key   = trim($body['list_key'] ?? '');
        $label = trim($body['label'] ?? '');
        if (!$key || !$label) { http_response_code(400); echo json_encode(['error' => 'Missing fields']); exit; }
        $stmt = $pdo->prepare("SELECT COALESCE(MAX(sort_order),0)+10 FROM list_items WHERE list_key=?");
        $stmt->execute([$key]);
        $order = (int)$stmt->fetchColumn();
        $stmt = $pdo->prepare("INSERT INTO list_items (list_key, label, sort_order) VALUES (?,?,?)");
        $stmt->execute([$key, $label, $order]);
        echo json_encode(['id' => (int)$pdo->lastInsertId(), 'label' => $label, 'sort_order' => $order]);
        break;

    case 'update':
        $id    = (int)($body['id'] ?? 0);
        $label = trim($body['label'] ?? '');
        if (!$id || !$label) { http_response_code(400); echo json_encode(['error' => 'Missing fields']); exit; }
        $pdo->prepare("UPDATE list_items SET label=? WHERE id=?")->execute([$label, $id]);
        echo json_encode(['ok' => true]);
        break;

    case 'delete':
        $id = (int)($body['id'] ?? 0);
        if (!$id) { http_response_code(400); echo json_encode(['error' => 'Missing id']); exit; }
        $pdo->prepare("DELETE FROM list_items WHERE id=?")->execute([$id]);
        echo json_encode(['ok' => true]);
        break;

    case 'reorder':
        $ids = $body['ids'] ?? [];
        if (!is_array($ids)) { http_response_code(400); echo json_encode(['error' => 'ids must be array']); exit; }
        $stmt = $pdo->prepare("UPDATE list_items SET sort_order=? WHERE id=?");
        foreach ($ids as $i => $id) {
            $stmt->execute([($i + 1) * 10, (int)$id]);
        }
        echo json_encode(['ok' => true]);
        break;

    default:
        http_response_code(400); echo json_encode(['error' => 'Unknown action']);
}
