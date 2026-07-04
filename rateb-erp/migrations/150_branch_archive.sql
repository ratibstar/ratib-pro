-- RATEB ERP — branch lifecycle archive (is_archived + archived_at; status unchanged)
-- Pattern: rateb_supplier_communications (073_supplier_comms_enhancements.sql)

SET NAMES utf8mb4;

SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_branches' AND COLUMN_NAME = 'is_archived');
SET @sql = IF(@c = 0,
    'ALTER TABLE rateb_branches ADD COLUMN is_archived TINYINT(1) NOT NULL DEFAULT 0 AFTER is_main',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_branches' AND COLUMN_NAME = 'archived_at');
SET @sql = IF(@c = 0,
    'ALTER TABLE rateb_branches ADD COLUMN archived_at DATETIME NULL AFTER is_archived',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx = (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_branches' AND INDEX_NAME = 'idx_branch_company_archived');
SET @sql = IF(@idx = 0,
    'ALTER TABLE rateb_branches ADD INDEX idx_branch_company_archived (company_id, is_archived)',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
