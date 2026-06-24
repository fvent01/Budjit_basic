-- ============================================================
--  Budjit — Plugin Schema
--  Run this AFTER schema.sql (depends on users table)
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------
--  Savings Goals plugin
-- ------------------------------------------------------------
DROP TABLE IF EXISTS savings_contributions;
DROP TABLE IF EXISTS savings_goals;

CREATE TABLE savings_goals (
    id              INT UNSIGNED  NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED  NOT NULL,
    name            VARCHAR(150)  NOT NULL,
    icon            VARCHAR(60)   NOT NULL DEFAULT 'ti-piggy-bank',
    color           VARCHAR(20)   NOT NULL DEFAULT '#1D9E75',
    target_amount   DECIMAL(12,2) NOT NULL,
    current_amount  DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    target_date     DATE,
    priority        TINYINT UNSIGNED NOT NULL DEFAULT 0,   -- lower = higher priority
    auto_allocate   TINYINT(1)    NOT NULL DEFAULT 0,
    auto_percent    DECIMAL(5,2)  NOT NULL DEFAULT 0.00,   -- % of weekly income
    is_completed    TINYINT(1)    NOT NULL DEFAULT 0,
    notes           TEXT,
    created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_sg_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_sg_user_priority (user_id, priority)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE savings_contributions (
    id          INT UNSIGNED  NOT NULL AUTO_INCREMENT PRIMARY KEY,
    goal_id     INT UNSIGNED  NOT NULL,
    user_id     INT UNSIGNED  NOT NULL,
    amount      DECIMAL(12,2) NOT NULL,
    note        VARCHAR(255),
    source      ENUM('manual','auto') NOT NULL DEFAULT 'manual',
    contributed_at DATE NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_sc_goal FOREIGN KEY (goal_id)  REFERENCES savings_goals(id) ON DELETE CASCADE,
    CONSTRAINT fk_sc_user FOREIGN KEY (user_id)  REFERENCES users(id)         ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
--  Debt Payoff Tracker plugin
-- ------------------------------------------------------------
DROP TABLE IF EXISTS debt_payments;
DROP TABLE IF EXISTS debts;

CREATE TABLE debts (
    id              INT UNSIGNED  NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED  NOT NULL,
    name            VARCHAR(150)  NOT NULL,
    debt_type       ENUM('credit_card','student_loan','auto','medical','personal','other') NOT NULL DEFAULT 'other',
    balance         DECIMAL(12,2) NOT NULL,
    original_balance DECIMAL(12,2) NOT NULL,
    interest_rate   DECIMAL(5,2)  NOT NULL DEFAULT 0.00,  -- APR %
    minimum_payment DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    due_day         TINYINT UNSIGNED,                     -- day of month
    is_paid_off     TINYINT(1)    NOT NULL DEFAULT 0,
    notes           TEXT,
    created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_debt_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_debt_user (user_id, balance)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE debt_payments (
    id          INT UNSIGNED  NOT NULL AUTO_INCREMENT PRIMARY KEY,
    debt_id     INT UNSIGNED  NOT NULL,
    user_id     INT UNSIGNED  NOT NULL,
    amount      DECIMAL(12,2) NOT NULL,
    paid_date   DATE          NOT NULL,
    note        VARCHAR(255),
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_dp_debt FOREIGN KEY (debt_id) REFERENCES debts(id)  ON DELETE CASCADE,
    CONSTRAINT fk_dp_user FOREIGN KEY (user_id) REFERENCES users(id)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
--  Recurring Bills & Subscriptions plugin
-- ------------------------------------------------------------
DROP TABLE IF EXISTS recurring_bill_logs;
DROP TABLE IF EXISTS recurring_bills;

CREATE TABLE recurring_bills (
    id              INT UNSIGNED  NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED  NOT NULL,
    name            VARCHAR(150)  NOT NULL,
    category        VARCHAR(100)  NOT NULL DEFAULT 'Subscription',
    amount          DECIMAL(12,2) NOT NULL,
    frequency       ENUM('weekly','biweekly','monthly','quarterly','annually') NOT NULL DEFAULT 'monthly',
    due_day         TINYINT UNSIGNED NOT NULL DEFAULT 1,   -- day of month (or day of week 0-6)
    billing_url     VARCHAR(500),
    is_active       TINYINT(1)    NOT NULL DEFAULT 1,
    auto_pay        TINYINT(1)    NOT NULL DEFAULT 0,
    icon            VARCHAR(60)   NOT NULL DEFAULT 'ti-refresh',
    color           VARCHAR(20)   NOT NULL DEFAULT '#378ADD',
    notes           TEXT,
    created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_rb_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE recurring_bill_logs (
    id          INT UNSIGNED  NOT NULL AUTO_INCREMENT PRIMARY KEY,
    bill_id     INT UNSIGNED  NOT NULL,
    user_id     INT UNSIGNED  NOT NULL,
    amount      DECIMAL(12,2) NOT NULL,
    due_date    DATE          NOT NULL,
    paid_date   DATE,
    is_paid     TINYINT(1)    NOT NULL DEFAULT 0,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_rbl_bill FOREIGN KEY (bill_id) REFERENCES recurring_bills(id) ON DELETE CASCADE,
    CONSTRAINT fk_rbl_user FOREIGN KEY (user_id) REFERENCES users(id)           ON DELETE CASCADE,
    INDEX idx_rbl_due (user_id, due_date, is_paid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
--  Calendar & Reminders plugin
-- ------------------------------------------------------------
DROP TABLE IF EXISTS reminders;

CREATE TABLE reminders (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED NOT NULL,
    title           VARCHAR(200) NOT NULL,
    reminder_type   ENUM('bill','debt','goal','custom') NOT NULL DEFAULT 'custom',
    linked_id       INT UNSIGNED,     -- FK to bills/debts/goals — loose reference
    linked_type     VARCHAR(50),      -- 'recurring_bill', 'debt', 'savings_goal'
    remind_date     DATE NOT NULL,
    remind_days_before TINYINT UNSIGNED NOT NULL DEFAULT 3,
    channels        SET('inapp','email','push') NOT NULL DEFAULT 'inapp',
    is_sent         TINYINT(1)   NOT NULL DEFAULT 0,
    is_dismissed    TINYINT(1)   NOT NULL DEFAULT 0,
    notes           TEXT,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_rem_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_rem_date (user_id, remind_date, is_sent)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Push notification subscriptions (browser push)
DROP TABLE IF EXISTS push_subscriptions;
CREATE TABLE push_subscriptions (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    endpoint    TEXT         NOT NULL,
    p256dh_key  TEXT         NOT NULL,
    auth_key    TEXT         NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_ps_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
--  Bank Import plugin
-- ------------------------------------------------------------
DROP TABLE IF EXISTS import_transaction_map;
DROP TABLE IF EXISTS import_transactions;
DROP TABLE IF EXISTS import_sessions;
DROP TABLE IF EXISTS plaid_accounts;

CREATE TABLE plaid_accounts (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED NOT NULL,
    access_token    VARCHAR(255) NOT NULL,   -- encrypted at rest
    item_id         VARCHAR(255) NOT NULL,
    institution_name VARCHAR(150),
    account_name    VARCHAR(150),
    account_mask    VARCHAR(10),
    account_type    VARCHAR(50),
    is_active       TINYINT(1)  NOT NULL DEFAULT 1,
    last_synced     DATETIME,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_pa_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE import_sessions (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    source      ENUM('plaid','excel','csv') NOT NULL,
    filename    VARCHAR(255),
    row_count   INT UNSIGNED NOT NULL DEFAULT 0,
    imported    INT UNSIGNED NOT NULL DEFAULT 0,
    skipped     INT UNSIGNED NOT NULL DEFAULT 0,
    status      ENUM('pending','processing','complete','failed') NOT NULL DEFAULT 'pending',
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_is2_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE import_transactions (
    id              INT UNSIGNED  NOT NULL AUTO_INCREMENT PRIMARY KEY,
    session_id      INT UNSIGNED  NOT NULL,
    user_id         INT UNSIGNED  NOT NULL,
    raw_date        DATE          NOT NULL,
    raw_description VARCHAR(255)  NOT NULL,
    raw_amount      DECIMAL(12,2) NOT NULL,
    raw_type        ENUM('debit','credit') NOT NULL DEFAULT 'debit',
    plaid_txn_id    VARCHAR(255),
    category_id     INT UNSIGNED,
    budget_id       INT UNSIGNED,
    mapped_as       ENUM('expense','income','skip') NOT NULL DEFAULT 'expense',
    is_imported     TINYINT(1)    NOT NULL DEFAULT 0,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_it_session  FOREIGN KEY (session_id)  REFERENCES import_sessions(id)      ON DELETE CASCADE,
    CONSTRAINT fk_it_user     FOREIGN KEY (user_id)     REFERENCES users(id)                ON DELETE CASCADE,
    CONSTRAINT fk_it_category FOREIGN KEY (category_id) REFERENCES budget_categories(id)   ON DELETE SET NULL,
    CONSTRAINT fk_it_budget   FOREIGN KEY (budget_id)   REFERENCES budgets(id)              ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
