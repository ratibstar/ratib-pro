-- RATEB ERP — cost centers + journal line cost_center_id catch-up (idempotent; fixes [l.cost_center_id])
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS rateb_cost_centers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    code VARCHAR(20) NOT NULL,
    name VARCHAR(200) NOT NULL,
    name_ar VARCHAR(200) NULL,
    parent_id INT UNSIGNED NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_cc_company_code (company_id, code),
    INDEX idx_cc_company (company_id),
    CONSTRAINT fk_cc_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_journal_lines' AND COLUMN_NAME = 'cost_center_id');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_journal_lines ADD COLUMN cost_center_id INT UNSIGNED NULL AFTER account_id, ADD INDEX idx_jl_cost_center (cost_center_id)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
