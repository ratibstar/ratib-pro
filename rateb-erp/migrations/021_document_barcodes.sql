-- Barcodes for invoices, contracts (+ backfill inventory)
SET NAMES utf8mb4;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_invoices' AND COLUMN_NAME = 'barcode');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_invoices ADD COLUMN barcode VARCHAR(80) NULL AFTER invoice_no, ADD COLUMN qr_code VARCHAR(255) NULL AFTER barcode, ADD INDEX idx_inv_doc_barcode (barcode)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_contracts' AND COLUMN_NAME = 'barcode');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_contracts ADD COLUMN barcode VARCHAR(80) NULL AFTER contract_no, ADD COLUMN qr_code VARCHAR(255) NULL AFTER barcode, ADD INDEX idx_ctr_doc_barcode (barcode)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE rateb_invoices
SET barcode = CONCAT('INV', LPAD(COALESCE(company_id, 0), 4, '0'), LPAD(id, 8, '0'))
WHERE (barcode IS NULL OR TRIM(barcode) = '') AND id > 0;

UPDATE rateb_contracts
SET barcode = CONCAT('CTR', LPAD(COALESCE(company_id, 0), 4, '0'), LPAD(id, 8, '0'))
WHERE (barcode IS NULL OR TRIM(barcode) = '') AND id > 0;

UPDATE rateb_inventory
SET barcode = CONCAT('RTB', LPAD(COALESCE(company_id, 0), 4, '0'), LPAD(id, 8, '0'))
WHERE (barcode IS NULL OR TRIM(barcode) = '') AND id > 0;
