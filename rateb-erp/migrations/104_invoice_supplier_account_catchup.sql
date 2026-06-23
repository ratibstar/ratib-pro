-- RATEB ERP — invoice supplier account catch-up (idempotent; fixes admin/invoices [supplier_account_no])
SET NAMES utf8mb4;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_invoices' AND COLUMN_NAME = 'payment_method');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_invoices ADD COLUMN payment_method VARCHAR(50) NULL',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_invoices' AND COLUMN_NAME = 'supplier_account_no');
SET @has_pm = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_invoices' AND COLUMN_NAME = 'payment_method');
SET @sql = IF(@col = 0,
    IF(@has_pm > 0,
        'ALTER TABLE rateb_invoices ADD COLUMN supplier_account_no VARCHAR(80) NULL AFTER payment_method',
        'ALTER TABLE rateb_invoices ADD COLUMN supplier_account_no VARCHAR(80) NULL'),
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_invoices' AND COLUMN_NAME = 'supplier_bank_account_id');
SET @has_san = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_invoices' AND COLUMN_NAME = 'supplier_account_no');
SET @sql = IF(@col = 0,
    IF(@has_san > 0,
        'ALTER TABLE rateb_invoices ADD COLUMN supplier_bank_account_id INT UNSIGNED NULL AFTER supplier_account_no',
        'ALTER TABLE rateb_invoices ADD COLUMN supplier_bank_account_id INT UNSIGNED NULL'),
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_invoice_lines' AND COLUMN_NAME = 'account_id');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_invoice_lines ADD COLUMN account_id INT UNSIGNED NULL',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
