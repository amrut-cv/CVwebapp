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
        long_term_assets        DECIMAL(14,2) NOT NULL DEFAULT 0,
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

    $pdo->exec("CREATE TABLE IF NOT EXISTS engagement_types (
        id                INT AUTO_INCREMENT PRIMARY KEY,
        type_key          VARCHAR(50)  NOT NULL,
        label             VARCHAR(255) NOT NULL,
        category          VARCHAR(50)  NOT NULL,
        duration_tag      VARCHAR(50)  NULL,
        description       TEXT         NOT NULL,
        rationale         TEXT         NOT NULL,
        sort_order        INT          NOT NULL DEFAULT 0,
        UNIQUE KEY uniq_type_key (type_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Seed engagement types if table is empty (moved off the hardcoded arrays
    // that used to live in contract_builder/index.php and generate.php).
    $engCount = (int)$pdo->query("SELECT COUNT(*) FROM engagement_types")->fetchColumn();
    if ($engCount === 0) {
        $engSeed = $pdo->prepare(
            "INSERT INTO engagement_types (type_key, label, category, duration_tag, description, rationale, sort_order)
             VALUES (?,?,?,?,?,?,?)"
        );
        $engRows = [
            ['full-retainer', 'Full-stack retainer', 'Retainership', 'Ongoing',
                'Strategy, content, and marketing ops — all three running together on an ongoing basis. We operate as an external marketing team, shared across functions.',
                'Based on what you\'ve shared, a full-stack retainer makes the most sense. You need consistent output across strategy, content, and execution — not a one-time project. We\'d operate as your marketing function, with clear goals, a shared calendar, and regular governance to make sure the work stays aligned with where the business is going.',
                10],
            ['outcome-retainer', 'Outcome-focused retainer', 'Retainership', 'Time-boxed',
                'Similar to full-stack retainer but it\'s time-boxed and around a specific goal. We define the target and the window together, then do everything needed to get there.',
                'What you\'ve described is a time-boxed problem, not a forever engagement. An outcome-focused retainer lets us define the goal together, set a window, and deploy whatever\'s needed to get there — then reassess. No lock-in beyond what the goal requires.',
                20],
            ['content-retainer', 'Content retainer', 'Retainership', 'Ongoing',
                'Ongoing production of content assets — video, text, images, webpages, etc, etc. Built to accumulate and compound over time.',
                'Your content engine isn\'t running at the level it needs to be. A content retainer gives you consistent, quality output that builds over time — compounding rather than campaign-based. Volume plus consistency is what moves the needle.',
                30],
            ['new-gtm', 'New product GTM', 'Project', null,
                'Positioning, identity, website, and a full sales kit (deck, videos, brochures, etc). Optional press outreach. For companies launching for the first time or after a pivot.',
                'You\'re entering the market fresh — or after a meaningful pivot. That means you need positioning, identity, and a full sales kit before anything else. We\'ll build the foundation so that every subsequent marketing activity has something real to stand on.',
                40],
            ['gtm-relaunch', 'GTM relaunch', 'Project', null,
                'Visual refresh of existing brand artefacts, updated website, updated sales kit (deck, videos, brochures, etc). Optional booth redesign and press outreach.',
                'You already have something in the market, but it\'s not landing the way it should. A relaunch isn\'t about starting over — it\'s about updating what exists to reflect where the company actually is now.',
                50],
            ['fundraising', 'Fundraising comms', 'Project', null,
                'Narrative clean-up, pitch deck, and website redesign (optional) for startups heading into a funding round.',
                'When you\'re heading into a round, the narrative has to do the work before you even get in the room. We\'ll clean up your positioning, tighten the deck, and make sure the website backs up the story you\'re telling investors.',
                60],
            ['sales-video', 'Content sprint', 'Project', null,
                'Fresh content for use in sales or others. Could be video focussed — product explainers, testimonials, or use-case demos. Could be other stuff.',
                'Video is the most efficient format for a complex product or a crowded market. A short series of well-made videos — explainers, use cases, testimonials — gives your sales team something that travels across every channel and conversation.',
                70],
            ['custom', 'Custom scope', 'Custom', null,
                'Scope defined as agreed between the parties.',
                'The scope here has been defined specifically for this engagement, based on what you\'ve described and what we believe will move the needle. We\'ll work from this as our starting point and adjust as we go.',
                80],
        ];
        foreach ($engRows as $row) $engSeed->execute($row);
    }

    // Consolidate card_description/doc_description into one description
    // column (migration — safe to run repeatedly). Existing rows keep
    // doc_description's text, since that is what clients actually see.
    try {
        $hasDocDesc = $pdo->query("SHOW COLUMNS FROM engagement_types LIKE 'doc_description'")->fetch();
    } catch (PDOException $e) { $hasDocDesc = false; }
    if ($hasDocDesc) {
        $pdo->exec("UPDATE engagement_types SET card_description = doc_description");
        try {
            $pdo->exec("ALTER TABLE engagement_types DROP COLUMN doc_description");
        } catch (PDOException $e) { /* already dropped */ }
    }
    try {
        $pdo->exec("ALTER TABLE engagement_types CHANGE COLUMN card_description description TEXT NOT NULL");
    } catch (PDOException $e) { /* already renamed */ }

    $pdo->exec("CREATE TABLE IF NOT EXISTS deals (
        id                  INT AUTO_INCREMENT PRIMARY KEY,
        deal_name           VARCHAR(255) NOT NULL,
        engagement_type_id  INT NULL,
        monthly_value       DECIMAL(14,2) NULL,
        expected_months     SMALLINT NULL,
        project_value       DECIMAL(14,2) NULL,
        stage               VARCHAR(30) NOT NULL DEFAULT '1. Contact',
        next_steps          TEXT NULL,
        main_contact        VARCHAR(255) NULL,
        phone_number        VARCHAR(50) NULL,
        email_address       VARCHAR(255) NULL,
        deal_owner          VARCHAR(255) NULL,
        source              ENUM('Outbound','Inbound') NOT NULL DEFAULT 'Outbound',
        created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_deals_stage (stage),
        FOREIGN KEY (engagement_type_id) REFERENCES engagement_types(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Add password_hash column (migration — safe to run repeatedly)
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN password_hash VARCHAR(255) NULL DEFAULT NULL");
    } catch (PDOException $e) { /* column already exists */ }

    // Add archived flag to deals (migration — safe to run repeatedly)
    try {
        $pdo->exec("ALTER TABLE deals ADD COLUMN archived TINYINT(1) NOT NULL DEFAULT 0");
    } catch (PDOException $e) { /* column already exists */ }

    // Add long_term_assets column to cashflow_entries (migration — safe to run repeatedly)
    try {
        $pdo->exec("ALTER TABLE cashflow_entries ADD COLUMN long_term_assets DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER receivables_next_month");
    } catch (PDOException $e) { /* column already exists */ }

    // Many-to-many links between deals and contracts (a deal can share several
    // contracts with the same client, e.g. proposal + signed contract)
    $pdo->exec("CREATE TABLE IF NOT EXISTS deal_contracts (
        id           INT AUTO_INCREMENT PRIMARY KEY,
        deal_id      INT NOT NULL,
        contract_id  INT NOT NULL,
        created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_deal_contract (deal_id, contract_id),
        FOREIGN KEY (deal_id) REFERENCES deals(id) ON DELETE CASCADE,
        FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS guests (
        id                       INT AUTO_INCREMENT PRIMARY KEY,
        guest_name               VARCHAR(255) NOT NULL,
        company_title            VARCHAR(255) NULL,
        bio                      TEXT NULL,
        email                    VARCHAR(255) NULL,
        phone                    VARCHAR(50) NULL,
        social_link              VARCHAR(255) NULL,
        source                   ENUM('Referral','Cold outreach','Inbound') NOT NULL DEFAULT 'Cold outreach',
        episode_topic            TEXT NULL,
        recording_date           DATE NULL,
        recording_date_confirmed TINYINT(1) NOT NULL DEFAULT 0,
        release_date             DATE NULL,
        release_date_confirmed   TINYINT(1) NOT NULL DEFAULT 0,
        episode_link             VARCHAR(500) NULL,
        notes                    TEXT NULL,
        stage                    VARCHAR(30) NOT NULL DEFAULT '1. Prospect',
        archived                 TINYINT(1) NOT NULL DEFAULT 0,
        created_at               TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at               TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_guests_stage (stage)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Seed known users if table is empty
    $count = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    if ($count === 0) {
        $seed = $pdo->prepare("INSERT IGNORE INTO users (email, name, role) VALUES (?,?,?)");
        $seed->execute(['amrut@corevoice.in',        'Amrut',      'admin']);
        $seed->execute(['subhasmita@corevoice.in',   'Subhasmita', 'editor']);
        $seed->execute(['nikhil@corevoice.in',        'Nikhil',     'editor']);
        $seed->execute(['piyush@corevoice.in',        'Piyush',     'editor']);
    }

    // Per-user module access (checkbox matrix in admin/users.php). Admins
    // bypass this entirely (see has_module_access() in session_guard.php).
    $pdo->exec("CREATE TABLE IF NOT EXISTS module_access (
        id           INT AUTO_INCREMENT PRIMARY KEY,
        user_id      INT NOT NULL,
        module_key   VARCHAR(50) NOT NULL,
        UNIQUE KEY uniq_user_module (user_id, module_key),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Grandfather every existing user into every module the first time this
    // table is populated, so nothing breaks before access is deliberately
    // revoked. Users added after this point start with no module access
    // until an admin grants it.
    $accessCount = (int)$pdo->query("SELECT COUNT(*) FROM module_access")->fetchColumn();
    if ($accessCount === 0) {
        $moduleKeys = array_keys(require __DIR__ . '/modules.php');
        $userIds = $pdo->query("SELECT id FROM users")->fetchAll(PDO::FETCH_COLUMN);
        if ($userIds) {
            $grant = $pdo->prepare("INSERT IGNORE INTO module_access (user_id, module_key) VALUES (?,?)");
            foreach ($userIds as $uid) {
                foreach ($moduleKeys as $key) {
                    $grant->execute([$uid, $key]);
                }
            }
        }
    }

    return $pdo;
}
