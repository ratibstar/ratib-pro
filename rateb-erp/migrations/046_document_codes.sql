-- Standard document codes: item_code, movement_no, evaluation_no

SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_inventory' AND COLUMN_NAME = 'item_code');
SET @sql = IF(@c = 0, 'ALTER TABLE rateb_inventory ADD COLUMN item_code VARCHAR(20) NULL AFTER company_id', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @c = (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_inventory' AND INDEX_NAME = 'uq_inv_company_item_code');
SET @sql = IF(@c = 0, 'ALTER TABLE rateb_inventory ADD UNIQUE KEY uq_inv_company_item_code (company_id, item_code)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_stock_movements' AND COLUMN_NAME = 'movement_no');
SET @sql = IF(@c = 0, 'ALTER TABLE rateb_stock_movements ADD COLUMN movement_no VARCHAR(20) NULL AFTER company_id', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @c = (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_stock_movements' AND INDEX_NAME = 'uq_sm_company_movement_no');
SET @sql = IF(@c = 0, 'ALTER TABLE rateb_stock_movements ADD UNIQUE KEY uq_sm_company_movement_no (company_id, movement_no)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_supplier_evaluations' AND COLUMN_NAME = 'evaluation_no');
SET @sql = IF(@c = 0, 'ALTER TABLE rateb_supplier_evaluations ADD COLUMN evaluation_no VARCHAR(20) NULL AFTER company_id', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @c = (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_supplier_evaluations' AND INDEX_NAME = 'uq_se_company_eval_no');
SET @sql = IF(@c = 0, 'ALTER TABLE rateb_supplier_evaluations ADD UNIQUE KEY uq_se_company_eval_no (company_id, evaluation_no)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
