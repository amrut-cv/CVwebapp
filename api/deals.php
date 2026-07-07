<?php
require __DIR__ . '/../session_guard.php';
require __DIR__ . '/../db.php';

header('Content-Type: application/json');

$pdo = getDB();

function deals_num($v) {
    if ($v === null || $v === '') return null;
    return (float)str_replace(',', '', $v);
}

function deals_payload(array $body): array {
    return [
        trim($body['deal_name'] ?? ''),
        $body['engagement_type_id'] ?? null ?: null,
        deals_num($body['monthly_value'] ?? null),
        $body['expected_months'] ?? null ?: null,
        deals_num($body['project_value'] ?? null),
        trim($body['stage'] ?? '1. Contact'),
        trim($body['next_steps'] ?? '') ?: null,
        trim($body['main_contact'] ?? '') ?: null,
        trim($body['phone_number'] ?? '') ?: null,
        trim($body['email_address'] ?? '') ?: null,
        trim($body['deal_owner'] ?? '') ?: null,
        ($body['source'] ?? 'Outbound') === 'Inbound' ? 'Inbound' : 'Outbound',
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode($pdo->query("SELECT * FROM deals ORDER BY updated_at DESC")->fetchAll());
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); echo json_encode(['error' => 'Method not allowed']); exit;
}

$body = json_decode(file_get_contents('php://input'), true);
$action = $body['action'] ?? '';

switch ($action) {
    case 'add':
        $name = trim($body['deal_name'] ?? '');
        if (!$name) { http_response_code(400); echo json_encode(['error' => 'Deal name required']); exit; }
        $stmt = $pdo->prepare(
            "INSERT INTO deals
             (deal_name, engagement_type_id, monthly_value, expected_months, project_value,
              stage, next_steps, main_contact, phone_number, email_address, deal_owner, source)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?)"
        );
        $stmt->execute(deals_payload($body));
        echo json_encode(['id' => (int)$pdo->lastInsertId()]);
        break;

    case 'update':
        $id = (int)($body['id'] ?? 0);
        if (!$id) { http_response_code(400); echo json_encode(['error' => 'Missing id']); exit; }
        $stmt = $pdo->prepare(
            "UPDATE deals SET
                deal_name=?, engagement_type_id=?, monthly_value=?, expected_months=?, project_value=?,
                stage=?, next_steps=?, main_contact=?, phone_number=?, email_address=?, deal_owner=?, source=?
             WHERE id=?"
        );
        $stmt->execute([...deals_payload($body), $id]);
        echo json_encode(['ok' => true]);
        break;

    case 'move_stage':
        $id    = (int)($body['id'] ?? 0);
        $stage = trim($body['stage'] ?? '');
        if (!$id || !$stage) { http_response_code(400); echo json_encode(['error' => 'Missing fields']); exit; }
        $pdo->prepare("UPDATE deals SET stage=? WHERE id=?")->execute([$stage, $id]);
        echo json_encode(['ok' => true]);
        break;

    case 'delete':
        $id = (int)($body['id'] ?? 0);
        if (!$id) { http_response_code(400); echo json_encode(['error' => 'Missing id']); exit; }
        $pdo->prepare("DELETE FROM deals WHERE id=?")->execute([$id]);
        echo json_encode(['ok' => true]);
        break;

    default:
        http_response_code(400); echo json_encode(['error' => 'Unknown action']);
}
