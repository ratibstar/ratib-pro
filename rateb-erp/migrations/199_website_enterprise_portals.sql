-- Phase WEBSITE-07 — Enterprise Customer / Employer / Partner Portal
-- Bridge tables only; CRM / ATS / Finance / Tickets / Documents remain ERP sources of truth.

CREATE TABLE IF NOT EXISTS rateb_website_portal_users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    portal_type ENUM('employer','customer','partner') NOT NULL,
    email VARCHAR(190) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(190) NOT NULL,
    phone VARCHAR(40) NULL,
    organization_name VARCHAR(190) NULL,
    crm_company_id INT UNSIGNED NULL,
    erp_customer_id INT UNSIGNED NULL,
    locale VARCHAR(8) NOT NULL DEFAULT 'en',
    status ENUM('active','pending','suspended') NOT NULL DEFAULT 'active',
    meta_json JSON NULL,
    last_login_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_portal_user_email (company_id, portal_type, email),
    INDEX idx_portal_user_company (company_id),
    INDEX idx_portal_user_type (portal_type),
    INDEX idx_portal_user_crm (crm_company_id),
    INDEX idx_portal_user_customer (erp_customer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_website_portal_requests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    portal_user_id INT UNSIGNED NOT NULL,
    portal_type ENUM('employer','customer','partner') NOT NULL,
    request_type VARCHAR(40) NOT NULL,
    title VARCHAR(255) NOT NULL,
    description MEDIUMTEXT NULL,
    priority ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
    status ENUM('draft','submitted','in_progress','approved','rejected','completed','cancelled') NOT NULL DEFAULT 'submitted',
    crm_lead_id INT UNSIGNED NULL,
    recruitment_candidate_id INT UNSIGNED NULL,
    approval_instance_id INT UNSIGNED NULL,
    meta_json JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_portal_req_company (company_id),
    INDEX idx_portal_req_user (portal_user_id),
    INDEX idx_portal_req_type (request_type),
    INDEX idx_portal_req_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_website_portal_documents (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    portal_user_id INT UNSIGNED NOT NULL,
    doc_category VARCHAR(40) NOT NULL DEFAULT 'attachment',
    title VARCHAR(255) NOT NULL,
    media_id INT UNSIGNED NULL,
    file_path VARCHAR(500) NULL,
    erp_document_id INT UNSIGNED NULL,
    version_no INT UNSIGNED NOT NULL DEFAULT 1,
    mime_type VARCHAR(120) NULL,
    file_size INT UNSIGNED NULL,
    status ENUM('active','archived') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_portal_doc_company (company_id),
    INDEX idx_portal_doc_user (portal_user_id),
    INDEX idx_portal_doc_category (doc_category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_website_portal_shortlists (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    portal_user_id INT UNSIGNED NOT NULL,
    recruitment_candidate_id INT UNSIGNED NOT NULL,
    career_id INT UNSIGNED NULL,
    status ENUM('shortlisted','approved','rejected','replacement_requested') NOT NULL DEFAULT 'shortlisted',
    notes MEDIUMTEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_portal_shortlist (portal_user_id, recruitment_candidate_id),
    INDEX idx_portal_short_company (company_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_website_portal_appointments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    portal_user_id INT UNSIGNED NOT NULL,
    appointment_type ENUM('meeting','interview','other') NOT NULL DEFAULT 'meeting',
    title VARCHAR(255) NOT NULL,
    starts_at DATETIME NOT NULL,
    ends_at DATETIME NULL,
    location VARCHAR(255) NULL,
    recruitment_candidate_id INT UNSIGNED NULL,
    status ENUM('scheduled','confirmed','cancelled','completed') NOT NULL DEFAULT 'scheduled',
    notes MEDIUMTEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_portal_appt_company (company_id),
    INDEX idx_portal_appt_user (portal_user_id),
    INDEX idx_portal_appt_starts (starts_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_website_portal_ticket_links (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    portal_user_id INT UNSIGNED NOT NULL,
    support_ticket_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_portal_ticket (portal_user_id, support_ticket_id),
    INDEX idx_portal_tkt_company (company_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO rateb_permissions (name, name_ar, slug, module, description, description_ar) VALUES
('Website Portal View', 'عرض بوابات الموقع', 'website.portal.view', 'website', 'View website portals', 'عرض بوابات الموقع'),
('Website Portal Manage', 'إدارة بوابات الموقع', 'website.portal.manage', 'website', 'Manage website portals', 'إدارة بوابات الموقع'),
('Website Customer Portal', 'بوابة العملاء', 'website.customer.manage', 'website', 'Manage customer portal', 'إدارة بوابة العملاء'),
('Website Employer Portal', 'بوابة أصحاب العمل', 'website.employer.manage', 'website', 'Manage employer portal', 'إدارة بوابة أصحاب العمل'),
('Website Partner Portal', 'بوابة الشركاء', 'website.partner.manage', 'website', 'Manage partner portal', 'إدارة بوابة الشركاء')
ON DUPLICATE KEY UPDATE name = VALUES(name), module = VALUES(module);

INSERT INTO rateb_role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM rateb_roles r
JOIN rateb_permissions p ON p.slug IN (
    'website.portal.view','website.portal.manage',
    'website.customer.manage','website.employer.manage','website.partner.manage'
)
WHERE r.slug = 'company_admin'
ON DUPLICATE KEY UPDATE role_id = role_id;
