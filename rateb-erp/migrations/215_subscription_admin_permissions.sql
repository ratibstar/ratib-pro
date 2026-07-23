-- RATEB ERP — Subscription Admin Panel permissions (Phase 9)
-- Adds subscriptions.view for read-only ops console access.
-- subscriptions.manage already exists; implies view via permissions-system.php.
--
-- Run: mysql -u user -p database < migrations/215_subscription_admin_permissions.sql

SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

INSERT INTO rateb_permissions (name, name_ar, slug, module, description, description_ar) VALUES
(
    'View Subscriptions',
    'عرض الاشتراكات',
    'subscriptions.view',
    'subscriptions',
    'View subscription engine ops console',
    'عرض لوحة تشغيل محرك الاشتراكات'
)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    name_ar = VALUES(name_ar),
    description = VALUES(description),
    description_ar = VALUES(description_ar);

-- Super-admin / platform roles that already manage subscriptions also get view.
INSERT IGNORE INTO rateb_role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM rateb_roles r
INNER JOIN rateb_permissions p ON p.slug = 'subscriptions.view'
WHERE r.slug IN ('super-admin', 'super_admin');

INSERT IGNORE INTO rateb_role_permissions (role_id, permission_id)
SELECT rp.role_id, pv.id
FROM rateb_role_permissions rp
INNER JOIN rateb_permissions pm ON pm.id = rp.permission_id AND pm.slug = 'subscriptions.manage'
INNER JOIN rateb_permissions pv ON pv.slug = 'subscriptions.view';
