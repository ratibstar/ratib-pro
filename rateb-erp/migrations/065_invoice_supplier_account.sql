-- Invoice: supplier bank account + line chart account (idempotent)
SET NAMES utf8mb4;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_invoices' AND COLUMN_NAME = 'supplier_account_no');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_invoices ADD COLUMN supplier_account_no VARCHAR(80) NULL AFTER payment_method',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_invoices' AND COLUMN_NAME = 'supplier_bank_account_id');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_invoices ADD COLUMN supplier_bank_account_id INT UNSIGNED NULL AFTER supplier_account_no',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_invoice_lines' AND COLUMN_NAME = 'account_id');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_invoice_lines ADD COLUMN account_id INT UNSIGNED NULL AFTER unit_price',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
