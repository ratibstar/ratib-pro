-- RATIB Contact Center — WebRTC Softphone
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS rcc_settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    group_key VARCHAR(60) NOT NULL,
    setting_key VARCHAR(80) NOT NULL,
    setting_value TEXT NULL,
    value_type ENUM('string','int','bool','json') NOT NULL DEFAULT 'string',
    is_encrypted TINYINT(1) NOT NULL DEFAULT 0,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_rcc_setting (tenant_id, group_key, setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rcc_agent_sip_sessions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    agent_id INT UNSIGNED NOT NULL,
    tenant_id INT UNSIGNED NOT NULL,
    sip_extension VARCHAR(40) NOT NULL,
    sip_domain VARCHAR(190) NOT NULL,
    status ENUM('online','offline') NOT NULL DEFAULT 'offline',
    session_token CHAR(64) NULL,
    user_agent VARCHAR(500) NULL,
    last_ping DATETIME(3) NULL,
    registered_at DATETIME(3) NULL,
    created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    updated_at DATETIME(3) NULL ON UPDATE CURRENT_TIMESTAMP(3),
    UNIQUE KEY uk_rcc_sip_sess_agent (tenant_id, agent_id),
    INDEX idx_rcc_sip_sess_status (tenant_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rcc_softphone_calls (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    call_id BIGINT UNSIGNED NULL,
    tenant_id INT UNSIGNED NOT NULL,
    agent_id INT UNSIGNED NOT NULL,
    queue_id INT UNSIGNED NULL,
    direction ENUM('inbound','outbound') NOT NULL DEFAULT 'inbound',
    status ENUM('ringing','connected','held','transferred','ended') NOT NULL DEFAULT 'ringing',
    remote_number VARCHAR(40) NOT NULL,
    sip_call_id VARCHAR(190) NULL,
    started_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    connected_at DATETIME(3) NULL,
    ended_at DATETIME(3) NULL,
    duration_seconds INT UNSIGNED NOT NULL DEFAULT 0,
    state_json JSON NULL,
    INDEX idx_rcc_sp_call_agent (tenant_id, agent_id, status),
    INDEX idx_rcc_sp_call_id (call_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rcc_sip_extensions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    agent_id INT UNSIGNED NULL,
    extension VARCHAR(40) NOT NULL,
    sip_username VARCHAR(80) NOT NULL,
    sip_password_ref VARCHAR(120) NOT NULL,
    sip_domain VARCHAR(190) NOT NULL DEFAULT 'pbx.ratib.local',
    wss_uri VARCHAR(255) NOT NULL DEFAULT 'wss://pbx.ratib.local:8089/ws',
    webrtc_enabled TINYINT(1) NOT NULL DEFAULT 1,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    UNIQUE KEY uk_rcc_sip_ext (tenant_id, extension),
    INDEX idx_rcc_sip_ext_agent (tenant_id, agent_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO rcc_settings (tenant_id, group_key, setting_key, setting_value, value_type)
VALUES (1, 'softphone', 'auto_answer_queue_calls', 'false', 'bool');

INSERT IGNORE INTO rcc_sip_extensions (tenant_id, agent_id, extension, sip_username, sip_password_ref, sip_domain, wss_uri, status)
VALUES (1, 1, '1001', 'agent-1001', 'RCC_SIP_PASS_TENANT_1', 'pbx.ratib.sa', 'wss://pbx.ratib.sa:8089/ws', 'active');
