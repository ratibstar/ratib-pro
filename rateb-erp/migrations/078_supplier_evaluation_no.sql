-- RATEB ERP — supplier evaluation document number (from 046, idempotent re-apply)
SET NAMES utf8mb4;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_supplier_evaluations' AND COLUMN_NAME = 'evaluation_no');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_supplier_evaluations ADD COLUMN evaluation_no VARCHAR(20) NULL AFTER company_id',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_supplier_evaluations' AND INDEX_NAME = 'uq_se_company_eval_no');
SET @sql = IF(@idx = 0,
    'ALTER TABLE rateb_supplier_evaluations ADD UNIQUE KEY uq_se_company_eval_no (company_id, evaluation_no)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
