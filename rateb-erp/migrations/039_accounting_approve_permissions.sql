-- RATEB ERP accounting approve permission
SET NAMES utf8mb4;

INSERT INTO rateb_permissions (name, name_ar, slug, module, description, description_ar) VALUES
('Approve Journal & Vouchers', 'اعتماد القيود والسندات', 'accounting.approve', 'accounting', 'Approve (post) manual journal entries and cash vouchers', 'اعتماد وترحيل القيود اليومية وسندات الصرف والقبض')
ON DUPLICATE KEY UPDATE name_ar = VALUES(name_ar), description_ar = VALUES(description_ar);

INSERT INTO rateb_role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM rateb_roles r
JOIN rateb_permissions p ON p.slug = 'accounting.approve'
WHERE r.slug = 'company-full-access'
ON DUPLICATE KEY UPDATE role_id = role_id;

INSERT INTO rateb_role_permissions (role_id, permission_id)
SELECT DISTINCT rp.role_id, p_new.id
FROM rateb_role_permissions rp
JOIN rateb_permissions p_old ON p_old.id = rp.permission_id AND p_old.slug = 'accounting.post'
JOIN rateb_permissions p_new ON p_new.slug = 'accounting.approve'
ON DUPLICATE KEY UPDATE role_id = role_id;
