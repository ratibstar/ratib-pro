-- RATIB Contact Center — Unified Omnichannel Conversations
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS rcc_contacts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    full_name VARCHAR(200) NULL,
    email VARCHAR(190) NULL,
    phone_primary VARCHAR(40) NULL,
    contact_type ENUM('lead','customer','vip','partner') NOT NULL DEFAULT 'customer',
    company_id INT UNSIGNED NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    deleted_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_rcc_contact_tenant (tenant_id),
    INDEX idx_rcc_contact_phone (tenant_id, phone_primary),
    INDEX idx_rcc_contact_email (tenant_id, email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rcc_customer_identity_map (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    contact_id INT UNSIGNED NULL,
    phone VARCHAR(40) NULL,
    email VARCHAR(190) NULL,
    erp_customer_id INT UNSIGNED NULL,
    external_ids JSON NULL,
    confidence DECIMAL(4,3) NOT NULL DEFAULT 1.000,
    matched_by VARCHAR(80) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_rcc_cim_tenant_phone (tenant_id, phone),
    INDEX idx_rcc_cim_tenant_email (tenant_id, email),
    INDEX idx_rcc_cim_erp (tenant_id, erp_customer_id),
    INDEX idx_rcc_cim_contact (tenant_id, contact_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rcc_conversations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    customer_id INT UNSIGNED NULL COMMENT 'rcc_contacts.id',
    customer_identity VARCHAR(190) NOT NULL COMMENT 'primary phone or email',
    assigned_agent_id INT UNSIGNED NULL,
    priority ENUM('low','medium','high','vip') NOT NULL DEFAULT 'medium',
    status ENUM('open','pending','closed') NOT NULL DEFAULT 'open',
    sla_status ENUM('green','yellow','red') NOT NULL DEFAULT 'green',
    priority_score DECIMAL(6,3) NULL,
    last_channel VARCHAR(40) NULL,
    last_message TEXT NULL,
    last_message_at DATETIME(3) NULL,
    channels_json JSON NULL,
    call_id BIGINT UNSIGNED NULL,
    ivr_session_id INT UNSIGNED NULL,
    metadata_json JSON NULL,
    unread_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_rcc_conv_tenant_status (tenant_id, status, last_message_at),
    INDEX idx_rcc_conv_agent (tenant_id, assigned_agent_id, status),
    INDEX idx_rcc_conv_identity (tenant_id, customer_identity),
    INDEX idx_rcc_conv_call (tenant_id, call_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rcc_conversation_messages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    conversation_id BIGINT UNSIGNED NOT NULL,
    channel ENUM('voice','whatsapp','email','chat','social','system') NOT NULL,
    direction ENUM('inbound','outbound') NOT NULL,
    message TEXT NULL,
    payload JSON NULL,
    external_id VARCHAR(190) NULL,
    sender_type ENUM('contact','agent','system') NOT NULL DEFAULT 'contact',
    sender_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    INDEX idx_rcc_cmsg_conv (conversation_id, created_at),
    INDEX idx_rcc_cmsg_tenant (tenant_id, created_at),
    CONSTRAINT fk_rcc_cmsg_conv FOREIGN KEY (conversation_id) REFERENCES rcc_conversations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE rcc_calls
    ADD COLUMN conversation_id BIGINT UNSIGNED NULL;
