-- RATEB ERP — Business completion permissions (stabilization phase)
SET NAMES utf8mb4;

INSERT INTO rateb_permissions (name, name_ar, slug, module, description, description_ar) VALUES
('View Inventory Batches', 'عرض دفعات المخزون', 'inventory_batches.view', 'inventory', 'View inventory batch tracking', 'عرض تتبع دفعات المخزون'),
('Manage Inventory Batches', 'إدارة دفعات المخزون', 'inventory_batches.manage', 'inventory', 'Create and manage inventory batches', 'إنشاء وإدارة دفعات المخزون'),
('View Supplier Classifications', 'عرض تصنيف الموردين', 'supplier_classifications.view', 'suppliers', 'View supplier classification tiers', 'عرض تصنيفات الموردين'),
('Manage Supplier Classifications', 'إدارة تصنيف الموردين', 'supplier_classifications.manage', 'suppliers', 'Manage supplier classifications', 'إدارة تصنيفات الموردين'),
('View Supplier KPI', 'عرض مؤشرات الموردين', 'supplier_kpi.view', 'suppliers', 'View supplier KPI dashboard', 'عرض لوحة مؤشرات الموردين'),
('View Asset Assignments', 'عرض تعيين الأصول', 'asset_assignments.view', 'assets', 'View asset assignment tracking', 'عرض تعيين الأصول'),
('Manage Asset Assignments', 'إدارة تعيين الأصول', 'asset_assignments.manage', 'assets', 'Manage asset assignments', 'إدارة تعيين الأصول'),
('View Asset Depreciation', 'عرض إهلاك الأصول', 'asset_depreciation.view', 'assets', 'View asset depreciation records', 'عرض سجل إهلاك الأصول'),
('Manage Asset Depreciation', 'إدارة إهلاك الأصول', 'asset_depreciation.manage', 'assets', 'Record asset depreciation', 'تسجيل إهلاك الأصول'),
('View Device Warranty', 'عرض ضمان الأجهزة', 'device_warranty.view', 'medical_devices', 'View device warranty tracking', 'عرض تتبع ضمان الأجهزة'),
('Manage Device Spare Parts', 'إدارة قطع غيار الأجهزة', 'device_spare_parts.manage', 'medical_devices', 'Manage device spare parts inventory', 'إدارة مخزون قطع غيار الأجهزة'),
('View Executive Dashboard', 'عرض لوحة الإدارة التنفيذية', 'executive.dashboard.view', 'reports', 'Cross-tenant executive dashboard', 'لوحة إدارية تنفيذية عبر الشركات'),
('View Company KPI Reports', 'عرض تقارير مؤشرات الشركة', 'reports.kpi.view', 'reports', 'Company KPI dashboard and exports', 'لوحة مؤشرات الشركة والتصدير'),
('View Cost Analysis Reports', 'عرض تقارير تحليل التكلفة', 'reports.cost_analysis.view', 'reports', 'Cost analysis reports', 'تقارير تحليل التكلفة'),
('View Inventory Valuation Reports', 'عرض تقارير تقييم المخزون', 'reports.inventory_valuation.view', 'reports', 'Inventory valuation reports', 'تقارير تقييم المخزون')
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    name_ar = VALUES(name_ar),
    module = VALUES(module),
    description = VALUES(description),
    description_ar = VALUES(description_ar);

INSERT INTO rateb_role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM rateb_roles r
JOIN rateb_permissions p ON p.slug IN (
    'inventory_batches.view', 'inventory_batches.manage',
    'supplier_classifications.view', 'supplier_classifications.manage',
    'supplier_kpi.view',
    'asset_assignments.view', 'asset_assignments.manage',
    'asset_depreciation.view', 'asset_depreciation.manage',
    'device_warranty.view', 'device_spare_parts.manage',
    'executive.dashboard.view',
    'reports.kpi.view', 'reports.cost_analysis.view', 'reports.inventory_valuation.view'
)
WHERE r.slug = 'super-admin'
ON DUPLICATE KEY UPDATE role_id = role_id;
