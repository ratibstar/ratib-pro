-- Emergency: restore super-admin login (admin@rateb.sa / 123456)
SET NAMES utf8mb4;

INSERT INTO rateb_roles (company_id, name, slug, description, is_system)
SELECT NULL, 'Super Admin', 'super-admin', 'Platform super administrator', 1
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM rateb_roles WHERE slug = 'super-admin');

INSERT INTO rateb_users (company_id, name, email, password, is_super_admin, status, locale)
SELECT NULL, 'Super Admin', 'admin@rateb.sa', '$2y$10$7qR7yib4llgToR8eILDO5e3ovQA8lsjA3k8sJfJ2LZ0tak3QrczJW', 1, 'active', 'ar'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM rateb_users WHERE email = 'admin@rateb.sa');

UPDATE rateb_users
SET password = '$2y$10$7qR7yib4llgToR8eILDO5e3ovQA8lsjA3k8sJfJ2LZ0tak3QrczJW',
    is_super_admin = 1,
    company_id = NULL,
    status = 'active',
    name = 'Super Admin',
    locale = 'ar',
    failed_attempts = 0,
    locked_until = NULL,
    two_factor_enabled = 0,
    two_factor_secret = NULL
WHERE email = 'admin@rateb.sa';
-- If login still fails, also run migrations/223_super_admin_password_both_columns.sql (password_hash column).

INSERT INTO rateb_user_roles (user_id, role_id)
SELECT u.id, r.id
FROM rateb_users u
JOIN rateb_roles r ON r.slug = 'super-admin'
WHERE u.email = 'admin@rateb.sa'
  AND NOT EXISTS (
      SELECT 1 FROM rateb_user_roles ur
      WHERE ur.user_id = u.id AND ur.role_id = r.id
  );
