<?php
// Read-only feed for the employee tool. Server to server only: the tool's backend
// sends "Authorization: Bearer <EMPLOYEE_API_TOKEN>" and ?email=<employee email>,
// and gets back that one person's numbers. No session login is used here.
require_once __DIR__ . '/../employee_api_secrets.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../payroll_planner/payout.php';

// The plan the employee tool reads. Change it here to point at another plan.
const EMPLOYEE_PAYOUT_PLAN_ID = 2;

header('Content-Type: application/json');
header('Cache-Control: no-store');

function employee_api_fail(int $code, string $msg): void {
    http_response_code($code);
    echo json_encode(['error' => $msg]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') employee_api_fail(405, 'Method not allowed');

$auth = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
if ($auth === '' && function_exists('getallheaders')) {
    foreach (getallheaders() as $k => $v) { if (strcasecmp($k, 'Authorization') === 0) { $auth = $v; break; } }
}
$token = (stripos($auth, 'Bearer ') === 0) ? trim(substr($auth, 7)) : '';
if (!defined('EMPLOYEE_API_TOKEN') || EMPLOYEE_API_TOKEN === '' || EMPLOYEE_API_TOKEN === 'CHANGE_ME'
    || $token === '' || !hash_equals(EMPLOYEE_API_TOKEN, $token)) {
    employee_api_fail(401, 'Unauthorized');
}

$email = trim((string)($_GET['email'] ?? ''));
if ($email === '') employee_api_fail(400, 'Missing email');

$stmt = getDB()->prepare("SELECT data FROM payroll_plans WHERE id = ?");
$stmt->execute([EMPLOYEE_PAYOUT_PLAN_ID]);
$data = $stmt->fetchColumn();
$plan = $data ? json_decode($data, true) : null;
if (!is_array($plan)) employee_api_fail(503, 'Plan not available');

$payload = payroll_employee_payload($plan, $email);
if ($payload === null) employee_api_fail(404, 'Unknown employee');

echo json_encode($payload);
