<?php
// stages.php — ordered kanban columns for the podcast guest pipeline.
// "tone" drives the column header color (neutral / success / danger / warning).
return [
    ['label' => '1. Prospect',            'tone' => 'neutral'],
    ['label' => '2. Conversation ongoing','tone' => 'neutral'],
    ['label' => '3. Confirmed',           'tone' => 'success'],
    ['label' => '4a. Recorded',           'tone' => 'success'],
    ['label' => '4b. Editing',            'tone' => 'success'],
    ['label' => '5. Published',           'tone' => 'success'],
    ['label' => '6a. Declined',           'tone' => 'danger'],
    ['label' => '6b. Cold',               'tone' => 'warning'],
    ['label' => '6c. Not a fit',          'tone' => 'warning'],
];
