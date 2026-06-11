-- RATEB ERP - Seed Data
SET NAMES utf8mb4;

INSERT INTO rateb_plans (name, slug, description, price_monthly, price_yearly, max_users, max_storage_mb, modules, is_active)
VALUES
('Starter', 'starter', 'Essential procurement for small clinics', 299.00, 2990.00, 5, 512, '["procurement","inventory","suppliers"]', 1),
('Professional', 'professional', 'Full procurement and inventory suite', 799.00, 7990.00, 25, 2048, '["procurement","inventory","suppliers","assets","contracts","reports"]', 1),
('Enterprise', 'enterprise', 'Complete healthcare ERP with all modules', 1999.00, 19990.00, 100, 10240, '["procurement","inventory","suppliers","assets","contracts","tenders","reports","medical_devices"]', 1)
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO rateb_permissions (name, slug, module, description) VALUES
('View Dashboard', 'dashboard.view', 'dashboard', 'Access dashboard'),
('Manage Companies', 'companies.manage', 'companies', 'Full company management'),
('View Companies', 'companies.view', 'companies', 'View companies'),
('Manage Subscriptions', 'subscriptions.manage', 'subscriptions', 'Manage subscriptions'),
('Manage Plans', 'plans.manage', 'plans', 'Manage plans'),
('Manage Users', 'users.manage', 'users', 'Manage users'),
('Manage Roles', 'roles.manage', 'roles', 'Manage roles'),
('Manage Permissions', 'permissions.manage', 'permissions', 'Manage permissions'),
('Manage Procurement', 'procurement.manage', 'procurement', 'Manage procurement'),
('Manage Inventory', 'inventory.manage', 'inventory', 'Manage inventory'),
('Manage Suppliers', 'suppliers.manage', 'suppliers', 'Manage suppliers'),
('Manage Assets', 'assets.manage', 'assets', 'Manage assets'),
('Manage Contracts', 'contracts.manage', 'contracts', 'Manage contracts'),
('Manage Tenders', 'tenders.manage', 'tenders', 'Manage tenders'),
('View Reports', 'reports.view', 'reports', 'View reports'),
('Manage Settings', 'settings.manage', 'settings', 'Manage system settings')
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO rateb_roles (company_id, name, slug, description, is_system)
SELECT NULL, 'Super Admin', 'super-admin', 'Platform super administrator', 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM rateb_roles WHERE slug = 'super-admin');

INSERT INTO rateb_role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM rateb_roles r CROSS JOIN rateb_permissions p WHERE r.slug = 'super-admin'
ON DUPLICATE KEY UPDATE role_id = role_id;

-- Default super admin: admin@rateb.sa / Rateb@2024 (change after first login)
INSERT INTO rateb_users (company_id, name, email, password, is_super_admin, status, locale)
SELECT NULL, 'Super Admin', 'admin@rateb.sa', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 'active', 'ar'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM rateb_users WHERE email = 'admin@rateb.sa');

INSERT INTO rateb_user_roles (user_id, role_id)
SELECT u.id, r.id FROM rateb_users u JOIN rateb_roles r ON r.slug = 'super-admin' WHERE u.email = 'admin@rateb.sa'
ON DUPLICATE KEY UPDATE user_id = user_id;

INSERT INTO rateb_system_settings (setting_key, setting_value, setting_group) VALUES
('app_name', 'RTAB ERP', 'general'),
('default_locale', 'ar', 'general'),
('default_currency', 'SAR', 'billing'),
('support_email', 'support@rateb.sa', 'general')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

INSERT INTO rateb_email_templates (slug, subject, body_html, body_text, is_active) VALUES
('welcome', 'Welcome to RTAB ERP', '<p>Welcome to RTAB ERP platform.</p>', 'Welcome to RTAB ERP platform.', 1),
('password_reset', 'Password Reset', '<p>Your password reset link.</p>', 'Your password reset link.', 1)
ON DUPLICATE KEY UPDATE subject = VALUES(subject);

INSERT INTO rateb_sms_templates (slug, body, is_active) VALUES
('otp', 'Your RTAB verification code is: {code}', 1),
('alert', 'RTAB Alert: {message}', 1)
ON DUPLICATE KEY UPDATE body = VALUES(body);
