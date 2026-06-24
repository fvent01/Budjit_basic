-- ============================================================
--  Migration: Unified Financial Import System
--  Replaces: bank-import plugin + excel-budget-import plugin
--  New module: financial-import
--
--  Run order:
--    1. This file (migration_financial_import.sql)
--
--  Safe to run on an existing database:
--    - All CREATE TABLE use IF NOT EXISTS
--    - All ALTER TABLE use ADD COLUMN IF NOT EXISTS
--    - Data migration is INSERT IGNORE (re-runnable, no duplicates)
--    - Old tables are kept; optional DROP block at the bottom
--
--  Reserved words backticked throughout:
--    `cursor`, `date`, `name`, `status`
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

-- ============================================================
--  STEP 1: Fix plaid_accounts schema
--  The original schema used 'access_token' but the model code
--  references 'access_token_enc'. This migration reconciles both.
--  Also adds missing columns for cursor-based sync.
-- ============================================================

ALTER TABLE plaid_accounts
    ADD COLUMN IF NOT EXISTS account_id       VARCHAR(255)  NULL    COMMENT 'Plaid account_id (stable per institution account)',
    ADD COLUMN IF NOT EXISTS account_subtype  VARCHAR(50)   NULL,
    ADD COLUMN IF NOT EXISTS institution_id   VARCHAR(100)  NULL,
    ADD COLUMN IF NOT EXISTS `cursor`         VARCHAR(512)  NULL    COMMENT 'Plaid /transactions/sync cursor',
    ADD COLUMN IF NOT EXISTS first_synced     DATETIME      NULL    COMMENT 'Timestamp of initial full sync',
    ADD COLUMN IF NOT EXISTS error_code       VARCHAR(100)  NULL    COMMENT 'Most recent Plaid error code',
    ADD COLUMN IF NOT EXISTS error_message    TEXT          NULL    COMMENT 'Most recent Plaid error message',
    ADD COLUMN IF NOT EXISTS updated_at       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Note: schema already uses access_token_enc exclusively — no data copy needed.

-- Unique constraint on Plaid's stable account_id.
-- Safe to ignore if the constraint already exists.
ALTER IGNORE TABLE plaid_accounts
    ADD UNIQUE KEY uq_plaid_account_id (account_id);

-- ============================================================
--  STEP 2: Create plaid_sync_log (if not already present)
-- ============================================================

