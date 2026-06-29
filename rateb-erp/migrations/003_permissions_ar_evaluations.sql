-- RATEB ERP - Arabic permissions, supplier evaluations, extra permissions
-- Arabic labels use UNHEX so deploy/cPanel upload cannot corrupt UTF-8 literals.
SET NAMES utf8mb4;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_permissions' AND COLUMN_NAME = 'name_ar');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE rateb_permissions ADD COLUMN name_ar VARCHAR(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL AFTER name, ADD COLUMN description_ar VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL AFTER description',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

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
('Manage Settings', 'settings.manage', 'settings', 'Manage system settings'),
('Manage Supplier Evaluations', 'evaluations.manage', 'suppliers', 'Create and manage supplier evaluations'),
('View Supplier Evaluations', 'evaluations.view', 'suppliers', 'View supplier evaluation records'),
('Manage Company Plans', 'company_plans.manage', 'companies', 'Edit company plan limits and modules'),
('Manage Access Control', 'access.manage', 'access', 'Full users, roles, permissions control'),
('View Accounting', 'accounting.view', 'accounting', 'View chart of accounts and journals'),
('Manage Accounting', 'accounting.manage', 'accounting', 'Manage chart of accounts and journal entries'),
('Post Journal Entries', 'accounting.post', 'accounting', 'Post and void journal entries')
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    module = VALUES(module),
    description = VALUES(description);

UPDATE rateb_permissions SET
  name_ar = CONVERT(UNHEX('D8B9D8B1D8B620D984D988D8ADD8A920D8A7D984D8AAD8ADD983D985') USING utf8mb4),
  description_ar = CONVERT(UNHEX('D8A7D984D988D8B5D988D98420D8A5D984D98920D984D988D8ADD8A920D8A7D984D8AAD8ADD983D985') USING utf8mb4)
WHERE slug = 'dashboard.view';

UPDATE rateb_permissions SET
  name_ar = CONVERT(UNHEX('D8A5D8AFD8A7D8B1D8A920D8A7D984D8B4D8B1D983D8A7D8AA') USING utf8mb4),
  description_ar = CONVERT(UNHEX('D8A5D8AFD8A7D8B1D8A920D983D8A7D985D984D8A920D984D984D8B4D8B1D983D8A7D8AA') USING utf8mb4)
WHERE slug = 'companies.manage';

UPDATE rateb_permissions SET
  name_ar = CONVERT(UNHEX('D8B9D8B1D8B620D8A7D984D8B4D8B1D983D8A7D8AA') USING utf8mb4),
  description_ar = CONVERT(UNHEX('D8B9D8B1D8B620D982D8A7D8A6D985D8A920D8A7D984D8B4D8B1D983D8A7D8AA') USING utf8mb4)
WHERE slug = 'companies.view';

UPDATE rateb_permissions SET
  name_ar = CONVERT(UNHEX('D8A5D8AFD8A7D8B1D8A920D8A7D984D8A7D8B4D8AAD8B1D8A7D983D8A7D8AA') USING utf8mb4),
  description_ar = CONVERT(UNHEX('D8A5D8AFD8A7D8B1D8A920D8A7D8B4D8AAD8B1D8A7D983D8A7D8AA20D8A7D984D8B4D8B1D983D8A7D8AA') USING utf8mb4)
WHERE slug = 'subscriptions.manage';

UPDATE rateb_permissions SET
  name_ar = CONVERT(UNHEX('D8A5D8AFD8A7D8B1D8A920D8A7D984D8A8D8A7D982D8A7D8AA') USING utf8mb4),
  description_ar = CONVERT(UNHEX('D8A5D8AFD8A7D8B1D8A920D8A8D8A7D982D8A7D8AA20D8A7D984D8A7D8B4D8AAD8B1D8A7D983') USING utf8mb4)
WHERE slug = 'plans.manage';

UPDATE rateb_permissions SET
  name_ar = CONVERT(UNHEX('D8A5D8AFD8A7D8B1D8A920D8A7D984D985D8B3D8AAD8AED8AFD985D98AD986') USING utf8mb4),
  description_ar = CONVERT(UNHEX('D8A5D8AFD8A7D8B1D8A920D985D8B3D8AAD8AED8AFD985D98A20D8A7D984D985D986D8B5D8A9') USING utf8mb4)
WHERE slug = 'users.manage';

