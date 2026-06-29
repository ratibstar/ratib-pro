-- RATEB ERP — canonical marketing/billing plan prices and limits (starter / professional / enterprise)
SET NAMES utf8mb4;

UPDATE rateb_plans SET
    name = 'Starter',
    description = 'Essential procurement for small clinics',
    price_monthly = 299.00,
    price_yearly = 2990.00,
    max_users = 5,
    max_branches = 3,
    max_storage_mb = 512,
    is_active = 1
WHERE slug = 'starter';

UPDATE rateb_plans SET
    name = 'Professional',
    description = 'Full procurement and inventory suite',
    price_monthly = 799.00,
    price_yearly = 7990.00,
    max_users = 25,
    max_branches = 5,
    max_storage_mb = 2048,
    is_active = 1
WHERE slug = 'professional';

UPDATE rateb_plans SET
    name = 'Enterprise',
    description = 'Complete healthcare ERP with all modules',
    price_monthly = 1999.00,
    price_yearly = 19990.00,
    max_users = 100,
    max_branches = 25,
    max_storage_mb = 10240,
    is_active = 1
WHERE slug = 'enterprise';

UPDATE rateb_plans SET is_active = 0
WHERE slug NOT IN ('starter', 'professional', 'enterprise')
  AND is_active = 1;
