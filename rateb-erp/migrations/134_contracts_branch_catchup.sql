-- RATEB ERP — contracts list + branch bootstrap catch-up (idempotent; fixes admin/ops/contracts DB errors)
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

CREATE TABLE IF NOT EXISTS rateb_branches (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    name VARCHAR(150) NOT NULL,
    code VARCHAR(50) NULL,
    address VARCHAR(255) NULL,
    phone VARCHAR(30) NULL,
    email VARCHAR(150) NULL,
    map_url VARCHAR(500) NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_branch_company (company_id),
    INDEX idx_branch_code (company_id, code),
    CONSTRAINT fk_branch_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_branches' AND COLUMN_NAME = 'is_main');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_branches ADD COLUMN is_main TINYINT(1) NOT NULL DEFAULT 0 AFTER status', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS rateb_user_branches (
    user_id INT UNSIGNED NOT NULL,
    branch_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, branch_id),
    INDEX idx_ub_user (user_id),
    INDEX idx_ub_branch (branch_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO rateb_branches (company_id, name, code, status, is_main)
SELECT c.id, CONVERT(UNHEX('D8A7D984D981D8B1D8B9D8A7D984D8B1D8A6D98AD8B3D98A') USING utf8mb4), 'MB001', 'active', 1
FROM rateb_companies c
WHERE NOT EXISTS (SELECT 1 FROM rateb_branches b WHERE b.company_id = c.id LIMIT 1);

UPDATE rateb_branches b
JOIN (SELECT company_id, MIN(id) AS mid FROM rateb_branches GROUP BY company_id) x
  ON x.company_id = b.company_id AND x.mid = b.id
SET b.is_main = 1
WHERE b.is_main = 0;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_contracts' AND COLUMN_NAME = 'branch_id');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_contracts ADD COLUMN branch_id INT UNSIGNED NULL AFTER company_id, ADD INDEX idx_contract_branch (branch_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_contracts' AND COLUMN_NAME = 'barcode');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_contracts ADD COLUMN barcode VARCHAR(80) NULL AFTER contract_no, ADD INDEX idx_ctr_doc_barcode (barcode)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_contracts' AND COLUMN_NAME = 'qr_code');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_contracts ADD COLUMN qr_code VARCHAR(255) NULL AFTER barcode', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_contracts' AND COLUMN_NAME = 'renewal_date');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_contracts ADD COLUMN renewal_date DATE NULL AFTER end_date', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_contracts' AND COLUMN_NAME = 'alert_days');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_contracts ADD COLUMN alert_days INT UNSIGNED NOT NULL DEFAULT 30 AFTER renewal_date', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_contracts' AND COLUMN_NAME = 'approval_status');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_contracts ADD COLUMN approval_status ENUM(''draft'',''pending'',''approved'',''rejected'') NOT NULL DEFAULT ''draft'' AFTER status', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE rateb_contracts t
JOIN rateb_branches b ON b.company_id = t.company_id AND b.is_main = 1
SET t.branch_id = b.id
WHERE t.branch_id IS NULL;
