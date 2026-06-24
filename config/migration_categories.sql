-- ============================================================
--  Category Management Migration
--  Run once against your existing database.
--  Adds is_system and is_hidden columns to budget_categories.
-- ============================================================

-- Add is_system flag (explicit, cleaner than relying solely on user_id IS NULL)
ALTER TABLE budget_categories
    ADD COLUMN is_system TINYINT(1) NOT NULL DEFAULT 0 AFTER is_active,
    ADD COLUMN is_hidden TINYINT(1) NOT NULL DEFAULT 0 AFTER is_system;

-- Back-fill: existing rows with user_id IS NULL are system categories
UPDATE budget_categories SET is_system = 1 WHERE user_id IS NULL;

-- Index for common queries
ALTER TABLE budget_categories
    ADD INDEX idx_cat_system  (is_system),
    ADD INDEX idx_cat_hidden  (is_hidden),
    ADD INDEX idx_cat_user    (user_id);
