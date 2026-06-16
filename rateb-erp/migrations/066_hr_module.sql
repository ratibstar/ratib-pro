-- RATEB ERP — Human Resources module permissions (UNHEX; deploy-safe)
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

INSERT INTO rateb_permissions (name, name_ar, slug, module, description, description_ar) VALUES
('View HR', 'View HR', 'hr.view', 'hr', 'View HR dashboard and employee lists', 'View HR dashboard and employee lists'),
('Manage HR', 'Manage HR', 'hr.manage', 'hr', 'Manage HR records, attendance, and payroll', 'Manage HR records, attendance, and payroll')
ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description);

UPDATE rateb_permissions SET name_ar = CONVERT(UNHEX('D8B9D8B1D8B620D8A7D984D985D988D8A7D8B1D8AF20D8A7D984D8A8D8B4D8B1D98AD8A9') USING utf8mb4), description_ar = CONVERT(UNHEX('D8B9D8B1D8B620D984D988D8ADD8A920D8A7D984D985D988D8A7D8B1D8AF20D8A7D984D8A8D8B4D8B1D98AD8A920D988D982D988D8A7D8A6D98520D8A7D984D985D988D8B8D981D98AD986') USING utf8mb4) WHERE slug = 'hr.view';
UPDATE rateb_permissions SET name_ar = CONVERT(UNHEX('D8A5D8AFD8A7D8B1D8A920D8A7D984D985D988D8A7D8B1D8AF20D8A7D984D8A8D8B4D8B1D98AD8A9') USING utf8mb4), description_ar = CONVERT(UNHEX('D8A5D8AFD8A7D8B1D8A920D8B3D8ACD984D8A7D8AA20D8A7D984D985D988D8A7D8B1D8AF20D8A7D984D8A8D8B4D8B1D98AD8A920D988D8A7D984D8ADD8B6D988D8B120D988D8A7D984D8B1D988D8A7D8AAD8A8') USING utf8mb4) WHERE slug = 'hr.manage';

INSERT IGNORE INTO rateb_role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM rateb_roles r
JOIN rateb_permissions p ON p.slug IN ('hr.view', 'hr.manage')
WHERE r.slug IN ('company-full-access', 'super-admin');
