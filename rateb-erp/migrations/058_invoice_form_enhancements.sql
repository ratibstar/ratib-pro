-- RATEB ERP — Invoice form enhancements (discount, terms, payment metadata)
SET NAMES utf8mb4;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_invoices' AND COLUMN_NAME = 'currency');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_invoices
        ADD COLUMN currency VARCHAR(3) NOT NULL DEFAULT ''SAR'' AFTER total_amount,
        ADD COLUMN discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER currency,
        ADD COLUMN discount_type ENUM(''value'',''percent'') NOT NULL DEFAULT ''value'' AFTER discount_amount,
        ADD COLUMN tax_rate DECIMAL(5,2) NOT NULL DEFAULT 15.00 AFTER discount_type,
        ADD COLUMN payment_terms_days INT UNSIGNED NOT NULL DEFAULT 30 AFTER tax_rate,
        ADD COLUMN payment_method VARCHAR(50) NULL AFTER payment_terms_days,
        ADD COLUMN invoice_type VARCHAR(50) NOT NULL DEFAULT ''tax'' AFTER invoice_no,
        ADD COLUMN po_number VARCHAR(80) NULL AFTER subscription_id,
        ADD COLUMN notes TEXT NULL AFTER status,
        ADD COLUMN payment_status ENUM(''unpaid'',''partial'',''paid'') NOT NULL DEFAULT ''unpaid'' AFTER notes',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
