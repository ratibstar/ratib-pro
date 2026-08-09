-- Ensure launch + ultimate exist (often missing when list page size hid them / insert failed).
SET NAMES utf8mb4;

INSERT INTO rateb_plans (
    name, slug, description,
    price_monthly, price_yearly,
    max_users, max_storage_mb, max_branches,
    modules, is_active
) VALUES
(
    'انطلاق', 'launch',
    'ابدأ بلوحة التحكم والإشعارات والتقارير الأساسية.',
    39.00, 351.00, 3, 256, 1,
    '["dashboard","notifications","profile","reports"]',
    1
),
(
    'متكامل', 'ultimate',
    'منصة رتب ERP كاملة مع الحوكمة والتحكم بالوصول.',
    249.00, 2241.00, 500, 51200, 100,
    '["dashboard","procurement","inventory","suppliers","assets","contracts","tenders","reports","medical_devices","accounting","documents","workflows","notifications","access_control","profile","hr","branches","pos","recruitment","crm","projects","approval","manufacturing","payroll","quality","bi","website","logistics","marketplace"]',
    1
)
ON DUPLICATE KEY UPDATE
    is_active = 1;
-- Do NOT reset name/description/prices — admin edits must persist.
