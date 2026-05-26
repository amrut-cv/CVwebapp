<?php
require __DIR__ . '/db_config.php';

function getDB(): PDO {
    static $pdo;
    if ($pdo) return $pdo;
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
    $pdo->exec("CREATE TABLE IF NOT EXISTS drafts (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        email      VARCHAR(255) NOT NULL,
        name       VARCHAR(255) NOT NULL DEFAULT 'Untitled',
        data       JSON         NOT NULL,
        created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_email (email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    return $pdo;
}
