-- rateb_asset_assignments: add created_at for list ordering (matches other asset tables)
SET NAMES utf8mb4;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_asset_assignments' AND COLUMN_NAME = 'created_at');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_asset_assignments ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER notes',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
