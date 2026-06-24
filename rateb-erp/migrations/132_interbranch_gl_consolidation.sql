-- RATEB ERP Phase 4 — inter-branch GL accounts + consolidated reporting permissions
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

INSERT INTO rateb_permissions (name, name_ar, slug, module, description, description_ar) VALUES
('Branch P&L Report', 'Branch P&L Report', 'branch.financial.pl', 'accounting', 'Profit and loss by branch', 'Profit and loss by branch'),
('Branch Balance Sheet', 'Branch Balance Sheet', 'branch.financial.bs', 'accounting', 'Balance sheet by branch', 'Balance sheet by branch'),
('Branch Cash Flow', 'Branch Cash Flow', 'branch.financial.cf', 'accounting', 'Cash flow by branch', 'Cash flow by branch'),
('Consolidated Financial Reports', 'Consolidated Financial Reports', 'branch.financial.consolidated', 'accounting', 'Head office consolidated statements', 'Head office consolidated statements'),
('Inter-Branch GL', 'Inter-Branch GL', 'branch.financial.interbranch', 'accounting', 'Due to/from branch accounts', 'Due to/from branch accounts')
ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description);

UPDATE rateb_permissions SET
    name_ar = CONVERT(UNHEX('D8A7D984D8A3D8B1D8A8D8A7D8AD20D988D8A7D984D8AED8B3D8A7D8A6D8B120D8ADD8B3D8A820D8A7D984D981D8B1D8B9') USING utf8mb4),
    description_ar = CONVERT(UNHEX('D982D8A7D8A6D985D8A920D8A7D984D8A3D8B1D8A8D8A7D8AD20D988D8A7D984D8AED8B3D8A7D8A6D8B120D984D983D98420D981D8B1D8B9') USING utf8mb4)
WHERE slug = 'branch.financial.pl';

UPDATE rateb_permissions SET
    name_ar = CONVERT(UNHEX('D8A7D984D985D98AD8B2D8A7D986D98AD8A920D8A7D984D8B9D985D988D985D98AD8A920D984D984D981D8B1D8B9') USING utf8mb4),
    description_ar = CONVERT(UNHEX('D8A7D984D985D98AD8B2D8A7D986D98AD8A920D8A7D984D8B9D985D988D985D98AD8A920D8ADD8B3D8A820D8A7D984D981D8B1D8B9') USING utf8mb4)
WHERE slug = 'branch.financial.bs';

UPDATE rateb_permissions SET
    name_ar = CONVERT(UNHEX('D8A7D984D8AAD8AFD981D98220D8A7D984D986D982D8AFD98A20D984D984D981D8B1D8B9') USING utf8mb4),
    description_ar = CONVERT(UNHEX('D982D8A7D8A6D985D8A920D8A7D984D8AAD8AFD981D98220D8A7D984D986D982D8AFD98A20D984D983D98420D981D8B1D8B9') USING utf8mb4)
WHERE slug = 'branch.financial.cf';

UPDATE rateb_permissions SET
    name_ar = CONVERT(UNHEX('D8AAD982D8A7D8B1D98AD8B120D985D988D8ADD8AFD8A9') USING utf8mb4),
    description_ar = CONVERT(UNHEX('D982D988D8A7D8A6D98520D985D8A7D984D98AD8A920D985D988D8ADD8AFD8A920D984D984D8A5D8AFD8A7D8B1D8A9') USING utf8mb4)
WHERE slug = 'branch.financial.consolidated';

UPDATE rateb_permissions SET
    name_ar = CONVERT(UNHEX('D8ADD8B3D8A7D8A8D8A7D8AA20D8A8D98AD98620D8A7D984D981D8B1D988D8B9') USING utf8mb4),
    description_ar = CONVERT(UNHEX('D985D8AFD98AD98620D988D8AFD8A7D8A6D98620D8A8D98AD98620D8A7D984D981D8B1D988D8B9') USING utf8mb4)
WHERE slug = 'branch.financial.interbranch';

INSERT IGNORE INTO rateb_role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM rateb_roles r
JOIN rateb_permissions p ON p.slug IN ('branch.financial.pl','branch.financial.bs','branch.financial.cf','branch.financial.consolidated','branch.financial.interbranch')
WHERE r.slug IN ('hq_admin','hq_manager','company-full-access');

INSERT IGNORE INTO rateb_role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM rateb_roles r
JOIN rateb_permissions p ON p.slug IN ('branch.financial.pl','branch.financial.bs','branch.financial.cf')
WHERE r.slug IN ('branch_manager','branch_user');

INSERT INTO rateb_chart_of_accounts (company_id, code, name, name_ar, account_type, parent_id, is_active)
SELECT c.id, '1350', 'Due From Branches', 'Due From Branches', 'asset', p.id, 1
FROM rateb_companies c
LEFT JOIN rateb_chart_of_accounts p ON p.company_id = c.id AND p.code = '1000'
WHERE NOT EXISTS (SELECT 1 FROM rateb_chart_of_accounts x WHERE x.company_id = c.id AND x.code = '1350');

INSERT INTO rateb_chart_of_accounts (company_id, code, name, name_ar, account_type, parent_id, is_active)
SELECT c.id, '2150', 'Due To Branches', 'Due To Branches', 'liability', p.id, 1
FROM rateb_companies c
LEFT JOIN rateb_chart_of_accounts p ON p.company_id = c.id AND p.code = '2000'
WHERE NOT EXISTS (SELECT 1 FROM rateb_chart_of_accounts x WHERE x.company_id = c.id AND x.code = '2150');

UPDATE rateb_chart_of_accounts SET name_ar = CONVERT(UNHEX('D985D8AFD98AD98620D984D984D981D8B1D988D8B9') USING utf8mb4) WHERE code = '1350' AND (name_ar IS NULL OR name_ar = 'Due From Branches');
UPDATE rateb_chart_of_accounts SET name_ar = CONVERT(UNHEX('D8AFD8A7D8A6D98620D984D984D981D8B1D988D8B9') USING utf8mb4) WHERE code = '2150' AND (name_ar IS NULL OR name_ar = 'Due To Branches');