UPDATE rateb_permissions SET
  name_ar = CONVERT(UNHEX('D8A5D8AFD8A7D8B1D8A920D8A7D984D8A3D8AFD988D8A7D8B1') USING utf8mb4),
  description_ar = CONVERT(UNHEX('D8A5D8AFD8A7D8B1D8A920D8A3D8AFD988D8A7D8B120D8A7D984D985D8B3D8AAD8AED8AFD985D98AD986') USING utf8mb4)
WHERE slug = 'roles.manage';

UPDATE rateb_permissions SET
  name_ar = CONVERT(UNHEX('D8A5D8AFD8A7D8B1D8A920D8A7D984D8B5D984D8A7D8ADD98AD8A7D8AA') USING utf8mb4),
  description_ar = CONVERT(UNHEX('D8A5D8AFD8A7D8B1D8A920D8B5D984D8A7D8ADD98AD8A7D8AA20D8A7D984D986D8B8D8A7D985') USING utf8mb4)
WHERE slug = 'permissions.manage';

UPDATE rateb_permissions SET
  name_ar = CONVERT(UNHEX('D8A5D8AFD8A7D8B1D8A920D8A7D984D985D8B4D8AAD8B1D98AD8A7D8AA') USING utf8mb4),
  description_ar = CONVERT(UNHEX('D8A5D8AFD8A7D8B1D8A920D8B9D985D984D98AD8A7D8AA20D8A7D984D8B4D8B1D8A7D8A1') USING utf8mb4)
WHERE slug = 'procurement.manage';

UPDATE rateb_permissions SET
  name_ar = CONVERT(UNHEX('D8A5D8AFD8A7D8B1D8A920D8A7D984D985D8AED8B2D988D986') USING utf8mb4),
  description_ar = CONVERT(UNHEX('D8A5D8AFD8A7D8B1D8A920D8A7D984D985D8AED8B2D988D98620D988D8A7D984D985D8B3D8AAD988D8AFD8B9D8A7D8AA') USING utf8mb4)
WHERE slug = 'inventory.manage';

UPDATE rateb_permissions SET
  name_ar = CONVERT(UNHEX('D8A5D8AFD8A7D8B1D8A920D8A7D984D985D988D8B1D8AFD98AD986') USING utf8mb4),
  description_ar = CONVERT(UNHEX('D8A5D8AFD8A7D8B1D8A920D8B3D8ACD98420D8A7D984D985D988D8B1D8AFD98AD986') USING utf8mb4)
WHERE slug = 'suppliers.manage';

UPDATE rateb_permissions SET
  name_ar = CONVERT(UNHEX('D8A5D8AFD8A7D8B1D8A920D8A7D984D8A3D8B5D988D984') USING utf8mb4),
  description_ar = CONVERT(UNHEX('D8A5D8AFD8A7D8B1D8A920D8A7D984D8A3D8B5D988D98420D8A7D984D8ABD8A7D8A8D8AAD8A9') USING utf8mb4)
WHERE slug = 'assets.manage';

UPDATE rateb_permissions SET
  name_ar = CONVERT(UNHEX('D8A5D8AFD8A7D8B1D8A920D8A7D984D8B9D982D988D8AF') USING utf8mb4),
  description_ar = CONVERT(UNHEX('D8A5D8AFD8A7D8B1D8A920D8A7D984D8B9D982D988D8AF') USING utf8mb4)
WHERE slug = 'contracts.manage';

UPDATE rateb_permissions SET
  name_ar = CONVERT(UNHEX('D8A5D8AFD8A7D8B1D8A920D8A7D984D985D986D8A7D982D8B5D8A7D8AA') USING utf8mb4),
  description_ar = CONVERT(UNHEX('D8A5D8AFD8A7D8B1D8A920D8A7D984D985D986D8A7D982D8B5D8A7D8AA') USING utf8mb4)
WHERE slug = 'tenders.manage';

UPDATE rateb_permissions SET
  name_ar = CONVERT(UNHEX('D8B9D8B1D8B620D8A7D984D8AAD982D8A7D8B1D98AD8B1') USING utf8mb4),
  description_ar = CONVERT(UNHEX('D8B9D8B1D8B620D8AAD982D8A7D8B1D98AD8B120D8A7D984D985D986D8B5D8A9') USING utf8mb4)
WHERE slug = 'reports.view';

