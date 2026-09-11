<?php
// stages.php — kanban columns per inquiry path (the contact form's `path`
// column: hire/join/hi). "tone" drives the column header color.
return [
    'hire' => [
        ['label' => 'New',                    'tone' => 'neutral'],
        ['label' => 'Pushed to Deals Tracker', 'tone' => 'success'],
        ['label' => 'Junk',                   'tone' => 'danger'],
    ],
    'join' => [
        ['label' => 'New',            'tone' => 'neutral'],
        ['label' => 'Review',         'tone' => 'neutral'],
        ['label' => 'Interview',      'tone' => 'neutral'],
        ['label' => 'Offer',          'tone' => 'success'],
        ['label' => 'Rejected',       'tone' => 'danger'],
        ['label' => 'Pool for later', 'tone' => 'warning'],
        ['label' => 'Junk',           'tone' => 'danger'],
    ],
    'hi' => [
        ['label' => 'New',     'tone' => 'neutral'],
        ['label' => 'Replied', 'tone' => 'success'],
        ['label' => 'Closed',  'tone' => 'warning'],
        ['label' => 'Junk',    'tone' => 'danger'],
    ],
    'corefounders' => [
        ['label' => 'New',        'tone' => 'neutral'],
        ['label' => 'Review',     'tone' => 'neutral'],
        ['label' => 'Approved',   'tone' => 'success'],
        ['label' => 'Waitlisted', 'tone' => 'warning'],
        ['label' => 'Rejected',   'tone' => 'danger'],
        ['label' => 'Junk',       'tone' => 'danger'],
    ],
    'studio' => [
        ['label' => 'New',       'tone' => 'neutral'],
        ['label' => 'Confirmed', 'tone' => 'neutral'],
        ['label' => 'Completed', 'tone' => 'success'],
        ['label' => 'Cancelled', 'tone' => 'danger'],
        ['label' => 'Junk',      'tone' => 'danger'],
    ],
];
