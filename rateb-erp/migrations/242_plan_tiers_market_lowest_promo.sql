-- Market-lowest SaaS prices + 3 months free on yearly (9 × monthly).
SET NAMES utf8mb4;

INSERT INTO rateb_plans (
    name, slug, description,
    price_monthly, price_yearly,
    max_users, max_storage_mb, max_branches,
    modules, is_active
) VALUES
(
    'Launch', 'launch',
    'Start with the control panel, alerts, and essential reporting',
    39.00, 351.00, 3, 256, 1,
    '["dashboard","notifications","profile","reports"]',
    1
),
(
    'Starter', 'starter',
    'Core purchasing operations for clinics and small establishments',
    69.00, 621.00, 8, 512, 3,
    '["dashboard","notifications","profile","reports","procurement","inventory","suppliers"]',
    1
),
(
    'Commerce', 'commerce',
    'Sell, stock, and deliver with POS, logistics, and marketplace',
    99.00, 891.00, 20, 2048, 5,
    '["dashboard","notifications","profile","reports","procurement","inventory","suppliers","pos","logistics","marketplace","branches"]',
    1
),
(
    'Professional', 'professional',
    'Grow with HR, CRM, projects, accounting, and approvals',
    129.00, 1161.00, 50, 5120, 10,
    '["dashboard","notifications","profile","reports","procurement","inventory","suppliers","pos","logistics","marketplace","branches","hr","recruitment","crm","projects","approval","accounting","assets","contracts","documents","workflows"]',
    1
),
(
    'Enterprise', 'enterprise',
    'Industrial depth: manufacturing, payroll, quality, BI, and website',
    179.00, 1611.00, 150, 15360, 25,
    '["dashboard","notifications","profile","reports","procurement","inventory","suppliers","pos","logistics","marketplace","branches","hr","recruitment","crm","projects","approval","accounting","assets","contracts","documents","workflows","manufacturing","payroll","quality","bi","website","tenders","medical_devices"]',
    1
),
(
    'Ultimate', 'ultimate',
    'Full Rateb ERP platform with governance and access control',
    249.00, 2241.00, 500, 51200, 100,
    '["dashboard","procurement","inventory","suppliers","assets","contracts","tenders","reports","medical_devices","accounting","documents","workflows","notifications","access_control","profile","hr","branches","pos","recruitment","crm","projects","approval","manufacturing","payroll","quality","bi","website","logistics","marketplace"]',
    1
)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    description = VALUES(description),
    price_monthly = VALUES(price_monthly),
    price_yearly = VALUES(price_yearly),
    max_users = VALUES(max_users),
    max_storage_mb = VALUES(max_storage_mb),
    max_branches = VALUES(max_branches),
    modules = VALUES(modules),
    is_active = 1;

UPDATE rateb_plans SET is_active = 0
WHERE slug NOT IN ('launch', 'starter', 'commerce', 'professional', 'enterprise', 'ultimate')
  AND is_active = 1;
