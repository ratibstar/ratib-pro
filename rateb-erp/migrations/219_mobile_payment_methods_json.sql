-- Agent Apps: per-company mobile payment methods JSON on white-label config.
-- Bootstrap also adds this column at runtime when missing.

SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'rateb_mobile_app_configs'
      AND COLUMN_NAME = 'payment_methods_json'
);
SET @sql := IF(
    @col_exists = 0,
    'ALTER TABLE rateb_mobile_app_configs ADD COLUMN payment_methods_json JSON NULL AFTER enabled_features',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
