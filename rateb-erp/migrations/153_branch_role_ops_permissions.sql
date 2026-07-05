-- RATEB ERP — Branch role operational permission bundles (branch_manager / branch_user)
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

INSERT IGNORE INTO rateb_role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM rateb_roles r
INNER JOIN rateb_permissions p ON p.slug IN (
    'dashboard.view',
    'branches.view',
    'branch.dashboard.view',
    'branch.reports.view',
    'branch.transfers.view',
    'branch.transfers.manage',
    'inventory.manage',
    'procurement.manage',
    'suppliers.manage',
    'reports.view'
)
WHERE r.slug = 'branch_manager';

INSERT IGNORE INTO rateb_role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM rateb_roles r
INNER JOIN rateb_permissions p ON p.slug IN (
    'dashboard.view',
    'branches.view',
    'branch.dashboard.view',
    'branch.reports.view',
    'reports.view'
)
WHERE r.slug = 'branch_user';
