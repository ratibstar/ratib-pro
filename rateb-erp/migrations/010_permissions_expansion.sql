-- RATEB ERP — Permissions & menu module slugs for expanded ERP (English INSERT; Arabic via later UNHEX migrations)
SET NAMES utf8mb4;

INSERT INTO rateb_permissions (name, slug, module, description) VALUES
('View Stock Movements', 'stock_movements.view', 'inventory', 'View stock movement history'),
('Manage Stock Movements', 'stock_movements.manage', 'inventory', 'Create stock movements'),
('View Product Categories', 'categories.view', 'inventory', 'View product categories'),
('Manage Product Categories', 'categories.manage', 'inventory', 'Manage product categories'),
('View Inventory Audits', 'inventory_audit.view', 'inventory', 'View inventory audits'),
('Manage Inventory Audits', 'inventory_audit.manage', 'inventory', 'Manage inventory audits'),
('View Documents', 'documents.view', 'documents', 'View uploaded documents'),
('Manage Documents', 'documents.manage', 'documents', 'Upload and manage documents'),
('View Workflows', 'workflows.view', 'workflows', 'View approval workflows'),
('Manage Workflows', 'workflows.manage', 'workflows', 'Configure approval workflows'),
('Approve Requests', 'workflows.approve', 'workflows', 'Approve or reject workflow items'),
('Export Reports', 'reports.export', 'reports', 'Export PDF Excel CSV'),
('View Supplier Communications', 'supplier_comms.view', 'suppliers', 'View supplier communication log'),
('Manage Supplier Communications', 'supplier_comms.manage', 'suppliers', 'Log supplier communications'),
('View Contract Renewals', 'contract_renewals.view', 'contracts', 'View contract renewals'),
('Manage Contract Renewals', 'contract_renewals.manage', 'contracts', 'Manage contract renewals'),
('View Asset Maintenance', 'asset_maintenance.view', 'assets', 'View asset maintenance'),
('Manage Asset Maintenance', 'asset_maintenance.manage', 'assets', 'Manage asset maintenance'),
('View Device Service', 'device_service.view', 'medical_devices', 'View device service history'),
('Manage Device Service', 'device_service.manage', 'medical_devices', 'Manage device service records'),
('View Procurement Analytics', 'procurement.analytics', 'procurement', 'View procurement KPIs'),
('Manage Notifications', 'notifications.manage', 'notifications', 'Manage notification center')
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    module = VALUES(module),
    description = VALUES(description);

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
WHERE r.slug = 'super_admin'
ON DUPLICATE KEY UPDATE role_id = role_id;

UPDATE rateb_plans SET modules = '["procurement","inventory","suppliers","assets","contracts","reports","accounting","documents","workflows"]' WHERE slug = 'professional';
UPDATE rateb_plans SET modules = '["procurement","inventory","suppliers","assets","contracts","tenders","reports","medical_devices","accounting","documents","workflows"]' WHERE slug = 'enterprise';
