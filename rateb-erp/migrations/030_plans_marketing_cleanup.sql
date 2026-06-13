-- RATEB ERP — Hide ad-hoc test plans from public marketing (keep starter/professional/enterprise)
SET NAMES utf8mb4;

UPDATE rateb_plans SET is_active = 0
WHERE slug NOT IN ('starter', 'professional', 'enterprise')
  AND is_active = 1;
