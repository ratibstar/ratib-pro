-- RATIB Contact Center — 009 AI assistant & tickets

CREATE TABLE IF NOT EXISTS rcc_tickets (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    ticket_no VARCHAR(32) NOT NULL,
    subject VARCHAR(512) NOT NULL,
    description TEXT NOT NULL,
    conversation_id INT UNSIGNED NULL,
    call_id INT UNSIGNED NULL,
    contact_id INT UNSIGNED NULL,
    channel VARCHAR(32) NOT NULL DEFAULT 'phone',
    priority ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
    status ENUM('open','in_progress','pending','resolved','closed') NOT NULL DEFAULT 'open',
    source VARCHAR(64) NULL,
    auto_created TINYINT(1) NOT NULL DEFAULT 0,
    resolution_due TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_rcc_tickets_no (tenant_id, ticket_no),
    KEY idx_rcc_tickets_tenant (tenant_id),
    KEY idx_rcc_tickets_contact (tenant_id, contact_id, status),
    KEY idx_rcc_tickets_conversation (conversation_id),
    CONSTRAINT fk_rcc_tickets_tenant FOREIGN KEY (tenant_id) REFERENCES rcc_tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rcc_ai_context (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    conversation_id INT UNSIGNED NOT NULL,
    sentiment VARCHAR(32) NULL,
    sentiment_score DECIMAL(5,3) NULL,
    intent VARCHAR(64) NULL,
    intent_confidence DECIMAL(5,3) NULL,
    summary_live TEXT NULL,
    summary_final TEXT NULL,
    risk_score DECIMAL(5,3) NULL,
    recommended_action VARCHAR(64) NULL,
    suggested_reply TEXT NULL,
    suggestions_json JSON NULL,
    ticket_id INT UNSIGNED NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_rcc_ai_context_conv (tenant_id, conversation_id),
    KEY idx_rcc_ai_context_tenant (tenant_id),
    CONSTRAINT fk_rcc_ai_context_tenant FOREIGN KEY (tenant_id) REFERENCES rcc_tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rcc_report_exports (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NULL,
    report_type VARCHAR(64) NOT NULL,
    format ENUM('csv','xlsx','pdf') NOT NULL,
    status ENUM('pending','processing','completed','failed') NOT NULL DEFAULT 'pending',
    file_path VARCHAR(512) NULL,
    parameters_json JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    PRIMARY KEY (id),
    KEY idx_rcc_report_exports_tenant (tenant_id),
    CONSTRAINT fk_rcc_report_exports_tenant FOREIGN KEY (tenant_id) REFERENCES rcc_tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
