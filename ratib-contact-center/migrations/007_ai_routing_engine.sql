-- RATIB Contact Center — 007 AI routing engine

CREATE TABLE IF NOT EXISTS rcc_agent_skills (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    agent_id INT UNSIGNED NOT NULL,
    skill VARCHAR(64) NOT NULL,
    level TINYINT UNSIGNED NOT NULL DEFAULT 5,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_rcc_agent_skills (tenant_id, agent_id, skill),
    KEY idx_rcc_agent_skills_tenant (tenant_id),
    CONSTRAINT fk_rcc_agent_skills_tenant FOREIGN KEY (tenant_id) REFERENCES rcc_tenants (id) ON DELETE CASCADE,
    CONSTRAINT fk_rcc_agent_skills_agent FOREIGN KEY (agent_id) REFERENCES rcc_agents (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rcc_routing_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    call_id INT UNSIGNED NOT NULL,
    selected_agent_id INT UNSIGNED NULL,
    selected_queue_id INT UNSIGNED NULL,
    sla_risk VARCHAR(16) NOT NULL DEFAULT 'green',
    decision_json JSON NOT NULL,
    score_json JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_rcc_routing_logs_tenant (tenant_id),
    KEY idx_rcc_routing_logs_call (tenant_id, call_id),
    KEY idx_rcc_routing_logs_queue (tenant_id, selected_queue_id, sla_risk),
    CONSTRAINT fk_rcc_routing_logs_tenant FOREIGN KEY (tenant_id) REFERENCES rcc_tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
