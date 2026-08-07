-- RATEB ERP — Add marketplace to SaaS plan module bundles (Phase 1).
-- Source of truth also updated in config/plan-tiers.php.
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

UPDATE rateb_plans SET
    modules = '["dashboard","procurement","inventory","suppliers","assets","contracts","reports","accounting","documents","workflows","hr","branches","notifications","recruitment","logistics","crm","projects","marketplace"]',
    is_active = 1
WHERE slug = 'professional';

UPDATE rateb_plans SET
    modules = '["dashboard","procurement","inventory","suppliers","assets","contracts","tenders","reports","medical_devices","accounting","documents","workflows","hr","branches","notifications","pos","recruitment","crm","projects","approval","manufacturing","payroll","quality","bi","website","logistics","marketplace","access_control","profile"]',
    is_active = 1
WHERE slug = 'enterprise';
