-- Canonical plan prices, limits, and module bundles (starter / professional / enterprise).
SET NAMES utf8mb4;

UPDATE rateb_plans SET
    name = 'Starter',
    description = 'Essential procurement for small clinics',
    price_monthly = 1500.00,
    price_yearly = 16000.00,
    max_users = 5,
    max_branches = 3,
    max_storage_mb = 512,
    modules = '["procurement","inventory","suppliers","reports"]',
    is_active = 1
WHERE slug = 'starter';

UPDATE rateb_plans SET
    name = 'Professional',
    description = 'Full procurement, inventory, contracts, and reporting suite',
    price_monthly = 1800.00,
    price_yearly = 19999.00,
    max_users = 25,
    max_branches = 5,
    max_storage_mb = 2048,
    modules = '["procurement","inventory","suppliers","assets","contracts","reports","accounting","documents","workflows","hr"]',
    is_active = 1
WHERE slug = 'professional';

UPDATE rateb_plans SET
    name = 'Enterprise',
    description = 'Complete healthcare ERP with all modules',
    price_monthly = 3000.00,
    price_yearly = 29999.00,
    max_users = 100,
    max_branches = 25,
    max_storage_mb = 10240,
    modules = '["procurement","inventory","suppliers","assets","contracts","tenders","reports","medical_devices","accounting","documents","workflows","hr"]',
    is_active = 1
WHERE slug = 'enterprise';

UPDATE rateb_plans SET is_active = 0
WHERE slug NOT IN ('starter', 'professional', 'enterprise')
  AND is_active = 1;
