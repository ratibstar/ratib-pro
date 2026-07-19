-- RATIB Mobile Apps Management — per-company (tenant) white-label config.
-- Logical name: mobile_app_configs → physical: rateb_mobile_app_configs

CREATE TABLE IF NOT EXISTS rateb_mobile_app_configs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    app_name VARCHAR(150) NOT NULL DEFAULT '',
    logo_path VARCHAR(500) NULL,
    icon_path VARCHAR(500) NULL,
    splash_path VARCHAR(500) NULL,
    theme_color VARCHAR(32) NOT NULL DEFAULT '#0D6EFD',
    status VARCHAR(20) NOT NULL DEFAULT 'inactive',
    enabled_features JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_mobile_app_config_company (company_id),
    KEY idx_mobile_app_config_status (status),
    CONSTRAINT fk_mobile_app_config_company
        FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO rateb_permissions (name, name_ar, slug, module, description, description_ar) VALUES
(
    'View Mobile Apps',
    'عرض تطبيقات الجوال',
    'mobile_apps.view',
    'mobile_apps',
    'View mobile app management and tenant branding',
    'عرض إدارة تطبيقات الجوال والعلامة التجارية للمستأجر'
),
(
    'Manage Mobile Apps',
    'إدارة تطبيقات الجوال',
    'mobile_apps.manage',
    'mobile_apps',
    'Enable/disable and configure mobile apps per company',
    'تفعيل وتعطيل وضبط تطبيقات الجوال لكل شركة'
)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    name_ar = VALUES(name_ar),
    module = VALUES(module),
    description = VALUES(description),
    description_ar = VALUES(description_ar);

INSERT IGNORE INTO rateb_role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM rateb_roles r
JOIN rateb_permissions p ON p.slug IN ('mobile_apps.view', 'mobile_apps.manage')
WHERE r.slug IN ('super-admin');
