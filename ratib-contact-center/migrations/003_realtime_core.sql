-- RATEB Contact Center — 003 realtime core

CREATE TABLE IF NOT EXISTS rcc_realtime_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_uuid CHAR(36) NOT NULL,
    tenant_id INT UNSIGNED NOT NULL,
    event_type VARCHAR(64) NOT NULL,
    payload JSON NOT NULL,
    created_at TIMESTAMP(3) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_rcc_realtime_events_uuid (event_uuid),
    KEY idx_rcc_realtime_tenant (tenant_id),
    KEY idx_rcc_realtime_type (tenant_id, event_type),
    KEY idx_rcc_realtime_created (created_at),
    CONSTRAINT fk_rcc_realtime_tenant FOREIGN KEY (tenant_id) REFERENCES rcc_tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rcc_agent_live_state (
    agent_id INT UNSIGNED NOT NULL,
    tenant_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NULL,
    status ENUM('offline','ready','busy','wrapup','paused') NOT NULL DEFAULT 'offline',
    current_call_id INT UNSIGNED NULL,
    queue_id INT UNSIGNED NULL,
    pause_reason VARCHAR(128) NULL,
    session_started_at TIMESTAMP(3) NULL,
    state_json JSON NULL,
    last_update TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
    PRIMARY KEY (agent_id, tenant_id),
    KEY idx_rcc_agent_live_tenant (tenant_id),
    KEY idx_rcc_agent_live_status (tenant_id, status),
    KEY idx_rcc_agent_live_queue (tenant_id, queue_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
