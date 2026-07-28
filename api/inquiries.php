<?php
require __DIR__ . '/../session_guard.php';
require_module_access('inquiries');
require_once __DIR__ . '/../db.php';
require __DIR__ . '/../inquiries/helpers.php';

header('Content-Type: application/json');

$pdo = getDB();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); echo json_encode(['error' => 'Method not allowed']); exit;
}

$body = json_decode(file_get_contents('php://input'), true);
$action = $body['action'] ?? '';

function iq_upsert_stage(PDO $pdo, int $submissionId, string $stage): void {
    $pdo->prepare(
        "INSERT INTO inquiry_tracking (submission_id, stage) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE stage = VALUES(stage)"
    )->execute([$submissionId, $stage]);
}

switch ($action) {
    case 'move_stage':
        $submissionId = (int)($body['submission_id'] ?? 0);
        $stage = trim($body['stage'] ?? '');
        if (!$submissionId || !$stage) { http_response_code(400); echo json_encode(['error' => 'Missing fields']); exit; }
        iq_upsert_stage($pdo, $submissionId, $stage);
        echo json_encode(['ok' => true]);
        break;

    case 'update':
        $submissionId = (int)($body['submission_id'] ?? 0);
        if (!$submissionId) { http_response_code(400); echo json_encode(['error' => 'Missing submission_id']); exit; }
        $pdo->prepare(
            "INSERT INTO inquiry_tracking (submission_id, owner, notes) VALUES (?,?,?)
             ON DUPLICATE KEY UPDATE owner = VALUES(owner), notes = VALUES(notes)"
        )->execute([
            $submissionId,
            trim($body['owner'] ?? '') ?: null,
            trim($body['notes'] ?? '') ?: null,
        ]);
        echo json_encode(['ok' => true]);
        break;

    case 'archive':
        $submissionId = (int)($body['submission_id'] ?? 0);
        $archived = !empty($body['archived']) ? 1 : 0;
        if (!$submissionId) { http_response_code(400); echo json_encode(['error' => 'Missing submission_id']); exit; }
        $pdo->prepare(
            "INSERT INTO inquiry_tracking (submission_id, archived) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE archived = VALUES(archived)"
        )->execute([$submissionId, $archived]);
        echo json_encode(['ok' => true]);
        break;

    case 'push_to_deal':
        $submissionId = (int)($body['submission_id'] ?? 0);
        if (!$submissionId) { http_response_code(400); echo json_encode(['error' => 'Missing submission_id']); exit; }

        $stmt = $pdo->prepare("SELECT * FROM contact_form_submissions WHERE id = ?");
        $stmt->execute([$submissionId]);
        $sub = $stmt->fetch();
        if (!$sub) { http_response_code(404); echo json_encode(['error' => 'Submission not found']); exit; }

        $dealName = trim((string)($sub['company'] ?? '')) ?: trim((string)($sub['name'] ?? ''));
        $ownerEmail = $_SESSION['auth_email'] ?? null;

        $insert = $pdo->prepare(
            "INSERT INTO deals (deal_name, main_contact, email_address, phone_number, next_steps, source, stage, deal_owner)
             VALUES (?,?,?,?,?,?,?,?)"
        );
        $insert->execute([
            $dealName ?: 'Untitled',
            $sub['name'] ?? null,
            $sub['email'] ?? null,
            $sub['mobile'] ?? null,
            iq_build_notes_table($sub),
            'Inbound',
            '2. Qualified',
            $ownerEmail,
        ]);
        $dealId = (int)$pdo->lastInsertId();

        $pdo->prepare(
            "INSERT INTO inquiry_tracking (submission_id, stage, owner, converted_deal_id) VALUES (?,?,?,?)
             ON DUPLICATE KEY UPDATE stage = VALUES(stage), converted_deal_id = VALUES(converted_deal_id),
                 owner = COALESCE(owner, VALUES(owner))"
        )->execute([$submissionId, 'Pushed to Deals Tracker', $ownerEmail, $dealId]);

        echo json_encode(['ok' => true, 'deal_id' => $dealId]);
        break;

    default:
        http_response_code(400); echo json_encode(['error' => 'Unknown action']);
}
