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
];
