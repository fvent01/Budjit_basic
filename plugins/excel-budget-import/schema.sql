-- ============================================================
--  Excel Budget Import Plugin — Schema
--  Run in phpMyAdmin > budjit > SQL tab
-- ============================================================

CREATE TABLE IF NOT EXISTS excel_import_sessions (
    id           INT UNSIGNED  NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id      INT UNSIGNED  NOT NULL,
    filename     VARCHAR(255)  NOT NULL,
    sheet_name   VARCHAR(150)  NOT NULL DEFAULT '',
    row_count    INT UNSIGNED  NOT NULL DEFAULT 0,
    imported     INT UNSIGNED  NOT NULL DEFAULT 0,
    skipped      INT UNSIGNED  NOT NULL DEFAULT 0,
    status       ENUM('pending','previewing','complete','failed') NOT NULL DEFAULT 'pending',
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_eis_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS excel_import_rows (
    id              INT UNSIGNED  NOT NULL AUTO_INCREMENT PRIMARY KEY,
    session_id      INT UNSIGNED  NOT NULL,
    user_id         INT UNSIGNED  NOT NULL,
    week_date       DATE          NOT NULL,
    record_type     ENUM('income','expense') NOT NULL,
    description     VARCHAR(200)  NOT NULL,
    amount          DECIMAL(12,2) NOT NULL,
    sheet_source    VARCHAR(100)  NOT NULL DEFAULT '',
    category_id     INT UNSIGNED,
    budget_id       INT UNSIGNED,
    mapped_as       ENUM('import','skip') NOT NULL DEFAULT 'import',
    is_imported     TINYINT(1)    NOT NULL DEFAULT 0,
    CONSTRAINT fk_eir_session  FOREIGN KEY (session_id)  REFERENCES excel_import_sessions(id) ON DELETE CASCADE,
    CONSTRAINT fk_eir_user     FOREIGN KEY (user_id)     REFERENCES users(id)                 ON DELETE CASCADE,
    CONSTRAINT fk_eir_category FOREIGN KEY (category_id) REFERENCES budget_categories(id)     ON DELETE SET NULL,
    INDEX idx_eir_session (session_id),
    INDEX idx_eir_date    (user_id, week_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
