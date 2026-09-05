-- RATEB ERP 263 platform oversight permissions + company feature toggles
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

INSERT INTO rateb_permissions (name, name_ar, slug, module, description, description_ar) VALUES
('Manage Module Add-on Catalog', 'كتالوج الوحدات في المنصة', 'module_addons.manage', 'module_addons', 'Manage platform commercial module catalog', 'إدارة كتالوج الوحدات التجاري على المنصة وإظهاره في القائمة'),
('Demo Module Locks', 'أقفال الوحدات التجريبية', 'module_addons.demo_locks', 'module_addons', 'Lock or unlock purchasable modules for demo companies', 'قفل أو فتح الوحدات القابلة للشراء للشركات التجريبية'),
('Agency ERP Updates', 'رفع التحديثات للوكالات', 'agency_updates.manage', 'agency_updates', 'Push ERP updates to linked agencies', 'رفع تحديثات ERP إلى الوكالات المرتبطة'),
('Company Approvals Oversight', 'اعتماد الشركات', 'companies.approvals', 'companies', 'Approve pending companies from platform oversight', 'اعتماد الشركات المعلقة من إشراف المنصة'),
('Platform Product Catalog', 'كتالوج منتجات المنصة', 'platform_catalog.manage', 'platform_catalog', 'Open the platform product catalog admin', 'فتح إدارة كتالوج منتجات المنصة'),
('View Platform Product Catalog', 'عرض كتالوج منتجات المنصة', 'platform_catalog.view', 'platform_catalog', 'Browse the platform product catalog from the company ERP', 'تصفح كتالوج منتجات المنصة من نظام الشركة'),
('Manage Company Permissions', 'صلاحيات الشركات', 'company_permissions.manage', 'companies', 'Edit per-company module entitlements', 'تعديل استحقاق وحدات الشركات')
ON DUPLICATE KEY UPDATE name_ar = VALUES(name_ar), description_ar = VALUES(description_ar), module = VALUES(module);

INSERT IGNORE INTO rateb_role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM rateb_roles r
CROSS JOIN rateb_permissions p
WHERE r.slug = 'super-admin'
  AND r.company_id IS NULL
  AND p.slug IN (
    'module_addons.manage',
    'module_addons.demo_locks',
    'agency_updates.manage',
    'companies.approvals',
    'platform_catalog.manage',
    'platform_catalog.view',
    'company_permissions.manage'
  );
