-- PHASE WEBSITE-05 — Enterprise Theme Marketplace (additive)
SET NAMES utf8mb4;

-- Global marketplace catalog (system packages; not tenant data)
CREATE TABLE IF NOT EXISTS rateb_website_theme_packages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(80) NOT NULL,
    name_en VARCHAR(160) NOT NULL DEFAULT '',
    name_ar VARCHAR(160) NOT NULL DEFAULT '',
    version VARCHAR(32) NOT NULL DEFAULT '1.0.0',
    engine_min VARCHAR(32) NOT NULL DEFAULT '1.0',
    package_path VARCHAR(255) NOT NULL DEFAULT '',
    manifest_json LONGTEXT NULL,
    preview_image VARCHAR(500) NULL,
    is_system TINYINT(1) NOT NULL DEFAULT 1,
    is_published TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_website_theme_pkg_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Per-tenant installed themes (never share rows across companies)
CREATE TABLE IF NOT EXISTS rateb_website_theme_installed (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    package_id INT UNSIGNED NULL,
    package_slug VARCHAR(80) NOT NULL,
    install_key VARCHAR(80) NOT NULL,
    name_en VARCHAR(160) NOT NULL DEFAULT '',
    name_ar VARCHAR(160) NOT NULL DEFAULT '',
    source ENUM('marketplace','duplicate','import') NOT NULL DEFAULT 'marketplace',
    status ENUM('installed','active','preview') NOT NULL DEFAULT 'installed',
    package_version VARCHAR(32) NOT NULL DEFAULT '1.0.0',
    parent_installed_id INT UNSIGNED NULL,
    installed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    activated_at DATETIME NULL,
    UNIQUE KEY uq_website_theme_install (company_id, install_key),
    KEY idx_website_theme_install_status (company_id, status),
    KEY idx_website_theme_install_pkg (company_id, package_slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Version / backup snapshots of installed themes (override + pointer state)
CREATE TABLE IF NOT EXISTS rateb_website_theme_versions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    installed_id INT UNSIGNED NOT NULL,
    version_no INT UNSIGNED NOT NULL DEFAULT 1,
    label VARCHAR(120) NULL,
    kind ENUM('backup','restore_point','export') NOT NULL DEFAULT 'backup',
    snapshot_json LONGTEXT NOT NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_website_theme_ver (company_id, installed_id, version_no),
    KEY idx_website_theme_ver_inst (company_id, installed_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tenant-copied theme assets (isolated under company storage)
CREATE TABLE IF NOT EXISTS rateb_website_theme_assets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    installed_id INT UNSIGNED NOT NULL,
    asset_key VARCHAR(120) NOT NULL,
    asset_type VARCHAR(40) NOT NULL DEFAULT 'image',
    file_path VARCHAR(500) NOT NULL,
    checksum CHAR(64) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_website_theme_asset (company_id, installed_id, asset_key),
    KEY idx_website_theme_asset_inst (company_id, installed_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Agency overrides (never mutate package source)
CREATE TABLE IF NOT EXISTS rateb_website_theme_overrides (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    installed_id INT UNSIGNED NOT NULL,
    override_json LONGTEXT NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_website_theme_override (company_id, installed_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pointers on existing theme row
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_cms_theme' AND COLUMN_NAME = 'active_installed_id');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_cms_theme ADD COLUMN active_installed_id INT UNSIGNED NULL AFTER tokens_json, ADD COLUMN preview_installed_id INT UNSIGNED NULL AFTER active_installed_id', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

INSERT INTO rateb_permissions (name, name_ar, slug, module, description, description_ar) VALUES
('Website Theme Marketplace', 'سوق سمات الموقع', 'website.theme.marketplace', 'website', 'Install and manage marketplace themes', 'تثبيت وإدارة سمات السوق'),
('Website Theme Import', 'استيراد سمة', 'website.theme.import', 'website', 'Import and export themes', 'استيراد وتصدير السمات')
ON DUPLICATE KEY UPDATE module = VALUES(module), name = VALUES(name);

INSERT INTO rateb_role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM rateb_roles r
JOIN rateb_permissions p ON p.slug IN ('website.theme.marketplace', 'website.theme.import', 'website.theme.manage')
WHERE r.slug IN ('company-full-access', 'super-admin')
ON DUPLICATE KEY UPDATE role_id = role_id;
