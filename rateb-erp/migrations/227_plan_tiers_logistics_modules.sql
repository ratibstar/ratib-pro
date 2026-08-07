-- RATEB ERP — Sync SaaS plan module bundles (logistics + full enterprise catalog).
-- Source of truth remains config/plan-tiers.php; this catchup updates rateb_plans JSON.
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

UPDATE rateb_plans SET
    description = 'Essential procurement for small clinics',
    modules = '["dashboard","procurement","inventory","suppliers","reports","notifications"]',
    is_active = 1
WHERE slug = 'starter';

UPDATE rateb_plans SET
    description = 'Full procurement, inventory, contracts, logistics, and reporting suite',
    modules = '["dashboard","procurement","inventory","suppliers","assets","contracts","reports","accounting","documents","workflows","hr","branches","notifications","recruitment","logistics","crm","projects"]',
    is_active = 1
WHERE slug = 'professional';

UPDATE rateb_plans SET
    description = 'Complete healthcare ERP with all modules',
    modules = '["dashboard","procurement","inventory","suppliers","assets","contracts","tenders","reports","medical_devices","accounting","documents","workflows","hr","branches","notifications","pos","recruitment","crm","projects","approval","manufacturing","payroll","quality","bi","website","logistics","access_control","profile"]',
    is_active = 1
WHERE slug = 'enterprise';
