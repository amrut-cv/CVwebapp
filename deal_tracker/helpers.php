<?php
// helpers.php — Indian-style currency formatting and the card value-line text,
// shared between the kanban board render and the add/edit modal.

function dt_inr($n) {
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
    return ($neg ? '-' : '') . '₹' . $formatted;
}

// Renders the one-line value summary shown on a deal card.
function dt_value_text(array $d): string {
    if ($d['monthly_value'] !== null) {
        $txt = dt_inr($d['monthly_value']) . '/mo';
        if ($d['expected_months']) $txt .= ' × ' . (int)$d['expected_months'] . 'mo';
        return $txt;
    }
    if ($d['project_value'] !== null) {
        return dt_inr($d['project_value']) . ' total';
    }
    return '—';
}
