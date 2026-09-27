-- Emergency follow-up: login still fails when rateb_users has a stale password_hash column.
-- Sets admin@rateb.sa / 123456 on every password column that exists.
SET NAMES utf8mb4;

SET @hash = '$2y$10$7qR7yib4llgToR8eILDO5e3ovQA8lsjA3k8sJfJ2LZ0tak3QrczJW';

UPDATE rateb_users
SET password = @hash,
    is_super_admin = 1,
    company_id = NULL,
    status = 'active',
    failed_attempts = 0,
    locked_until = NULL,
    two_factor_enabled = 0,
    two_factor_secret = NULL
WHERE email = 'admin@rateb.sa';

-- Safe on DBs that added password_hash to rateb_users (ignore error if column missing).
UPDATE rateb_users
SET password_hash = @hash
WHERE email = 'admin@rateb.sa';
