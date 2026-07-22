-- RATEB Contact Center — 012 supervisor & workforce management (Phase 9)

CREATE TABLE IF NOT EXISTS rcc_wfm_shifts (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    code VARCHAR(64) NOT NULL,
    name VARCHAR(255) NOT NULL,
    name_ar VARCHAR(255) NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    break_minutes INT UNSIGNED NOT NULL DEFAULT 30,
    grace_minutes INT UNSIGNED NOT NULL DEFAULT 5,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_rcc_wfm_shift_code (tenant_id, code),
    KEY idx_rcc_wfm_shift_tenant (tenant_id),
    CONSTRAINT fk_rcc_wfm_shift_tenant FOREIGN KEY (tenant_id) REFERENCES rcc_tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rcc_wfm_shift_assignments (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    shift_id INT UNSIGNED NOT NULL,
    agent_id INT UNSIGNED NOT NULL,
    work_date DATE NOT NULL,
    notes VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_rcc_wfm_assign (tenant_id, agent_id, work_date),
    KEY idx_rcc_wfm_assign_shift (tenant_id, shift_id, work_date),
    CONSTRAINT fk_rcc_wfm_assign_tenant FOREIGN KEY (tenant_id) REFERENCES rcc_tenants (id) ON DELETE CASCADE,
    CONSTRAINT fk_rcc_wfm_assign_shift FOREIGN KEY (shift_id) REFERENCES rcc_wfm_shifts (id) ON DELETE CASCADE,
    CONSTRAINT fk_rcc_wfm_assign_agent FOREIGN KEY (agent_id) REFERENCES rcc_agents (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rcc_wfm_attendance (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    agent_id INT UNSIGNED NOT NULL,
    work_date DATE NOT NULL,
    scheduled_shift_id INT UNSIGNED NULL,
    clock_in TIMESTAMP NULL,
    clock_out TIMESTAMP NULL,
    status ENUM('scheduled','present','late','absent','left_early') NOT NULL DEFAULT 'scheduled',
    adherence_pct DECIMAL(5,2) NULL,
    notes VARCHAR(255) NULL,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_rcc_wfm_attendance (tenant_id, agent_id, work_date),
    KEY idx_rcc_wfm_attendance_date (tenant_id, work_date),
    CONSTRAINT fk_rcc_wfm_att_tenant FOREIGN KEY (tenant_id) REFERENCES rcc_tenants (id) ON DELETE CASCADE,
    CONSTRAINT fk_rcc_wfm_att_agent FOREIGN KEY (agent_id) REFERENCES rcc_agents (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rcc_wfm_breaks (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    agent_id INT UNSIGNED NOT NULL,
    break_type ENUM('lunch','prayer','meeting','personal','other') NOT NULL DEFAULT 'other',
    started_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ended_at TIMESTAMP NULL,
    reason VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_rcc_wfm_breaks_agent (tenant_id, agent_id, started_at),
    KEY idx_rcc_wfm_breaks_open (tenant_id, agent_id, ended_at),
    CONSTRAINT fk_rcc_wfm_break_tenant FOREIGN KEY (tenant_id) REFERENCES rcc_tenants (id) ON DELETE CASCADE,
    CONSTRAINT fk_rcc_wfm_break_agent FOREIGN KEY (agent_id) REFERENCES rcc_agents (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rcc_supervisor_alerts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    alert_type VARCHAR(64) NOT NULL,
    severity ENUM('info','warning','critical') NOT NULL DEFAULT 'warning',
    title VARCHAR(255) NOT NULL,
    title_ar VARCHAR(255) NULL,
    message TEXT NULL,
    message_ar TEXT NULL,
    source_event VARCHAR(64) NULL,
    queue_id INT UNSIGNED NULL,
    agent_id INT UNSIGNED NULL,
    payload_json JSON NULL,
    acknowledged_by_user_id INT UNSIGNED NULL,
    acknowledged_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_rcc_sup_alerts_tenant (tenant_id, created_at),
    KEY idx_rcc_sup_alerts_open (tenant_id, acknowledged_at),
    CONSTRAINT fk_rcc_sup_alerts_tenant FOREIGN KEY (tenant_id) REFERENCES rcc_tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rcc_supervisor_alert_rules (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    rule_key VARCHAR(64) NOT NULL,
    is_enabled TINYINT(1) NOT NULL DEFAULT 1,
    config_json JSON NULL,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_rcc_sup_rule (tenant_id, rule_key),
    CONSTRAINT fk_rcc_sup_rules_tenant FOREIGN KEY (tenant_id) REFERENCES rcc_tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO rcc_permissions (slug, name, module) VALUES
('rcc.supervisor.view', 'View Supervisor Suite', 'supervisor'),
('rcc.supervisor.wallboard', 'Live Wallboard', 'supervisor'),
('rcc.supervisor.queues', 'Queue Monitor', 'supervisor'),
('rcc.supervisor.agents', 'Agent Monitor', 'supervisor'),
('rcc.supervisor.sla', 'SLA Dashboard', 'supervisor'),
('rcc.supervisor.wfm', 'Workforce Management', 'supervisor'),
('rcc.supervisor.shifts', 'Shift Planner', 'supervisor'),
('rcc.supervisor.attendance', 'Attendance Tracking', 'supervisor'),
('rcc.supervisor.breaks', 'Break Management', 'supervisor'),
('rcc.supervisor.alerts', 'Supervisor Alerts', 'supervisor'),
('rcc.supervisor.reports', 'Supervisor Reports', 'supervisor');

INSERT IGNORE INTO rcc_role_permissions (role_id, permission_id)
SELECT 1, id FROM rcc_permissions WHERE slug LIKE 'rcc.supervisor.%';

INSERT IGNORE INTO rcc_role_permissions (role_id, permission_id)
SELECT 2, id FROM rcc_permissions WHERE slug IN (
    'rcc.supervisor.view', 'rcc.supervisor.wallboard', 'rcc.supervisor.queues',
    'rcc.supervisor.agents', 'rcc.supervisor.sla', 'rcc.supervisor.wfm',
    'rcc.supervisor.shifts', 'rcc.supervisor.attendance', 'rcc.supervisor.breaks',
    'rcc.supervisor.alerts', 'rcc.supervisor.reports', 'rcc.supervisor.dashboard',
    'rcc.reports.view'
);

INSERT IGNORE INTO rcc_supervisor_alert_rules (tenant_id, rule_key, is_enabled, config_json)
SELECT t.id, 'sla_red', 1, '{"min_severity":"critical"}'
FROM rcc_tenants t WHERE t.status = 'active';

INSERT IGNORE INTO rcc_supervisor_alert_rules (tenant_id, rule_key, is_enabled, config_json)
SELECT t.id, 'queue_no_agents', 1, '{"min_waiting":1}'
FROM rcc_tenants t WHERE t.status = 'active';

INSERT IGNORE INTO rcc_supervisor_alert_rules (tenant_id, rule_key, is_enabled, config_json)
SELECT t.id, 'agent_long_break', 1, '{"max_break_minutes":30}'
FROM rcc_tenants t WHERE t.status = 'active';
