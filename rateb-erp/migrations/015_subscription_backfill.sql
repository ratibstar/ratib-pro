-- Backfill subscriptions for active companies + queue retry support
SET NAMES utf8mb4;

-- Assign starter plan to active companies missing plan_id
UPDATE rateb_companies c
JOIN rateb_plans p ON p.slug = 'starter'
SET c.plan_id = p.id
WHERE c.status = 'active' AND (c.plan_id IS NULL OR c.plan_id = 0);

-- Create active yearly subscriptions where missing
INSERT INTO rateb_subscriptions (company_id, plan_id, status, billing_cycle, amount, starts_at, ends_at, auto_renew)
SELECT c.id, c.plan_id, 'active', 'yearly', COALESCE(p.price_yearly, 0.00), CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 YEAR), 1
FROM rateb_companies c
JOIN rateb_plans p ON p.id = c.plan_id
WHERE c.status = 'active' AND c.plan_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM rateb_subscriptions s WHERE s.company_id = c.id LIMIT 1);

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_notification_queue' AND COLUMN_NAME = 'attempt_count');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_notification_queue ADD COLUMN attempt_count TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER status',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Default company portal role (replaces legacy module-permissions bypass)
INSERT INTO rateb_roles (company_id, name, slug, description, is_system)
SELECT NULL, 'Company Full Access', 'company-full-access', 'Default ERP access for company portal users', 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM rateb_roles WHERE slug = 'company-full-access' LIMIT 1);

INSERT INTO rateb_role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM rateb_roles r
JOIN rateb_permissions p ON p.module NOT IN ('companies', 'subscriptions', 'plans', 'permissions', 'roles')
WHERE r.slug = 'company-full-access'
ON DUPLICATE KEY UPDATE role_id = role_id;

INSERT INTO rateb_user_roles (user_id, role_id)
SELECT u.id, r.id FROM rateb_users u
JOIN rateb_roles r ON r.slug = 'company-full-access'
WHERE u.company_id IS NOT NULL AND u.is_super_admin = 0 AND u.status = 'active'
  AND NOT EXISTS (SELECT 1 FROM rateb_user_roles ur WHERE ur.user_id = u.id LIMIT 1)
ON DUPLICATE KEY UPDATE user_id = user_id;
