-- RATEB ERP — Procurement Ops Agent Audit Events
-- Creates audit table for agent tool invocations

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS rateb_agent_audit_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    session_id VARCHAR(128) NOT NULL,
    tool_name VARCHAR(60) NOT NULL,
    input_json JSON NOT NULL,
    output_json JSON NULL,
    status ENUM('success','error','denied') NOT NULL,
    error_code VARCHAR(60) NULL,
    policy_checks_json JSON NOT NULL,
    request_id VARCHAR(64) NULL,
    llm_model VARCHAR(80) NULL,
    llm_tokens_in INT UNSIGNED NULL,
    llm_tokens_out INT UNSIGNED NULL,
    duration_ms INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_company_created (company_id, created_at),
    INDEX idx_user_created (user_id, created_at),
    INDEX idx_tool_status (tool_name, status),
    INDEX idx_session (session_id),
    INDEX idx_request (request_id),
    CONSTRAINT fk_agent_audit_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE RESTRICT,
    CONSTRAINT fk_agent_audit_user FOREIGN KEY (user_id) REFERENCES rateb_users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;