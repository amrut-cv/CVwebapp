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

// ₹1,50,000 style (Indian digit grouping), with a real minus sign for negatives.
function payroll_inr(float $v): string {
    $n = (int)round(abs($v));
    $s = (string)$n;
    if (strlen($s) > 3) {
        $head = substr($s, 0, -3);
        $s = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $head) . ',' . substr($s, -3);
    }
    return ($v < 0 && $n > 0 ? "\u{2212}" : '') . "\u{20B9}" . $s;
}

// ₹19L style for revenue thresholds.
function payroll_lakh(float $v): string {
    return "\u{20B9}" . rtrim(rtrim(number_format($v / 100000, 2, '.', ''), '0'), '.') . 'L';
}

// The person's pay structure as data plus plain-text lines for display.
// Only the person's own numbers and the R0..R3 thresholds are used.
function payroll_arrangement(array $person, array $settings): array {
    $r = [(float)($settings['r0'] ?? 0), (float)($settings['r1'] ?? 0), (float)($settings['r2'] ?? 0), (float)($settings['r3'] ?? 0)];
    $fixed  = (float)($person['fixed'] ?? 0);
    $client = max(0.0, (float)($person['fromClient'] ?? 0));
    $pools = [];
    $variable = 0.0;
    for ($k = 1; $k <= 3; $k++) {
        $target = max(0.0, (float)($person['variable' . $k] ?? 0));
        if ($target <= 0) continue;
        $variable += $target;
        $pools[] = ['pool' => $k, 'target' => (int)round($target), 'startsAt' => (int)round($r[$k - 1]), 'fullAt' => (int)round($r[$k])];
    }
    $guaranteed = $fixed + $client;
    $max = $guaranteed + $variable;

    $lines = [];
    if ($fixed < 0) {
        if ($client > 0) $lines[] = 'Paid directly by a client: ' . payroll_inr($client) . ' a month.';
        $lines[] = $client > 0
            ? 'Fixed from the company: ' . payroll_inr($fixed) . ', which offsets the client payment. Together that is ' . payroll_inr($guaranteed) . ' a month, guaranteed.'
            : 'Fixed from the company: ' . payroll_inr($fixed) . ' a month.';
    } else {
        if ($fixed > 0) $lines[] = 'Fixed: ' . payroll_inr($fixed) . ' a month, guaranteed.';
        if ($client > 0) $lines[] = 'Paid directly by a client: ' . payroll_inr($client) . ' a month.';
    }
    if (count($pools) === 1) {
        $p = $pools[0];
        $lines[] = ($p['fullAt'] > $p['startsAt'])
            ? 'Variable: up to ' . payroll_inr($p['target']) . ' from Pool ' . $p['pool'] . '. It starts once monthly revenue passes ' . payroll_lakh($p['startsAt']) . ' and is paid in full at ' . payroll_lakh($p['fullAt']) . '.'
            : 'Variable: up to ' . payroll_inr($p['target']) . ' from Pool ' . $p['pool'] . ', paid in full once monthly revenue reaches ' . payroll_lakh($p['fullAt']) . '.';
    } elseif (count($pools) > 1) {
        $parts = array_map(function ($p) {
            return 'up to ' . payroll_inr($p['target']) . ' from Pool ' . $p['pool'] . ' (revenue ' . payroll_lakh($p['startsAt']) . ' to ' . payroll_lakh($p['fullAt']) . ')';
        }, $pools);
        $last = array_pop($parts);
        $lines[] = 'Variable: ' . (count($parts) ? implode(', ', $parts) . ' and ' : '') . $last . '.';
    } else {
        $lines[] = 'No variable pay in your arrangement.';
    }
    if ($variable > 0) $lines[] = 'Most you can earn in a month: ' . payroll_inr($max) . '.';

    return [
        'arrangement' => [
            'fixed' => (int)round($fixed), 'client' => (int)round($client),
            'guaranteed' => (int)round($guaranteed), 'pools' => $pools, 'maxPerMonth' => (int)round($max),
        ],
        'description' => $lines,
    ];
}

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
    ] + payroll_arrangement($match, $settings) + [
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
