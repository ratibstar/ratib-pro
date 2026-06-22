-- RATIB Contact Center — 004 IVR runtime

CREATE TABLE IF NOT EXISTS rcc_ivr_flows (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 0,
    entry_node_id INT UNSIGNED NULL,
    default_locale VARCHAR(8) NOT NULL DEFAULT 'ar',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_rcc_ivr_flows_tenant (tenant_id),
    KEY idx_rcc_ivr_flows_active (tenant_id, is_active),
    CONSTRAINT fk_rcc_ivr_flows_tenant FOREIGN KEY (tenant_id) REFERENCES rcc_tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rcc_ivr_nodes (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    flow_id INT UNSIGNED NOT NULL,
    type VARCHAR(32) NOT NULL,
    payload JSON NOT NULL,
    next_node_id INT UNSIGNED NULL,
    fallback_node_id INT UNSIGNED NULL,
    max_retries INT UNSIGNED NOT NULL DEFAULT 3,
    timeout_seconds INT UNSIGNED NOT NULL DEFAULT 10,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_rcc_ivr_nodes_flow (flow_id),
    CONSTRAINT fk_rcc_ivr_nodes_flow FOREIGN KEY (flow_id) REFERENCES rcc_ivr_flows (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rcc_ivr_sessions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    call_id INT UNSIGNED NOT NULL,
    call_uuid CHAR(36) NULL,
    tenant_id INT UNSIGNED NOT NULL,
    flow_id INT UNSIGNED NOT NULL,
    current_node_id INT UNSIGNED NULL,
    state JSON NOT NULL,
    status ENUM('active','waiting_input','completed','failed','timeout') NOT NULL DEFAULT 'active',
    channel_id VARCHAR(128) NULL,
    locale VARCHAR(8) NOT NULL DEFAULT 'ar',
    retry_count INT UNSIGNED NOT NULL DEFAULT 0,
    started_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    PRIMARY KEY (id),
    KEY idx_rcc_ivr_sessions_tenant (tenant_id),
    KEY idx_rcc_ivr_sessions_call (tenant_id, call_id),
    KEY idx_rcc_ivr_sessions_channel (tenant_id, channel_id, status),
    CONSTRAINT fk_rcc_ivr_sessions_tenant FOREIGN KEY (tenant_id) REFERENCES rcc_tenants (id) ON DELETE CASCADE,
    CONSTRAINT fk_rcc_ivr_sessions_flow FOREIGN KEY (flow_id) REFERENCES rcc_ivr_flows (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
