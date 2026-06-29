-- RATEB ERP — ensure journal_entries.source_type includes branch_transfer (enterprise cert)
SET NAMES utf8mb4;

SET @has_bt = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'rateb_journal_entries'
      AND COLUMN_NAME = 'source_type'
      AND COLUMN_TYPE LIKE '%branch_transfer%'
);

SET @sql = IF(@has_bt = 0,
    'ALTER TABLE rateb_journal_entries MODIFY source_type ENUM(
        ''manual'',''invoice'',''payment'',''purchase_order'',''subscription'',
        ''cash_voucher'',''stock_movement'',''purchase_invoice'',
        ''supplier_payment'',''year_end_close'',''branch_transfer''
    ) NOT NULL DEFAULT ''manual''',
    'SELECT 1');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
