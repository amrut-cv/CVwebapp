<?php
require __DIR__ . '/../session_guard.php';
require __DIR__ . '/../db.php';

header('Content-Type: application/json');

$pdo = getDB();

function guests_payload(array $body): array {
    return [
        trim($body['guest_name'] ?? ''),
        trim($body['company_title'] ?? '') ?: null,
        trim($body['bio'] ?? '') ?: null,
        trim($body['email'] ?? '') ?: null,
        trim($body['phone'] ?? '') ?: null,
        trim($body['social_link'] ?? '') ?: null,
        in_array($body['source'] ?? '', ['Referral', 'Cold outreach', 'Inbound']) ? $body['source'] : 'Cold outreach',
        trim($body['episode_topic'] ?? '') ?: null,
        trim($body['recording_date'] ?? '') ?: null,
        !empty($body['recording_date_confirmed']) ? 1 : 0,
        trim($body['release_date'] ?? '') ?: null,
        !empty($body['release_date_confirmed']) ? 1 : 0,
        trim($body['episode_link'] ?? '') ?: null,
        trim($body['notes'] ?? '') ?: null,
        trim($body['stage'] ?? '1. Prospect'),
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode($pdo->query("SELECT * FROM guests ORDER BY updated_at DESC")->fetchAll());
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); echo json_encode(['error' => 'Method not allowed']); exit;
}

$body = json_decode(file_get_contents('php://input'), true);
$action = $body['action'] ?? '';

switch ($action) {
    case 'add':
        $name = trim($body['guest_name'] ?? '');
        if (!$name) { http_response_code(400); echo json_encode(['error' => 'Guest name required']); exit; }
        $stmt = $pdo->prepare(
            "INSERT INTO guests
             (guest_name, company_title, bio, email, phone, social_link, source, episode_topic,
              recording_date, recording_date_confirmed, release_date, release_date_confirmed,
              episode_link, notes, stage)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
        );
        $stmt->execute(guests_payload($body));
        echo json_encode(['id' => (int)$pdo->lastInsertId()]);
        break;

    case 'update':
        $id = (int)($body['id'] ?? 0);
        if (!$id) { http_response_code(400); echo json_encode(['error' => 'Missing id']); exit; }
        $stmt = $pdo->prepare(
            "UPDATE guests SET
                guest_name=?, company_title=?, bio=?, email=?, phone=?, social_link=?, source=?, episode_topic=?,
                recording_date=?, recording_date_confirmed=?, release_date=?, release_date_confirmed=?,
                episode_link=?, notes=?, stage=?
             WHERE id=?"
        );
        $stmt->execute([...guests_payload($body), $id]);
        echo json_encode(['ok' => true]);
        break;

    case 'archive':
        $id = (int)($body['id'] ?? 0);
        $archived = !empty($body['archived']) ? 1 : 0;
        if (!$id) { http_response_code(400); echo json_encode(['error' => 'Missing id']); exit; }
        $pdo->prepare("UPDATE guests SET archived=? WHERE id=?")->execute([$archived, $id]);
        echo json_encode(['ok' => true]);
        break;

    case 'move_stage':
        $id    = (int)($body['id'] ?? 0);
        $stage = trim($body['stage'] ?? '');
        if (!$id || !$stage) { http_response_code(400); echo json_encode(['error' => 'Missing fields']); exit; }
        $pdo->prepare("UPDATE guests SET stage=? WHERE id=?")->execute([$stage, $id]);
        echo json_encode(['ok' => true]);
        break;

    case 'delete':
        $id = (int)($body['id'] ?? 0);
        if (!$id) { http_response_code(400); echo json_encode(['error' => 'Missing id']); exit; }
        $pdo->prepare("DELETE FROM guests WHERE id=?")->execute([$id]);
        echo json_encode(['ok' => true]);
        break;

    default:
        http_response_code(400); echo json_encode(['error' => 'Unknown action']);
}
