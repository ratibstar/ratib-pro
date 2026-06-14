-- Inventory form: notes/description field
SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_inventory' AND COLUMN_NAME = 'notes'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE rateb_inventory ADD COLUMN notes TEXT NULL AFTER document_path',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
