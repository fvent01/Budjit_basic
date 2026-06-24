-- ============================================================
--  Budjit — Full Phase 1 Schema
--  MySQL 8+ / MariaDB 10.4+
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

-- ------------------------------------------------------------
--  Drop tables in safe order
-- ------------------------------------------------------------
DROP TABLE IF EXISTS sessions;
DROP TABLE IF EXISTS expense_notes;
DROP TABLE IF EXISTS expenses;
DROP TABLE IF EXISTS recurring_expenses;
DROP TABLE IF EXISTS income_entries;
DROP TABLE IF EXISTS income_sources;
DROP TABLE IF EXISTS budget_items;
DROP TABLE IF EXISTS budget_categories;
DROP TABLE IF EXISTS budgets;
DROP TABLE IF EXISTS user_preferences;
DROP TABLE IF EXISTS settings;
DROP TABLE IF EXISTS plugins;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS roles;

SET FOREIGN_KEY_CHECKS = 1;

-- ------------------------------------------------------------
--  Roles
-- ------------------------------------------------------------
CREATE TABLE roles (
    id        TINYINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name      VARCHAR(50)      NOT NULL UNIQUE,
    label     VARCHAR(100)     NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO roles (name, label) VALUES
    ('admin',   'Administrator'),
    ('parent',  'Parent / User'),
    ('viewer',  'Read-only Viewer');

-- ------------------------------------------------------------
--  Users
-- ------------------------------------------------------------
CREATE TABLE users (
    id            INT UNSIGNED     NOT NULL AUTO_INCREMENT PRIMARY KEY,
    role_id       TINYINT UNSIGNED NOT NULL DEFAULT 2,
    first_name    VARCHAR(80)      NOT NULL,
    last_name     VARCHAR(80)      NOT NULL,
    email         VARCHAR(191)     NOT NULL UNIQUE,
    password_hash VARCHAR(255)     NOT NULL,
    is_active     TINYINT(1)       NOT NULL DEFAULT 1,
    created_at    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
--  Sessions  (server-side session store — optional but secure)
-- ------------------------------------------------------------
CREATE TABLE sessions (
    id         VARCHAR(128)     NOT NULL PRIMARY KEY,
    user_id    INT UNSIGNED     NOT NULL,
    ip_address VARCHAR(45)      NOT NULL DEFAULT '',
    user_agent TEXT,
    payload    TEXT             NOT NULL,
    created_at DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_activity DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_sessions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
--  Settings  (global, key-value)
-- ------------------------------------------------------------
CREATE TABLE settings (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    value       TEXT,
    updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO settings (setting_key, value) VALUES
    ('currency',         'USD'),
    ('currency_symbol',  '$'),
    ('week_start_day',   'monday'),
    ('app_name',         'Budjit'),
    ('theme',            'light');

-- ------------------------------------------------------------
--  User Preferences
-- ------------------------------------------------------------
CREATE TABLE user_preferences (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id      INT UNSIGNED NOT NULL,
    pref_key     VARCHAR(100) NOT NULL,
    value        TEXT,
    UNIQUE KEY uq_user_pref (user_id, pref_key),
    CONSTRAINT fk_prefs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
--  Budget Categories
-- ------------------------------------------------------------
CREATE TABLE budget_categories (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED,                        -- NULL = system default
    name       VARCHAR(100) NOT NULL,
    icon       VARCHAR(60)  NOT NULL DEFAULT 'ti-wallet',
    color      VARCHAR(20)  NOT NULL DEFAULT '#1D9E75',
    sort_order TINYINT UNSIGNED NOT NULL DEFAULT 0,
    is_active  TINYINT(1)   NOT NULL DEFAULT 1,
    CONSTRAINT fk_cats_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO budget_categories (user_id, name, icon, color, sort_order) VALUES
    (NULL, 'Housing',       'ti-building',         '#1D9E75', 1),
    (NULL, 'Utilities',     'ti-bolt',             '#378ADD', 2),
    (NULL, 'Food',          'ti-shopping-cart',    '#EF9F27', 3),
    (NULL, 'Fuel',          'ti-gas-station',      '#E24B4A', 4),
    (NULL, 'Savings',       'ti-piggy-bank',       '#9FE1CB', 5),
    (NULL, 'Entertainment', 'ti-device-gamepad-2', '#D4537E', 6),
    (NULL, 'Kids',          'ti-baby-carriage',    '#7F77DD', 7),
    (NULL, 'Emergency Fund','ti-shield',           '#185FA5', 8),
    (NULL, 'Healthcare',    'ti-heart-rate-monitor','#639922', 9),
    (NULL, 'Other',         'ti-dots',             '#888780', 10);

-- ------------------------------------------------------------
--  Budgets
-- ------------------------------------------------------------
CREATE TABLE budgets (
    id           INT UNSIGNED    NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id      INT UNSIGNED    NOT NULL,
    title        VARCHAR(150)    NOT NULL,
    period_type  ENUM('weekly','monthly') NOT NULL DEFAULT 'weekly',
    start_date   DATE            NOT NULL,
    end_date     DATE            NOT NULL,
    total_income DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
    total_budget DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
    status       ENUM('active','archived','draft') NOT NULL DEFAULT 'active',
    notes        TEXT,
    created_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_budgets_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_budgets_user_dates (user_id, start_date, end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
--  Budget Items  (category allocations within a budget)
-- ------------------------------------------------------------
CREATE TABLE budget_items (
    id          INT UNSIGNED  NOT NULL AUTO_INCREMENT PRIMARY KEY,
    budget_id   INT UNSIGNED  NOT NULL,
    category_id INT UNSIGNED  NOT NULL,
    allocated   DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    notes       VARCHAR(255),
    CONSTRAINT fk_bi_budget   FOREIGN KEY (budget_id)   REFERENCES budgets(id)           ON DELETE CASCADE,
    CONSTRAINT fk_bi_category FOREIGN KEY (category_id) REFERENCES budget_categories(id) ON DELETE RESTRICT,
    UNIQUE KEY uq_budget_category (budget_id, category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
--  Income Sources  (reusable source definitions)
-- ------------------------------------------------------------
CREATE TABLE income_sources (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    name        VARCHAR(100) NOT NULL,
    source_type ENUM('salary','freelance','side_job','benefit','child_support','other') NOT NULL DEFAULT 'other',
    is_recurring TINYINT(1)  NOT NULL DEFAULT 0,
    frequency   ENUM('weekly','biweekly','monthly','one_time') NOT NULL DEFAULT 'one_time',
    default_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    is_active   TINYINT(1)   NOT NULL DEFAULT 1,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_is_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
--  Income Entries  (actual income records, optionally linked to a budget)
-- ------------------------------------------------------------
CREATE TABLE income_entries (
    id               INT UNSIGNED  NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id          INT UNSIGNED  NOT NULL,
    budget_id        INT UNSIGNED,
    income_source_id INT UNSIGNED,
    description      VARCHAR(200)  NOT NULL,
    amount           DECIMAL(12,2) NOT NULL,
    received_date    DATE          NOT NULL,
    is_recurring     TINYINT(1)    NOT NULL DEFAULT 0,
    notes            TEXT,
    created_at       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_ie_user   FOREIGN KEY (user_id)          REFERENCES users(id)          ON DELETE CASCADE,
    CONSTRAINT fk_ie_budget FOREIGN KEY (budget_id)        REFERENCES budgets(id)        ON DELETE SET NULL,
    CONSTRAINT fk_ie_source FOREIGN KEY (income_source_id) REFERENCES income_sources(id) ON DELETE SET NULL,
    INDEX idx_ie_user_date (user_id, received_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
--  Expenses
-- ------------------------------------------------------------
CREATE TABLE expenses (
    id          INT UNSIGNED  NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED  NOT NULL,
    budget_id   INT UNSIGNED,
    category_id INT UNSIGNED  NOT NULL,
    description VARCHAR(200)  NOT NULL,
    amount      DECIMAL(12,2) NOT NULL,
    expense_date DATE         NOT NULL,
    is_paid     TINYINT(1)    NOT NULL DEFAULT 0,
    is_recurring TINYINT(1)   NOT NULL DEFAULT 0,
    tags        VARCHAR(255),
    notes       TEXT,
    created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_exp_user     FOREIGN KEY (user_id)     REFERENCES users(id)             ON DELETE CASCADE,
    CONSTRAINT fk_exp_budget   FOREIGN KEY (budget_id)   REFERENCES budgets(id)           ON DELETE SET NULL,
    CONSTRAINT fk_exp_category FOREIGN KEY (category_id) REFERENCES budget_categories(id) ON DELETE RESTRICT,
    INDEX idx_exp_user_date (user_id, expense_date),
    INDEX idx_exp_budget    (budget_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
--  Recurring Expenses  (templates for auto-generating expenses)
-- ------------------------------------------------------------
CREATE TABLE recurring_expenses (
    id          INT UNSIGNED  NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED  NOT NULL,
    category_id INT UNSIGNED  NOT NULL,
    description VARCHAR(200)  NOT NULL,
    amount      DECIMAL(12,2) NOT NULL,
    frequency   ENUM('weekly','biweekly','monthly') NOT NULL DEFAULT 'monthly',
    due_day     TINYINT UNSIGNED,   -- day of month (1-31) or NULL for weekly
    is_active   TINYINT(1)    NOT NULL DEFAULT 1,
    last_generated DATE,
    created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_re_user     FOREIGN KEY (user_id)     REFERENCES users(id)             ON DELETE CASCADE,
    CONSTRAINT fk_re_category FOREIGN KEY (category_id) REFERENCES budget_categories(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
--  Plugins registry
-- ------------------------------------------------------------
CREATE TABLE plugins (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    slug         VARCHAR(100) NOT NULL UNIQUE,
    name         VARCHAR(150) NOT NULL,
    version      VARCHAR(20)  NOT NULL DEFAULT '1.0.0',
    author       VARCHAR(150),
    homepage     VARCHAR(500),
    is_enabled   TINYINT(1)   NOT NULL DEFAULT 0,
    is_third_party TINYINT(1) NOT NULL DEFAULT 0,
    installed_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
--  Migrations tracker
--  Records which .sql migration files have been applied.
-- ------------------------------------------------------------
CREATE TABLE migrations (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    migration   VARCHAR(255) NOT NULL UNIQUE,   -- filename, e.g. "20260101_001_add_indexes.sql"
    source      VARCHAR(50)  NOT NULL DEFAULT 'core', -- 'core' or plugin slug
    ran_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
