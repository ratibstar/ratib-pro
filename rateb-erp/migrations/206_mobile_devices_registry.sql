-- Unified Mobile Device Registry (Phase J)
-- Phone apps only — not POS/offline devices.

CREATE TABLE IF NOT EXISTS rateb_mobile_devices (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    client_app VARCHAR(32) NOT NULL,
    platform VARCHAR(16) NOT NULL DEFAULT 'other',
    device_id VARCHAR(64) NOT NULL,
    push_token VARCHAR(512) NULL,
    app_version VARCHAR(64) NULL,
    last_seen_at DATETIME NULL,
    status ENUM('active', 'inactive', 'revoked') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_mobile_device_identity (company_id, client_app, device_id),
    KEY idx_mobile_device_user (company_id, user_id, status),
    KEY idx_mobile_device_seen (company_id, last_seen_at),
    CONSTRAINT fk_mobile_device_company
        FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
