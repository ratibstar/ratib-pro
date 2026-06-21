-- RATIB Contact Center — Real-Time Core Layer
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS rcc_realtime_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_uuid CHAR(36) NOT NULL,
    tenant_id INT UNSIGNED NOT NULL,
    event_type VARCHAR(80) NOT NULL,
    payload JSON NOT NULL,
    created_at TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    UNIQUE KEY uk_rcc_rt_event_uuid (event_uuid),
    INDEX idx_rcc_rt_tenant_time (tenant_id, created_at),
    INDEX idx_rcc_rt_type (tenant_id, event_type, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rcc_agent_live_state (
    agent_id INT UNSIGNED NOT NULL,
    tenant_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NULL,
    status ENUM('offline','ready','busy','wrapup','paused') NOT NULL DEFAULT 'offline',
    current_call_id BIGINT UNSIGNED NULL,
    queue_id INT UNSIGNED NULL,
    pause_reason VARCHAR(120) NULL,
    session_started_at DATETIME NULL,
    last_update DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
    state_json JSON NULL,
    PRIMARY KEY (tenant_id, agent_id),
    INDEX idx_rcc_agent_live_status (tenant_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rcc_erp_activity_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    erp_company_id INT UNSIGNED NULL,
    event_uuid CHAR(36) NOT NULL,
    activity_type VARCHAR(80) NOT NULL,
    reference_type VARCHAR(60) NULL,
    reference_id BIGINT UNSIGNED NULL,
    summary VARCHAR(500) NOT NULL,
    payload JSON NULL,
    created_at TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    INDEX idx_rcc_erp_act_tenant (tenant_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE rcc_queues
    ADD COLUMN sla_target_seconds INT UNSIGNED NULL DEFAULT 300 AFTER status;

CREATE TABLE IF NOT EXISTS rcc_agents (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    extension VARCHAR(20) NULL,
    status ENUM('offline','online','break','lunch','training','wrap_up') NOT NULL DEFAULT 'offline',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_rcc_agent_user (tenant_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
