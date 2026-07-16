<?php
// fields.php — shared field config for the cashflow entry form and status page.
// Column order here drives both the entry table rows and the breakdown display.
return [
    'Assets' => [
        'axis_bank'              => 'Axis bank',
        'rbl_bank'                => 'RBL bank',
        'long_term_deposits'      => 'Long-term deposits',
        'receivables_this_month'  => "Receivables this month (expected payment value)",
        'receivables_next_month'  => "Receivables next month (expected payment value)",
        'long_term_assets'        => 'Long-term assets',
    ],
    'Payroll (paid at EOM)' => [
        'fte_net_pay'             => 'FTE net pay (liability)',
        'fte_net_pay_actual'      => "FTE net pay (expected actual payment value)",
        'ftc_net_pay'             => 'FTC net pay (liability)',
        'ftc_net_pay_actual'      => "FTC net pay (expected actual payment value)",
        'interns_freelancers'     => "Interns + freelancers (expected actual payment value)",
        'others_net_pay'          => 'Others net pay',
        'reimbursements'          => 'Est. reimbursements',
    ],
    'Other liabilities' => [
        'axis_cc'                 => 'Axis CC 2880',
        'yes_cc'                  => 'Yes CC 5220',
        'long_term_borrowals'     => 'Long-term borrowals',
        'gst_this_month'          => 'GST payable this month',
        'gst_next_month'          => 'GST payable next month',
        'tds_this_month'          => 'TDS payable this month',
        'tds_next_month'          => 'TDS payable next month',
        'rent_payable'            => 'Rent payable',
    ],
];
