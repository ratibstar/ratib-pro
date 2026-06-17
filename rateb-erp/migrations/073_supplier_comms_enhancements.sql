-- RATEB ERP — supplier communications enhancements (idempotent)
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_supplier_communications' AND COLUMN_NAME = 'comm_date');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_supplier_communications ADD COLUMN comm_date DATE NULL AFTER subject',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_supplier_communications' AND COLUMN_NAME = 'comm_time');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_supplier_communications ADD COLUMN comm_time TIME NULL AFTER comm_date',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_supplier_communications' AND COLUMN_NAME = 'details');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_supplier_communications ADD COLUMN details TEXT NULL AFTER comm_time',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_supplier_communications' AND COLUMN_NAME = 'responsible_name');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_supplier_communications ADD COLUMN responsible_name VARCHAR(150) NULL AFTER body',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_supplier_communications' AND COLUMN_NAME = 'supplier_contact');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_supplier_communications ADD COLUMN supplier_contact VARCHAR(150) NULL AFTER responsible_name',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_supplier_communications' AND COLUMN_NAME = 'supplier_phone');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_supplier_communications ADD COLUMN supplier_phone VARCHAR(50) NULL AFTER supplier_contact',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_supplier_communications' AND COLUMN_NAME = 'supplier_email');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_supplier_communications ADD COLUMN supplier_email VARCHAR(150) NULL AFTER supplier_phone',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_supplier_communications' AND COLUMN_NAME = 'comm_status');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_supplier_communications ADD COLUMN comm_status VARCHAR(20) NOT NULL DEFAULT ''new'' AFTER supplier_email',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_supplier_communications' AND COLUMN_NAME = 'follow_up_date');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_supplier_communications ADD COLUMN follow_up_date DATE NULL AFTER comm_status',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_supplier_communications' AND COLUMN_NAME = 'follow_up_priority');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_supplier_communications ADD COLUMN follow_up_priority VARCHAR(20) NOT NULL DEFAULT ''medium'' AFTER follow_up_date',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_supplier_communications' AND COLUMN_NAME = 'purchase_order_id');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_supplier_communications ADD COLUMN purchase_order_id INT UNSIGNED NULL AFTER follow_up_priority',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_supplier_communications' AND COLUMN_NAME = 'rfq_id');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_supplier_communications ADD COLUMN rfq_id INT UNSIGNED NULL AFTER purchase_order_id',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_supplier_communications' AND COLUMN_NAME = 'is_archived');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_supplier_communications ADD COLUMN is_archived TINYINT(1) NOT NULL DEFAULT 0 AFTER rfq_id',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_supplier_communications' AND COLUMN_NAME = 'archived_at');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_supplier_communications ADD COLUMN archived_at DATETIME NULL AFTER is_archived',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE rateb_supplier_communications
SET comm_date = DATE(created_at)
WHERE comm_date IS NULL AND created_at IS NOT NULL;
