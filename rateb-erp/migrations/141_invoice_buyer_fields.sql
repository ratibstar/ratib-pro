-- Tax invoice buyer (customer) fields on invoices
SET @has_buyer_name := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_invoices' AND COLUMN_NAME = 'buyer_legal_name');
SET @sql := IF(@has_buyer_name = 0,
    'ALTER TABLE rateb_invoices
        ADD COLUMN buyer_legal_name VARCHAR(255) NULL AFTER invoice_type,
        ADD COLUMN buyer_vat_number VARCHAR(20) NULL AFTER buyer_legal_name,
        ADD COLUMN buyer_cr_number VARCHAR(20) NULL AFTER buyer_vat_number,
        ADD COLUMN buyer_address VARCHAR(500) NULL AFTER buyer_cr_number',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
