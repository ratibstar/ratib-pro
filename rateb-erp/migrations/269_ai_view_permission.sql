-- RATEB ERP — Register ai.view in rateb_permissions for Roles matrix UI
-- Slug/module match config/module-permissions.php + permission-labels-*.php.
-- Does not change middleware, AiController, or permission_implies.
--
-- Safe to re-run: ON DUPLICATE KEY UPDATE on slug unique key.

SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

INSERT INTO rateb_permissions (name, name_ar, slug, module, description, description_ar) VALUES
(
    'View RATEB AI',
    'عرض RATEB AI',
    'ai.view',
    'ai',
    'Access the RATEB AI assistant',
    'الوصول إلى مساعد RATEB AI'
)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    name_ar = VALUES(name_ar),
    module = VALUES(module),
    description = VALUES(description),
    description_ar = VALUES(description_ar);

-- Ensure platform super-admin can assign/see the slug in matrix scope (idempotent).
INSERT IGNORE INTO rateb_role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM rateb_roles r
INNER JOIN rateb_permissions p ON p.slug = 'ai.view'
WHERE r.slug IN ('super-admin', 'super_admin');
