-- RATEB ERP — document barcode columns catch-up (idempotent; fixes scan/doc [barcode] errors)
SET NAMES utf8mb4;

-- purchase orders
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_purchase_orders' AND COLUMN_NAME = 'barcode');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_purchase_orders ADD COLUMN barcode VARCHAR(80) NULL AFTER order_no, ADD INDEX idx_po_barcode (barcode)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_purchase_orders' AND COLUMN_NAME = 'qr_code');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_purchase_orders ADD COLUMN qr_code VARCHAR(255) NULL AFTER barcode',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- inventory
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_inventory' AND COLUMN_NAME = 'barcode');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_inventory ADD COLUMN barcode VARCHAR(80) NULL AFTER sku',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_inventory' AND COLUMN_NAME = 'qr_code');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_inventory ADD COLUMN qr_code VARCHAR(255) NULL AFTER barcode',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_inventory' AND INDEX_NAME = 'idx_inv_barcode');
SET @sql = IF(@idx = 0,
    'ALTER TABLE rateb_inventory ADD INDEX idx_inv_barcode (barcode)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- invoices
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_invoices' AND COLUMN_NAME = 'barcode');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_invoices ADD COLUMN barcode VARCHAR(80) NULL AFTER invoice_no, ADD INDEX idx_inv_doc_barcode (barcode)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_invoices' AND COLUMN_NAME = 'qr_code');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_invoices ADD COLUMN qr_code VARCHAR(255) NULL AFTER barcode',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- contracts
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_contracts' AND COLUMN_NAME = 'barcode');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_contracts ADD COLUMN barcode VARCHAR(80) NULL AFTER contract_no, ADD INDEX idx_ctr_doc_barcode (barcode)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_contracts' AND COLUMN_NAME = 'qr_code');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_contracts ADD COLUMN qr_code VARCHAR(255) NULL AFTER barcode',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- backfill missing barcodes (scan-safe prefixes)
UPDATE rateb_purchase_orders
SET barcode = CONCAT('PO', LPAD(COALESCE(company_id, 0), 4, '0'), LPAD(id, 8, '0'))
WHERE (barcode IS NULL OR TRIM(barcode) = '') AND id > 0;

UPDATE rateb_inventory
SET barcode = CONCAT('RTB', LPAD(COALESCE(company_id, 0), 4, '0'), LPAD(id, 8, '0'))
WHERE (barcode IS NULL OR TRIM(barcode) = '') AND id > 0;

UPDATE rateb_invoices
SET barcode = CONCAT('INV', LPAD(COALESCE(company_id, 0), 4, '0'), LPAD(id, 8, '0'))
WHERE (barcode IS NULL OR TRIM(barcode) = '') AND id > 0;

UPDATE rateb_contracts
SET barcode = CONCAT('CTR', LPAD(COALESCE(company_id, 0), 4, '0'), LPAD(id, 8, '0'))
WHERE (barcode IS NULL OR TRIM(barcode) = '') AND id > 0;
