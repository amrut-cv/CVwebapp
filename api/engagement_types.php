<?php
require __DIR__ . '/../session_guard.php';
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json');

$pdo = getDB();

function et_slug(string $label): string {
    $slug = strtolower(trim($label));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    return trim($slug, '-') ?: 'type';
}

function et_unique_key(PDO $pdo, string $base): string {
    $key = $base;
    $i = 2;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM engagement_types WHERE type_key = ?");
    while (true) {
        $stmt->execute([$key]);
        if ((int)$stmt->fetchColumn() === 0) return $key;
        $key = $base . '-' . $i;
        $i++;
    }
}

// ── GET: return all engagement types ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode($pdo->query("SELECT * FROM engagement_types ORDER BY sort_order, id")->fetchAll());
    exit;
}

// ── POST: add / update / delete / reorder ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); echo json_encode(['error' => 'Method not allowed']); exit;
}

$body = json_decode(file_get_contents('php://input'), true);
$action = $body['action'] ?? '';

switch ($action) {
    case 'add':
        $label = trim($body['label'] ?? '');
        if (!$label) { http_response_code(400); echo json_encode(['error' => 'Label required']); exit; }
        $key = et_unique_key($pdo, et_slug($label));
        $order = (int)$pdo->query("SELECT COALESCE(MAX(sort_order),0)+10 FROM engagement_types")->fetchColumn();
        $stmt = $pdo->prepare(
            "INSERT INTO engagement_types (type_key, label, category, duration_tag, description, rationale, sort_order)
             VALUES (?,?,?,?,?,?,?)"
        );
        $stmt->execute([
            $key, $label,
            trim($body['category'] ?? ''),
            trim($body['duration_tag'] ?? '') ?: null,
            trim($body['description'] ?? ''),
            trim($body['rationale'] ?? ''),
            $order,
        ]);
        echo json_encode(['id' => (int)$pdo->lastInsertId(), 'type_key' => $key, 'sort_order' => $order]);
        break;

    case 'update':
        $id = (int)($body['id'] ?? 0);
        if (!$id) { http_response_code(400); echo json_encode(['error' => 'Missing id']); exit; }
        $stmt = $pdo->prepare(
            "UPDATE engagement_types
             SET label=?, category=?, duration_tag=?, description=?, rationale=?
             WHERE id=?"
        );
        $stmt->execute([
            trim($body['label'] ?? ''),
            trim($body['category'] ?? ''),
            trim($body['duration_tag'] ?? '') ?: null,
            trim($body['description'] ?? ''),
            trim($body['rationale'] ?? ''),
            $id,
        ]);
        echo json_encode(['ok' => true]);
        break;

    case 'delete':
        $id = (int)($body['id'] ?? 0);
        if (!$id) { http_response_code(400); echo json_encode(['error' => 'Missing id']); exit; }
        $pdo->prepare("DELETE FROM engagement_types WHERE id=?")->execute([$id]);
        echo json_encode(['ok' => true]);
        break;

    case 'reorder':
        $ids = $body['ids'] ?? [];
        if (!is_array($ids)) { http_response_code(400); echo json_encode(['error' => 'ids must be array']); exit; }
        $stmt = $pdo->prepare("UPDATE engagement_types SET sort_order=? WHERE id=?");
        foreach ($ids as $i => $id) {
            $stmt->execute([($i + 1) * 10, (int)$id]);
        }
        echo json_encode(['ok' => true]);
        break;

    default:
        http_response_code(400); echo json_encode(['error' => 'Unknown action']);
}
