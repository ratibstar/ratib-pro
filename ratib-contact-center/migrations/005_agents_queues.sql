-- RATEB Contact Center — 005 agents & queues

CREATE TABLE IF NOT EXISTS rcc_agents (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NULL,
    extension VARCHAR(32) NOT NULL,
    display_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NULL,
    is_senior TINYINT(1) NOT NULL DEFAULT 0,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_rcc_agents_tenant_ext (tenant_id, extension),
    KEY idx_rcc_agents_tenant (tenant_id),
    KEY idx_rcc_agents_user (user_id),
    CONSTRAINT fk_rcc_agents_tenant FOREIGN KEY (tenant_id) REFERENCES rcc_tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rcc_queues (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    code VARCHAR(64) NOT NULL,
    name VARCHAR(255) NOT NULL,
    name_ar VARCHAR(255) NULL,
    sla_target_seconds INT UNSIGNED NOT NULL DEFAULT 300,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    strategy VARCHAR(32) NOT NULL DEFAULT 'rrmemory',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_rcc_queues_tenant_code (tenant_id, code),
    KEY idx_rcc_queues_tenant (tenant_id),
    CONSTRAINT fk_rcc_queues_tenant FOREIGN KEY (tenant_id) REFERENCES rcc_tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rcc_queue_members (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    queue_id INT UNSIGNED NOT NULL,
    agent_id INT UNSIGNED NOT NULL,
    penalty INT NOT NULL DEFAULT 0,
    is_paused TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_rcc_queue_members (tenant_id, queue_id, agent_id),
    KEY idx_rcc_queue_members_tenant (tenant_id),
    CONSTRAINT fk_rcc_queue_members_tenant FOREIGN KEY (tenant_id) REFERENCES rcc_tenants (id) ON DELETE CASCADE,
    CONSTRAINT fk_rcc_queue_members_queue FOREIGN KEY (queue_id) REFERENCES rcc_queues (id) ON DELETE CASCADE,
    CONSTRAINT fk_rcc_queue_members_agent FOREIGN KEY (agent_id) REFERENCES rcc_agents (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
