-- RATEB ERP — one-shot procurement production catch-up (idempotent)
-- Creates purchase request tables + columns from 009/044/045 if missing.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS rateb_purchase_requests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    request_no VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    department VARCHAR(120) NULL,
    priority ENUM('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
    status ENUM('draft','submitted','approved','rejected','cancelled') NOT NULL DEFAULT 'draft',
    requested_by INT UNSIGNED NULL,
    approved_by INT UNSIGNED NULL,
    total_estimated DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_pr_company_no (company_id, request_no),
    INDEX idx_pr_company (company_id),
    CONSTRAINT fk_pr_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_purchase_request_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    purchase_request_id INT UNSIGNED NOT NULL,
    item_name VARCHAR(255) NOT NULL,
    sku VARCHAR(80) NULL,
    quantity DECIMAL(12,3) NOT NULL DEFAULT 1.000,
    unit VARCHAR(30) NOT NULL DEFAULT 'unit',
    unit_price DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    total_price DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pri_company (company_id),
    INDEX idx_pri_pr (purchase_request_id),
    CONSTRAINT fk_pri_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_pri_pr FOREIGN KEY (purchase_request_id) REFERENCES rateb_purchase_requests(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_purchase_requests' AND COLUMN_NAME = 'expected_date');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_purchase_requests ADD COLUMN expected_date DATE NULL AFTER department',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_purchase_requests' AND COLUMN_NAME = 'currency');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_purchase_requests ADD COLUMN currency VARCHAR(3) NOT NULL DEFAULT ''SAR'' AFTER total_estimated',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_purchase_requests' AND COLUMN_NAME = 'notes_history');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_purchase_requests ADD COLUMN notes_history JSON NULL AFTER notes',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_purchase_request_items' AND COLUMN_NAME = 'inventory_id');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_purchase_request_items ADD COLUMN inventory_id INT UNSIGNED NULL AFTER purchase_request_id, ADD INDEX idx_pri_inventory (inventory_id)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_purchase_request_items' AND COLUMN_NAME = 'tax_name');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_purchase_request_items ADD COLUMN description VARCHAR(500) NULL AFTER item_name, ADD COLUMN tax_name VARCHAR(80) NULL DEFAULT ''Local Sales 0%'' AFTER unit_price, ADD COLUMN tax_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00 AFTER tax_name, ADD COLUMN excluding_tax TINYINT(1) NOT NULL DEFAULT 1 AFTER tax_rate',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
