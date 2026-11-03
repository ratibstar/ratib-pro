-- RATEB ERP — Register automotive.view and automotive.manage.
-- Does not alter inventory, stock, purchasing, POS, or accounting tables.
-- Safe to re-run: ON DUPLICATE KEY UPDATE on slug.

SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

INSERT INTO rateb_permissions (name, name_ar, slug, module, description, description_ar) VALUES
(
    'View automotive spare parts',
    'عرض قطع غيار السيارات',
    'automotive.view',
    'automotive',
    'Open the automotive spare parts module',
    'فتح وحدة قطع غيار السيارات'
),
(
    'Manage automotive spare parts',
    'إدارة قطع غيار السيارات',
    'automotive.manage',
    'automotive',
    'Manage the automotive spare parts module',
    'إدارة وحدة قطع غيار السيارات'
)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    name_ar = VALUES(name_ar),
    module = VALUES(module),
    description = VALUES(description),
    description_ar = VALUES(description_ar);

INSERT IGNORE INTO rateb_role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM rateb_roles r
INNER JOIN rateb_permissions p ON p.slug IN ('automotive.view', 'automotive.manage')
WHERE r.slug IN ('super-admin', 'super_admin');