UPDATE rateb_permissions SET
  name_ar = CONVERT(UNHEX('D8A5D8AFD8A7D8B1D8A920D8A7D984D8A5D8B9D8AFD8A7D8AFD8A7D8AA') USING utf8mb4),
  description_ar = CONVERT(UNHEX('D8A5D8AFD8A7D8B1D8A920D8A5D8B9D8AFD8A7D8AFD8A7D8AA20D8A7D984D986D8B8D8A7D985') USING utf8mb4)
WHERE slug = 'settings.manage';

UPDATE rateb_permissions SET
  name_ar = CONVERT(UNHEX('D8A5D8AFD8A7D8B1D8A920D8AAD982D98AD98AD98520D8A7D984D985D988D8B1D8AFD98AD986') USING utf8mb4),
  description_ar = CONVERT(UNHEX('D8A5D986D8B4D8A7D8A120D988D8A5D8AFD8A7D8B1D8A920D8AAD982D98AD98AD985D8A7D8AA20D8A7D984D985D988D8B1D8AFD98AD986') USING utf8mb4)
WHERE slug = 'evaluations.manage';

UPDATE rateb_permissions SET
  name_ar = CONVERT(UNHEX('D8B9D8B1D8B620D8AAD982D98AD98AD98520D8A7D984D985D988D8B1D8AFD98AD986') USING utf8mb4),
  description_ar = CONVERT(UNHEX('D8B9D8B1D8B620D8B3D8ACD984D8A7D8AA20D8AAD982D98AD98AD98520D8A7D984D985D988D8B1D8AFD98AD986') USING utf8mb4)
WHERE slug = 'evaluations.view';

UPDATE rateb_permissions SET
  name_ar = CONVERT(UNHEX('D8A5D8AFD8A7D8B1D8A920D8A8D8A7D982D8A7D8AA20D8A7D984D8B4D8B1D983D8A7D8AA') USING utf8mb4),
  description_ar = CONVERT(UNHEX('D8AAD8B9D8AFD98AD98420D8ADD8AFD988D8AF20D8A7D984D8A8D8A7D982D8A920D988D8A7D984D988D8ADD8AFD8A7D8AA20D984D984D8B4D8B1D983D8A7D8AA') USING utf8mb4)
WHERE slug = 'company_plans.manage';

UPDATE rateb_permissions SET
  name_ar = CONVERT(UNHEX('D8A5D8AFD8A7D8B1D8A920D8A7D984D8AAD8ADD983D98520D8A8D8A7D984D988D8B5D988D984') USING utf8mb4),
  description_ar = CONVERT(UNHEX('D8A7D984D8AAD8ADD983D98520D8A7D984D983D8A7D985D98420D8A8D8A7D984D985D8B3D8AAD8AED8AFD985D98AD98620D988D8A7D984D8A3D8AFD988D8A7D8B120D988D8A7D984D8B5D984D8A7D8ADD98AD8A7D8AA') USING utf8mb4)
WHERE slug = 'access.manage';

UPDATE rateb_permissions SET
  name_ar = CONVERT(UNHEX('D8B9D8B1D8B620D8A7D984D8ADD8B3D8A7D8A8D8A7D8AA') USING utf8mb4),
  description_ar = CONVERT(UNHEX('D8B9D8B1D8B620D8AFD984D98AD98420D8A7D984D8ADD8B3D8A7D8A8D8A7D8AA20D988D8A7D984D982D98AD988D8AF') USING utf8mb4)
WHERE slug = 'accounting.view';

UPDATE rateb_permissions SET
  name_ar = CONVERT(UNHEX('D8A5D8AFD8A7D8B1D8A920D8A7D984D8ADD8B3D8A7D8A8D8A7D8AA') USING utf8mb4),
  description_ar = CONVERT(UNHEX('D8A5D8AFD8A7D8B1D8A920D8AFD984D98AD98420D8A7D984D8ADD8B3D8A7D8A8D8A7D8AA20D988D8A7D984D982D98AD988D8AF20D8A7D984D98AD988D985D98AD8A9') USING utf8mb4)
WHERE slug = 'accounting.manage';

UPDATE rateb_permissions SET
  name_ar = CONVERT(UNHEX('D8AAD8B1D8ADD98AD98420D8A7D984D982D98AD988D8AF') USING utf8mb4),
  description_ar = CONVERT(UNHEX('D8AAD8B1D8ADD98AD98420D988D8A5D984D8BAD8A7D8A120D8A7D984D982D98AD988D8AF20D8A7D984D985D8ADD8A7D8B3D8A8D98AD8A9') USING utf8mb4)
WHERE slug = 'accounting.post';

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
