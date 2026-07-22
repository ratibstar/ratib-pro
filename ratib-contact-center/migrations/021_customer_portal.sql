-- RATEB Contact Center — 021 customer portal & white label & reseller (Phase 11)

CREATE TABLE IF NOT EXISTS rcc_portal_users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    contact_id INT UNSIGNED NOT NULL,
    email VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    locale VARCHAR(8) NOT NULL DEFAULT 'ar',
    status ENUM('active','disabled','locked') NOT NULL DEFAULT 'active',
    last_login_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_rcc_portal_users_email (tenant_id, email),
    KEY idx_rcc_portal_users_contact (tenant_id, contact_id),
    CONSTRAINT fk_rcc_portal_users_tenant FOREIGN KEY (tenant_id) REFERENCES rcc_tenants (id) ON DELETE CASCADE,
    CONSTRAINT fk_rcc_portal_users_contact FOREIGN KEY (contact_id) REFERENCES rcc_contacts (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rcc_portal_sessions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    portal_user_id INT UNSIGNED NOT NULL,
    session_token CHAR(64) NOT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(512) NULL,
    expires_at TIMESTAMP NOT NULL,
    revoked_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_rcc_portal_sessions_token (session_token),
    KEY idx_rcc_portal_sessions_user (tenant_id, portal_user_id),
    CONSTRAINT fk_rcc_portal_sessions_tenant FOREIGN KEY (tenant_id) REFERENCES rcc_tenants (id) ON DELETE CASCADE,
    CONSTRAINT fk_rcc_portal_sessions_user FOREIGN KEY (portal_user_id) REFERENCES rcc_portal_users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rcc_whitelabel_domains (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    domain VARCHAR(255) NOT NULL,
    is_primary TINYINT(1) NOT NULL DEFAULT 0,
    ssl_status ENUM('pending','active','failed') NOT NULL DEFAULT 'pending',
    verification_token VARCHAR(64) NULL,
    verified_at TIMESTAMP NULL,
    status ENUM('pending','active','disabled') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_rcc_whitelabel_domain (domain),
    KEY idx_rcc_whitelabel_domains_tenant (tenant_id),
    CONSTRAINT fk_rcc_whitelabel_domains_tenant FOREIGN KEY (tenant_id) REFERENCES rcc_tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rcc_whitelabel_branding (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    logo_url VARCHAR(512) NULL,
    logo_dark_url VARCHAR(512) NULL,
    favicon_url VARCHAR(512) NULL,
    company_name VARCHAR(255) NULL,
    company_name_ar VARCHAR(255) NULL,
    support_email VARCHAR(255) NULL,
    support_phone VARCHAR(40) NULL,
    primary_color VARCHAR(16) NULL,
    accent_color VARCHAR(16) NULL,
    custom_css TEXT NULL,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_rcc_whitelabel_branding_tenant (tenant_id),
    CONSTRAINT fk_rcc_whitelabel_branding_tenant FOREIGN KEY (tenant_id) REFERENCES rcc_tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rcc_whitelabel_themes (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    theme_key VARCHAR(64) NOT NULL DEFAULT 'default',
    mode ENUM('light','dark','auto') NOT NULL DEFAULT 'auto',
    tokens_json JSON NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_rcc_whitelabel_theme (tenant_id, theme_key),
    CONSTRAINT fk_rcc_whitelabel_themes_tenant FOREIGN KEY (tenant_id) REFERENCES rcc_tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rcc_email_templates (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    template_key VARCHAR(64) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    subject_ar VARCHAR(255) NULL,
    body_html MEDIUMTEXT NOT NULL,
    body_html_ar MEDIUMTEXT NULL,
    variables_json JSON NULL,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_rcc_email_templates (tenant_id, template_key),
    CONSTRAINT fk_rcc_email_templates_tenant FOREIGN KEY (tenant_id) REFERENCES rcc_tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rcc_resellers (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    code VARCHAR(64) NOT NULL,
    name VARCHAR(255) NOT NULL,
    name_ar VARCHAR(255) NULL,
    contact_email VARCHAR(255) NOT NULL,
    commission_rate DECIMAL(5,2) NOT NULL DEFAULT 15.00,
    revenue_share_pct DECIMAL(5,2) NOT NULL DEFAULT 20.00,
    status ENUM('active','suspended','archived') NOT NULL DEFAULT 'active',
    metadata_json JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_rcc_resellers_code (code),
    UNIQUE KEY uq_rcc_resellers_tenant (tenant_id),
    CONSTRAINT fk_rcc_resellers_tenant FOREIGN KEY (tenant_id) REFERENCES rcc_tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rcc_reseller_commissions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    reseller_id INT UNSIGNED NOT NULL,
    sub_tenant_id INT UNSIGNED NOT NULL,
    invoice_id INT UNSIGNED NULL,
    payment_id INT UNSIGNED NULL,
    commission_amount DECIMAL(12,2) NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'SAR',
    status ENUM('pending','approved','paid','void') NOT NULL DEFAULT 'pending',
    period_month CHAR(7) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    paid_at TIMESTAMP NULL,
    PRIMARY KEY (id),
    KEY idx_rcc_reseller_comm_reseller (reseller_id, period_month),
    KEY idx_rcc_reseller_comm_sub (sub_tenant_id),
    CONSTRAINT fk_rcc_reseller_comm_reseller FOREIGN KEY (reseller_id) REFERENCES rcc_resellers (id) ON DELETE CASCADE,
    CONSTRAINT fk_rcc_reseller_comm_sub FOREIGN KEY (sub_tenant_id) REFERENCES rcc_tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO rcc_email_templates (tenant_id, template_key, subject, subject_ar, body_html, body_html_ar) VALUES
(1, 'portal_welcome', 'Welcome to your customer portal', 'مرحباً بك في بوابة العملاء',
 '<p>Hello {{name}}, your portal account is ready.</p>', '<p>مرحباً {{name}}، حسابك في البوابة جاهز.</p>'),
(1, 'invoice_issued', 'New invoice {{invoice_no}}', 'فاتورة جديدة {{invoice_no}}',
 '<p>Invoice {{invoice_no}} for {{amount}} {{currency}} is due {{due_date}}.</p>',
 '<p>فاتورة {{invoice_no}} بمبلغ {{amount}} {{currency}} مستحقة {{due_date}}.</p>');

INSERT IGNORE INTO rcc_permissions (slug, name, module) VALUES
('rcc.portal.admin', 'Administer Customer Portal', 'portal'),
('rcc.whitelabel.manage', 'Manage White Label', 'saas'),
('rcc.reseller.view', 'View Reseller Program', 'saas'),
('rcc.reseller.manage', 'Manage Resellers', 'saas'),
('rcc.provisioning.manage', 'Tenant Provisioning', 'saas');

INSERT IGNORE INTO rcc_role_permissions (role_id, permission_id)
SELECT 1, id FROM rcc_permissions WHERE slug IN (
    'rcc.portal.admin', 'rcc.whitelabel.manage', 'rcc.reseller.view', 'rcc.reseller.manage', 'rcc.provisioning.manage'
);

INSERT IGNORE INTO rcc_role_permissions (role_id, permission_id)
SELECT 2, id FROM rcc_permissions WHERE slug IN ('rcc.portal.admin', 'rcc.whitelabel.manage', 'rcc.reseller.view');
