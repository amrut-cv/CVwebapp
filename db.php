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
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        email      VARCHAR(255) NOT NULL,
        name       VARCHAR(255) NOT NULL DEFAULT '',
        role       ENUM('admin','editor','viewer') NOT NULL DEFAULT 'editor',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY idx_user_email (email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS contract_shares (
        id                INT AUTO_INCREMENT PRIMARY KEY,
        contract_id       INT NOT NULL,
        shared_with_email VARCHAR(255) NOT NULL,
        permission        ENUM('view','edit') NOT NULL DEFAULT 'view',
        shared_by_email   VARCHAR(255) NOT NULL,
        created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_share (contract_id, shared_with_email),
        INDEX idx_shared_with (shared_with_email),
        FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS cashflow_entries (
        id                      INT AUTO_INCREMENT PRIMARY KEY,
        entry_date              DATE NOT NULL,
        axis_bank               DECIMAL(14,2) NOT NULL DEFAULT 0,
        rbl_bank                DECIMAL(14,2) NOT NULL DEFAULT 0,
        long_term_deposits      DECIMAL(14,2) NOT NULL DEFAULT 0,
        receivables_this_month  DECIMAL(14,2) NOT NULL DEFAULT 0,
        receivables_next_month  DECIMAL(14,2) NOT NULL DEFAULT 0,
        fte_net_pay             DECIMAL(14,2) NOT NULL DEFAULT 0,
        fte_net_pay_actual      DECIMAL(14,2) NOT NULL DEFAULT 0,
        ftc_net_pay             DECIMAL(14,2) NOT NULL DEFAULT 0,
        ftc_net_pay_actual      DECIMAL(14,2) NOT NULL DEFAULT 0,
        interns_freelancers     DECIMAL(14,2) NOT NULL DEFAULT 0,
        others_net_pay          DECIMAL(14,2) NOT NULL DEFAULT 0,
        reimbursements          DECIMAL(14,2) NOT NULL DEFAULT 0,
        axis_cc                 DECIMAL(14,2) NOT NULL DEFAULT 0,
        yes_cc                  DECIMAL(14,2) NOT NULL DEFAULT 0,
        long_term_borrowals     DECIMAL(14,2) NOT NULL DEFAULT 0,
        gst_this_month          DECIMAL(14,2) NOT NULL DEFAULT 0,
        gst_next_month          DECIMAL(14,2) NOT NULL DEFAULT 0,
        tds_this_month          DECIMAL(14,2) NOT NULL DEFAULT 0,
        tds_next_month          DECIMAL(14,2) NOT NULL DEFAULT 0,
        rent_payable            DECIMAL(14,2) NOT NULL DEFAULT 0,
        filled_by_email         VARCHAR(255) NOT NULL DEFAULT '',
        created_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_entry_date (entry_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Add password_hash column (migration — safe to run repeatedly)
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN password_hash VARCHAR(255) NULL DEFAULT NULL");
    } catch (PDOException $e) { /* column already exists */ }

    // Seed known users if table is empty
    $count = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    if ($count === 0) {
        $seed = $pdo->prepare("INSERT IGNORE INTO users (email, name, role) VALUES (?,?,?)");
        $seed->execute(['amrut@corevoice.in',        'Amrut',      'admin']);
        $seed->execute(['subhasmita@corevoice.in',   'Subhasmita', 'editor']);
        $seed->execute(['nikhil@corevoice.in',        'Nikhil',     'editor']);
        $seed->execute(['piyush@corevoice.in',        'Piyush',     'editor']);
    }
    return $pdo;
}
