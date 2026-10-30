<?php
// Server-side copy of the payroll planner formula (payroll_planner/calc.js),
// used by api/employee_payout.php. Keep the two in step.

function payroll_pct(float $revenue, float $lo, float $hi): float {
    if ($hi <= $lo) return ($hi > 0 && $revenue >= $hi) ? 1.0 : 0.0;
    return max(0.0, min(1.0, ($revenue - $lo) / ($hi - $lo)));
}

// One person's pay at a given revenue. Fixed may be negative; from client and
// the variable targets are clamped at zero. Amounts are rupees, unrounded.
function payroll_person_payout(array $person, array $settings, float $revenue): array {
    $r = [(float)($settings['r0'] ?? 0), (float)($settings['r1'] ?? 0), (float)($settings['r2'] ?? 0), (float)($settings['r3'] ?? 0)];
    $fixed  = (float)($person['fixed'] ?? 0);
    $client = max(0.0, (float)($person['fromClient'] ?? 0));
    $pools  = [];
    for ($k = 1; $k <= 3; $k++) {
        $target = max(0.0, (float)($person['variable' . $k] ?? 0));
        $pools[$k] = $target * payroll_pct($revenue, $r[$k - 1], $r[$k]);
    }
    return [
        'fixed' => $fixed, 'client' => $client,
        'pool1' => $pools[1], 'pool2' => $pools[2], 'pool3' => $pools[3],
        'total' => $fixed + $client + $pools[1] + $pools[2] + $pools[3],
    ];
}

// Whole rupees, with the total as the sum of the rounded parts so the rows add up.
function payroll_row(array $person, array $settings, float $revenue): array {
    $p = payroll_person_payout($person, $settings, $revenue);
    $row = [
        'fixed' => (int)round($p['fixed']), 'client' => (int)round($p['client']),
        'pool1' => (int)round($p['pool1']), 'pool2' => (int)round($p['pool2']), 'pool3' => (int)round($p['pool3']),
    ];
    $row['total'] = $row['fixed'] + $row['client'] + $row['pool1'] + $row['pool2'] + $row['pool3'];
    return $row;
}

// Service revenue (ex-GST) for the months already finished, from the Zoho invoice
// exports: excludes IIT-M, client-reimbursable items, merchandise and the Wadhwani
// draft. Add the next month here once its invoices are final.
const PAYROLL_ACTUAL_REVENUE = [
    '2026-04' => 2015000,
    '2026-05' => 1485000,
    '2026-06' => 1900000,
    '2026-07' => 2553000,
    '2026-08' => 2324000,
    '2026-09' => 1794000,
    '2026-10' => 1380000,
];
const PAYROLL_WHATIF_FROM = 1200000;
const PAYROLL_WHATIF_TO   = 2800000;
const PAYROLL_WHATIF_STEP = 100000;

// $plan is the saved plan document (settings + people). Returns null when no
// person has this email.
function payroll_employee_payload(array $plan, string $email): ?array {
    $settings = $plan['settings'] ?? [];
    $match = null;
    foreach (($plan['people'] ?? []) as $p) {
        $e = strtolower(trim((string)($p['email'] ?? '')));
        if ($e !== '' && $e === strtolower(trim($email))) { $match = $p; break; }
    }
    if ($match === null) return null;

    $out = [
        'name' => (string)($match['name'] ?? ''),
        'r0' => (int)round((float)($settings['r0'] ?? 0)), 'r1' => (int)round((float)($settings['r1'] ?? 0)),
        'r2' => (int)round((float)($settings['r2'] ?? 0)), 'r3' => (int)round((float)($settings['r3'] ?? 0)),
        'actual' => [], 'whatIf' => [],
    ];
    foreach (PAYROLL_ACTUAL_REVENUE as $month => $rev) {
        $out['actual'][] = ['month' => $month, 'revenue' => $rev] + payroll_row($match, $settings, (float)$rev);
    }
    for ($rev = PAYROLL_WHATIF_FROM; $rev <= PAYROLL_WHATIF_TO; $rev += PAYROLL_WHATIF_STEP) {
        $out['whatIf'][] = ['revenue' => $rev] + payroll_row($match, $settings, (float)$rev);
    }
    return $out;
}
