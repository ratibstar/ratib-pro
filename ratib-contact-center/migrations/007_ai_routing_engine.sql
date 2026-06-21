-- RATIB Contact Center — AI Routing Engine
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS rcc_agent_skills (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    agent_id INT UNSIGNED NOT NULL,
    skill ENUM('sales','support','billing') NOT NULL,
    level TINYINT UNSIGNED NOT NULL DEFAULT 3 COMMENT '1=novice .. 5=expert',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_rcc_agent_skill (tenant_id, agent_id, skill),
    INDEX idx_rcc_skill_tenant (tenant_id, skill),
    INDEX idx_rcc_skill_agent (tenant_id, agent_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rcc_routing_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    call_id BIGINT UNSIGNED NOT NULL,
    selected_agent_id INT UNSIGNED NULL,
    selected_queue_id INT UNSIGNED NULL,
    sla_risk ENUM('green','yellow','red') NOT NULL DEFAULT 'green',
    decision_json JSON NOT NULL,
    score_json JSON NOT NULL,
    created_at TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    INDEX idx_rcc_routing_call (tenant_id, call_id),
    INDEX idx_rcc_routing_time (tenant_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rcc_queue_members (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    queue_id INT UNSIGNED NOT NULL,
    agent_id INT UNSIGNED NOT NULL,
    penalty INT NOT NULL DEFAULT 0,
    is_paused TINYINT(1) NOT NULL DEFAULT 0,
    UNIQUE KEY uk_rcc_qmember (tenant_id, queue_id, agent_id),
    INDEX idx_rcc_qmember_agent (tenant_id, agent_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE rcc_agents
    ADD COLUMN is_senior TINYINT(1) NOT NULL DEFAULT 0 AFTER status,
    ADD COLUMN department VARCHAR(80) NULL AFTER is_senior;

ALTER TABLE rcc_calls
    ADD COLUMN agent_id INT UNSIGNED NULL AFTER queue_id,
    ADD COLUMN priority_score DECIMAL(6,3) NULL AFTER agent_id,
    ADD COLUMN routing_reason VARCHAR(255) NULL AFTER priority_score;

-- Optional tenant override slot for routing weights (JSON in setting_value)
INSERT INTO rcc_settings (tenant_id, group_key, setting_key, setting_value, value_type)
VALUES (1, 'routing', 'weights', NULL, 'json')
ON DUPLICATE KEY UPDATE group_key = VALUES(group_key);

-- Demo agent skills (agents 1–3 assumed from softphone seed)
INSERT INTO rcc_agent_skills (tenant_id, agent_id, skill, level) VALUES
(1, 1, 'sales', 5),
(1, 1, 'support', 3),
(1, 2, 'support', 5),
(1, 2, 'billing', 4),
(1, 3, 'billing', 5),
(1, 3, 'support', 2)
ON DUPLICATE KEY UPDATE level = VALUES(level);

UPDATE rcc_agents SET is_senior = 1 WHERE tenant_id = 1 AND id = 1;

INSERT INTO rcc_queue_members (tenant_id, queue_id, agent_id, penalty)
SELECT 1, q.id, a.id, 0
FROM rcc_queues q
CROSS JOIN rcc_agents a
WHERE q.tenant_id = 1 AND a.tenant_id = 1
ON DUPLICATE KEY UPDATE penalty = VALUES(penalty);
