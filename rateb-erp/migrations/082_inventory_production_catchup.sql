-- RATEB ERP — one-shot inventory production catch-up (idempotent)
-- Warehouses, inventory, stock movements, product categories + columns from 009/022/046/048/056/076.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS rateb_warehouses (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    name VARCHAR(150) NOT NULL,
    code VARCHAR(50) NULL,
    location VARCHAR(255) NULL,
    manager_name VARCHAR(150) NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_wh_company (company_id),
    CONSTRAINT fk_wh_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_product_categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    name VARCHAR(150) NOT NULL,
    name_ar VARCHAR(150) NULL,
    parent_id INT UNSIGNED NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pc_company (company_id),
    CONSTRAINT fk_pc_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_inventory (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    warehouse_id INT UNSIGNED NULL,
    item_name VARCHAR(255) NOT NULL,
    sku VARCHAR(80) NULL,
    category VARCHAR(120) NULL,
    quantity DECIMAL(12,3) NOT NULL DEFAULT 0.000,
    unit VARCHAR(30) NOT NULL DEFAULT 'unit',
    unit_cost DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    reorder_level DECIMAL(12,3) NOT NULL DEFAULT 0.000,
    expiry_date DATE NULL,
    status ENUM('active','inactive','expired') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_inv_company (company_id),
    CONSTRAINT fk_inv_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_inv_warehouse FOREIGN KEY (warehouse_id) REFERENCES rateb_warehouses(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_stock_movements (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    inventory_id INT UNSIGNED NOT NULL,
    warehouse_id INT UNSIGNED NULL,
    movement_type ENUM('in','out','transfer','adjustment') NOT NULL,
    quantity DECIMAL(12,3) NOT NULL,
    reference_type VARCHAR(50) NULL,
    reference_id INT UNSIGNED NULL,
    notes TEXT NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_sm_company (company_id),
    INDEX idx_sm_inventory (inventory_id),
    CONSTRAINT fk_sm_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_sm_inventory FOREIGN KEY (inventory_id) REFERENCES rateb_inventory(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_inventory' AND COLUMN_NAME = 'item_code');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_inventory ADD COLUMN item_code VARCHAR(20) NULL AFTER company_id',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_inventory' AND COLUMN_NAME = 'category_id');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_inventory ADD COLUMN category_id INT UNSIGNED NULL AFTER category',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_inventory' AND COLUMN_NAME = 'barcode');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_inventory ADD COLUMN barcode VARCHAR(80) NULL AFTER sku, ADD COLUMN qr_code VARCHAR(255) NULL AFTER barcode, ADD COLUMN min_stock DECIMAL(12,3) NOT NULL DEFAULT 0.000 AFTER reorder_level, ADD COLUMN max_stock DECIMAL(12,3) NULL AFTER min_stock',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_inventory' AND COLUMN_NAME = 'production_date');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_inventory ADD COLUMN production_date DATE NULL AFTER reorder_level',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_inventory' AND COLUMN_NAME = 'document_path');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_inventory ADD COLUMN document_path VARCHAR(500) NULL AFTER qr_code',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_inventory' AND COLUMN_NAME = 'notes');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_inventory ADD COLUMN notes TEXT NULL AFTER document_path',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_inventory' AND INDEX_NAME = 'uq_inv_company_item_code');
SET @sql = IF(@idx = 0,
    'ALTER TABLE rateb_inventory ADD UNIQUE KEY uq_inv_company_item_code (company_id, item_code)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_inventory' AND INDEX_NAME = 'idx_inv_barcode');
SET @sql = IF(@idx = 0,
    'ALTER TABLE rateb_inventory ADD INDEX idx_inv_barcode (barcode)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_inventory' AND INDEX_NAME = 'idx_inv_category');
SET @sql = IF(@idx = 0,
    'ALTER TABLE rateb_inventory ADD INDEX idx_inv_category (category_id)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_product_categories' AND COLUMN_NAME = 'code');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_product_categories ADD COLUMN code VARCHAR(40) NULL AFTER company_id',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_product_categories' AND COLUMN_NAME = 'sort_order');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_product_categories ADD COLUMN sort_order INT UNSIGNED NOT NULL DEFAULT 0 AFTER parent_id',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_product_categories' AND COLUMN_NAME = 'is_visible');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_product_categories ADD COLUMN is_visible TINYINT(1) NOT NULL DEFAULT 1 AFTER is_active',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
