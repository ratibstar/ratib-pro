-- RATEB ERP — Permissions & menu module slugs for expanded ERP
SET NAMES utf8mb4;

INSERT INTO rateb_permissions (name, name_ar, slug, module, description, description_ar) VALUES
('View Stock Movements', 'عرض حركات المخزون', 'stock_movements.view', 'inventory', 'View stock movement history', 'عرض سجل حركات المخزون'),
('Manage Stock Movements', 'إدارة حركات المخزون', 'stock_movements.manage', 'inventory', 'Create stock movements', 'إنشاء حركات المخزون'),
('View Product Categories', 'عرض تصنيفات المنتجات', 'categories.view', 'inventory', 'View product categories', 'عرض تصنيفات المنتجات'),
('Manage Product Categories', 'إدارة تصنيفات المنتجات', 'categories.manage', 'inventory', 'Manage product categories', 'إدارة تصنيفات المنتجات'),
('View Inventory Audits', 'عرض جرد المخزون', 'inventory_audit.view', 'inventory', 'View inventory audits', 'عرض عمليات الجرد'),
('Manage Inventory Audits', 'إدارة جرد المخزون', 'inventory_audit.manage', 'inventory', 'Manage inventory audits', 'إدارة عمليات الجرد'),
('View Documents', 'عرض المستندات', 'documents.view', 'documents', 'View uploaded documents', 'عرض المستندات المرفوعة'),
('Manage Documents', 'إدارة المستندات', 'documents.manage', 'documents', 'Upload and manage documents', 'رفع وإدارة المستندات'),
('View Workflows', 'عرض سير الموافقات', 'workflows.view', 'workflows', 'View approval workflows', 'عرض سير الموافقات'),
('Manage Workflows', 'إدارة سير الموافقات', 'workflows.manage', 'workflows', 'Configure approval workflows', 'إعداد سير الموافقات'),
('Approve Requests', 'اعتماد الطلبات', 'workflows.approve', 'workflows', 'Approve or reject workflow items', 'اعتماد أو رفض الطلبات'),
('Export Reports', 'تصدير التقارير', 'reports.export', 'reports', 'Export PDF Excel CSV', 'تصدير PDF Excel CSV'),
('View Supplier Communications', 'عرض تواصل الموردين', 'supplier_comms.view', 'suppliers', 'View supplier communication log', 'عرض سجل التواصل'),
('Manage Supplier Communications', 'إدارة تواصل الموردين', 'supplier_comms.manage', 'suppliers', 'Log supplier communications', 'تسجيل التواصل مع الموردين'),
('View Contract Renewals', 'عرض تجديد العقود', 'contract_renewals.view', 'contracts', 'View contract renewals', 'عرض تجديدات العقود'),
('Manage Contract Renewals', 'إدارة تجديد العقود', 'contract_renewals.manage', 'contracts', 'Manage contract renewals', 'إدارة تجديدات العقود'),
('View Asset Maintenance', 'عرض صيانة الأصول', 'asset_maintenance.view', 'assets', 'View asset maintenance', 'عرض صيانة الأصول'),
('Manage Asset Maintenance', 'إدارة صيانة الأصول', 'asset_maintenance.manage', 'assets', 'Manage asset maintenance', 'إدارة صيانة الأصول'),
('View Device Service', 'عرض خدمة الأجهزة', 'device_service.view', 'medical_devices', 'View device service history', 'عرض سجل خدمة الأجهزة'),
('Manage Device Service', 'إدارة خدمة الأجهزة', 'device_service.manage', 'medical_devices', 'Manage device service records', 'إدارة سجل خدمة الأجهزة'),
('View Procurement Analytics', 'عرض تحليلات المشتريات', 'procurement.analytics', 'procurement', 'View procurement KPIs', 'عرض مؤشرات المشتريات'),
('Manage Notifications', 'إدارة الإشعارات', 'notifications.manage', 'notifications', 'Manage notification center', 'إدارة مركز الإشعارات')
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    name_ar = VALUES(name_ar),
    module = VALUES(module),
    description = VALUES(description),
    description_ar = VALUES(description_ar);

INSERT INTO rateb_role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM rateb_roles r
JOIN rateb_permissions p ON p.slug IN (
    'stock_movements.view', 'stock_movements.manage', 'categories.view', 'categories.manage',
    'inventory_audit.view', 'inventory_audit.manage', 'documents.view', 'documents.manage',
    'workflows.view', 'workflows.manage', 'workflows.approve', 'reports.export',
    'supplier_comms.view', 'supplier_comms.manage', 'contract_renewals.view', 'contract_renewals.manage',
    'asset_maintenance.view', 'asset_maintenance.manage', 'device_service.view', 'device_service.manage',
    'procurement.analytics', 'notifications.manage'
)
WHERE r.slug = 'super-admin'
ON DUPLICATE KEY UPDATE role_id = role_id;

UPDATE rateb_plans SET modules = '["procurement","inventory","suppliers","assets","contracts","reports","accounting","documents","workflows"]' WHERE slug = 'professional';
UPDATE rateb_plans SET modules = '["procurement","inventory","suppliers","assets","contracts","tenders","reports","medical_devices","accounting","documents","workflows"]' WHERE slug = 'enterprise';
