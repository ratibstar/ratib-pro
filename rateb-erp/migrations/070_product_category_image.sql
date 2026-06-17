-- RATEB ERP — product category cover image (idempotent)
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_product_categories' AND COLUMN_NAME = 'image_path');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_product_categories ADD COLUMN image_path VARCHAR(500) NULL AFTER icon',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
