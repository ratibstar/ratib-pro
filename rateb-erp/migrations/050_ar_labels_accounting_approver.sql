-- RATEB ERP — fix Arabic permission labels, accounting approver role
SET NAMES utf8mb4;

UPDATE rateb_permissions SET name_ar = 'عرض تحويلات المستودعات' WHERE slug = 'warehouse_transfers.view';
UPDATE rateb_permissions SET name_ar = 'إدارة تحويلات المستودعات' WHERE slug = 'warehouse_transfers.manage';
UPDATE rateb_permissions SET name_ar = 'عرض توقعات المخزون' WHERE slug = 'inventory_forecast.view';
UPDATE rateb_permissions SET name_ar = 'إدارة المشتريات' WHERE slug = 'procurement.manage';
UPDATE rateb_permissions SET name_ar = 'إدارة الموردين' WHERE slug = 'suppliers.manage';
UPDATE rateb_permissions SET name_ar = 'عرض تقييم الموردين' WHERE slug = 'evaluations.view';
UPDATE rateb_permissions SET name_ar = 'إدارة تقييم الموردين' WHERE slug = 'evaluations.manage';
UPDATE rateb_permissions SET name_ar = 'إدارة المناقصات' WHERE slug = 'tenders.manage';
UPDATE rateb_permissions SET name_ar = 'إدارة المستخدمين' WHERE slug = 'users.manage';
UPDATE rateb_permissions SET name_ar = 'إدارة المخزون' WHERE slug = 'inventory.manage';
UPDATE rateb_permissions SET name_ar = 'اعتماد القيود والسندات' WHERE slug = 'accounting.approve';

INSERT INTO rateb_roles (company_id, name, slug, description, is_system) VALUES
(NULL, 'Accounting Approver', 'accounting-approver', 'Approve journal entries and cash vouchers; dashboard access only', 1)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    description = VALUES(description),
    is_system = VALUES(is_system);

INSERT IGNORE INTO rateb_role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM rateb_roles r
JOIN rateb_permissions p ON p.slug IN ('dashboard.view', 'accounting.approve')
WHERE r.slug = 'accounting-approver';
