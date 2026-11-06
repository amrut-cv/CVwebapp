<?php
require __DIR__ . '/../session_guard.php';
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json');

if (!has_module_access('profitability_planner')) {
    http_response_code(403); echo json_encode(['error' => 'Forbidden']); exit;
}

$pdo   = getDB();
$email = $_SESSION['auth_email'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $rows = $pdo->query(
        "SELECT id, name, updated_by, updated_at FROM planner_scenarios ORDER BY updated_at DESC, id DESC"
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
    $stmt = $pdo->prepare("SELECT id, name, data, updated_by, updated_at FROM planner_scenarios WHERE id = ?");
    $stmt->execute([$id]);
    $r = $stmt->fetch();
    if (!$r) { http_response_code(404); echo json_encode(['error' => 'Not found']); exit; }
    $r['data'] = json_decode($r['data'], true);
    echo json_encode(['ok' => true, 'scenario' => $r], JSON_UNESCAPED_UNICODE);
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
        http_response_code(400); echo json_encode(['error' => 'Scenario too large']); exit;
    }
    if ($id) {
        $chk = $pdo->prepare("SELECT id FROM planner_scenarios WHERE id = ?");
        $chk->execute([$id]);
        if (!$chk->fetch()) { http_response_code(404); echo json_encode(['error' => 'Not found']); exit; }
        $pdo->prepare("UPDATE planner_scenarios SET name = ?, data = ?, updated_by = ? WHERE id = ?")
            ->execute([$name, $json, $email, $id]);
    } else {
        $pdo->prepare("INSERT INTO planner_scenarios (name, data, created_by, updated_by) VALUES (?,?,?,?)")
            ->execute([$name, $json, $email, $email]);
        $id = (int)$pdo->lastInsertId();
    }
    echo json_encode(['ok' => true, 'id' => $id, 'name' => $name]);
    exit;
}

if ($action === 'delete') {
    $id = (int)($body['id'] ?? 0);
    if (!$id) { http_response_code(400); echo json_encode(['error' => 'Missing id']); exit; }
    $pdo->prepare("DELETE FROM planner_scenarios WHERE id = ?")->execute([$id]);
    echo json_encode(['ok' => true]);
    exit;
}

// Payroll planner plans, read-only, for "Calculate salaries from the payroll planner".
// Only what the pay formula needs is sent (no emails, no from-client amounts), and only
// to people who also have the Payroll planner module.
if ($action === 'payroll_plans' || $action === 'payroll_plan') {
    if (!has_module_access('payroll_planner')) {
        http_response_code(403); echo json_encode(['error' => 'Needs access to the Payroll planner']); exit;
    }
    if ($action === 'payroll_plans') {
        $rows = $pdo->query("SELECT id, name, locked FROM payroll_plans ORDER BY locked DESC, updated_at DESC, id DESC")->fetchAll();
        echo json_encode(['ok' => true, 'plans' => $rows]);
        exit;
    }
    $stmt = $pdo->prepare("SELECT id, name, locked, data FROM payroll_plans WHERE id = ?");
    $stmt->execute([(int)($body['id'] ?? 0)]);
    $r = $stmt->fetch();
    if (!$r) { http_response_code(404); echo json_encode(['error' => 'Payroll plan not found']); exit; }
    $d = json_decode($r['data'], true) ?: [];
    $s = $d['settings'] ?? [];
    $people = array_map(function ($p) {
        return [
            'name' => (string)($p['name'] ?? ''), 'group' => (string)($p['group'] ?? 'ICs'),
            'fixed' => (float)($p['fixed'] ?? 0),
            'variable1' => (float)($p['variable1'] ?? 0), 'variable2' => (float)($p['variable2'] ?? 0), 'variable3' => (float)($p['variable3'] ?? 0),
        ];
    }, $d['people'] ?? []);
    echo json_encode(['ok' => true, 'plan' => [
        'id' => (int)$r['id'], 'name' => $r['name'], 'locked' => (int)$r['locked'],
        'settings' => ['r0' => (float)($s['r0'] ?? 0), 'r1' => (float)($s['r1'] ?? 0), 'r2' => (float)($s['r2'] ?? 0), 'r3' => (float)($s['r3'] ?? 0)],
        'people' => $people,
    ]], JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(400); echo json_encode(['error' => 'Unknown action']);
