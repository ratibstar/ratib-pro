-- RATEB Contact Center — 006 softphone & WebRTC

CREATE TABLE IF NOT EXISTS rcc_sip_extensions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    agent_id INT UNSIGNED NULL,
    extension VARCHAR(32) NOT NULL,
    sip_username VARCHAR(128) NOT NULL,
    sip_password_ref VARCHAR(128) NOT NULL,
    sip_domain VARCHAR(255) NOT NULL,
    wss_uri VARCHAR(512) NULL,
    webrtc_enabled TINYINT(1) NOT NULL DEFAULT 1,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_rcc_sip_ext_tenant_user (tenant_id, sip_username),
    KEY idx_rcc_sip_ext_tenant (tenant_id),
    KEY idx_rcc_sip_ext_agent (tenant_id, agent_id),
    CONSTRAINT fk_rcc_sip_ext_tenant FOREIGN KEY (tenant_id) REFERENCES rcc_tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rcc_agent_sip_sessions (
    agent_id INT UNSIGNED NOT NULL,
    tenant_id INT UNSIGNED NOT NULL,
    sip_extension VARCHAR(32) NOT NULL,
    sip_domain VARCHAR(255) NOT NULL,
    status ENUM('online','offline') NOT NULL DEFAULT 'offline',
    session_token CHAR(64) NULL,
    user_agent VARCHAR(512) NULL,
    last_ping TIMESTAMP(3) NULL,
    registered_at TIMESTAMP(3) NULL,
    updated_at TIMESTAMP(3) NULL ON UPDATE CURRENT_TIMESTAMP(3),
    PRIMARY KEY (agent_id, tenant_id),
    KEY idx_rcc_agent_sip_tenant (tenant_id),
    KEY idx_rcc_agent_sip_status (tenant_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rcc_softphone_calls (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    call_id INT UNSIGNED NULL,
    tenant_id INT UNSIGNED NOT NULL,
    agent_id INT UNSIGNED NOT NULL,
    queue_id INT UNSIGNED NULL,
    direction ENUM('inbound','outbound') NOT NULL,
    status ENUM('ringing','connected','held','transferred','ended') NOT NULL DEFAULT 'ringing',
    remote_number VARCHAR(40) NOT NULL,
    sip_call_id VARCHAR(128) NULL,
    duration_seconds INT UNSIGNED NULL,
    state_json JSON NULL,
    started_at TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    connected_at TIMESTAMP(3) NULL,
    ended_at TIMESTAMP(3) NULL,
    PRIMARY KEY (id),
    KEY idx_rcc_softphone_tenant_agent (tenant_id, agent_id),
    KEY idx_rcc_softphone_status (tenant_id, agent_id, status),
    KEY idx_rcc_softphone_call (call_id),
    CONSTRAINT fk_rcc_softphone_tenant FOREIGN KEY (tenant_id) REFERENCES rcc_tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
