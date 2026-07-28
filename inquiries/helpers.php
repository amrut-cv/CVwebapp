<?php
// helpers.php — shared config and the notes-table builder used when pushing
// a "hire" inquiry into Deal Tracker.

// path => tab label, in display order.
function iq_path_tabs(): array {
    return [
        'hire' => 'Clients',
        'join' => 'Talent',
        'hi'   => 'Others',
    ];
}

// Fields from contact_form_submissions that are not mapped to a dedicated
// deals column, in the order they should appear in the generated notes
// table. Labels are chosen to avoid colliding with the deal's own "stage"
// and "Notes" concepts once this text lands in deals.next_steps.
function iq_notes_field_map(): array {
    return [
        'sub_reason'   => 'Sub-reason',
        'role'         => 'Role',
        'linkedin'     => 'LinkedIn',
        'heard'        => 'Heard about us',
        'heard_detail' => 'Heard detail',
        'website'      => 'Website',
        'sector'       => 'Sector',
        'stage'        => 'Funding stage',
        'needs'        => 'Needs',
        'problem'      => 'Problem',
        'notes'        => 'Their notes',
    ];
}

// Builds the colon-separated notes table from a contact_form_submissions
// row. Every field always appears (blank after the colon if empty), so a
// salesperson can fill gaps in by hand after a call.
function iq_build_notes_table(array $submission): string {
    $lines = [];
    foreach (iq_notes_field_map() as $col => $label) {
        $lines[] = $label . ': ' . trim((string)($submission[$col] ?? ''));
    }
    $submitted = $submission['created_at'] ?? '';
    $lines[] = 'Submitted: ' . ($submitted ? date('j M Y', strtotime($submitted)) : '');
    return implode("\n", $lines);
}
