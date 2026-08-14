-- Phase B HR security — ESS employee resolver index (idempotent, additive)
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

SET @idx = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'rateb_employees'
      AND INDEX_NAME = 'idx_employees_user_company'
);
SET @sql = IF(@idx = 0,
    'ALTER TABLE rateb_employees ADD INDEX idx_employees_user_company (user_id, company_id)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
