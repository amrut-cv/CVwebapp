<?php
// CoreContacts DB helpers
require_once __DIR__ . '/../db.php';

function cc_member(string $email): ?array {
    $db = getDB();
    $st = $db->prepare('SELECT * FROM org_members WHERE email = ?');
    $st->execute([$email]);
    return $st->fetch() ?: null;
}

function cc_rel_types(): array {
    $db = getDB();
    return $db->query(
        "SELECT v.value_id, v.value FROM global_list_values v
         JOIN global_lists l ON l.list_id = v.list_id
         WHERE l.name = 'relationship_types' AND v.is_active = 1
         ORDER BY v.sort_order"
    )->fetchAll();
}

function cc_domain_tags(): array {
    $db = getDB();
    return $db->query(
        "SELECT v.value FROM global_list_values v
         JOIN global_lists l ON l.list_id = v.list_id
         WHERE l.name = 'domain_tags' AND v.is_active = 1
         ORDER BY v.sort_order"
    )->fetchAll(PDO::FETCH_COLUMN);
}

function cc_institutions(): array {
    $db = getDB();
    return $db->query(
        "SELECT v.value FROM global_list_values v
         JOIN global_lists l ON l.list_id = v.list_id
         WHERE l.name = 'notable_institutions' AND v.is_active = 1
         ORDER BY v.sort_order"
    )->fetchAll(PDO::FETCH_COLUMN);
}

function uuid(): string {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0,0xffff), mt_rand(0,0xffff), mt_rand(0,0xffff),
        mt_rand(0,0x0fff)|0x4000, mt_rand(0,0x3fff)|0x8000,
        mt_rand(0,0xffff), mt_rand(0,0xffff), mt_rand(0,0xffff));
}
