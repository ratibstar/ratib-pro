-- RATEB ERP — Offline device activation fields (POS Phase 2C)
-- Additive: pending status + activate/approve audit columns on rateb_offline_devices
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

SET @tbl = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_offline_devices');

-- Expand status ENUM with pending (idempotent when already present)
SET @has_pending = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'rateb_offline_devices'
      AND COLUMN_NAME = 'status'
      AND COLUMN_TYPE LIKE '%pending%'
);
SET @sql = IF(@tbl > 0 AND @has_pending = 0,
    'ALTER TABLE rateb_offline_devices MODIFY COLUMN status ENUM(''pending'',''active'',''inactive'',''revoked'') NOT NULL DEFAULT ''pending''',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_offline_devices' AND COLUMN_NAME = 'activated_by');
SET @sql = IF(@tbl > 0 AND @col = 0,
    'ALTER TABLE rateb_offline_devices ADD COLUMN activated_by INT UNSIGNED NULL AFTER status',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_offline_devices' AND COLUMN_NAME = 'activated_at');
SET @sql = IF(@tbl > 0 AND @col = 0,
    'ALTER TABLE rateb_offline_devices ADD COLUMN activated_at DATETIME NULL AFTER activated_by',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_offline_devices' AND COLUMN_NAME = 'approved_by');
SET @sql = IF(@tbl > 0 AND @col = 0,
    'ALTER TABLE rateb_offline_devices ADD COLUMN approved_by INT UNSIGNED NULL AFTER activated_at',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_offline_devices' AND INDEX_NAME = 'idx_offline_device_status');
SET @sql = IF(@tbl > 0 AND @idx = 0,
    'ALTER TABLE rateb_offline_devices ADD INDEX idx_offline_device_status (company_id, status)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

INSERT INTO rateb_permissions (name, name_ar, slug, module, description, description_ar) VALUES
('Manage POS Devices', 'إدارة أجهزة نقطة البيع', 'pos.devices.manage', 'pos',
 'Activate and revoke offline POS devices', 'تفعيل وإلغاء أجهزة نقطة البيع دون اتصال')
ON DUPLICATE KEY UPDATE name = VALUES(name), name_ar = VALUES(name_ar), description = VALUES(description);

INSERT IGNORE INTO rateb_role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM rateb_roles r
INNER JOIN rateb_permissions p ON p.slug = 'pos.devices.manage'
WHERE r.slug IN ('pos_manager', 'super-admin', 'company-full-access');

-- Anyone who already manages POS settings can manage devices
INSERT IGNORE INTO rateb_role_permissions (role_id, permission_id)
SELECT rp.role_id, p_new.id
FROM rateb_role_permissions rp
INNER JOIN rateb_permissions p_old ON p_old.id = rp.permission_id AND p_old.slug = 'pos.settings.manage'
INNER JOIN rateb_permissions p_new ON p_new.slug = 'pos.devices.manage';
