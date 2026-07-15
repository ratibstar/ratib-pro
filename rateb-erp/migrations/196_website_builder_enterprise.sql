-- PHASE WEBSITE-04 — Enterprise Website Builder (additive)
SET NAMES utf8mb4;

-- Theme design tokens (colors, typography, radius, shadows, …)
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_cms_theme' AND COLUMN_NAME = 'tokens_json');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_cms_theme ADD COLUMN tokens_json JSON NULL AFTER custom_js', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- Page draft/published versioning
CREATE TABLE IF NOT EXISTS rateb_website_page_versions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL DEFAULT 0,
    page_id INT UNSIGNED NOT NULL,
    version_no INT UNSIGNED NOT NULL DEFAULT 1,
    status ENUM('draft','published','scheduled','archived') NOT NULL DEFAULT 'draft',
    label VARCHAR(120) NULL,
    snapshot_json LONGTEXT NOT NULL,
    seo_json JSON NULL,
    published_at DATETIME NULL,
    scheduled_at DATETIME NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_website_page_ver (company_id, page_id, version_no),
    KEY idx_website_page_ver_status (company_id, page_id, status),
    KEY idx_website_page_ver_sched (company_id, scheduled_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Reusable block library (cross-page)
CREATE TABLE IF NOT EXISTS rateb_website_block_library (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL DEFAULT 0,
    slug VARCHAR(80) NOT NULL,
    block_type VARCHAR(60) NOT NULL,
    name_en VARCHAR(160) NOT NULL DEFAULT '',
    name_ar VARCHAR(160) NOT NULL DEFAULT '',
    payload_json JSON NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_website_lib_slug (company_id, slug),
    KEY idx_website_lib_type (company_id, block_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Media folders
CREATE TABLE IF NOT EXISTS rateb_website_media_folders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL DEFAULT 0,
    parent_id INT UNSIGNED NULL,
    name VARCHAR(160) NOT NULL,
    slug VARCHAR(120) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_website_folder (company_id, parent_id, slug),
    KEY idx_website_folder_parent (company_id, parent_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_cms_media' AND COLUMN_NAME = 'folder_id');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_cms_media ADD COLUMN folder_id INT UNSIGNED NULL AFTER company_id, ADD INDEX idx_cms_media_folder (company_id, folder_id)', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- Visual forms
CREATE TABLE IF NOT EXISTS rateb_website_forms (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL DEFAULT 0,
    slug VARCHAR(80) NOT NULL,
    name_en VARCHAR(160) NOT NULL DEFAULT '',
    name_ar VARCHAR(160) NOT NULL DEFAULT '',
    success_message_en VARCHAR(500) NULL,
    success_message_ar VARCHAR(500) NULL,
    crm_enabled TINYINT(1) NOT NULL DEFAULT 1,
    crm_source_code VARCHAR(40) NULL DEFAULT 'website_form',
    notify_email VARCHAR(180) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_website_form_slug (company_id, slug),
    KEY idx_website_form_active (company_id, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_website_form_fields (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL DEFAULT 0,
    form_id INT UNSIGNED NOT NULL,
    field_key VARCHAR(80) NOT NULL,
    field_type VARCHAR(40) NOT NULL DEFAULT 'text',
    label_en VARCHAR(160) NOT NULL DEFAULT '',
    label_ar VARCHAR(160) NOT NULL DEFAULT '',
    placeholder_en VARCHAR(160) NULL,
    placeholder_ar VARCHAR(160) NULL,
    options_json JSON NULL,
    is_required TINYINT(1) NOT NULL DEFAULT 0,
    validation_json JSON NULL,
    sort_order INT NOT NULL DEFAULT 0,
    KEY idx_website_form_fields (company_id, form_id, sort_order),
    UNIQUE KEY uq_website_form_field (form_id, field_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_website_form_submissions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL DEFAULT 0,
    form_id INT UNSIGNED NOT NULL,
    payload_json JSON NOT NULL,
    crm_lead_id INT UNSIGNED NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_website_form_sub (company_id, form_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Preview tokens
CREATE TABLE IF NOT EXISTS rateb_website_preview_tokens (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL DEFAULT 0,
    page_id INT UNSIGNED NOT NULL,
    version_id INT UNSIGNED NULL,
    token CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_website_preview_token (token),
    KEY idx_website_preview_exp (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Permissions
INSERT INTO rateb_permissions (name, name_ar, slug, module, description, description_ar) VALUES
('Website View', 'عرض الموقع', 'website.view', 'website', 'View website builder', 'عرض منشئ الموقع'),
('Website Manage', 'إدارة الموقع', 'website.manage', 'website', 'Manage website module', 'إدارة وحدة الموقع'),
('Website Pages', 'صفحات الموقع', 'website.pages.manage', 'website', 'Manage pages', 'إدارة الصفحات'),
('Website Builder', 'منشئ الصفحات', 'website.builder.manage', 'website', 'Use drag-and-drop builder', 'استخدام منشئ السحب والإفلات'),
('Website Media', 'وسائط الموقع', 'website.media.manage', 'website', 'Manage website media', 'إدارة وسائط الموقع'),
('Website Theme', 'سمة الموقع', 'website.theme.manage', 'website', 'Edit theme tokens', 'تعديل رموز السمة'),
('Website Publish', 'نشر الموقع', 'website.publish', 'website', 'Publish and rollback pages', 'نشر الصفحات والتراجع'),
('Website Forms', 'نماذج الموقع', 'website.forms.manage', 'website', 'Manage website forms', 'إدارة نماذج الموقع')
ON DUPLICATE KEY UPDATE module = VALUES(module), name = VALUES(name);

INSERT INTO rateb_role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM rateb_roles r
JOIN rateb_permissions p ON p.slug IN (
    'website.view', 'website.manage', 'website.pages.manage', 'website.builder.manage',
    'website.media.manage', 'website.theme.manage', 'website.publish', 'website.forms.manage'
)
WHERE r.slug IN ('company-full-access', 'super-admin')
ON DUPLICATE KEY UPDATE role_id = role_id;
