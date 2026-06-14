-- RATEB ERP — Accounting approve permission (اعتماد القيود والسندات)
SET NAMES utf8mb4;

INSERT INTO rateb_permissions (name, name_ar, slug, module, description, description_ar)
SELECT * FROM (
    SELECT
        'Approve Journal & Vouchers' AS name,
        'اعتماد القيود والسندات' AS name_ar,
        'accounting.approve' AS slug,
        'accounting' AS module,
        'Approve (post) manual journal entries and cash vouchers' AS description,
        'اعتماد وترحيل القيود اليومية وسندات الصرف والقبض' AS description_ar
) AS src
WHERE NOT EXISTS (SELECT 1 FROM rateb_permissions WHERE slug = 'accounting.approve');

-- Grant to company-full-access
INSERT INTO rateb_role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM rateb_roles r
JOIN rateb_permissions p ON p.slug = 'accounting.approve'
WHERE r.slug = 'company-full-access'
ON DUPLICATE KEY UPDATE role_id = role_id;

-- Mirror: roles that had accounting.post also get accounting.approve
INSERT INTO rateb_role_permissions (role_id, permission_id)
SELECT DISTINCT rp.role_id, p_new.id
FROM rateb_role_permissions rp
JOIN rateb_permissions p_old ON p_old.id = rp.permission_id AND p_old.slug = 'accounting.post'
JOIN rateb_permissions p_new ON p_new.slug = 'accounting.approve'
ON DUPLICATE KEY UPDATE role_id = role_id;
