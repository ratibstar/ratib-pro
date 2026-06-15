-- -----------------------------------------------------------------------------
-- Seed data
-- -----------------------------------------------------------------------------

INSERT INTO rateb_plans (name, slug, description, price_monthly, price_yearly, max_users, max_storage_mb, modules, is_active)
VALUES
('Starter', 'starter', 'Essential procurement for small clinics', 299.00, 2990.00, 5, 512, '["procurement","inventory","suppliers"]', 1),
('Professional', 'professional', 'Full procurement and inventory suite', 799.00, 7990.00, 25, 2048, '["procurement","inventory","suppliers","assets","contracts","reports","accounting"]', 1),
('Enterprise', 'enterprise', 'Complete healthcare ERP with all modules', 1999.00, 19990.00, 100, 10240, '["procurement","inventory","suppliers","assets","contracts","tenders","reports","medical_devices","accounting"]', 1);

INSERT INTO rateb_permissions (name, name_ar, slug, module, description, description_ar) VALUES
('View Dashboard', 'عرض لوحة التحكم', 'dashboard.view', 'dashboard', 'Access dashboard', 'الوصول إلى لوحة التحكم'),
('Manage Companies', 'إدارة الشركات', 'companies.manage', 'companies', 'Full company management', 'إدارة كاملة للشركات'),
('View Companies', 'عرض الشركات', 'companies.view', 'companies', 'View companies', 'عرض قائمة الشركات'),
('Manage Subscriptions', 'إدارة الاشتراكات', 'subscriptions.manage', 'subscriptions', 'Manage subscriptions', 'إدارة اشتراكات الشركات'),
('Manage Plans', 'إدارة الباقات', 'plans.manage', 'plans', 'Manage plans', 'إدارة باقات الاشتراك'),
('Manage Users', 'إدارة المستخدمين', 'users.manage', 'users', 'Manage users', 'إدارة مستخدمي المنصة'),
('Manage Roles', 'إدارة الأدوار', 'roles.manage', 'roles', 'Manage roles', 'إدارة أدوار المستخدمين'),
('Manage Permissions', 'إدارة الصلاحيات', 'permissions.manage', 'permissions', 'Manage permissions', 'إدارة صلاحيات النظام'),
('Manage Procurement', 'إدارة المشتريات', 'procurement.manage', 'procurement', 'Manage procurement', 'إدارة عمليات الشراء'),
('Manage Inventory', 'إدارة المخزون', 'inventory.manage', 'inventory', 'Manage inventory', 'إدارة المخزون والمستودعات'),
('Manage Suppliers', 'إدارة الموردين', 'suppliers.manage', 'suppliers', 'Manage suppliers', 'إدارة سجل الموردين'),
('Manage Assets', 'إدارة الأصول', 'assets.manage', 'assets', 'Manage assets', 'إدارة الأصول الثابتة'),
('Manage Contracts', 'إدارة العقود', 'contracts.manage', 'contracts', 'Manage contracts', 'إدارة العقود'),
('Manage Tenders', 'إدارة المناقصات', 'tenders.manage', 'tenders', 'Manage tenders', 'إدارة المناقصات'),
('View Reports', 'عرض التقارير', 'reports.view', 'reports', 'View reports', 'عرض تقارير المنصة'),
('Manage Settings', 'إدارة الإعدادات', 'settings.manage', 'settings', 'Manage system settings', 'إدارة إعدادات النظام'),
('Manage Supplier Evaluations', 'إدارة تقييم الموردين', 'evaluations.manage', 'suppliers', 'Create and manage supplier evaluations', 'إنشاء وإدارة تقييمات الموردين'),
('View Supplier Evaluations', 'عرض تقييم الموردين', 'evaluations.view', 'suppliers', 'View supplier evaluation records', 'عرض سجلات تقييم الموردين'),
('Manage Company Plans', 'إدارة باقات الشركات', 'company_plans.manage', 'companies', 'Edit company plan limits and modules', 'تعديل حدود الباقة والوحدات للشركات'),
('Manage Access Control', 'إدارة التحكم بالوصول', 'access.manage', 'access', 'Full users, roles, permissions control', 'التحكم الكامل بالمستخدمين والأدوار والصلاحيات'),
('View Accounting', 'عرض الحسابات', 'accounting.view', 'accounting', 'View chart of accounts and journals', 'عرض دليل الحسابات والقيود'),
('Manage Accounting', 'إدارة الحسابات', 'accounting.manage', 'accounting', 'Manage chart of accounts and journal entries', 'إدارة دليل الحسابات والقيود اليومية'),
('Post Journal Entries', 'ترحيل القيود', 'accounting.post', 'accounting', 'Post and void journal entries', 'ترحيل وإلغاء القيود المحاسبية');

