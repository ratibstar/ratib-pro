-- RATEB ERP — account lockout columns on rateb_users (from 023, idempotent re-apply)
SET NAMES utf8mb4;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_users' AND COLUMN_NAME = 'failed_attempts');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_users ADD COLUMN failed_attempts INT UNSIGNED NOT NULL DEFAULT 0',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_users' AND COLUMN_NAME = 'locked_until');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_users ADD COLUMN locked_until DATETIME NULL',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
