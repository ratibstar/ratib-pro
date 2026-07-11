-- RATEB ERP — Phase 16A Enterprise Accounting Platform (ONLINE FOUNDATION)
-- Additive only. Does not alter Offline Foundation / Baseline v1.2 / POS / Recruitment.
-- Extends existing GL tables; adds currencies, FX, tax codes, profit centers, recurring, opening balances, audit.
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- ---------------------------------------------------------------------------
-- Additive columns on existing journal entries (safe / idempotent)
-- ---------------------------------------------------------------------------
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_journal_entries' AND COLUMN_NAME = 'public_uuid');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_journal_entries ADD COLUMN public_uuid CHAR(36) NULL AFTER id, ADD UNIQUE KEY uq_je_uuid (public_uuid)', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_journal_entries' AND COLUMN_NAME = 'lifecycle_status');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_journal_entries ADD COLUMN lifecycle_status ENUM(''draft'',''balanced'',''posted'',''locked'',''reversed'',''archived'') NOT NULL DEFAULT ''draft'' AFTER status, ADD INDEX idx_je_lifecycle (company_id, lifecycle_status)', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_journal_entries' AND COLUMN_NAME = 'currency_code');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_journal_entries ADD COLUMN currency_code CHAR(3) NULL AFTER lifecycle_status', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_journal_entries' AND COLUMN_NAME = 'exchange_rate');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_journal_entries ADD COLUMN exchange_rate DECIMAL(18,8) NULL AFTER currency_code', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_journal_entries' AND COLUMN_NAME = 'profit_center_id');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_journal_entries ADD COLUMN profit_center_id INT UNSIGNED NULL AFTER exchange_rate', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_journal_entries' AND COLUMN_NAME = 'tax_code_id');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_journal_entries ADD COLUMN tax_code_id INT UNSIGNED NULL AFTER profit_center_id', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_journal_entries' AND COLUMN_NAME = 'is_opening_balance');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_journal_entries ADD COLUMN is_opening_balance TINYINT(1) NOT NULL DEFAULT 0 AFTER tax_code_id', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_journal_entries' AND COLUMN_NAME = 'locked_at');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_journal_entries ADD COLUMN locked_at DATETIME NULL AFTER is_opening_balance, ADD COLUMN locked_by INT UNSIGNED NULL AFTER locked_at', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_journal_entries' AND COLUMN_NAME = 'reversed_entry_id');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_journal_entries ADD COLUMN reversed_entry_id INT UNSIGNED NULL AFTER locked_by', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_journal_entries' AND COLUMN_NAME = 'archived_at');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_journal_entries ADD COLUMN archived_at DATETIME NULL AFTER reversed_entry_id', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_journal_entries' AND COLUMN_NAME = 'deleted_at');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_journal_entries ADD COLUMN deleted_at DATETIME NULL AFTER archived_at', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_journal_entries' AND COLUMN_NAME = 'updated_by');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_journal_entries ADD COLUMN updated_by INT UNSIGNED NULL AFTER created_by', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- Sync lifecycle from legacy status where still default draft
UPDATE rateb_journal_entries SET lifecycle_status = 'posted' WHERE status = 'posted' AND lifecycle_status = 'draft';
UPDATE rateb_journal_entries SET lifecycle_status = 'reversed' WHERE status = 'void' AND lifecycle_status = 'draft';

