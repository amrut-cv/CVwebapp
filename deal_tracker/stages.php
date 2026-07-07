<?php
// stages.php — ordered kanban columns. "tone" drives the column header color
// (neutral / success / danger / warning) — purely visual, not stored on deals.
return [
    ['label' => '1. Contact',      'tone' => 'neutral'],
    ['label' => '2. Qualified',    'tone' => 'neutral'],
    ['label' => '3. Proposal sent','tone' => 'neutral'],
    ['label' => '4. Negotiation',  'tone' => 'neutral'],
    ['label' => '5a. Won',         'tone' => 'success'],
    ['label' => '5b. Ongoing',     'tone' => 'success'],
    ['label' => '5c. Completed',   'tone' => 'success'],
    ['label' => '6a. Lost',        'tone' => 'danger'],
    ['label' => '7a. On hold',     'tone' => 'warning'],
    ['label' => '7b. Keep Warm',   'tone' => 'warning'],
];
