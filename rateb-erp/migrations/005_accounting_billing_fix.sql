-- RATEB ERP — Accounting billing cleanup + demo seed
SET NAMES utf8mb4;

DELETE i FROM rateb_invoices i
LEFT JOIN rateb_companies c ON c.id = i.company_id
WHERE c.id IS NULL;

DELETE p FROM rateb_payments p
LEFT JOIN rateb_companies c ON c.id = p.company_id
WHERE c.id IS NULL;

DELETE s FROM rateb_subscriptions s
LEFT JOIN rateb_companies c ON c.id = s.company_id
WHERE c.id IS NULL;

DELETE s FROM rateb_subscriptions s
LEFT JOIN rateb_plans p ON p.id = s.plan_id
WHERE p.id IS NULL;

INSERT INTO rateb_companies (name, slug, email, status, plan_id, user_limit, storage_limit_mb)
SELECT 'شركة تجريبية', 'demo-company', 'demo@rateb.sa', 'active', p.id, 10, 1024
FROM rateb_plans p
WHERE p.slug = 'starter'
  AND NOT EXISTS (SELECT 1 FROM rateb_companies LIMIT 1)
LIMIT 1;

INSERT INTO rateb_permissions (name, name_ar, slug, module, description, description_ar) VALUES
('Manage Billing', 'إدارة الفوترة', 'billing.manage', 'accounting', 'Manage invoices and payments', 'إدارة الفواتير والمدفوعات')
ON DUPLICATE KEY UPDATE name_ar = VALUES(name_ar);

INSERT INTO rateb_role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM rateb_roles r
JOIN rateb_permissions p ON p.slug IN ('billing.manage', 'accounting.view', 'accounting.manage')
WHERE r.slug = 'super-admin'
ON DUPLICATE KEY UPDATE role_id = role_id;
