<?php
require __DIR__ . '/../session_guard.php';
require_module_access('guest_tracker');
require_once __DIR__ . '/../db.php';
require __DIR__ . '/guide_render.php';

$id = (int)($_GET['id'] ?? 0);
$pdo = getDB();
$stmt = $pdo->prepare("SELECT * FROM guests WHERE id = ?");
$stmt->execute([$id]);
$guest = $stmt->fetch();

if (!$guest) {
    http_response_code(404);
    echo '<p style="font-family:sans-serif;padding:40px">Guest not found.</p>';
    exit;
}

echo render_guest_guide($guest);
