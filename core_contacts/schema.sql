-- CoreContacts schema
-- Run once: mysql -u cvapp -p CVwebapp < schema.sql

SET FOREIGN_KEY_CHECKS = 0;

-- ── org_members ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS org_members (
    member_id  CHAR(36)     PRIMARY KEY,
    name       VARCHAR(200) NOT NULL,
    email      VARCHAR(200) NOT NULL UNIQUE,
    role       ENUM('admin') NOT NULL DEFAULT 'admin',
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed from login.php allowlist
INSERT IGNORE INTO org_members (member_id, name, email) VALUES
    (UUID(), 'Amrut',      'amrut@corevoice.in'),
    (UUID(), 'Subhasmita', 'subhasmita@corevoice.in'),
    (UUID(), 'Nikhil',     'nikhil@corevoice.in'),
    (UUID(), 'Piyush',     'piyush@corevoice.in');

-- ── global_lists ───────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS global_lists (
    list_id     CHAR(36)     PRIMARY KEY,
    name        VARCHAR(100) NOT NULL UNIQUE,
    description VARCHAR(300),
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS global_list_values (
    value_id   CHAR(36)     PRIMARY KEY,
    list_id    CHAR(36)     NOT NULL,
    value      VARCHAR(200) NOT NULL,
    sort_order INT          NOT NULL DEFAULT 0,
    is_active  BOOLEAN      NOT NULL DEFAULT TRUE,
    created_by CHAR(36),
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (list_id) REFERENCES global_lists(list_id),
    UNIQUE KEY uq_list_value (list_id, value)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed global lists
INSERT IGNORE INTO global_lists (list_id, name, description) VALUES
    (UUID(), 'notable_institutions', 'Institutions that flag high-signal contacts'),
    (UUID(), 'relationship_types',   'How you know someone'),
    (UUID(), 'domain_tags',          'Professional domain tags');

-- Seed values (using subqueries for list_id)
INSERT IGNORE INTO global_list_values (value_id, list_id, value, sort_order)
SELECT UUID(), list_id, v.value, v.sort_order
FROM global_lists,
(SELECT 'IIT Madras' AS value, 1 AS sort_order UNION ALL
 SELECT 'IIT Bombay',    2 UNION ALL
 SELECT 'IIT Delhi',     3 UNION ALL
 SELECT 'IIT Kharagpur', 4 UNION ALL
 SELECT 'IIT Kanpur',    5 UNION ALL
 SELECT 'IIT Roorkee',   6 UNION ALL
 SELECT 'IIM Ahmedabad', 7 UNION ALL
 SELECT 'IIM Bangalore', 8 UNION ALL
 SELECT 'IIM Calcutta',  9 UNION ALL
 SELECT 'BITS Pilani',   10 UNION ALL
 SELECT 'NIT Trichy',    11) v
WHERE global_lists.name = 'notable_institutions';

INSERT IGNORE INTO global_list_values (value_id, list_id, value, sort_order)
SELECT UUID(), list_id, v.value, v.sort_order
FROM global_lists,
(SELECT 'alumni'    AS value, 1 AS sort_order UNION ALL
 SELECT 'colleague', 2 UNION ALL
 SELECT 'event',     3 UNION ALL
 SELECT 'referral',  4 UNION ALL
 SELECT 'online',    5 UNION ALL
 SELECT 'client',    6 UNION ALL
 SELECT 'other',     7) v
WHERE global_lists.name = 'relationship_types';

INSERT IGNORE INTO global_list_values (value_id, list_id, value, sort_order)
SELECT UUID(), list_id, v.value, v.sort_order
FROM global_lists,
(SELECT 'embedded-engineer' AS value, 1 AS sort_order UNION ALL
 SELECT 'SaaS',      2 UNION ALL
 SELECT 'hardware',  3 UNION ALL
 SELECT 'VC',        4 UNION ALL
 SELECT 'deep-tech', 5 UNION ALL
 SELECT 'consulting',6 UNION ALL
 SELECT 'founder',   7 UNION ALL
 SELECT 'investor',  8) v
WHERE global_lists.name = 'domain_tags';

-- ── person_clusters ────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS person_clusters (
    cluster_id       CHAR(36)     PRIMARY KEY,
    full_name        VARCHAR(200) NOT NULL,
    linkedin_url     VARCHAR(300),
    current_role     VARCHAR(200),
    current_company  VARCHAR(200),
    city             VARCHAR(100),
    notes            TEXT,
    last_updated_by  CHAR(36),
    last_updated_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (last_updated_by) REFERENCES org_members(member_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── cluster_emails ─────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS cluster_emails (
    email_id   CHAR(36)     PRIMARY KEY,
    cluster_id CHAR(36)     NOT NULL,
    email      VARCHAR(200) NOT NULL,
    label      ENUM('work','personal','college','other'),
    is_primary BOOLEAN      NOT NULL DEFAULT FALSE,
    added_by   CHAR(36),
    added_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cluster_id) REFERENCES person_clusters(cluster_id) ON DELETE CASCADE,
    UNIQUE KEY uq_cluster_email (cluster_id, email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── cluster_phones ─────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS cluster_phones (
    phone_id   CHAR(36)     PRIMARY KEY,
    cluster_id CHAR(36)     NOT NULL,
    phone      VARCHAR(50)  NOT NULL,
    label      ENUM('mobile','work','whatsapp','other'),
    is_primary BOOLEAN      NOT NULL DEFAULT FALSE,
    added_by   CHAR(36),
    added_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cluster_id) REFERENCES person_clusters(cluster_id) ON DELETE CASCADE,
    UNIQUE KEY uq_cluster_phone (cluster_id, phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── contacts (row-level) ───────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS contacts (
    contact_id          CHAR(36)     PRIMARY KEY,
    owner_member_id     CHAR(36)     NOT NULL,
    cluster_id          CHAR(36),
    space               ENUM('personal','shared') NOT NULL DEFAULT 'personal',
    origin_source       ENUM('gmail','linkedin','whatsapp','manual','adopted') NOT NULL DEFAULT 'manual',
    relationship_origin VARCHAR(300),
    relationship_type   CHAR(36),
    relationship_strength ENUM('close','acquaintance','distant'),
    notes               TEXT,
    private_notes       TEXT,
    added_at            TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_interacted_at  TIMESTAMP,
    FOREIGN KEY (owner_member_id) REFERENCES org_members(member_id),
    FOREIGN KEY (cluster_id)      REFERENCES person_clusters(cluster_id),
    FOREIGN KEY (relationship_type) REFERENCES global_list_values(value_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── education ──────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS education (
    edu_id      CHAR(36)     PRIMARY KEY,
    cluster_id  CHAR(36)     NOT NULL,
    institution VARCHAR(200) NOT NULL,
    is_notable  BOOLEAN      NOT NULL DEFAULT FALSE,
    degree      VARCHAR(100),
    field       VARCHAR(100),
    year_start  YEAR,
    year_end    YEAR,
    added_by    CHAR(36),
    added_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cluster_id) REFERENCES person_clusters(cluster_id) ON DELETE CASCADE,
    FOREIGN KEY (added_by)   REFERENCES org_members(member_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── experience ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS experience (
    exp_id      CHAR(36)     PRIMARY KEY,
    cluster_id  CHAR(36)     NOT NULL,
    company     VARCHAR(200) NOT NULL,
    role        VARCHAR(200),
    is_founder  BOOLEAN      NOT NULL DEFAULT FALSE,
    is_investor BOOLEAN      NOT NULL DEFAULT FALSE,
    year_start  YEAR,
    year_end    YEAR,
    added_by    CHAR(36),
    added_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cluster_id) REFERENCES person_clusters(cluster_id) ON DELETE CASCADE,
    FOREIGN KEY (added_by)   REFERENCES org_members(member_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── tags ───────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS contact_tags (
    tag_id     CHAR(36)     PRIMARY KEY,
    cluster_id CHAR(36)     NOT NULL,
    tag        VARCHAR(100) NOT NULL,
    added_by   CHAR(36),
    added_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cluster_id) REFERENCES person_clusters(cluster_id) ON DELETE CASCADE,
    UNIQUE KEY uq_cluster_tag (cluster_id, tag)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── duplicate_links ────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS duplicate_links (
    link_id          CHAR(36) PRIMARY KEY,
    contact_id_a     CHAR(36) NOT NULL,
    contact_id_b     CHAR(36) NOT NULL,
    status           ENUM('pending','confirmed','dismissed') NOT NULL DEFAULT 'pending',
    confirmed_by     CHAR(36),
    confirmed_at     TIMESTAMP,
    merged_cluster_id CHAR(36),
    FOREIGN KEY (contact_id_a)      REFERENCES contacts(contact_id),
    FOREIGN KEY (contact_id_b)      REFERENCES contacts(contact_id),
    FOREIGN KEY (confirmed_by)      REFERENCES org_members(member_id),
    FOREIGN KEY (merged_cluster_id) REFERENCES person_clusters(cluster_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── tasks ──────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS tasks (
    task_id     CHAR(36)     PRIMARY KEY,
    contact_id  CHAR(36)     NOT NULL,
    cluster_id  CHAR(36),
    created_by  CHAR(36)     NOT NULL,
    assigned_to CHAR(36)     NOT NULL,
    message     VARCHAR(500) NOT NULL,
    status      ENUM('open','done') NOT NULL DEFAULT 'open',
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resolved_at TIMESTAMP,
    FOREIGN KEY (contact_id)  REFERENCES contacts(contact_id),
    FOREIGN KEY (cluster_id)  REFERENCES person_clusters(cluster_id),
    FOREIGN KEY (created_by)  REFERENCES org_members(member_id),
    FOREIGN KEY (assigned_to) REFERENCES org_members(member_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── outreach_log ───────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS outreach_log (
    log_id          CHAR(36) PRIMARY KEY,
    contact_id      CHAR(36) NOT NULL,
    cluster_id      CHAR(36),
    sent_by         CHAR(36) NOT NULL,
    channel         ENUM('email','linkedin','whatsapp','phone','in-person','other') NOT NULL,
    sent_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    response_status ENUM('no-response','replied','meeting-set','not-relevant'),
    notes           TEXT,
    FOREIGN KEY (contact_id) REFERENCES contacts(contact_id),
    FOREIGN KEY (cluster_id) REFERENCES person_clusters(cluster_id),
    FOREIGN KEY (sent_by)    REFERENCES org_members(member_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;
