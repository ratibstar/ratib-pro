-- Phase WEBSITE-08 — Customer Self-Service & Client Workspace
-- Bridge tables only; ERP Contracts / ATS / Finance / Workflow / Support remain sources of truth.

CREATE TABLE IF NOT EXISTS rateb_website_portal_ticket_replies (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    portal_user_id INT UNSIGNED NOT NULL,
    support_ticket_id INT UNSIGNED NOT NULL,
    body MEDIUMTEXT NOT NULL,
    attachment_media_id INT UNSIGNED NULL,
    attachment_path VARCHAR(500) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_portal_reply_company (company_id),
    INDEX idx_portal_reply_ticket (support_ticket_id),
    INDEX idx_portal_reply_user (portal_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_website_portal_contacts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    portal_user_id INT UNSIGNED NOT NULL,
    contact_name VARCHAR(190) NOT NULL,
    email VARCHAR(190) NULL,
    phone VARCHAR(40) NULL,
    role_title VARCHAR(120) NULL,
    is_primary TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_portal_contact_company (company_id),
    INDEX idx_portal_contact_user (portal_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO rateb_permissions (name, name_ar, slug, module, description, description_ar) VALUES
('Website Customer Workspace', 'مساحة عمل العميل', 'website.customer.workspace', 'website', 'Access customer self-service workspace', 'الوصول لمساحة عمل العميل الذاتية')
ON DUPLICATE KEY UPDATE name = VALUES(name), module = VALUES(module);

INSERT INTO rateb_role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM rateb_roles r
JOIN rateb_permissions p ON p.slug IN ('website.customer.workspace')
WHERE r.slug = 'company_admin'
ON DUPLICATE KEY UPDATE role_id = role_id;
