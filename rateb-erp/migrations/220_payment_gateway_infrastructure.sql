-- Payment gateway infrastructure — Moyasar (multi-tenant, ERP-native)
-- Does NOT alter rateb_invoices or accounting tables.

CREATE TABLE IF NOT EXISTS rateb_payment_gateway_settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NULL COMMENT 'NULL = platform default',
    gateway_slug VARCHAR(40) NOT NULL DEFAULT 'moyasar',
    enabled TINYINT(1) NOT NULL DEFAULT 0,
    mode ENUM('sandbox','production') NOT NULL DEFAULT 'sandbox',
    publishable_key_enc VARCHAR(512) NULL,
    secret_key_enc VARCHAR(512) NULL,
    webhook_secret_enc VARCHAR(512) NULL,
    callback_url VARCHAR(500) NULL,
    webhook_url VARCHAR(500) NULL,
    health_status ENUM('unknown','healthy','degraded','failed') NOT NULL DEFAULT 'unknown',
    last_health_check_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_gateway_settings_company_slug (company_id, gateway_slug),
    INDEX idx_gateway_settings_slug (gateway_slug),
    INDEX idx_gateway_settings_enabled (enabled)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_payment_transactions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    invoice_id INT UNSIGNED NOT NULL,
    gateway_slug VARCHAR(40) NOT NULL DEFAULT 'moyasar',
    external_id VARCHAR(120) NULL,
    idempotency_key VARCHAR(64) NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'SAR',
    status ENUM('pending','processing','completed','failed','cancelled','refunded','partially_refunded') NOT NULL DEFAULT 'pending',
    rateb_payment_id INT UNSIGNED NULL,
    redirect_url VARCHAR(1000) NULL,
    callback_token VARCHAR(64) NOT NULL,
    error_code VARCHAR(80) NULL,
    error_message VARCHAR(500) NULL,
    raw_request_json JSON NULL,
    raw_response_json JSON NULL,
    initiated_at DATETIME NULL,
    completed_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_payment_tx_idempotency (idempotency_key),
    INDEX idx_payment_tx_company (company_id),
    INDEX idx_payment_tx_invoice (invoice_id),
    INDEX idx_payment_tx_external (external_id),
    INDEX idx_payment_tx_status (status),
    INDEX idx_payment_tx_callback (callback_token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_payment_webhooks (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    gateway_slug VARCHAR(40) NOT NULL DEFAULT 'moyasar',
    event_id VARCHAR(120) NOT NULL,
    transaction_id INT UNSIGNED NULL,
    signature_valid TINYINT(1) NOT NULL DEFAULT 0,
    payload_hash CHAR(64) NOT NULL,
    status ENUM('received','processed','ignored','failed') NOT NULL DEFAULT 'received',
    payload_json JSON NULL,
    client_ip VARCHAR(45) NULL,
    received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    processed_at DATETIME NULL,
    UNIQUE KEY uq_payment_webhook_event (gateway_slug, event_id),
    INDEX idx_payment_webhook_tx (transaction_id),
    INDEX idx_payment_webhook_status (status),
    INDEX idx_payment_webhook_hash (payload_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
