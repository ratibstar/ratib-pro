-- Canonical plan prices/limits — must match site/pricing and config/lang plan_*_features.
SET NAMES utf8mb4;

UPDATE rateb_plans SET
    name = 'Starter',
    description = 'Essential procurement for small clinics',
    price_monthly = 1500.00,
    price_yearly = 16000.00,
    max_users = 5,
    max_branches = 3,
    max_storage_mb = 512,
    is_active = 1
WHERE slug = 'starter';

UPDATE rateb_plans SET
    name = 'Professional',
    description = 'Full procurement and inventory suite',
    price_monthly = 1800.00,
    price_yearly = 19999.00,
    max_users = 25,
    max_branches = 5,
    max_storage_mb = 2048,
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
    is_active = 1
WHERE slug = 'enterprise';

UPDATE rateb_plans SET is_active = 0
WHERE slug NOT IN ('starter', 'professional', 'enterprise')
  AND is_active = 1;