INSERT INTO rateb_roles (company_id, name, slug, description, is_system)
SELECT NULL, 'Super Admin', 'super-admin', 'Platform super administrator', 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM rateb_roles WHERE slug = 'super-admin');

INSERT INTO rateb_roles (company_id, name, slug, description, is_system)
SELECT NULL, 'Accountant', 'accountant', 'Accounting and reports access', 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM rateb_roles WHERE slug = 'accountant');

INSERT INTO rateb_roles (company_id, name, slug, description, is_system)
SELECT NULL, 'Access Manager', 'access-manager', 'Users and roles management', 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM rateb_roles WHERE slug = 'access-manager');

INSERT INTO rateb_role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM rateb_roles r CROSS JOIN rateb_permissions p WHERE r.slug = 'super-admin';

INSERT INTO rateb_role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM rateb_roles r
JOIN rateb_permissions p ON p.slug IN ('accounting.view', 'accounting.manage', 'accounting.post', 'reports.view', 'dashboard.view')
WHERE r.slug = 'accountant';

INSERT INTO rateb_role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM rateb_roles r
JOIN rateb_permissions p ON p.slug IN ('access.manage', 'users.manage', 'roles.manage', 'permissions.manage', 'dashboard.view')
WHERE r.slug = 'access-manager';

INSERT INTO rateb_users (company_id, name, email, password, is_super_admin, status, locale)
VALUES (NULL, 'Super Admin', 'admin@rateb.sa', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 'active', 'ar');

INSERT INTO rateb_user_roles (user_id, role_id)
SELECT u.id, r.id FROM rateb_users u JOIN rateb_roles r ON r.slug = 'super-admin' WHERE u.email = 'admin@rateb.sa';

INSERT INTO rateb_system_settings (setting_key, setting_value, setting_group) VALUES
('app_name', 'RATEB ERP', 'general'),
('default_locale', 'ar', 'general'),
('default_currency', 'SAR', 'billing'),
('support_email', 'support@rateb.sa', 'general');

INSERT INTO rateb_email_templates (slug, subject, body_html, body_text, is_active) VALUES
('welcome', 'Welcome to RATEB ERP', '<p>Welcome to RATEB ERP platform.</p>', 'Welcome to RATEB ERP platform.', 1),
('password_reset', 'Password Reset', '<p>Your password reset link.</p>', 'Your password reset link.', 1),
('invoice_sent', 'Invoice {invoice_no} — {company}', '<p>Hello {company},</p><p>Invoice <strong>{invoice_no}</strong> has been issued for <strong>{total} {currency}</strong>.</p><p>Due date: {due_date}</p><p><a href="{preview_url}">View invoice</a></p>', 'Invoice {invoice_no} — {total} {currency} — due {due_date}', 1),
('invoice_due_reminder', 'Reminder: invoice {invoice_no} due soon', '<p>Reminder for invoice <strong>{invoice_no}</strong> — <strong>{total} {currency}</strong>.</p><p>Due date: {due_date}</p>', 'Invoice reminder {invoice_no} — {due_date}', 1),
('invoice_overdue_notice', 'Overdue invoice: {invoice_no}', '<p>Invoice <strong>{invoice_no}</strong> is overdue (due {due_date}).</p><p>Amount due: <strong>{total} {currency}</strong></p>', 'Overdue invoice {invoice_no}', 1);

INSERT INTO rateb_sms_templates (slug, body, is_active) VALUES
('otp', 'Your RATEB verification code is: {code}', 1),
('alert', 'RATEB Alert: {message}', 1);

SET FOREIGN_KEY_CHECKS = 1;
