-- RATEB Contact Center — 008 omnichannel conversations

CREATE TABLE IF NOT EXISTS rcc_conversations (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    customer_id INT UNSIGNED NULL,
    customer_identity VARCHAR(255) NOT NULL,
    assigned_agent_id INT UNSIGNED NULL,
    priority VARCHAR(16) NOT NULL DEFAULT 'medium',
    status ENUM('open','pending','closed') NOT NULL DEFAULT 'open',
    sla_status VARCHAR(16) NOT NULL DEFAULT 'green',
    priority_score DECIMAL(8,3) NULL,
    last_channel VARCHAR(32) NULL,
    last_message TEXT NULL,
    last_message_at TIMESTAMP(3) NULL,
    channels_json JSON NULL,
    call_id INT UNSIGNED NULL,
    ivr_session_id INT UNSIGNED NULL,
    metadata_json JSON NULL,
    unread_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_rcc_conversations_tenant (tenant_id),
    KEY idx_rcc_conversations_identity (tenant_id, customer_identity, status),
    KEY idx_rcc_conversations_agent (tenant_id, assigned_agent_id, status),
    KEY idx_rcc_conversations_call (tenant_id, call_id),
    CONSTRAINT fk_rcc_conversations_tenant FOREIGN KEY (tenant_id) REFERENCES rcc_tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rcc_conversation_messages (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    conversation_id INT UNSIGNED NOT NULL,
    channel VARCHAR(32) NOT NULL,
    direction ENUM('inbound','outbound') NOT NULL,
    message TEXT NOT NULL,
    payload JSON NULL,
    external_id VARCHAR(128) NULL,
    sender_type VARCHAR(32) NOT NULL DEFAULT 'customer',
    sender_id INT UNSIGNED NULL,
    delivery_status VARCHAR(32) NULL,
    created_at TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    PRIMARY KEY (id),
    KEY idx_rcc_conv_msgs_tenant (tenant_id),
    KEY idx_rcc_conv_msgs_conversation (tenant_id, conversation_id),
    KEY idx_rcc_conv_msgs_external (tenant_id, external_id),
    CONSTRAINT fk_rcc_conv_msgs_tenant FOREIGN KEY (tenant_id) REFERENCES rcc_tenants (id) ON DELETE CASCADE,
    CONSTRAINT fk_rcc_conv_msgs_conv FOREIGN KEY (conversation_id) REFERENCES rcc_conversations (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rcc_customer_identity_map (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    contact_id INT UNSIGNED NULL,
    phone VARCHAR(40) NULL,
    email VARCHAR(255) NULL,
    erp_customer_id INT UNSIGNED NULL,
    matched_by VARCHAR(64) NOT NULL,
    confidence DECIMAL(5,3) NOT NULL DEFAULT 0.5,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_rcc_identity_tenant (tenant_id),
    KEY idx_rcc_identity_phone (tenant_id, phone),
    KEY idx_rcc_identity_email (tenant_id, email),
    CONSTRAINT fk_rcc_identity_tenant FOREIGN KEY (tenant_id) REFERENCES rcc_tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rcc_channel_outbox (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    conversation_id INT UNSIGNED NOT NULL,
    message_id BIGINT UNSIGNED NULL,
    channel VARCHAR(32) NOT NULL,
    status ENUM('pending','sent','failed') NOT NULL DEFAULT 'pending',
    payload JSON NOT NULL,
    error_message TEXT NULL,
    sent_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_rcc_outbox_tenant (tenant_id),
    KEY idx_rcc_outbox_status (tenant_id, status),
    CONSTRAINT fk_rcc_outbox_tenant FOREIGN KEY (tenant_id) REFERENCES rcc_tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
