-- RATIB Contact Center — 020 SaaS billing engine (Phase 11)

ALTER TABLE rcc_tenants
    ADD COLUMN parent_tenant_id INT UNSIGNED NULL,
    ADD COLUMN reseller_id INT UNSIGNED NULL,
    ADD COLUMN plan_id INT UNSIGNED NULL,
    ADD COLUMN billing_email VARCHAR(255) NULL,
    ADD COLUMN trial_ends_at TIMESTAMP NULL;

ALTER TABLE rcc_tenants
    ADD KEY idx_rcc_tenants_parent (parent_tenant_id),
    ADD KEY idx_rcc_tenants_reseller (reseller_id),
    ADD KEY idx_rcc_tenants_plan (plan_id);

CREATE TABLE IF NOT EXISTS rcc_plans (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(64) NOT NULL,
    name VARCHAR(255) NOT NULL,
    name_ar VARCHAR(255) NULL,
    description TEXT NULL,
    description_ar TEXT NULL,
    billing_cycle ENUM('monthly','quarterly','yearly') NOT NULL DEFAULT 'monthly',
    currency CHAR(3) NOT NULL DEFAULT 'SAR',
    price_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    setup_fee DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    max_agents INT UNSIGNED NOT NULL DEFAULT 5,
    max_queues INT UNSIGNED NOT NULL DEFAULT 3,
    max_storage_mb INT UNSIGNED NOT NULL DEFAULT 5120,
    features_json JSON NULL,
    is_public TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    status ENUM('active','archived') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_rcc_plans_code (code),
    KEY idx_rcc_plans_status (status, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rcc_subscriptions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    plan_id INT UNSIGNED NOT NULL,
    status ENUM('trialing','active','past_due','cancelled','suspended') NOT NULL DEFAULT 'trialing',
    current_period_start TIMESTAMP NOT NULL,
    current_period_end TIMESTAMP NOT NULL,
    cancel_at_period_end TINYINT(1) NOT NULL DEFAULT 0,
    cancelled_at TIMESTAMP NULL,
    external_ref VARCHAR(128) NULL,
    metadata_json JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_rcc_subscriptions_tenant (tenant_id, status),
    KEY idx_rcc_subscriptions_period (current_period_end),
    CONSTRAINT fk_rcc_subscriptions_tenant FOREIGN KEY (tenant_id) REFERENCES rcc_tenants (id) ON DELETE CASCADE,
    CONSTRAINT fk_rcc_subscriptions_plan FOREIGN KEY (plan_id) REFERENCES rcc_plans (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rcc_invoices (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    subscription_id INT UNSIGNED NULL,
    invoice_no VARCHAR(32) NOT NULL,
    status ENUM('draft','open','paid','void','uncollectible') NOT NULL DEFAULT 'draft',
    currency CHAR(3) NOT NULL DEFAULT 'SAR',
    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    tax_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    total_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    amount_paid DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    due_at TIMESTAMP NULL,
    paid_at TIMESTAMP NULL,
    line_items_json JSON NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_rcc_invoices_no (tenant_id, invoice_no),
    KEY idx_rcc_invoices_tenant_status (tenant_id, status),
    CONSTRAINT fk_rcc_invoices_tenant FOREIGN KEY (tenant_id) REFERENCES rcc_tenants (id) ON DELETE CASCADE,
    CONSTRAINT fk_rcc_invoices_sub FOREIGN KEY (subscription_id) REFERENCES rcc_subscriptions (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rcc_payments (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    invoice_id INT UNSIGNED NOT NULL,
    gateway VARCHAR(32) NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'SAR',
    status ENUM('pending','processing','succeeded','failed','refunded') NOT NULL DEFAULT 'pending',
    external_id VARCHAR(128) NULL,
    payer_email VARCHAR(255) NULL,
    metadata_json JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_rcc_payments_tenant (tenant_id, status),
    KEY idx_rcc_payments_invoice (invoice_id),
    KEY idx_rcc_payments_external (gateway, external_id),
    CONSTRAINT fk_rcc_payments_tenant FOREIGN KEY (tenant_id) REFERENCES rcc_tenants (id) ON DELETE CASCADE,
    CONSTRAINT fk_rcc_payments_invoice FOREIGN KEY (invoice_id) REFERENCES rcc_invoices (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rcc_payment_transactions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    payment_id INT UNSIGNED NOT NULL,
    gateway VARCHAR(32) NOT NULL,
    transaction_type ENUM('authorize','capture','refund','void','webhook') NOT NULL,
    status VARCHAR(32) NOT NULL,
    amount DECIMAL(12,2) NULL,
    currency CHAR(3) NULL,
    external_ref VARCHAR(128) NULL,
    raw_response JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_rcc_pay_tx_payment (payment_id),
    KEY idx_rcc_pay_tx_tenant (tenant_id, created_at),
    CONSTRAINT fk_rcc_pay_tx_tenant FOREIGN KEY (tenant_id) REFERENCES rcc_tenants (id) ON DELETE CASCADE,
    CONSTRAINT fk_rcc_pay_tx_payment FOREIGN KEY (payment_id) REFERENCES rcc_payments (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rcc_usage_metrics (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    metric_key VARCHAR(64) NOT NULL,
    metric_date DATE NOT NULL,
    quantity DECIMAL(14,4) NOT NULL DEFAULT 0,
    unit VARCHAR(32) NOT NULL DEFAULT 'count',
    metadata_json JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_rcc_usage_metric (tenant_id, metric_key, metric_date),
    KEY idx_rcc_usage_tenant_date (tenant_id, metric_date),
    CONSTRAINT fk_rcc_usage_tenant FOREIGN KEY (tenant_id) REFERENCES rcc_tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rcc_payment_gateways (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NULL,
    gateway VARCHAR(32) NOT NULL,
    display_name VARCHAR(128) NOT NULL,
    is_enabled TINYINT(1) NOT NULL DEFAULT 0,
    is_sandbox TINYINT(1) NOT NULL DEFAULT 1,
    credentials_ref VARCHAR(128) NULL,
    config_json JSON NULL,
    webhook_secret_ref VARCHAR(128) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_rcc_payment_gateway (tenant_id, gateway),
    KEY idx_rcc_payment_gateway_enabled (is_enabled, gateway),
    CONSTRAINT fk_rcc_payment_gateways_tenant FOREIGN KEY (tenant_id) REFERENCES rcc_tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rcc_licenses (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    license_key VARCHAR(64) NOT NULL,
    plan_id INT UNSIGNED NULL,
    seats INT UNSIGNED NOT NULL DEFAULT 5,
    status ENUM('active','expired','revoked') NOT NULL DEFAULT 'active',
    issued_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    metadata_json JSON NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_rcc_licenses_key (license_key),
    KEY idx_rcc_licenses_tenant (tenant_id, status),
    CONSTRAINT fk_rcc_licenses_tenant FOREIGN KEY (tenant_id) REFERENCES rcc_tenants (id) ON DELETE CASCADE,
    CONSTRAINT fk_rcc_licenses_plan FOREIGN KEY (plan_id) REFERENCES rcc_plans (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO rcc_plans (code, name, name_ar, billing_cycle, price_amount, max_agents, max_queues, features_json, sort_order) VALUES
('starter', 'Starter', 'المبتدئ', 'monthly', 299.00, 5, 2, '{"ivr":true,"softphone":true,"inbox":true}', 10),
('professional', 'Professional', 'احترافي', 'monthly', 799.00, 25, 10, '{"ivr":true,"softphone":true,"inbox":true,"crm":true,"qa":true}', 20),
('enterprise', 'Enterprise', 'المؤسسات', 'monthly', 1999.00, 100, 50, '{"ivr":true,"softphone":true,"inbox":true,"crm":true,"qa":true,"analytics":true,"white_label":true}', 30);

INSERT IGNORE INTO rcc_payment_gateways (tenant_id, gateway, display_name, is_enabled, is_sandbox, sort_order) VALUES
(NULL, 'stripe', 'Stripe', 0, 1, 10),
(NULL, 'paypal', 'PayPal', 0, 1, 20),
(NULL, 'moyasar', 'Moyasar', 0, 1, 30),
(NULL, 'hyperpay', 'HyperPay', 0, 1, 40),
(NULL, 'tabby', 'Tabby', 0, 1, 50),
(NULL, 'tamara', 'Tamara', 0, 1, 60);

INSERT IGNORE INTO rcc_permissions (slug, name, module) VALUES
('rcc.billing.view', 'View Billing', 'billing'),
('rcc.billing.manage', 'Manage Billing', 'billing'),
('rcc.billing.invoices', 'Manage Invoices', 'billing'),
('rcc.billing.payments', 'Process Payments', 'billing'),
('rcc.billing.subscriptions', 'Manage Subscriptions', 'billing'),
('rcc.license.view', 'View Licenses', 'billing'),
('rcc.license.manage', 'Manage Licenses', 'billing');

INSERT IGNORE INTO rcc_role_permissions (role_id, permission_id)
SELECT 1, id FROM rcc_permissions WHERE slug LIKE 'rcc.billing.%' OR slug LIKE 'rcc.license.%';

INSERT IGNORE INTO rcc_role_permissions (role_id, permission_id)
SELECT 2, id FROM rcc_permissions WHERE slug IN ('rcc.billing.view', 'rcc.license.view');