CREATE TABLE IF NOT EXISTS plaid_sync_log (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id      INT UNSIGNED NOT NULL,
    account_id   INT UNSIGNED NULL,
    trigger_type VARCHAR(50)  NOT NULL DEFAULT 'manual',
    added        INT          NOT NULL DEFAULT 0,
    modified     INT          NOT NULL DEFAULT 0,
    removed      INT          NOT NULL DEFAULT 0,
    error        TEXT         NULL,
    duration_ms  INT          NOT NULL DEFAULT 0,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_psl_user    FOREIGN KEY (user_id)    REFERENCES users(id)          ON DELETE CASCADE,
    CONSTRAINT fk_psl_account FOREIGN KEY (account_id) REFERENCES plaid_accounts(id) ON DELETE SET NULL,
    INDEX idx_psl_user (user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  STEP 3: Create unified financial_import_sessions table
-- ============================================================

CREATE TABLE IF NOT EXISTS financial_import_sessions (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id      INT UNSIGNED NOT NULL,
    source       ENUM('plaid','csv','excel') NOT NULL,
    filename     VARCHAR(255) NULL,
    total_rows   INT UNSIGNED NOT NULL DEFAULT 0,
    imported     INT UNSIGNED NOT NULL DEFAULT 0,
    duplicates   INT UNSIGNED NOT NULL DEFAULT 0,
    failed       INT UNSIGNED NOT NULL DEFAULT 0,
    status       ENUM('pending','processing','complete','failed') NOT NULL DEFAULT 'pending',
    errors       TEXT         NULL COMMENT 'Pipe-delimited row-level error summary',
    completed_at DATETIME     NULL,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_fis_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_fis_user_created (user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  STEP 4: Create unified transactions table
--  Deduplication enforced by UNIQUE on (external_id, user_id, source).
-- ============================================================

CREATE TABLE IF NOT EXISTS transactions (
    id                INT UNSIGNED  NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id           INT UNSIGNED  NOT NULL,
    import_session_id INT UNSIGNED  NULL COMMENT 'FK to financial_import_sessions',
    account_id        INT UNSIGNED  NULL COMMENT 'FK to plaid_accounts for Plaid rows',
    external_id       VARCHAR(512)  NOT NULL COMMENT 'plaid:txn_id or sha256 hash for file imports',
    source            ENUM('plaid','csv','excel') NOT NULL,
    amount            DECIMAL(12,2) NOT NULL,
    `date`            DATE          NOT NULL,
    `name`            VARCHAR(255)  NOT NULL,
    merchant          VARCHAR(255)  NULL,
    category          VARCHAR(255)  NULL COMMENT 'Raw category string from source',
    category_id       INT UNSIGNED  NULL COMMENT 'Mapped budget_categories.id',
    pending           TINYINT(1)    NOT NULL DEFAULT 0 COMMENT '1 = Plaid pending flag',
    mapped_as         ENUM('expense','income','skip') NOT NULL DEFAULT 'expense',
    budget_id         INT UNSIGNED  NULL,
    `status`          ENUM('pending_review','imported','skipped') NOT NULL DEFAULT 'pending_review',
    created_at        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_txn_external (external_id, user_id, source),
    CONSTRAINT fk_txn_user     FOREIGN KEY (user_id)           REFERENCES users(id)                    ON DELETE CASCADE,
    CONSTRAINT fk_txn_session  FOREIGN KEY (import_session_id) REFERENCES financial_import_sessions(id) ON DELETE SET NULL,
    CONSTRAINT fk_txn_account  FOREIGN KEY (account_id)        REFERENCES plaid_accounts(id)            ON DELETE SET NULL,
    CONSTRAINT fk_txn_category FOREIGN KEY (category_id)       REFERENCES budget_categories(id)         ON DELETE SET NULL,
    CONSTRAINT fk_txn_budget   FOREIGN KEY (budget_id)         REFERENCES budgets(id)                   ON DELETE SET NULL,
    INDEX idx_txn_user_date (user_id, `date`),
    INDEX idx_txn_status    (user_id, `status`),
    INDEX idx_txn_account   (account_id),
    INDEX idx_txn_session   (import_session_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  STEP 5: Create plaid_transactions stub (fresh installs)
--  then migrate existing rows → unified transactions table.
-- ============================================================

CREATE TABLE IF NOT EXISTS plaid_transactions (
    id                INT UNSIGNED  NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id           INT UNSIGNED  NOT NULL,
    plaid_account_id  INT UNSIGNED  NOT NULL,
    plaid_txn_id      VARCHAR(255)  NOT NULL,
    amount            DECIMAL(12,2) NOT NULL,
    `date`            DATE          NOT NULL,
    `name`            VARCHAR(255)  NOT NULL DEFAULT '',
    merchant_name     VARCHAR(255)  NULL,
    plaid_category    VARCHAR(255)  NULL,
    plaid_category_id VARCHAR(100)  NULL,
    payment_channel   VARCHAR(50)   NULL,
    pending           TINYINT(1)    NOT NULL DEFAULT 0,
    category_id       INT UNSIGNED  NULL,
    mapped_as         ENUM('expense','income','skip') NOT NULL DEFAULT 'expense',
    budget_id         INT UNSIGNED  NULL,
    `status`          ENUM('pending_review','imported','skipped') NOT NULL DEFAULT 'pending_review',
    imported_at       DATETIME      NULL,
    created_at        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_pt_user    FOREIGN KEY (user_id)          REFERENCES users(id)          ON DELETE CASCADE,
    CONSTRAINT fk_pt_account FOREIGN KEY (plaid_account_id) REFERENCES plaid_accounts(id) ON DELETE CASCADE,
    UNIQUE KEY uq_plaid_txn (plaid_txn_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migrate plaid_transactions → transactions (INSERT IGNORE = safe to re-run)
INSERT IGNORE INTO transactions
    (user_id, account_id, external_id, source,
     amount, `date`, `name`, merchant, category, category_id,
     pending, mapped_as, budget_id, `status`, created_at, updated_at)
SELECT
    pt.user_id,
    pt.plaid_account_id                 AS account_id,
    CONCAT('plaid:', pt.plaid_txn_id)  AS external_id,
    'plaid'                             AS source,
    pt.amount,
    pt.`date`,
    pt.`name`,
    pt.merchant_name                    AS merchant,
    pt.plaid_category                   AS category,
    pt.category_id,
    pt.pending,
    pt.mapped_as,
    pt.budget_id,
    pt.`status`,
    pt.created_at,
    pt.created_at                       AS updated_at
FROM plaid_transactions pt;

-- ============================================================
--  STEP 6: Migrate import_sessions + import_transactions
--  (CSV/Excel rows from the old bank-import plugin)
-- ============================================================

CREATE TABLE IF NOT EXISTS import_sessions (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    source     ENUM('plaid','excel','csv') NOT NULL,
    filename   VARCHAR(255) NULL,
    row_count  INT UNSIGNED NOT NULL DEFAULT 0,
    imported   INT UNSIGNED NOT NULL DEFAULT 0,
    skipped    INT UNSIGNED NOT NULL DEFAULT 0,
    `status`   ENUM('pending','processing','complete','failed') NOT NULL DEFAULT 'pending',
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_is_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS import_transactions (
    id              INT UNSIGNED  NOT NULL AUTO_INCREMENT PRIMARY KEY,
    session_id      INT UNSIGNED  NOT NULL,
    user_id         INT UNSIGNED  NOT NULL,
    raw_date        DATE          NOT NULL,
    raw_description VARCHAR(255)  NOT NULL,
    raw_amount      DECIMAL(12,2) NOT NULL,
    raw_type        ENUM('debit','credit') NOT NULL DEFAULT 'debit',
    plaid_txn_id    VARCHAR(255)  NULL,
    category_id     INT UNSIGNED  NULL,
    budget_id       INT UNSIGNED  NULL,
    mapped_as       ENUM('expense','income','skip') NOT NULL DEFAULT 'expense',
    is_imported     TINYINT(1)    NOT NULL DEFAULT 0,
    created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_it_session FOREIGN KEY (session_id) REFERENCES import_sessions(id) ON DELETE CASCADE,
    CONSTRAINT fk_it_user    FOREIGN KEY (user_id)    REFERENCES users(id)            ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migrate import_sessions → financial_import_sessions
INSERT IGNORE INTO financial_import_sessions
    (id, user_id, source, filename, total_rows, imported, duplicates, failed, `status`, completed_at, created_at)
SELECT
    is2.id,
    is2.user_id,
    is2.source,
    is2.filename,
    is2.row_count AS total_rows,
    is2.imported,
    0             AS duplicates,
    is2.skipped   AS failed,
    CASE is2.`status`
        WHEN 'complete' THEN 'complete'
        WHEN 'failed'   THEN 'failed'
        ELSE 'complete'
    END           AS `status`,
    is2.created_at AS completed_at,
    is2.created_at
FROM import_sessions is2
WHERE NOT EXISTS (
    SELECT 1 FROM financial_import_sessions fis WHERE fis.id = is2.id
);

-- Migrate import_transactions → transactions
INSERT IGNORE INTO transactions
    (user_id, import_session_id, external_id, source,
     amount, `date`, `name`, category_id, pending,
     mapped_as, budget_id, `status`, created_at, updated_at)
SELECT
    it.user_id,
    it.session_id AS import_session_id,
    CONCAT('file:', SHA2(CONCAT(
        COALESCE(s.source, 'csv'), '|',
        it.user_id,               '|',
        it.raw_date,              '|',
        it.raw_description,       '|',
        it.raw_amount
    ), 256))      AS external_id,
    COALESCE(s.source, 'csv') AS source,
    it.raw_amount AS amount,
    it.raw_date   AS `date`,
    it.raw_description AS `name`,
    it.category_id,
    0             AS pending,
    it.mapped_as,
    it.budget_id,
    CASE it.is_imported WHEN 1 THEN 'imported' ELSE 'pending_review' END AS `status`,
    it.created_at,
    it.created_at AS updated_at
FROM import_transactions it
LEFT JOIN import_sessions s ON s.id = it.session_id;

-- ============================================================
--  STEP 7: Migrate excel_import_sessions + excel_import_rows
-- ============================================================

CREATE TABLE IF NOT EXISTS excel_import_sessions (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    filename   VARCHAR(255) NOT NULL,
    sheet_name VARCHAR(150) NOT NULL DEFAULT '',
    row_count  INT UNSIGNED NOT NULL DEFAULT 0,
    imported   INT UNSIGNED NOT NULL DEFAULT 0,
    skipped    INT UNSIGNED NOT NULL DEFAULT 0,
    `status`   ENUM('pending','previewing','complete','failed') NOT NULL DEFAULT 'pending',
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_eis_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS excel_import_rows (
    id           INT UNSIGNED  NOT NULL AUTO_INCREMENT PRIMARY KEY,
    session_id   INT UNSIGNED  NOT NULL,
    user_id      INT UNSIGNED  NOT NULL,
    week_date    DATE          NOT NULL,
    record_type  ENUM('income','expense') NOT NULL,
    description  VARCHAR(200)  NOT NULL,
    amount       DECIMAL(12,2) NOT NULL,
    sheet_source VARCHAR(100)  NOT NULL DEFAULT '',
    category_id  INT UNSIGNED  NULL,
    budget_id    INT UNSIGNED  NULL,
    mapped_as    ENUM('import','skip') NOT NULL DEFAULT 'import',
    is_imported  TINYINT(1)    NOT NULL DEFAULT 0,
    CONSTRAINT fk_eir_session FOREIGN KEY (session_id) REFERENCES excel_import_sessions(id) ON DELETE CASCADE,
    CONSTRAINT fk_eir_user    FOREIGN KEY (user_id)    REFERENCES users(id)                  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migrate excel_import_sessions → financial_import_sessions
INSERT IGNORE INTO financial_import_sessions
    (user_id, source, filename, total_rows, imported, duplicates, failed, `status`, completed_at, created_at)
SELECT
    eis.user_id,
    'excel'       AS source,
    eis.filename,
    eis.row_count AS total_rows,
    eis.imported,
    0             AS duplicates,
    eis.skipped   AS failed,
    CASE eis.`status`
        WHEN 'complete'   THEN 'complete'
        WHEN 'failed'     THEN 'failed'
        WHEN 'previewing' THEN 'complete'
        ELSE 'complete'
    END           AS `status`,
    eis.created_at AS completed_at,
    eis.created_at
FROM excel_import_sessions eis
WHERE NOT EXISTS (
    SELECT 1 FROM financial_import_sessions fis
    WHERE fis.user_id    = eis.user_id
      AND fis.source     = 'excel'
      AND fis.filename   = eis.filename
      AND fis.created_at = eis.created_at
);

-- Migrate excel_import_rows → transactions
INSERT IGNORE INTO transactions
    (user_id, external_id, source, amount, `date`, `name`,
     category_id, pending, mapped_as, budget_id, `status`, created_at, updated_at)
SELECT
    eir.user_id,
    CONCAT('file:', SHA2(CONCAT(
        'excel',          '|',
        eir.user_id,      '|',
        eir.week_date,    '|',
        eir.description,  '|',
        eir.amount
    ), 256))  AS external_id,
    'excel'   AS source,
    eir.amount,
    eir.week_date AS `date`,
    eir.description AS `name`,
    eir.category_id,
    0         AS pending,
    CASE eir.record_type WHEN 'income' THEN 'income' ELSE 'expense' END AS mapped_as,
    eir.budget_id,
    CASE eir.is_imported WHEN 1 THEN 'imported' ELSE 'pending_review' END AS `status`,
    NOW()     AS created_at,
    NOW()     AS updated_at
FROM excel_import_rows eir;

-- ============================================================
--  STEP 8: Register financial-import in plugins table
-- ============================================================

INSERT INTO plugins (slug, name, version, is_enabled, installed_at)
VALUES ('financial-import', 'Financial Import', '1.0.0', 1, NOW())
ON DUPLICATE KEY UPDATE is_enabled = 1, version = '1.0.0';

-- Disable the legacy plugin records
UPDATE plugins SET is_enabled = 0 WHERE slug IN ('bank-import', 'excel-budget-import');

-- ============================================================
--  STEP 9: Verification queries (run manually to confirm)
-- ============================================================

-- SELECT source, COUNT(*) AS total, SUM(amount) AS volume
-- FROM transactions GROUP BY source;

-- SELECT user_id, COUNT(*) AS pending
-- FROM transactions WHERE `status`='pending_review' GROUP BY user_id;

-- SELECT source, COUNT(*) AS sessions, SUM(imported) AS imported
-- FROM financial_import_sessions GROUP BY source;

-- SELECT COUNT(*) AS orphans FROM transactions t
-- LEFT JOIN users u ON u.id = t.user_id WHERE u.id IS NULL;

-- ============================================================
--  STEP 10 (OPTIONAL): Drop legacy tables after verification
--  WARNING: Only run after confirming all data migrated correctly.
-- ============================================================

-- DROP TABLE IF EXISTS excel_import_rows;
-- DROP TABLE IF EXISTS excel_import_sessions;
-- DROP TABLE IF EXISTS import_transactions;
-- DROP TABLE IF EXISTS import_sessions;
-- DROP TABLE IF EXISTS plaid_transactions;

SET FOREIGN_KEY_CHECKS = 1;
