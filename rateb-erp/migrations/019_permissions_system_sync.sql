-- RATEB ERP — sync company-full-access with entire operational permission set
SET NAMES utf8mb4;

-- Remove platform-only permissions wrongly granted to company portal role
DELETE rp FROM rateb_role_permissions rp
INNER JOIN rateb_roles r ON r.id = rp.role_id
INNER JOIN rateb_permissions p ON p.id = rp.permission_id
WHERE r.slug = 'company-full-access'
  AND (
    p.module IN ('companies', 'subscriptions', 'plans', 'permissions', 'roles', 'users', 'settings', 'access')
    OR p.slug IN (
        'executive.dashboard.view',
        'billing.manage',
        'companies.view',
        'companies.manage',
        'company_plans.manage',
        'subscriptions.manage',
        'plans.manage',
        'settings.manage',
        'access.manage',
        'users.manage',
        'roles.manage',
        'permissions.manage'
    )
  );

-- Grant every company-operational permission (dashboard + all tenant modules + notifications)
INSERT INTO rateb_role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM rateb_roles r
JOIN rateb_permissions p ON (
    p.module IN (
        'dashboard',
        'procurement',
        'inventory',
        'suppliers',
        'assets',
        'contracts',
        'tenders',
        'reports',
        'medical_devices',
        'accounting',
        'documents',
        'workflows',
        'notifications'
    )
    AND p.slug NOT IN ('executive.dashboard.view', 'billing.manage')
)
WHERE r.slug = 'company-full-access'
ON DUPLICATE KEY UPDATE role_id = role_id;

-- Ensure super-admin retains full matrix (idempotent)
INSERT INTO rateb_role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM rateb_roles r
CROSS JOIN rateb_permissions p
WHERE r.slug = 'super-admin'
ON DUPLICATE KEY UPDATE role_id = role_id;
