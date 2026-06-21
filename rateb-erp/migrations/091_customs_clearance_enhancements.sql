-- RATEB ERP — customs clearance fields + permissions (idempotent, UNHEX Arabic — CI-safe)
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_purchase_orders' AND COLUMN_NAME = 'customs_clearance_amount');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_purchase_orders ADD COLUMN customs_clearance_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER shipping_amount',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_purchase_orders' AND COLUMN_NAME = 'customs_declaration_no');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_purchase_orders
        ADD COLUMN customs_declaration_no VARCHAR(80) NULL AFTER customs_clearance_amount,
        ADD COLUMN customs_clearance_date DATE NULL AFTER customs_declaration_no,
        ADD COLUMN customs_broker_id INT UNSIGNED NULL AFTER customs_clearance_date,
        ADD COLUMN customs_clearance_status VARCHAR(30) NOT NULL DEFAULT '''' AFTER customs_broker_id,
        ADD INDEX idx_po_customs_broker (customs_broker_id)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

INSERT INTO rateb_permissions (name, name_ar, slug, module, description, description_ar) VALUES
('View Customs Clearance', '', 'customs_clearance.view', 'procurement', 'View customs clearance costs and status on purchase orders', ''),
('Manage Customs Clearance', '', 'customs_clearance.manage', 'procurement', 'Edit customs clearance fields on purchase orders', '')
ON DUPLICATE KEY UPDATE name = VALUES(name), module = VALUES(module), description = VALUES(description);

UPDATE rateb_permissions SET
    name_ar = CONVERT(UNHEX('D8B9D8B1D8B620D8A7D984D8AAD8AED984D98AD8B520D8A7D984D8ACD985D8B1D983D98A') USING utf8mb4),
    description_ar = CONVERT(UNHEX('D8B9D8B1D8B620D8AAD983D8A7D984D98AD98120D988D8ADD8A7D984D8A920D8A7D984D8AAD8AED984D98AD8B520D8A7D984D8ACD985D8B1D983D98A20D8B9D984D98920D8A3D988D8A7D985D8B120D8A7D984D8B4D8B1D8A7D8A1') USING utf8mb4)
WHERE slug = 'customs_clearance.view';

UPDATE rateb_permissions SET
    name_ar = CONVERT(UNHEX('D8A5D8AFD8A7D8B1D8A920D8A7D984D8AAD8AED984D98AD8B520D8A7D984D8ACD985D8B1D983D98A') USING utf8mb4),
    description_ar = CONVERT(UNHEX('D8AAD8B9D8AFD98AD98420D8A8D98AD8A7D986D8A7D8AA20D8A7D984D8AAD8AED984D98AD8B520D8A7D984D8ACD985D8B1D983D98A20D8B9D984D98920D8A3D988D8A7D985D8B120D8A7D984D8B4D8B1D8A7D8A1') USING utf8mb4)
WHERE slug = 'customs_clearance.manage';

INSERT IGNORE INTO rateb_role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM rateb_roles r
JOIN rateb_permissions p ON p.slug IN ('customs_clearance.view', 'customs_clearance.manage')
WHERE r.slug = 'company-full-access';

INSERT IGNORE INTO rateb_role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM rateb_roles r
JOIN rateb_permissions p ON p.slug IN ('customs_clearance.view', 'customs_clearance.manage')
WHERE r.slug = 'super-admin';
