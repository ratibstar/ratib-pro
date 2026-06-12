-- Repair plans/companies with empty module lists (fixes hidden company nav)
SET NAMES utf8mb4;

UPDATE rateb_plans
SET modules = '["procurement","inventory","suppliers"]'
WHERE modules IS NULL OR TRIM(modules) = '' OR modules = '[]';

UPDATE rateb_companies c
JOIN rateb_plans p ON p.id = c.plan_id
SET c.modules = p.modules
WHERE c.modules IS NULL OR TRIM(c.modules) = '' OR c.modules = '[]';

UPDATE rateb_companies
SET user_limit = 10
WHERE user_limit IS NULL OR user_limit < 1;

UPDATE rateb_companies
SET storage_limit_mb = 1024
WHERE storage_limit_mb IS NULL OR storage_limit_mb < 1;

UPDATE rateb_plans
SET max_users = 10
WHERE max_users IS NULL OR max_users < 1;

UPDATE rateb_plans
SET max_storage_mb = 1024
WHERE max_storage_mb IS NULL OR max_storage_mb < 1;
