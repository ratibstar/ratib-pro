-- Phase WEBSITE-09 — Enterprise Online Services & Booking Platform
-- Bridge tables only; ERP CRM / ATS / Finance / Workflow remain sources of truth.

CREATE TABLE IF NOT EXISTS rateb_website_service_requests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    portal_user_id INT UNSIGNED NULL,
    service_type ENUM('recruitment','domestic_worker','workforce','package','other') NOT NULL DEFAULT 'recruitment',
    package_code VARCHAR(80) NULL,
    title VARCHAR(255) NOT NULL,
    description MEDIUMTEXT NULL,
    priority ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
    status ENUM('draft','submitted','booked','paid','in_progress','completed','cancelled') NOT NULL DEFAULT 'submitted',
    agreement_accepted TINYINT(1) NOT NULL DEFAULT 0,
    agreement_accepted_at DATETIME NULL,
    amount DECIMAL(12,2) NULL,
    currency CHAR(3) NOT NULL DEFAULT 'SAR',
    payment_status ENUM('unpaid','pending','paid','failed','refunded') NOT NULL DEFAULT 'unpaid',
    payment_ref VARCHAR(120) NULL,
    crm_lead_id INT UNSIGNED NULL,
    portal_request_id INT UNSIGNED NULL,
    meta_json JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_svc_req_company (company_id),
    INDEX idx_svc_req_user (portal_user_id),
    INDEX idx_svc_req_status (status),
    INDEX idx_svc_req_payment (payment_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_website_service_appointments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    portal_user_id INT UNSIGNED NULL,
    service_request_id INT UNSIGNED NULL,
    portal_appointment_id INT UNSIGNED NULL,
    title VARCHAR(255) NOT NULL,
    starts_at DATETIME NOT NULL,
    ends_at DATETIME NULL,
    location VARCHAR(255) NULL,
    status ENUM('scheduled','confirmed','cancelled','completed') NOT NULL DEFAULT 'scheduled',
    notes MEDIUMTEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_svc_appt_company (company_id),
    INDEX idx_svc_appt_user (portal_user_id),
    INDEX idx_svc_appt_request (service_request_id),
    INDEX idx_svc_appt_starts (starts_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_website_service_timeline (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    service_request_id INT UNSIGNED NOT NULL,
    portal_user_id INT UNSIGNED NULL,
    event_code VARCHAR(80) NOT NULL,
    title VARCHAR(255) NOT NULL,
    body MEDIUMTEXT NULL,
    actor VARCHAR(40) NOT NULL DEFAULT 'system',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_svc_tl_company (company_id),
    INDEX idx_svc_tl_request (service_request_id),
    INDEX idx_svc_tl_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO rateb_permissions (name, name_ar, slug, module, description, description_ar) VALUES
('Website Online Services', 'الخدمات الإلكترونية', 'website.services.manage', 'website', 'Manage website online services', 'إدارة الخدمات الإلكترونية للموقع')
ON DUPLICATE KEY UPDATE name = VALUES(name), module = VALUES(module);

INSERT INTO rateb_role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM rateb_roles r
JOIN rateb_permissions p ON p.slug = 'website.services.manage'
WHERE r.slug = 'company_admin'
ON DUPLICATE KEY UPDATE role_id = role_id;
