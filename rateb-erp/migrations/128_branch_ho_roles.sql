-- RATEB ERP — Head Office roles + branch dashboard permissions
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

INSERT INTO rateb_permissions (name, name_ar, slug, module, description, description_ar) VALUES
('Branch Dashboard', 'Branch Dashboard', 'branch.dashboard.view', 'branches', 'View branch KPI dashboard', 'View branch KPI dashboard'),
('Branch Comparison', 'Branch Comparison', 'branch.dashboard.compare', 'branches', 'Compare metrics between branches', 'Compare metrics between branches'),
('Branch Reports', 'Branch Reports', 'branch.reports.view', 'branches', 'View branch-level reports', 'View branch-level reports'),
('Inter-Branch Transfers View', 'Inter-Branch Transfers View', 'branch.transfers.view', 'branches', 'View inter-branch transfer requests', 'View inter-branch transfer requests'),
('Inter-Branch Transfers Manage', 'Inter-Branch Transfers Manage', 'branch.transfers.manage', 'branches', 'Create and approve inter-branch transfers', 'Create and approve inter-branch transfers')
ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description);

UPDATE rateb_permissions SET
    name_ar = CONVERT(UNHEX('D984D988D8ADD8A920D8A7D984D981D8B1D988D8B9') USING utf8mb4),
    description_ar = CONVERT(UNHEX('D8B9D8B1D8B620D985D8A4D8B4D8B1D8A7D8AA20D8A7D984D8A3D8AFD8A7D8A120D984D984D981D8B1D988D8B9') USING utf8mb4)
WHERE slug = 'branch.dashboard.view';

UPDATE rateb_permissions SET
    name_ar = CONVERT(UNHEX('D985D982D8A7D8B1D986D8A920D8A7D984D981D8B1D988D8B9') USING utf8mb4),
    description_ar = CONVERT(UNHEX('D985D982D8A7D8B1D986D8A920D985D8A4D8B4D8B1D8A7D8AA20D8A8D98AD98620D8A7D984D981D8B1D988D8B9') USING utf8mb4)
WHERE slug = 'branch.dashboard.compare';

UPDATE rateb_permissions SET
    name_ar = CONVERT(UNHEX('D8AAD982D8A7D8B1D98AD8B120D8A7D984D981D8B1D988D8B9') USING utf8mb4),
    description_ar = CONVERT(UNHEX('D8AAD982D8A7D8B1D98AD8B120D8A5D8AFD8A7D8B1D8A920D8A7D984D981D8B1D988D8B9') USING utf8mb4)
WHERE slug = 'branch.reports.view';

UPDATE rateb_permissions SET
    name_ar = CONVERT(UNHEX('D8B9D8B1D8B620D8A7D984D8AAD8ADD988D98AD98420D8A8D98AD98620D8A7D984D981D8B1D988D8B9') USING utf8mb4),
    description_ar = CONVERT(UNHEX('D8B9D8B1D8B620D8B7D984D8A8D8A7D8AA20D8A7D984D8AAD8ADD988D98AD98420D8A8D98AD98620D8A7D984D981D8B1D988D8B9') USING utf8mb4)
WHERE slug = 'branch.transfers.view';

UPDATE rateb_permissions SET
    name_ar = CONVERT(UNHEX('D8A5D8AFD8A7D8B1D8A920D8A7D984D8AAD8ADD988D98AD98420D8A8D98AD98620D8A7D984D981D8B1D988D8B9') USING utf8mb4),
    description_ar = CONVERT(UNHEX('D8A5D986D8B4D8A7D8A120D988D8A7D8B9D8AAD985D8A7D8AF20D8A7D984D8AAD8ADD988D98AD98420D8A8D98AD98620D8A7D984D981D8B1D988D8B9') USING utf8mb4)
WHERE slug = 'branch.transfers.manage';

INSERT INTO rateb_roles (company_id, name, slug, description, is_system)
SELECT c.id, 'HQ Admin', 'hq_admin', 'Head office — all branches', 1
FROM rateb_companies c
WHERE NOT EXISTS (SELECT 1 FROM rateb_roles r WHERE r.company_id = c.id AND r.slug = 'hq_admin');

INSERT INTO rateb_roles (company_id, name, slug, description, is_system)
SELECT c.id, 'HQ Manager', 'hq_manager', 'Head office manager — all branches read/compare', 1
FROM rateb_companies c
WHERE NOT EXISTS (SELECT 1 FROM rateb_roles r WHERE r.company_id = c.id AND r.slug = 'hq_manager');

INSERT INTO rateb_roles (company_id, name, slug, description, is_system)
SELECT c.id, 'Branch Manager', 'branch_manager', 'Single-branch manager', 1
FROM rateb_companies c
WHERE NOT EXISTS (SELECT 1 FROM rateb_roles r WHERE r.company_id = c.id AND r.slug = 'branch_manager');

INSERT INTO rateb_roles (company_id, name, slug, description, is_system)
SELECT c.id, 'Branch User', 'branch_user', 'Single-branch operational user', 1
FROM rateb_companies c
WHERE NOT EXISTS (SELECT 1 FROM rateb_roles r WHERE r.company_id = c.id AND r.slug = 'branch_user');

INSERT IGNORE INTO rateb_role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM rateb_roles r
JOIN rateb_permissions p ON p.slug IN ('branches.access_all', 'branch.dashboard.view', 'branch.dashboard.compare', 'branch.reports.view', 'branch.transfers.view', 'branch.transfers.manage', 'branches.view', 'branches.manage')
WHERE r.slug = 'hq_admin';

INSERT IGNORE INTO rateb_role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM rateb_roles r
JOIN rateb_permissions p ON p.slug IN ('branches.access_all', 'branch.dashboard.view', 'branch.dashboard.compare', 'branch.reports.view', 'branch.transfers.view')
WHERE r.slug = 'hq_manager';

INSERT IGNORE INTO rateb_role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM rateb_roles r
JOIN rateb_permissions p ON p.slug IN ('branch.dashboard.view', 'branch.reports.view', 'branch.transfers.view', 'branch.transfers.manage', 'branches.view')
WHERE r.slug = 'branch_manager';

INSERT IGNORE INTO rateb_role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM rateb_roles r
JOIN rateb_permissions p ON p.slug IN ('branch.dashboard.view', 'branch.reports.view')
WHERE r.slug = 'branch_user';

INSERT IGNORE INTO rateb_role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM rateb_roles r
JOIN rateb_permissions p ON p.slug IN ('branch.dashboard.view', 'branch.dashboard.compare', 'branch.reports.view', 'branch.transfers.view', 'branch.transfers.manage')
WHERE r.slug = 'company-full-access';
