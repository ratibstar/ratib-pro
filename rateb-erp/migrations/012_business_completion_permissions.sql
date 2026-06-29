-- RATEB ERP — Business completion permissions (English INSERT; Arabic via UNHEX migrations)
SET NAMES utf8mb4;

INSERT INTO rateb_permissions (name, slug, module, description) VALUES
('View Inventory Batches', 'inventory_batches.view', 'inventory', 'View inventory batch tracking'),
('Manage Inventory Batches', 'inventory_batches.manage', 'inventory', 'Create and manage inventory batches'),
('View Supplier Classifications', 'supplier_classifications.view', 'suppliers', 'View supplier classification tiers'),
('Manage Supplier Classifications', 'supplier_classifications.manage', 'suppliers', 'Manage supplier classifications'),
('View Supplier KPI', 'supplier_kpi.view', 'suppliers', 'View supplier KPI dashboard'),
('View Asset Assignments', 'asset_assignments.view', 'assets', 'View asset assignment tracking'),
('Manage Asset Assignments', 'asset_assignments.manage', 'assets', 'Manage asset assignments'),
('View Asset Depreciation', 'asset_depreciation.view', 'assets', 'View asset depreciation records'),
('Manage Asset Depreciation', 'asset_depreciation.manage', 'assets', 'Record asset depreciation'),
('View Device Warranty', 'device_warranty.view', 'medical_devices', 'View device warranty tracking'),
('Manage Device Spare Parts', 'device_spare_parts.manage', 'medical_devices', 'Manage device spare parts inventory'),
('View Executive Dashboard', 'executive.dashboard.view', 'reports', 'Cross-tenant executive dashboard'),
('View Company KPI Reports', 'reports.kpi.view', 'reports', 'Company KPI dashboard and exports'),
('View Cost Analysis Reports', 'reports.cost_analysis.view', 'reports', 'Cost analysis reports'),
('View Inventory Valuation Reports', 'reports.inventory_valuation.view', 'reports', 'Inventory valuation reports')
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    module = VALUES(module),
    description = VALUES(description);

INSERT INTO rateb_role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM rateb_roles r
JOIN rateb_permissions p ON p.slug IN (
    'inventory_batches.view', 'inventory_batches.manage',
    'supplier_classifications.view', 'supplier_classifications.manage',
    'supplier_kpi.view',
    'asset_assignments.view', 'asset_assignments.manage',
    'asset_depreciation.view', 'asset_depreciation.manage',
    'device_warranty.view', 'device_spare_parts.manage',
    'executive.dashboard.view', 'reports.kpi.view', 'reports.cost_analysis.view', 'reports.inventory_valuation.view'
)
WHERE r.slug = 'super_admin'
ON DUPLICATE KEY UPDATE role_id = role_id;
