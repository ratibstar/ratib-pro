-- RATIB Contact Center — AI Assistant Copilot context store
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS rcc_tickets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    ticket_no VARCHAR(64) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    description TEXT NULL,
    conversation_id BIGINT UNSIGNED NULL,
    call_id BIGINT UNSIGNED NULL,
    channel VARCHAR(40) NULL,
    priority ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
    status ENUM('open','pending','resolved','closed') NOT NULL DEFAULT 'open',
    source VARCHAR(40) NULL DEFAULT 'manual',
    auto_created TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_rcc_ticket_tenant (tenant_id, status),
    INDEX idx_rcc_ticket_conv (tenant_id, conversation_id),
    UNIQUE KEY uk_rcc_ticket_no (tenant_id, ticket_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rcc_ai_context (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    conversation_id BIGINT UNSIGNED NOT NULL,
    sentiment VARCHAR(40) NULL,
    sentiment_score DECIMAL(6,3) NULL,
    intent VARCHAR(80) NULL,
    intent_confidence DECIMAL(6,3) NULL,
    summary_live TEXT NULL,
    summary_final TEXT NULL,
    risk_score DECIMAL(6,3) NULL,
    recommended_action VARCHAR(80) NULL,
    suggested_reply TEXT NULL,
    suggestions_json JSON NULL,
    ticket_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME(3) NULL ON UPDATE CURRENT_TIMESTAMP(3),
    UNIQUE KEY uk_rcc_ai_ctx_conv (tenant_id, conversation_id),
    INDEX idx_rcc_ai_ctx_risk (tenant_id, risk_score),
    CONSTRAINT fk_rcc_ai_ctx_conv FOREIGN KEY (conversation_id) REFERENCES rcc_conversations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
