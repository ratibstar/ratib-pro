-- HR Phase L — letter issue linkage on employee requests (additive only)
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

SET @col = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'rateb_hr_employee_requests'
      AND COLUMN_NAME = 'document_id'
);
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_hr_employee_requests
        ADD COLUMN document_id INT UNSIGNED NULL,
        ADD COLUMN issued_at DATETIME NULL,
        ADD COLUMN issued_by INT UNSIGNED NULL,
        ADD INDEX idx_hr_req_document (company_id, document_id)',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
