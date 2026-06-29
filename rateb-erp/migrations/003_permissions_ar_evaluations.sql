-- RATEB ERP - Arabic permissions, supplier evaluations, extra permissions
-- Note: "empty result set" / "0 rows affected" in phpMyAdmin is NORMAL when columns/data already exist.
SET NAMES utf8mb4;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_permissions' AND COLUMN_NAME = 'name_ar');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE rateb_permissions ADD COLUMN name_ar VARCHAR(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL AFTER name, ADD COLUMN description_ar VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL AFTER description',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Upsert: creates missing permissions AND updates Arabic labels (safe to re-run)
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
('Post Journal Entries', 'ترحيل القيود', 'accounting.post', 'accounting', 'Post and void journal entries', 'ترحيل وإلغاء القيود المحاسبية')
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    name_ar = VALUES(name_ar),
    module = VALUES(module),
    description = VALUES(description),
    description_ar = VALUES(description_ar);

INSERT INTO rateb_role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM rateb_roles r
JOIN rateb_permissions p ON p.slug IN (
    'evaluations.manage', 'evaluations.view', 'company_plans.manage',
    'access.manage', 'accounting.view', 'accounting.manage', 'accounting.post'
)
WHERE r.slug = 'super-admin'
ON DUPLICATE KEY UPDATE role_id = role_id;

CREATE TABLE IF NOT EXISTS rateb_supplier_evaluations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    supplier_id INT UNSIGNED NOT NULL,
    evaluated_by INT UNSIGNED NULL,
    evaluation_date DATE NOT NULL,
    quality_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
    delivery_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
    price_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
    service_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
    overall_score DECIMAL(4,2) NOT NULL DEFAULT 0.00,
    comments TEXT NULL,
    status ENUM('draft','published','archived') NOT NULL DEFAULT 'published',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_eval_company (company_id),
    INDEX idx_eval_supplier (supplier_id),
    CONSTRAINT fk_eval_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_eval_supplier FOREIGN KEY (supplier_id) REFERENCES rateb_suppliers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO rateb_system_settings (setting_key, setting_value, setting_group)
VALUES ('default_locale', 'ar', 'general')
ON DUPLICATE KEY UPDATE setting_value = 'ar';
