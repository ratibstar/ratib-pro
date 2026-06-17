-- RATEB ERP — product categories enhancements (idempotent)
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_product_categories' AND COLUMN_NAME = 'code');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_product_categories ADD COLUMN code VARCHAR(40) NULL AFTER company_id',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_product_categories' AND COLUMN_NAME = 'description_en');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_product_categories ADD COLUMN description_en TEXT NULL AFTER name_ar',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_product_categories' AND COLUMN_NAME = 'description_ar');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_product_categories ADD COLUMN description_ar TEXT NULL AFTER description_en',
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

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_product_categories' AND COLUMN_NAME = 'icon');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_product_categories ADD COLUMN icon VARCHAR(80) NULL AFTER is_visible',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_product_categories' AND INDEX_NAME = 'idx_pc_parent');
SET @sql = IF(@idx = 0,
    'ALTER TABLE rateb_product_categories ADD INDEX idx_pc_parent (parent_id)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_product_categories' AND INDEX_NAME = 'idx_pc_sort');
SET @sql = IF(@idx = 0,
    'ALTER TABLE rateb_product_categories ADD INDEX idx_pc_sort (company_id, sort_order)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
