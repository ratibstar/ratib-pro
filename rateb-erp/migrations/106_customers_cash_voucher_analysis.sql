-- RATEB ERP — customers master + customer analysis on cash vouchers (idempotent)
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS rateb_customers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    code VARCHAR(30) NOT NULL,
    name VARCHAR(200) NOT NULL,
    name_ar VARCHAR(200) NULL,
    phone VARCHAR(50) NULL,
    email VARCHAR(120) NULL,
    tax_id VARCHAR(20) NULL,
    cost_center_id INT UNSIGNED NULL,
    notes TEXT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_customer_company_code (company_id, code),
    INDEX idx_customer_company (company_id),
    INDEX idx_customer_cc (cost_center_id),
    CONSTRAINT fk_customer_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_cash_vouchers' AND COLUMN_NAME = 'customer_id');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_cash_vouchers ADD COLUMN customer_id INT UNSIGNED NULL AFTER party_name, ADD INDEX idx_cv_customer (customer_id)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
