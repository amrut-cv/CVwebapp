<?php
// One-time seeder for list_items. Run once, then delete.
require_once __DIR__ . '/db.php';
$pdo = getDB();

$existing = (int)$pdo->query("SELECT COUNT(*) FROM list_items")->fetchColumn();
if ($existing > 0) { echo "Already seeded ($existing rows). Delete list_items rows first to re-seed.\n"; exit; }

$lists = [
    'scope_strategy' => [
        'Your positioning & messaging',
        'Your marketing strategy',
        'CMO office & OKRs',
        'Your GTM strategy',
        'Customer & competitor research',
        'A growth experiment framework',
        'Your investor narrative',
        'Your content strategy',
        'Founder personal brand',
        'SEO, AEO & AI visibility',
    ],
    'scope_content' => [
        'Website',
        'Logo & visual identity',
        'Brand guide',
        'Social posts',
        'Reels & short video',
        'Long-form video',
        'Blogs & newsletters',
        'Ad creatives',
        'Sales decks & presentations',
        'Testimonials & case studies',
        'Product explainers & guides',
        'Event & booth collateral',
        'Pitch deck',
        'One-pager & exec summary',
        'Thought leadership',
    ],
    'scope_ops' => [
        'Social media',
        'Paid ads',
        'SEO & AEO',
        'ABM & outreach',
        'Campaigns & events',
        'Community building',
        'Marketing automation & CRM',
        'Performance tracking',
    ],
    'brief_sales' => [
        'Need to reduce friction in sales',
        'Sales team lacks good supporting material',
        'Sales not working, needs a renewed vibe',
        'New product GTM / entering new segment / area',
        'Post-fundraise, need to scale growth',
    ],
    'brief_messaging' => [
        "Market doesn't appreciate us enough",
        'Improve positioning for talent hiring',
        'Product is hard to explain',
        'Preparing for fundraising event',
    ],
    'brief_mkt_strategy' => [
        'Long sales cycle, need consistent engagement',
        'Want to own category narrative',
        'Need video assets ASAP',
    ],
    'brief_structure' => [
        'Founding team doing marketing',
        'Marketing function led by generalist (non-marketing)',
        'Team has marketers',
    ],
    'brief_engagement' => [
        'Goal already defined',
        'Timeline already defined',
        'Budget already defined',
    ],
];

$stmt = $pdo->prepare("INSERT INTO list_items (list_key, label, sort_order) VALUES (?,?,?)");
$total = 0;
foreach ($lists as $key => $items) {
    foreach ($items as $i => $label) {
        $stmt->execute([$key, $label, ($i + 1) * 10]);
        $total++;
    }
}
echo "Seeded $total items across " . count($lists) . " lists.\n";
