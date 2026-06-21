-- RATEB ERP — customs clearance fields + permissions (idempotent)
SET NAMES utf8mb4;

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
('View Customs Clearance', 'عرض التخليص الجمركي', 'customs_clearance.view', 'procurement', 'View customs clearance costs and status on purchase orders', 'عرض تكاليف وحالة التخليص الجمركي على أوامر الشراء'),
('Manage Customs Clearance', 'إدارة التخليص الجمركي', 'customs_clearance.manage', 'procurement', 'Edit customs clearance fields on purchase orders', 'تعديل بيانات التخليص الجمركي على أوامر الشراء')
ON DUPLICATE KEY UPDATE name_ar = VALUES(name_ar), description_ar = VALUES(description_ar), module = VALUES(module);

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
