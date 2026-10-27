<?php
require __DIR__ . '/../session_guard.php';
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json');

if (!has_module_access('payroll_planner')) {
    http_response_code(403); echo json_encode(['error' => 'Forbidden']); exit;
}

$pdo   = getDB();
$email = $_SESSION['auth_email'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $rows = $pdo->query(
        "SELECT id, name, updated_by, updated_at FROM payroll_plans ORDER BY updated_at DESC, id DESC"
    )->fetchAll();
    echo json_encode($rows);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); echo json_encode(['error' => 'Method not allowed']); exit;
}

// Browsers can't send a JSON content type cross-site without a CORS preflight,
// so this blocks forged form posts to an endpoint that has no CSRF token.
if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== 0) {
    http_response_code(415); echo json_encode(['error' => 'Unsupported content type']); exit;
}

$body   = json_decode(file_get_contents('php://input'), true);
$action = is_array($body) ? ($body['action'] ?? '') : '';

if ($action === 'load') {
    $id = (int)($body['id'] ?? 0);
    $stmt = $pdo->prepare("SELECT id, name, data, updated_by, updated_at FROM payroll_plans WHERE id = ?");
    $stmt->execute([$id]);
    $r = $stmt->fetch();
    if (!$r) { http_response_code(404); echo json_encode(['error' => 'Not found']); exit; }
    $r['data'] = json_decode($r['data'], true);
    echo json_encode(['ok' => true, 'plan' => $r], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'save') {
    $id   = (int)($body['id'] ?? 0);
    $name = trim((string)($body['name'] ?? ''));
    $data = $body['data'] ?? null;
    if ($name === '' || mb_strlen($name) > 255 || !is_array($data)) {
        http_response_code(400); echo json_encode(['error' => 'Name and data are required']); exit;
    }
    $json = json_encode($data, JSON_UNESCAPED_UNICODE);
    if ($json === false || strlen($json) > 2000000) {
        http_response_code(400); echo json_encode(['error' => 'Plan too large']); exit;
    }
    if ($id) {
        $chk = $pdo->prepare("SELECT id FROM payroll_plans WHERE id = ?");
        $chk->execute([$id]);
        if (!$chk->fetch()) { http_response_code(404); echo json_encode(['error' => 'Not found']); exit; }
        $pdo->prepare("UPDATE payroll_plans SET name = ?, data = ?, updated_by = ? WHERE id = ?")
            ->execute([$name, $json, $email, $id]);
    } else {
        $pdo->prepare("INSERT INTO payroll_plans (name, data, created_by, updated_by) VALUES (?,?,?,?)")
            ->execute([$name, $json, $email, $email]);
        $id = (int)$pdo->lastInsertId();
    }
    echo json_encode(['ok' => true, 'id' => $id, 'name' => $name]);
    exit;
}

if ($action === 'delete') {
    $id = (int)($body['id'] ?? 0);
    if (!$id) { http_response_code(400); echo json_encode(['error' => 'Missing id']); exit; }
    $pdo->prepare("DELETE FROM payroll_plans WHERE id = ?")->execute([$id]);
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(400); echo json_encode(['error' => 'Unknown action']);
