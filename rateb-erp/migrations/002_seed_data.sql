-- RATEB ERP - Seed Data
SET NAMES utf8mb4;

INSERT INTO rateb_plans (name, slug, description, price_monthly, price_yearly, max_users, max_storage_mb, modules, is_active)
VALUES
('انطلاق', 'launch', 'ابدأ بلوحة التحكم والإشعارات والتقارير الأساسية.', 39.00, 351.00, 3, 256, '["dashboard","notifications","profile","reports"]', 1),
('أساسي', 'starter', 'تشغيل المشتريات والمخزون والموردين للمنشآت الصغيرة.', 69.00, 621.00, 8, 512, '["dashboard","notifications","profile","reports","procurement","inventory","suppliers"]', 1),
('تجاري', 'commerce', 'البيع والتوزيع عبر نقطة البيع واللوجستيات وسوق الخدمات.', 99.00, 891.00, 20, 2048, '["dashboard","notifications","profile","reports","procurement","inventory","suppliers","pos","logistics","marketplace","branches"]', 1),
('احترافي', 'professional', 'نمو إداري مع الموارد البشرية وCRM والمشاريع والحسابات.', 129.00, 1161.00, 50, 5120, '["dashboard","notifications","profile","reports","procurement","inventory","suppliers","pos","logistics","marketplace","branches","hr","recruitment","crm","projects","approval","accounting","assets","contracts","documents","workflows"]', 1),
('مؤسسات', 'enterprise', 'عمق مؤسسي: التصنيع والرواتب والجودة وذكاء الأعمال.', 179.00, 1611.00, 150, 15360, '["dashboard","notifications","profile","reports","procurement","inventory","suppliers","pos","logistics","marketplace","branches","hr","recruitment","crm","projects","approval","accounting","assets","contracts","documents","workflows","manufacturing","payroll","quality","bi","website","tenders","medical_devices"]', 1),
('متكامل', 'ultimate', 'منصة رتب ERP كاملة مع الحوكمة والتحكم بالوصول.', 249.00, 2241.00, 500, 51200, '["dashboard","procurement","inventory","suppliers","assets","contracts","tenders","reports","medical_devices","accounting","documents","workflows","notifications","access_control","profile","hr","branches","pos","recruitment","crm","projects","approval","manufacturing","payroll","quality","bi","website","logistics","marketplace"]', 1)
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

-- Default super admin: admin@rateb.sa / 123456 (change after first login)
INSERT INTO rateb_users (company_id, name, email, password, is_super_admin, status, locale)
SELECT NULL, 'Super Admin', 'admin@rateb.sa', '$2y$10$7qR7yib4llgToR8eILDO5e3ovQA8lsjA3k8sJfJ2LZ0tak3QrczJW', 1, 'active', 'ar'
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
