<?php
// One-time migration: remap old trigger values to new ones in all saved drafts.
// Run from CLI: php migrate_triggers.php
// Or via SSH: php /var/www/app.corevoice.in/public_html/CVwebapp/migrate_triggers.php
require __DIR__ . '/db.php';

$MAP = [
    'Founding team doing marketing themselves'  => ['Founding team doing marketing'],
    'Post-fundraise, ready to scale output'     => ['Post-fundraise, need to scale growth'],
    'One goal, not ongoing work'                => ['Goal already defined'],
    'Budget and deadline already defined'       => ['Budget already defined', 'Timeline already defined'],
    'Specific metric needs to move'             => ['Goal already defined'],
    'Need to reduce friction in sales'          => ['Need to reduce friction in sales'],
    'Long product cycle, need visibility'       => ['Long sales cycle, need consistent engagement'],
    'Want to own category narrative'            => ['Want to own category narrative'],
    'Product is hard to explain'                => ['Product is hard to explain'],
    'No video assets currently exist'           => ['Need video assets ASAP'],
    'Sales team lacks supporting material'      => ['Sales team lacks good supporting material'],
    'Sales needs a renewed vibe'                => ['Sales not working, needs a renewed vibe'],
    'First time going to market'                => ['New product GTM / entering new segment / area'],
    'New product line launching soon'           => ['New product GTM / entering new segment / area'],
    'Entering new market or segment'            => ['New product GTM / entering new segment / area'],
    'Post-pivot, need fresh positioning'        => ["Market doesn't appreciate us enough"],
    'Preparing for fundraise or event'          => ['Preparing for fundraising event'],
    'Round opening in 1\xe2\x80\x933 months'   => ['Preparing for fundraising event'],
    // Dropped (no equivalent): No marketing function in place,
    // Pitch materials outdated or inconsistent, New investors seeing you first time
];

$pdo    = getDB();
$rows   = $pdo->query("SELECT id, name, data FROM drafts")->fetchAll();
$total  = count($rows);
$changed = 0;

foreach ($rows as $row) {
    $data = json_decode($row['data'], true);
    $old  = $data['triggers'] ?? [];
    if (empty($old)) {
        echo "Draft {$row['id']} ({$row['name']}): no triggers, skipped\n";
        continue;
    }

    $new = [];
    foreach ($old as $t) {
        if (isset($MAP[$t])) {
            foreach ($MAP[$t] as $mapped) {
                if (!in_array($mapped, $new)) $new[] = $mapped;
            }
        } else {
            echo "  [DROP] \"{$t}\"\n";
        }
    }

    $data['triggers'] = $new;
    $pdo->prepare("UPDATE drafts SET data = ? WHERE id = ?")
        ->execute([json_encode($data, JSON_UNESCAPED_UNICODE), $row['id']]);

    echo "Draft {$row['id']} ({$row['name']}): " . implode(', ', $old) . "\n";
    echo "  → " . (count($new) ? implode(', ', $new) : '(none)') . "\n";
    $changed++;
}

echo "\nDone. {$changed}/{$total} drafts updated.\n";
