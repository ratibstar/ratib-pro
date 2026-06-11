-- RATEB ERP - Arabic permissions, supplier evaluations, extra permissions
SET NAMES utf8mb4;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_permissions' AND COLUMN_NAME = 'name_ar');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE rateb_permissions ADD COLUMN name_ar VARCHAR(120) NULL AFTER name, ADD COLUMN description_ar VARCHAR(255) NULL AFTER description',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE rateb_permissions SET
    name_ar = 'عرض لوحة التحكم', description_ar = 'الوصول إلى لوحة التحكم'
WHERE slug = 'dashboard.view';

UPDATE rateb_permissions SET
    name_ar = 'إدارة الشركات', description_ar = 'إدارة كاملة للشركات'
WHERE slug = 'companies.manage';

UPDATE rateb_permissions SET
    name_ar = 'عرض الشركات', description_ar = 'عرض قائمة الشركات'
WHERE slug = 'companies.view';

UPDATE rateb_permissions SET
    name_ar = 'إدارة الاشتراكات', description_ar = 'إدارة اشتراكات الشركات'
WHERE slug = 'subscriptions.manage';

UPDATE rateb_permissions SET
    name_ar = 'إدارة الباقات', description_ar = 'إدارة باقات الاشتراك'
WHERE slug = 'plans.manage';

UPDATE rateb_permissions SET
    name_ar = 'إدارة المستخدمين', description_ar = 'إدارة مستخدمي المنصة'
WHERE slug = 'users.manage';

UPDATE rateb_permissions SET
    name_ar = 'إدارة الأدوار', description_ar = 'إدارة أدوار المستخدمين'
WHERE slug = 'roles.manage';

UPDATE rateb_permissions SET
    name_ar = 'إدارة الصلاحيات', description_ar = 'إدارة صلاحيات النظام'
WHERE slug = 'permissions.manage';

UPDATE rateb_permissions SET
    name_ar = 'إدارة المشتريات', description_ar = 'إدارة عمليات الشراء'
WHERE slug = 'procurement.manage';

UPDATE rateb_permissions SET
    name_ar = 'إدارة المخزون', description_ar = 'إدارة المخزون والمستودعات'
WHERE slug = 'inventory.manage';

UPDATE rateb_permissions SET
    name_ar = 'إدارة الموردين', description_ar = 'إدارة سجل الموردين'
WHERE slug = 'suppliers.manage';

UPDATE rateb_permissions SET
    name_ar = 'إدارة الأصول', description_ar = 'إدارة الأصول الثابتة'
WHERE slug = 'assets.manage';

UPDATE rateb_permissions SET
    name_ar = 'إدارة العقود', description_ar = 'إدارة العقود'
WHERE slug = 'contracts.manage';

UPDATE rateb_permissions SET
    name_ar = 'إدارة المناقصات', description_ar = 'إدارة المناقصات'
WHERE slug = 'tenders.manage';

UPDATE rateb_permissions SET
    name_ar = 'عرض التقارير', description_ar = 'عرض تقارير المنصة'
WHERE slug = 'reports.view';

UPDATE rateb_permissions SET
    name_ar = 'إدارة الإعدادات', description_ar = 'إدارة إعدادات النظام'
WHERE slug = 'settings.manage';

INSERT INTO rateb_permissions (name, name_ar, slug, module, description, description_ar) VALUES
('Manage Supplier Evaluations', 'إدارة تقييم الموردين', 'evaluations.manage', 'suppliers', 'Create and manage supplier evaluations', 'إنشاء وإدارة تقييمات الموردين'),
('View Supplier Evaluations', 'عرض تقييم الموردين', 'evaluations.view', 'suppliers', 'View supplier evaluation records', 'عرض سجلات تقييم الموردين'),
('Manage Company Plans', 'إدارة باقات الشركات', 'company_plans.manage', 'companies', 'Edit company plan limits and modules', 'تعديل حدود الباقة والوحدات للشركات')
ON DUPLICATE KEY UPDATE name_ar = VALUES(name_ar), description_ar = VALUES(description_ar);

INSERT INTO rateb_role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM rateb_roles r
JOIN rateb_permissions p ON p.slug IN ('evaluations.manage', 'evaluations.view', 'company_plans.manage')
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

UPDATE rateb_system_settings SET setting_value = 'ar' WHERE setting_key = 'default_locale';
