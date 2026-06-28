-- RATEB ERP — Oversight approval permissions per module (مراقبة الإدارة)
SET NAMES utf8mb4;

INSERT INTO rateb_permissions (name, name_ar, slug, module, description, description_ar) VALUES
('Oversight Approve', 'اعتماد مراقبة الإدارة', 'oversight.approve', 'workflows', 'Approve or reject pending records from administration oversight', 'اعتماد أو رفض السجلات المعلقة من مراقبة الإدارة'),
('Procurement Oversight', 'مراقبة المشتريات', 'procurement.oversight', 'procurement', 'View procurement approval queue in oversight', 'عرض قائمة اعتمادات المشتريات في مراقبة الإدارة'),
('Inventory Oversight', 'مراقبة المخزون', 'inventory.oversight', 'inventory', 'View inventory approval queue in oversight', 'عرض قائمة اعتمادات المخزون في مراقبة الإدارة'),
('Suppliers Oversight', 'مراقبة الموردين', 'suppliers.oversight', 'suppliers', 'View supplier approval queue in oversight', 'عرض قائمة اعتمادات الموردين في مراقبة الإدارة'),
('HR Oversight', 'مراقبة الموارد البشرية', 'hr.oversight', 'hr', 'View HR approval queue in oversight', 'عرض قائمة اعتمادات الموارد البشرية في مراقبة الإدارة'),
('Accounting Oversight', 'مراقبة المحاسبة', 'accounting.oversight', 'accounting', 'View accounting approval queue in oversight', 'عرض قائمة اعتمادات المحاسبة في مراقبة الإدارة'),
('Contracts Oversight', 'مراقبة العقود', 'contracts.oversight', 'contracts', 'View contracts approval queue in oversight', 'عرض قائمة اعتمادات العقود في مراقبة الإدارة'),
('Assets Oversight', 'مراقبة الأصول', 'assets.oversight', 'assets', 'View assets approval queue in oversight', 'عرض قائمة اعتمادات الأصول في مراقبة الإدارة'),
('CMS Oversight', 'مراقبة المحتوى', 'cms.oversight', 'cms', 'View CMS approval queue in oversight', 'عرض قائمة اعتمادات نظام المحتوى في مراقبة الإدارة'),
('Executive Oversight', 'مراقبة اللوحة التنفيذية', 'executive.oversight', 'dashboard', 'View executive dashboard oversight queue', 'عرض اعتمادات اللوحة التنفيذية في مراقبة الإدارة'),
('Access Oversight', 'مراقبة التحكم بالوصول', 'access.oversight', 'access', 'View access-control oversight queue', 'عرض اعتمادات التحكم بالوصول في مراقبة الإدارة'),
('Notifications Oversight', 'مراقبة الإشعارات', 'notifications.oversight', 'notifications', 'View notifications oversight queue', 'عرض اعتمادات الإشعارات في مراقبة الإدارة')
ON DUPLICATE KEY UPDATE name_ar = VALUES(name_ar), description_ar = VALUES(description_ar), module = VALUES(module);

INSERT IGNORE INTO rateb_role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM rateb_roles r
CROSS JOIN rateb_permissions p
WHERE r.slug = 'super-admin'
  AND p.slug IN (
    'oversight.approve',
    'procurement.oversight',
    'inventory.oversight',
    'suppliers.oversight',
    'hr.oversight',
    'accounting.oversight',
    'contracts.oversight',
    'assets.oversight',
    'cms.oversight',
    'executive.oversight',
    'access.oversight',
    'notifications.oversight'
  );
