-- Emergency: reset admin@rateb.sa / 123456 and clear account lock (run in phpMyAdmin on admin_rateb-erp).
-- Production uses column `password` only (no password_hash).
SET NAMES utf8mb4;

UPDATE rateb_users
SET password = '$2y$10$7qR7yib4llgToR8eILDO5e3ovQA8lsjA3k8sJfJ2LZ0tak3QrczJW',
    is_super_admin = 1,
    company_id = NULL,
    status = 'active',
    failed_attempts = 0,
    locked_until = NULL,
    two_factor_enabled = 0,
    two_factor_secret = NULL
WHERE email = 'admin@rateb.sa';
