-- RATEB ERP — notifications + product categories catch-up (idempotent)
-- Can block inventory save when low-stock alert fires or category dropdown queries fail.
SET NAMES utf8mb4;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_notifications' AND COLUMN_NAME = 'trigger_type');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_notifications ADD COLUMN trigger_type VARCHAR(50) NULL AFTER type',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_notifications' AND COLUMN_NAME = 'entity_type');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_notifications ADD COLUMN entity_type VARCHAR(50) NULL AFTER trigger_type',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_notifications' AND COLUMN_NAME = 'entity_id');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_notifications ADD COLUMN entity_id INT UNSIGNED NULL AFTER entity_type',
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
