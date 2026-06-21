-- RATIB Contact Center — IVR Runtime Engine (data-driven state machine)
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

CREATE TABLE IF NOT EXISTS rcc_ivr_flows (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    name VARCHAR(120) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 0,
    entry_node_id INT UNSIGNED NULL,
    default_locale ENUM('en','ar') NOT NULL DEFAULT 'ar',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_rcc_ivr_flow_tenant (tenant_id, is_active),
    CONSTRAINT fk_rcc_ivr_flow_tenant FOREIGN KEY (tenant_id) REFERENCES rcc_tenants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rcc_ivr_nodes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    flow_id INT UNSIGNED NOT NULL,
    type ENUM('play_message','collect_input','route_call','hangup') NOT NULL,
    payload JSON NOT NULL,
    next_node_id INT UNSIGNED NULL,
    fallback_node_id INT UNSIGNED NULL,
    max_retries TINYINT UNSIGNED NOT NULL DEFAULT 3,
    timeout_seconds TINYINT UNSIGNED NOT NULL DEFAULT 10,
    sort_order INT NOT NULL DEFAULT 0,
    INDEX idx_rcc_ivr_node_flow (flow_id, sort_order),
    CONSTRAINT fk_rcc_ivr_node_flow FOREIGN KEY (flow_id) REFERENCES rcc_ivr_flows(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rcc_ivr_sessions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    call_id BIGINT UNSIGNED NOT NULL,
    call_uuid CHAR(36) NULL,
    tenant_id INT UNSIGNED NOT NULL,
    flow_id INT UNSIGNED NOT NULL,
    current_node_id INT UNSIGNED NULL,
    state JSON NOT NULL,
    status ENUM('active','waiting_input','completed','failed','timeout') NOT NULL DEFAULT 'active',
    channel_id VARCHAR(120) NULL,
    locale ENUM('en','ar') NOT NULL DEFAULT 'ar',
    retry_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
    started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    completed_at DATETIME NULL,
    INDEX idx_rcc_ivr_sess_call (call_id),
    INDEX idx_rcc_ivr_sess_tenant (tenant_id, status),
    INDEX idx_rcc_ivr_sess_channel (channel_id),
    CONSTRAINT fk_rcc_ivr_sess_call FOREIGN KEY (call_id) REFERENCES rcc_calls(id) ON DELETE CASCADE,
    CONSTRAINT fk_rcc_ivr_sess_flow FOREIGN KEY (flow_id) REFERENCES rcc_ivr_flows(id),
    CONSTRAINT fk_rcc_ivr_sess_tenant FOREIGN KEY (tenant_id) REFERENCES rcc_tenants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
