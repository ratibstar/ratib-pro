-- RATEB ERP — Human Resources module permissions
SET NAMES utf8mb4;

INSERT INTO rateb_permissions (name, name_ar, slug, module, description, description_ar) VALUES
('View HR', 'عرض الموارد البشرية', 'hr.view', 'hr', 'View HR dashboard and employee lists', 'عرض لوحة الموارد البشرية وقوائم الموظفين'),
('Manage HR', 'إدارة الموارد البشرية', 'hr.manage', 'hr', 'Manage HR records, attendance, and payroll', 'إدارة سجلات الموارد البشرية والحضور والرواتب')
ON DUPLICATE KEY UPDATE name_ar = VALUES(name_ar), description_ar = VALUES(description_ar);

INSERT IGNORE INTO rateb_role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM rateb_roles r
JOIN rateb_permissions p ON p.slug IN ('hr.view', 'hr.manage')
WHERE r.slug IN ('company-full-access', 'super-admin');