-- Soft delete on CoA / cost centers / fiscal (additive)
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_chart_of_accounts' AND COLUMN_NAME = 'public_uuid');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_chart_of_accounts ADD COLUMN public_uuid CHAR(36) NULL AFTER id', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_chart_of_accounts' AND COLUMN_NAME = 'deleted_at');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_chart_of_accounts ADD COLUMN deleted_at DATETIME NULL AFTER updated_at', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_chart_of_accounts' AND COLUMN_NAME = 'created_by');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_chart_of_accounts ADD COLUMN created_by INT UNSIGNED NULL AFTER is_active, ADD COLUMN updated_by INT UNSIGNED NULL AFTER created_by', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_cost_centers' AND COLUMN_NAME = 'deleted_at');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_cost_centers ADD COLUMN deleted_at DATETIME NULL AFTER updated_at', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_fiscal_periods' AND COLUMN_NAME = 'locked');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_fiscal_periods ADD COLUMN locked TINYINT(1) NOT NULL DEFAULT 0 AFTER status', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ---------------------------------------------------------------------------
-- Currencies
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS rateb_accounting_currencies (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_uuid CHAR(36) NOT NULL,
    company_id INT UNSIGNED NOT NULL,
    branch_id INT UNSIGNED NULL,
    code CHAR(3) NOT NULL,
    name VARCHAR(120) NOT NULL,
    name_ar VARCHAR(120) NULL,
    symbol VARCHAR(8) NULL,
    decimal_places TINYINT UNSIGNED NOT NULL DEFAULT 2,
    is_base TINYINT(1) NOT NULL DEFAULT 0,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    UNIQUE KEY uq_acc_cur_uuid (public_uuid),
    UNIQUE KEY uq_acc_cur_code (company_id, code),
    INDEX idx_acc_cur_company (company_id, deleted_at),
    CONSTRAINT fk_acc_cur_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Exchange rates
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS rateb_accounting_exchange_rates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_uuid CHAR(36) NOT NULL,
    company_id INT UNSIGNED NOT NULL,
    branch_id INT UNSIGNED NULL,
    from_currency CHAR(3) NOT NULL,
    to_currency CHAR(3) NOT NULL,
    rate DECIMAL(18,8) NOT NULL,
    rate_date DATE NOT NULL,
    source VARCHAR(80) NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    UNIQUE KEY uq_acc_fx_uuid (public_uuid),
    UNIQUE KEY uq_acc_fx_day (company_id, from_currency, to_currency, rate_date),
    INDEX idx_acc_fx_company (company_id, rate_date),
    CONSTRAINT fk_acc_fx_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Tax codes
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS rateb_accounting_tax_codes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_uuid CHAR(36) NOT NULL,
    company_id INT UNSIGNED NOT NULL,
    branch_id INT UNSIGNED NULL,
    code VARCHAR(40) NOT NULL,
    name VARCHAR(160) NOT NULL,
    name_ar VARCHAR(160) NULL,
    rate_percent DECIMAL(8,4) NOT NULL DEFAULT 0.0000,
    tax_type ENUM('vat','withholding','other') NOT NULL DEFAULT 'vat',
    recoverable TINYINT(1) NOT NULL DEFAULT 1,
    account_id INT UNSIGNED NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    UNIQUE KEY uq_acc_tax_uuid (public_uuid),
    UNIQUE KEY uq_acc_tax_code (company_id, code),
    INDEX idx_acc_tax_company (company_id, deleted_at),
    CONSTRAINT fk_acc_tax_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Profit centers
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS rateb_accounting_profit_centers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_uuid CHAR(36) NOT NULL,
    company_id INT UNSIGNED NOT NULL,
    branch_id INT UNSIGNED NULL,
    code VARCHAR(40) NOT NULL,
    name VARCHAR(160) NOT NULL,
    name_ar VARCHAR(160) NULL,
    parent_id INT UNSIGNED NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    UNIQUE KEY uq_acc_pc_uuid (public_uuid),
    UNIQUE KEY uq_acc_pc_code (company_id, code),
    INDEX idx_acc_pc_company (company_id, deleted_at),
    CONSTRAINT fk_acc_pc_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Recurring journals
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS rateb_accounting_recurring_journals (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_uuid CHAR(36) NOT NULL,
    company_id INT UNSIGNED NOT NULL,
    branch_id INT UNSIGNED NULL,
    code VARCHAR(40) NOT NULL,
    name VARCHAR(190) NOT NULL,
    name_ar VARCHAR(190) NULL,
    description TEXT NULL,
    frequency ENUM('daily','weekly','monthly','quarterly','yearly') NOT NULL DEFAULT 'monthly',
    next_run_date DATE NULL,
    end_date DATE NULL,
    currency_code CHAR(3) NULL,
    status ENUM('active','paused','inactive') NOT NULL DEFAULT 'active',
    last_generated_entry_id INT UNSIGNED NULL,
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    UNIQUE KEY uq_acc_rj_uuid (public_uuid),
    UNIQUE KEY uq_acc_rj_code (company_id, code),
    INDEX idx_acc_rj_company (company_id, status, deleted_at),
    CONSTRAINT fk_acc_rj_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_accounting_recurring_journal_lines (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    recurring_journal_id INT UNSIGNED NOT NULL,
    account_id INT UNSIGNED NOT NULL,
    cost_center_id INT UNSIGNED NULL,
    profit_center_id INT UNSIGNED NULL,
    debit DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    credit DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    memo VARCHAR(255) NULL,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_acc_rjl_parent (recurring_journal_id),
    CONSTRAINT fk_acc_rjl_parent FOREIGN KEY (recurring_journal_id) REFERENCES rateb_accounting_recurring_journals(id) ON DELETE CASCADE,
    CONSTRAINT fk_acc_rjl_account FOREIGN KEY (account_id) REFERENCES rateb_chart_of_accounts(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Status history / activities / attachment metadata
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS rateb_accounting_status_history (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    journal_entry_id INT UNSIGNED NOT NULL,
    from_status VARCHAR(40) NULL,
    to_status VARCHAR(40) NOT NULL,
    reason VARCHAR(500) NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_acc_hist_je (company_id, journal_entry_id),
    CONSTRAINT fk_acc_hist_je FOREIGN KEY (journal_entry_id) REFERENCES rateb_journal_entries(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_accounting_activities (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    journal_entry_id INT UNSIGNED NULL,
    entity_type VARCHAR(40) NOT NULL DEFAULT 'journal',
    entity_id INT UNSIGNED NULL,
    action VARCHAR(80) NOT NULL,
    summary VARCHAR(500) NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_acc_act_company (company_id, created_at),
    INDEX idx_acc_act_je (journal_entry_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_accounting_document_links (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_uuid CHAR(36) NOT NULL,
    company_id INT UNSIGNED NOT NULL,
    journal_entry_id INT UNSIGNED NULL,
    entity_type VARCHAR(40) NOT NULL DEFAULT 'journal',
    entity_id INT UNSIGNED NOT NULL,
    document_id INT UNSIGNED NULL,
    file_name VARCHAR(255) NULL,
    mime_type VARCHAR(120) NULL,
    notes VARCHAR(500) NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    UNIQUE KEY uq_acc_doc_uuid (public_uuid),
    INDEX idx_acc_doc_entity (company_id, entity_type, entity_id),
    CONSTRAINT fk_acc_doc_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Permissions (additive; existing accounting.view/manage/post retained)
INSERT INTO rateb_permissions (name, name_ar, slug, module, description, description_ar) VALUES
('Create Accounting', 'إنشاء محاسبة', 'accounting.create', 'accounting', 'Create drafts and master data', 'إنشاء مسودات والبيانات الأساسية'),
('Update Accounting', 'تحديث محاسبة', 'accounting.update', 'accounting', 'Update drafts and master data', 'تحديث المسودات والبيانات الأساسية'),
('Reverse Accounting', 'عكس قيود', 'accounting.reverse', 'accounting', 'Reverse posted journals', 'عكس القيود المرحلة'),
('Close Accounting Period', 'إقفال فترة', 'accounting.close_period', 'accounting', 'Close or lock fiscal periods', 'إقفال أو قفل الفترات المالية'),
('Accounting Admin', 'إدارة المحاسبة', 'accounting.admin', 'accounting', 'Full accounting administration', 'إدارة كاملة للمحاسبة')
ON DUPLICATE KEY UPDATE name = VALUES(name), name_ar = VALUES(name_ar), description = VALUES(description), description_ar = VALUES(description_ar);

INSERT IGNORE INTO rateb_role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM rateb_roles r
JOIN rateb_permissions p ON p.slug IN (
    'accounting.view','accounting.create','accounting.update','accounting.post',
    'accounting.reverse','accounting.close_period','accounting.admin','accounting.manage'
)
WHERE r.slug IN ('company-full-access', 'super-admin', 'accountant');
