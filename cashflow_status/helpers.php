<?php
// helpers.php — Indian-style number formatting and the cashflow roll-up calc shared
// by the entry form and the status page.

function cf_digits($n) {
    $n = (float)$n;
    $neg = $n < 0;
    $n = (string)round(abs($n));
    if (strlen($n) > 3) {
        $last3 = substr($n, -3);
        $rest  = substr($n, 0, -3);
        $groups = [];
        while (strlen($rest) > 2) {
            $groups[] = substr($rest, -2);
            $rest = substr($rest, 0, -2);
        }
        if ($rest !== '') $groups[] = $rest;
        $formatted = implode(',', array_reverse($groups)) . ',' . $last3;
    } else {
        $formatted = $n;
    }
    return ($neg ? '-' : '') . $formatted;
}

function cf_inr($n) {
    return '₹' . cf_digits($n);
}

// Rolls up the 20 raw fields on a cashflow_entries row into the assets/liabilities/
// cash-position tiers and the two salary-only months-of-cash ratios.
function cf_calc($e) {
    if (!$e) return null;
    $g = fn($k) => (float)($e[$k] ?? 0);

    $eom_assets          = $g('axis_bank') + $g('rbl_bank') + $g('receivables_this_month');
    $total_liquid_assets = $eom_assets + $g('receivables_next_month');
    $total_assets        = $total_liquid_assets + $g('long_term_deposits');

    $eom_liab          = $g('fte_net_pay_actual') + $g('ftc_net_pay_actual') + $g('interns_freelancers')
                        + $g('others_net_pay') + $g('reimbursements') + $g('gst_this_month')
                        + $g('tds_this_month') + $g('rent_payable');
    $total_liquid_liab = $eom_liab + $g('axis_cc') + $g('yes_cc') + $g('gst_next_month') + $g('tds_next_month');
    $total_liab        = $total_liquid_liab + $g('long_term_borrowals');

    $eom_position          = $eom_assets - $eom_liab;
    $total_liquid_position = $total_liquid_assets - $total_liquid_liab;
    $total_position        = $total_assets - $total_liab;

    $salary        = $g('fte_net_pay') + $g('ftc_net_pay');
    $months_liquid = $salary != 0 ? $total_liquid_position / $salary : null;
    $months_total  = $salary != 0 ? $total_position / $salary : null;

    return compact(
        'eom_assets', 'total_liquid_assets', 'total_assets',
        'eom_liab', 'total_liquid_liab', 'total_liab',
        'eom_position', 'total_liquid_position', 'total_position',
        'months_liquid', 'months_total'
    );
}
