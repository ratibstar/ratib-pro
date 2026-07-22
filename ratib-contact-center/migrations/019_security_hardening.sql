-- RATEB Contact Center — 019 security hardening (Phase 10I)

CREATE TABLE IF NOT EXISTS rcc_audit_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NULL,
    action VARCHAR(128) NOT NULL,
    resource_type VARCHAR(64) NULL,
    resource_id INT UNSIGNED NULL,
    ip_address VARCHAR(45) NULL,
    payload JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_rcc_audit_tenant (tenant_id, created_at),
    KEY idx_rcc_audit_action (tenant_id, action),
    CONSTRAINT fk_rcc_audit_tenant FOREIGN KEY (tenant_id) REFERENCES rcc_tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rcc_api_rate_limits (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    client_key VARCHAR(128) NOT NULL,
    endpoint VARCHAR(128) NOT NULL,
    window_start TIMESTAMP NOT NULL,
    request_count INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_rcc_rate_limit (tenant_id, client_key, endpoint, window_start),
    KEY idx_rcc_rate_limit_window (window_start),
    CONSTRAINT fk_rcc_rate_limit_tenant FOREIGN KEY (tenant_id) REFERENCES rcc_tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rcc_user_devices (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    device_fingerprint VARCHAR(128) NOT NULL,
    user_agent VARCHAR(512) NULL,
    last_ip VARCHAR(45) NULL,
    last_seen_at TIMESTAMP NULL,
    is_trusted TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_rcc_user_device (tenant_id, user_id, device_fingerprint),
    CONSTRAINT fk_rcc_user_devices_tenant FOREIGN KEY (tenant_id) REFERENCES rcc_tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rcc_user_sessions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    session_token_hash CHAR(64) NOT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(512) NULL,
    expires_at TIMESTAMP NOT NULL,
    revoked_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_rcc_user_sessions_user (tenant_id, user_id, expires_at),
    KEY idx_rcc_user_sessions_token (session_token_hash),
    CONSTRAINT fk_rcc_user_sessions_tenant FOREIGN KEY (tenant_id) REFERENCES rcc_tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rcc_login_history (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NULL,
    email VARCHAR(255) NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(512) NULL,
    success TINYINT(1) NOT NULL DEFAULT 0,
    failure_reason VARCHAR(128) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_rcc_login_history_user (tenant_id, user_id, created_at),
    KEY idx_rcc_login_history_ip (tenant_id, ip_address, created_at),
    CONSTRAINT fk_rcc_login_history_tenant FOREIGN KEY (tenant_id) REFERENCES rcc_tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rcc_ip_restrictions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    cidr VARCHAR(64) NOT NULL,
    rule_type ENUM('allow','deny') NOT NULL DEFAULT 'allow',
    label VARCHAR(128) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_rcc_ip_restrictions_tenant (tenant_id, is_active),
    CONSTRAINT fk_rcc_ip_restrictions_tenant FOREIGN KEY (tenant_id) REFERENCES rcc_tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rcc_supervisor_approvals (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    approval_type VARCHAR(64) NOT NULL,
    requested_by_user_id INT UNSIGNED NOT NULL,
    resource_type VARCHAR(64) NULL,
    resource_id INT UNSIGNED NULL,
    payload_json JSON NULL,
    status ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
    decided_by_user_id INT UNSIGNED NULL,
    decided_at TIMESTAMP NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_rcc_approvals_tenant (tenant_id, status, created_at),
    CONSTRAINT fk_rcc_approvals_tenant FOREIGN KEY (tenant_id) REFERENCES rcc_tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rcc_audit_retention (
    tenant_id INT UNSIGNED NOT NULL,
    retention_days INT UNSIGNED NOT NULL DEFAULT 365,
    last_purge_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (tenant_id),
    CONSTRAINT fk_rcc_audit_retention_tenant FOREIGN KEY (tenant_id) REFERENCES rcc_tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO rcc_audit_retention (tenant_id, retention_days)
SELECT id, 365 FROM rcc_tenants WHERE status = 'active';

INSERT IGNORE INTO rcc_permissions (slug, name, module) VALUES
('rcc.security.audit', 'View Audit Logs', 'security'),
('rcc.security.sessions', 'Manage Sessions', 'security'),
('rcc.security.ip', 'Manage IP Restrictions', 'security'),
('rcc.security.approvals', 'Supervisor Approvals', 'security');

INSERT IGNORE INTO rcc_role_permissions (role_id, permission_id)
SELECT 1, id FROM rcc_permissions WHERE slug LIKE 'rcc.security.%';

INSERT IGNORE INTO rcc_role_permissions (role_id, permission_id)
SELECT 2, id FROM rcc_permissions WHERE slug IN ('rcc.security.audit', 'rcc.security.approvals');

INSERT IGNORE INTO rcc_permissions (slug, name, module) VALUES
('rcc.ai.qa', 'AI QA Scoring', 'ai'),
('rcc.ai.insights', 'AI Insights', 'ai');

INSERT IGNORE INTO rcc_role_permissions (role_id, permission_id)
SELECT 1, id FROM rcc_permissions WHERE slug IN ('rcc.ai.qa', 'rcc.ai.insights');

INSERT IGNORE INTO rcc_role_permissions (role_id, permission_id)
SELECT 2, id FROM rcc_permissions WHERE slug IN ('rcc.ai.qa', 'rcc.ai.insights');
