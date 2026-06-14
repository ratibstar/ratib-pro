-- RATEB ERP — fill permission gaps (warehouse transfers, forecast, sync roles)
SET NAMES utf8mb4;

INSERT INTO rateb_permissions (name, name_ar, slug, module, description, description_ar) VALUES
('View Warehouse Transfers', 'عرض تحويلات المستودعات', 'warehouse_transfers.view', 'inventory', 'View inter-warehouse transfer requests', 'عرض طلبات التحويل بين المستودعات'),
('Manage Warehouse Transfers', 'إدارة تحويلات المستودعات', 'warehouse_transfers.manage', 'inventory', 'Create and approve warehouse transfers', 'إنشاء واعتماد تحويلات المستودعات'),
('View Inventory Forecast', 'عرض توقعات المخزون', 'inventory_forecast.view', 'inventory', 'View inventory reorder and consumption forecast', 'عرض توقعات إعادة الطلب واستهلاك المخزون')
ON DUPLICATE KEY UPDATE name_ar = VALUES(name_ar), description_ar = VALUES(description_ar);

INSERT IGNORE INTO rateb_role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM rateb_roles r
JOIN rateb_permissions p ON p.slug IN (
    'warehouse_transfers.view',
    'warehouse_transfers.manage',
    'inventory_forecast.view',
    'supplier_comms.view',
    'supplier_comms.manage'
)
WHERE r.slug = 'company-full-access';

INSERT IGNORE INTO rateb_role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM rateb_roles r
CROSS JOIN rateb_permissions p
WHERE r.slug = 'super-admin'
  AND p.slug IN ('warehouse_transfers.view', 'warehouse_transfers.manage', 'inventory_forecast.view');
