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
    $pdo->exec("CREATE TABLE IF NOT EXISTS list_items (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        list_key   VARCHAR(50)  NOT NULL,
        label      VARCHAR(255) NOT NULL,
        sort_order INT          NOT NULL DEFAULT 0,
        INDEX idx_list_key (list_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS case_studies (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        name        VARCHAR(255) NOT NULL,
        description TEXT         NOT NULL,
        sort_order  INT          NOT NULL DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS contracts (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        owner_email VARCHAR(255) NOT NULL,
        client_name VARCHAR(255) NOT NULL DEFAULT '',
        status      VARCHAR(20)  NOT NULL DEFAULT 'draft',
        data        MEDIUMTEXT   NOT NULL,
        created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
        updated_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_contracts_owner (owner_email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    return $pdo;
}
