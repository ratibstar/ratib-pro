-- Phase WEBSITE-06 — Enterprise Career Portal & ATS Integration
-- Jobs: rateb_cms_careers (tenant-scoped, no duplicate job table)
-- Applications: bridge to rateb_recruitment_candidates via CandidateService

-- Extend CMS careers as ATS job postings (presentation reads this table only)
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_cms_careers' AND COLUMN_NAME = 'category_slug');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_cms_careers
    ADD COLUMN category_slug VARCHAR(80) NULL AFTER department_ar,
    ADD COLUMN employment_type VARCHAR(40) NULL AFTER category_slug,
    ADD COLUMN experience_level VARCHAR(40) NULL AFTER employment_type,
    ADD COLUMN salary_min DECIMAL(12,2) NULL AFTER experience_level,
    ADD COLUMN salary_max DECIMAL(12,2) NULL AFTER salary_min,
    ADD COLUMN salary_currency CHAR(3) NULL DEFAULT ''SAR'' AFTER salary_max,
    ADD COLUMN country_code CHAR(2) NULL AFTER salary_currency,
    ADD COLUMN city_en VARCHAR(80) NULL AFTER country_code,
    ADD COLUMN city_ar VARCHAR(80) NULL AFTER city_en,
    ADD COLUMN skills_json JSON NULL AFTER city_ar,
    ADD COLUMN languages_json JSON NULL AFTER skills_json,
    ADD COLUMN education_level VARCHAR(80) NULL AFTER languages_json,
    ADD COLUMN requirements_en MEDIUMTEXT NULL AFTER education_level,
    ADD COLUMN requirements_ar MEDIUMTEXT NULL AFTER requirements_en,
    ADD COLUMN benefits_en MEDIUMTEXT NULL AFTER requirements_ar,
    ADD COLUMN benefits_ar MEDIUMTEXT NULL AFTER benefits_en,
    ADD COLUMN meta_title_en VARCHAR(255) NULL AFTER benefits_ar,
    ADD COLUMN meta_title_ar VARCHAR(255) NULL AFTER meta_title_en,
    ADD COLUMN meta_description_en VARCHAR(500) NULL AFTER meta_title_ar,
    ADD COLUMN meta_description_ar VARCHAR(500) NULL AFTER meta_description_en,
    ADD COLUMN canonical_url VARCHAR(500) NULL AFTER meta_description_ar,
    ADD COLUMN og_image VARCHAR(500) NULL AFTER canonical_url,
    ADD COLUMN featured TINYINT(1) NOT NULL DEFAULT 0 AFTER og_image,
    ADD COLUMN recruiter_user_id INT UNSIGNED NULL AFTER featured,
    ADD COLUMN published_at DATETIME NULL AFTER recruiter_user_id,
    ADD COLUMN application_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER published_at,
    ADD INDEX idx_cms_careers_category (category_slug),
    ADD INDEX idx_cms_careers_featured (featured),
    ADD INDEX idx_cms_careers_status_company (status, company_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Candidate portal accounts (isolated from ERP users)
CREATE TABLE IF NOT EXISTS rateb_website_career_portal_users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    email VARCHAR(190) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(190) NOT NULL,
    phone VARCHAR(40) NULL,
    nationality CHAR(2) NULL,
    country_code CHAR(2) NULL,
    city VARCHAR(80) NULL,
    linkedin_url VARCHAR(500) NULL,
    portfolio_url VARCHAR(500) NULL,
    resume_media_id INT UNSIGNED NULL,
    resume_path VARCHAR(500) NULL,
    locale VARCHAR(8) NOT NULL DEFAULT 'en',
    status ENUM('active','suspended') NOT NULL DEFAULT 'active',
    email_verified_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_career_portal_email (company_id, email),
    INDEX idx_career_portal_company (company_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Application bridge: website → ATS candidate
CREATE TABLE IF NOT EXISTS rateb_website_career_applications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    career_id INT UNSIGNED NOT NULL,
    portal_user_id INT UNSIGNED NULL,
    recruitment_candidate_id INT UNSIGNED NOT NULL,
    cover_letter MEDIUMTEXT NULL,
    expected_salary DECIMAL(12,2) NULL,
    availability_date DATE NULL,
    status ENUM('submitted','reviewing','withdrawn','rejected','hired') NOT NULL DEFAULT 'submitted',
    meta_json JSON NULL,
    resume_media_id INT UNSIGNED NULL,
    resume_path VARCHAR(500) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_career_app_company (company_id),
    INDEX idx_career_app_career (career_id),
    INDEX idx_career_app_portal (portal_user_id),
    INDEX idx_career_app_candidate (recruitment_candidate_id),
    INDEX idx_career_app_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_website_career_saved_jobs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    portal_user_id INT UNSIGNED NOT NULL,
    career_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_career_saved (portal_user_id, career_id),
    INDEX idx_career_saved_company (company_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO rateb_permissions (name, name_ar, slug, module, description, description_ar) VALUES
('Website Careers View', 'عرض وظائف الموقع', 'website.careers.view', 'website', 'View career portal settings', 'عرض إعدادات بوابة الوظائف'),
('Website Careers Manage', 'إدارة وظائف الموقع', 'website.careers.manage', 'website', 'Manage career portal and job postings', 'إدارة بوابة الوظائف والإعلانات')
ON DUPLICATE KEY UPDATE name = VALUES(name), module = VALUES(module);

INSERT INTO rateb_role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM rateb_roles r
JOIN rateb_permissions p ON p.slug IN ('website.careers.view', 'website.careers.manage')
WHERE r.slug = 'company_admin'
ON DUPLICATE KEY UPDATE role_id = role_id;
