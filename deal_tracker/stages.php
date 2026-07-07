<?php
// stages.php — ordered kanban columns. "tone" drives the column header color
// (neutral / success / danger / warning) — purely visual, not stored on deals.
// "group" drives which columns show by default: "core" always shows, "early"
// and "late" are hidden by default and toggled on via the board's show/hide links.
return [
    ['label' => '1. Contact',      'tone' => 'neutral', 'group' => 'early'],
    ['label' => '2. Qualified',    'tone' => 'neutral', 'group' => 'core'],
    ['label' => '3. Proposal sent','tone' => 'neutral', 'group' => 'core'],
    ['label' => '4. Negotiation',  'tone' => 'neutral', 'group' => 'core'],
    ['label' => '5a. Won',         'tone' => 'success', 'group' => 'core'],
    ['label' => '5b. Ongoing',     'tone' => 'success', 'group' => 'core'],
    ['label' => '5c. Completed',   'tone' => 'success', 'group' => 'late'],
    ['label' => '6a. Lost',        'tone' => 'danger',  'group' => 'late'],
    ['label' => '7a. On hold',     'tone' => 'warning', 'group' => 'late'],
    ['label' => '7b. Keep Warm',   'tone' => 'warning', 'group' => 'late'],
];
