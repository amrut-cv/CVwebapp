<?php
require __DIR__ . '/../session_guard.php';
require __DIR__ . '/../db.php';

header('Content-Type: application/json');

$email = $_SESSION['auth_email'];
$role  = current_role();
$pdo   = getDB();

// GET — list this user's contracts (owned + shared)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->prepare(
        "SELECT c.id, c.client_name AS name, c.status, c.updated_at, c.owner_email,
                CASE WHEN c.owner_email = ? THEN 'owner' ELSE cs.permission END AS my_permission
         FROM contracts c
         LEFT JOIN contract_shares cs ON cs.contract_id = c.id AND cs.shared_with_email = ?
         WHERE c.owner_email = ? OR cs.shared_with_email = ?
         ORDER BY c.updated_at DESC"
    );
    $stmt->execute([$email, $email, $email, $email]);
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
        if ($role === 'admin') {
            $check = $pdo->prepare("SELECT id FROM contracts WHERE id = ?");
            $check->execute([$id]);
        } else {
            $check = $pdo->prepare(
                "SELECT c.id FROM contracts c
                 LEFT JOIN contract_shares cs ON cs.contract_id = c.id AND cs.shared_with_email = ?
                 WHERE c.id = ? AND (c.owner_email = ? OR cs.permission = 'edit')"
            );
            $check->execute([$email, $id, $email]);
        }
        if ($check->fetch()) {
            $pdo->prepare("UPDATE contracts SET client_name = ?, data = ? WHERE id = ?")
                ->execute([$name, $data, $id]);
        } else {
            $id = 0;
        }
    }
    if (!$id) {
        $stmt = $pdo->prepare(
            "INSERT INTO contracts (owner_email, client_name, data) VALUES (?,?,?)"
        );
        $stmt->execute([$email, $name, $data]);
        $id = (int)$pdo->lastInsertId();
    }

    $row = $pdo->prepare("SELECT id, client_name AS name, status, updated_at FROM contracts WHERE id = ?");
    $row->execute([$id]);
    $r = $row->fetch();
    echo json_encode(['ok' => true, 'id' => $r['id'], 'name' => $r['name'], 'updated_at' => $r['updated_at']]);
    exit;
}

if ($action === 'load') {
    $id = (int)($body['id'] ?? 0);
    if (!$id) { http_response_code(400); echo json_encode(['error' => 'Missing id']); exit; }

    if ($role === 'admin') {
        $stmt = $pdo->prepare(
            "SELECT id, client_name AS name, status, data, updated_at, owner_email FROM contracts WHERE id = ?"
        );
        $stmt->execute([$id]);
        $r = $stmt->fetch();
        if ($r) $r['my_permission'] = 'owner';
    } else {
        $stmt = $pdo->prepare(
            "SELECT c.id, c.client_name AS name, c.status, c.data, c.updated_at, c.owner_email,
                    CASE WHEN c.owner_email = ? THEN 'owner' ELSE cs.permission END AS my_permission
             FROM contracts c
             LEFT JOIN contract_shares cs ON cs.contract_id = c.id AND cs.shared_with_email = ?
             WHERE c.id = ? AND (c.owner_email = ? OR cs.shared_with_email = ?)"
        );
        $stmt->execute([$email, $email, $id, $email, $email]);
        $r = $stmt->fetch();
    }

    if (!$r) { http_response_code(404); echo json_encode(['error' => 'Not found']); exit; }
    $r['data'] = json_decode($r['data'], true);
    echo json_encode(['ok' => true, 'contract' => $r]);
    exit;
}

if ($action === 'delete') {
    $id = (int)($body['id'] ?? 0);
    if (!$id) { http_response_code(400); echo json_encode(['error' => 'Missing id']); exit; }
    if ($role === 'admin') {
        $pdo->prepare("DELETE FROM contracts WHERE id = ?")->execute([$id]);
    } else {
        $pdo->prepare("DELETE FROM contracts WHERE id = ? AND owner_email = ?")->execute([$id, $email]);
    }
    echo json_encode(['ok' => true]);
    exit;
}

if ($action === 'get_shares') {
    $contract_id = (int)($body['contract_id'] ?? 0);
    if (!$contract_id) { http_response_code(400); echo json_encode(['error' => 'Missing contract_id']); exit; }

    if ($role !== 'admin') {
        $own = $pdo->prepare("SELECT id FROM contracts WHERE id = ? AND owner_email = ?");
        $own->execute([$contract_id, $email]);
        if (!$own->fetch()) { http_response_code(403); echo json_encode(['error' => 'Forbidden']); exit; }
    }

    $stmt = $pdo->prepare(
        "SELECT cs.shared_with_email, cs.permission, u.name
         FROM contract_shares cs
         LEFT JOIN users u ON u.email = cs.shared_with_email
         WHERE cs.contract_id = ?
         ORDER BY cs.created_at"
    );
    $stmt->execute([$contract_id]);
    echo json_encode(['ok' => true, 'shares' => $stmt->fetchAll()]);
    exit;
}

if ($action === 'share') {
    $contract_id = (int)($body['contract_id'] ?? 0);
    $with_email  = strtolower(trim($body['shared_with_email'] ?? ''));
    $permission  = in_array($body['permission'] ?? '', ['view', 'edit']) ? $body['permission'] : 'view';

    if (!$contract_id || !$with_email) {
        http_response_code(400); echo json_encode(['error' => 'Missing fields']); exit;
    }
    if ($role !== 'admin') {
        $own = $pdo->prepare("SELECT id FROM contracts WHERE id = ? AND owner_email = ?");
        $own->execute([$contract_id, $email]);
        if (!$own->fetch()) { http_response_code(403); echo json_encode(['error' => 'Forbidden']); exit; }
    }

    $usr = $pdo->prepare("SELECT email FROM users WHERE email = ?");
    $usr->execute([$with_email]);
    if (!$usr->fetch()) { http_response_code(400); echo json_encode(['error' => 'User not found']); exit; }

    $pdo->prepare(
        "INSERT INTO contract_shares (contract_id, shared_with_email, permission, shared_by_email)
         VALUES (?,?,?,?)
         ON DUPLICATE KEY UPDATE permission = ?, shared_by_email = ?"
    )->execute([$contract_id, $with_email, $permission, $email, $permission, $email]);

    echo json_encode(['ok' => true]);
    exit;
}

if ($action === 'unshare') {
    $contract_id = (int)($body['contract_id'] ?? 0);
    $with_email  = strtolower(trim($body['shared_with_email'] ?? ''));

    if (!$contract_id || !$with_email) {
        http_response_code(400); echo json_encode(['error' => 'Missing fields']); exit;
    }
    if ($role !== 'admin') {
        $own = $pdo->prepare("SELECT id FROM contracts WHERE id = ? AND owner_email = ?");
        $own->execute([$contract_id, $email]);
        if (!$own->fetch()) { http_response_code(403); echo json_encode(['error' => 'Forbidden']); exit; }
    }

    $pdo->prepare("DELETE FROM contract_shares WHERE contract_id = ? AND shared_with_email = ?")
        ->execute([$contract_id, $with_email]);
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(400); echo json_encode(['error' => 'Unknown action']);
