-- RATEB ERP — Saudi-style Chart of Accounts meta (account level + cash-flow class)
-- Additive / idempotent. Tree seed & legacy rename are applied by AccountingService::ensureDefaultAccounts().
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_chart_of_accounts' AND COLUMN_NAME = 'account_level');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_chart_of_accounts ADD COLUMN account_level TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER parent_id', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_chart_of_accounts' AND COLUMN_NAME = 'cash_flow_class');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_chart_of_accounts ADD COLUMN cash_flow_class ENUM(''operating'',''investing'',''financing'',''unclassified'') NOT NULL DEFAULT ''unclassified'' AFTER account_level', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- Widen code for deep Saudi codes (already VARCHAR(20) on most installs; keep safe).
SET @colType = (SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_chart_of_accounts' AND COLUMN_NAME = 'code' LIMIT 1);
SET @colLen = (SELECT CHARACTER_MAXIMUM_LENGTH FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_chart_of_accounts' AND COLUMN_NAME = 'code' LIMIT 1);
SET @sql = IF(@colType = 'varchar' AND @colLen IS NOT NULL AND @colLen < 20, 'ALTER TABLE rateb_chart_of_accounts MODIFY COLUMN code VARCHAR(20) NOT NULL', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
