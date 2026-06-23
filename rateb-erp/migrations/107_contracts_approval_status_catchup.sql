-- RATEB ERP — contracts approval_status + related columns catch-up (idempotent; fixes [approval_status])
SET NAMES utf8mb4;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_contracts' AND COLUMN_NAME = 'renewal_date');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_contracts ADD COLUMN renewal_date DATE NULL AFTER end_date',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_contracts' AND COLUMN_NAME = 'alert_days');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_contracts ADD COLUMN alert_days INT UNSIGNED NOT NULL DEFAULT 30 AFTER renewal_date',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_contracts' AND COLUMN_NAME = 'approval_status');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_contracts ADD COLUMN approval_status ENUM(''draft'',''pending'',''approved'',''rejected'') NOT NULL DEFAULT ''draft'' AFTER status',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_contracts' AND COLUMN_NAME = 'signature_status');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_contracts ADD COLUMN signature_status ENUM(''none'',''pending'',''partial'',''signed'') NOT NULL DEFAULT ''none'' AFTER approval_status',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_contracts' AND COLUMN_NAME = 'signature_trail');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_contracts ADD COLUMN signature_trail JSON NULL AFTER signature_status',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS rateb_contract_renewals (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    contract_id INT UNSIGNED NOT NULL,
    renewal_date DATE NOT NULL,
    new_end_date DATE NULL,
    new_value DECIMAL(14,2) NULL,
    status ENUM('planned','completed','cancelled') NOT NULL DEFAULT 'planned',
    notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_cr_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_cr_contract FOREIGN KEY (contract_id) REFERENCES rateb_contracts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
