-- RATEB Contact Center — 023 marketplace & add-ons (Phase 11)

CREATE TABLE IF NOT EXISTS rcc_marketplace_addons (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(64) NOT NULL,
    name VARCHAR(255) NOT NULL,
    name_ar VARCHAR(255) NULL,
    description TEXT NULL,
    description_ar TEXT NULL,
    category VARCHAR(64) NOT NULL DEFAULT 'integration',
    billing_type ENUM('one_time','monthly','usage') NOT NULL DEFAULT 'monthly',
    price_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    currency CHAR(3) NOT NULL DEFAULT 'SAR',
    icon VARCHAR(128) NULL,
    config_schema_json JSON NULL,
    is_published TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_rcc_marketplace_addons_code (code),
    KEY idx_rcc_marketplace_addons_cat (category, is_published)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rcc_tenant_addons (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    addon_id INT UNSIGNED NOT NULL,
    status ENUM('active','cancelled','suspended') NOT NULL DEFAULT 'active',
    config_json JSON NULL,
    subscribed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    cancelled_at TIMESTAMP NULL,
    external_ref VARCHAR(128) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_rcc_tenant_addon (tenant_id, addon_id),
    KEY idx_rcc_tenant_addons_status (tenant_id, status),
    CONSTRAINT fk_rcc_tenant_addons_tenant FOREIGN KEY (tenant_id) REFERENCES rcc_tenants (id) ON DELETE CASCADE,
    CONSTRAINT fk_rcc_tenant_addons_addon FOREIGN KEY (addon_id) REFERENCES rcc_marketplace_addons (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rcc_reseller_revenue_shares (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    reseller_id INT UNSIGNED NOT NULL,
    sub_tenant_id INT UNSIGNED NOT NULL,
    addon_id INT UNSIGNED NULL,
    invoice_id INT UNSIGNED NULL,
    gross_amount DECIMAL(12,2) NOT NULL,
    share_pct DECIMAL(5,2) NOT NULL,
    share_amount DECIMAL(12,2) NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'SAR',
    period_month CHAR(7) NOT NULL,
    status ENUM('pending','settled','void') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    settled_at TIMESTAMP NULL,
    PRIMARY KEY (id),
    KEY idx_rcc_revenue_share_reseller (reseller_id, period_month),
    CONSTRAINT fk_rcc_revenue_share_reseller FOREIGN KEY (reseller_id) REFERENCES rcc_resellers (id) ON DELETE CASCADE,
    CONSTRAINT fk_rcc_revenue_share_sub FOREIGN KEY (sub_tenant_id) REFERENCES rcc_tenants (id) ON DELETE CASCADE,
    CONSTRAINT fk_rcc_revenue_share_addon FOREIGN KEY (addon_id) REFERENCES rcc_marketplace_addons (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO rcc_marketplace_addons (code, name, name_ar, category, billing_type, price_amount, sort_order) VALUES
('ai-copilot-pro', 'AI Copilot Pro', 'مساعد الذكاء الاصطناعي برو', 'ai', 'monthly', 199.00, 10),
('advanced-analytics', 'Advanced Analytics', 'تحليلات متقدمة', 'analytics', 'monthly', 149.00, 20),
('whatsapp-business', 'WhatsApp Business API', 'واتساب للأعمال', 'channel', 'monthly', 99.00, 30),
('recording-archive', 'Recording Archive 1TB', 'أرشيف التسجيلات 1TB', 'storage', 'monthly', 79.00, 40),
('white-label-pack', 'White Label Pack', 'حزمة العلامة البيضاء', 'branding', 'monthly', 249.00, 50),
('reseller-toolkit', 'Reseller Toolkit', 'أدوات الموزع', 'reseller', 'monthly', 0.00, 60);

INSERT IGNORE INTO rcc_permissions (slug, name, module) VALUES
('rcc.marketplace.view', 'View Marketplace', 'marketplace'),
('rcc.marketplace.manage', 'Manage Add-ons', 'marketplace'),
('rcc.marketplace.subscribe', 'Subscribe to Add-ons', 'marketplace');

INSERT IGNORE INTO rcc_role_permissions (role_id, permission_id)
SELECT 1, id FROM rcc_permissions WHERE slug LIKE 'rcc.marketplace.%';

INSERT IGNORE INTO rcc_role_permissions (role_id, permission_id)
SELECT 2, id FROM rcc_permissions WHERE slug IN ('rcc.marketplace.view', 'rcc.marketplace.subscribe');
