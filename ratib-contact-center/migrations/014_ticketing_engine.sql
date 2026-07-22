-- RATEB Contact Center — 014 advanced ticketing (Phase 10B)

CREATE TABLE IF NOT EXISTS rcc_ticket_categories (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    code VARCHAR(64) NOT NULL,
    name VARCHAR(128) NOT NULL,
    name_ar VARCHAR(128) NULL,
    parent_id INT UNSIGNED NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uq_rcc_ticket_cat_code (tenant_id, code),
    KEY idx_rcc_ticket_cat_tenant (tenant_id),
    CONSTRAINT fk_rcc_ticket_cat_tenant FOREIGN KEY (tenant_id) REFERENCES rcc_tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rcc_ticket_priorities (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    code VARCHAR(32) NOT NULL,
    name VARCHAR(64) NOT NULL,
    name_ar VARCHAR(64) NULL,
    weight INT NOT NULL DEFAULT 50,
    color VARCHAR(16) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_rcc_ticket_pri_code (tenant_id, code),
    CONSTRAINT fk_rcc_ticket_pri_tenant FOREIGN KEY (tenant_id) REFERENCES rcc_tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rcc_ticket_statuses (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    code VARCHAR(32) NOT NULL,
    name VARCHAR(64) NOT NULL,
    name_ar VARCHAR(64) NULL,
    is_closed TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_rcc_ticket_status_code (tenant_id, code),
    CONSTRAINT fk_rcc_ticket_status_tenant FOREIGN KEY (tenant_id) REFERENCES rcc_tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 003_queue_ticket_stub used BIGINT id; 014 FK tables require INT UNSIGNED to match 009 schema.
ALTER TABLE rcc_tickets MODIFY id INT UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE rcc_tickets ADD COLUMN contact_id INT UNSIGNED NULL;

ALTER TABLE rcc_tickets ADD COLUMN conversation_id INT UNSIGNED NULL;

ALTER TABLE rcc_tickets ADD COLUMN source VARCHAR(64) NULL DEFAULT 'manual';

ALTER TABLE rcc_tickets ADD COLUMN auto_created TINYINT(1) NOT NULL DEFAULT 0;

ALTER TABLE rcc_tickets ADD COLUMN resolution_due TIMESTAMP NULL;

ALTER TABLE rcc_tickets ADD COLUMN updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP;

ALTER TABLE rcc_tickets ADD COLUMN category_id INT UNSIGNED NULL;

ALTER TABLE rcc_tickets ADD COLUMN priority_id INT UNSIGNED NULL;

ALTER TABLE rcc_tickets ADD COLUMN status_id INT UNSIGNED NULL;

ALTER TABLE rcc_tickets ADD COLUMN assigned_agent_id INT UNSIGNED NULL;

ALTER TABLE rcc_tickets ADD COLUMN assigned_by_user_id INT UNSIGNED NULL;

ALTER TABLE rcc_tickets ADD COLUMN parent_ticket_id INT UNSIGNED NULL;

ALTER TABLE rcc_tickets ADD COLUMN merged_into_id INT UNSIGNED NULL;

ALTER TABLE rcc_tickets ADD COLUMN first_response_at TIMESTAMP NULL;

ALTER TABLE rcc_tickets ADD COLUMN resolved_at TIMESTAMP NULL;

ALTER TABLE rcc_tickets ADD COLUMN closed_at TIMESTAMP NULL;

ALTER TABLE rcc_tickets ADD KEY idx_rcc_tickets_assignee (tenant_id, assigned_agent_id, status);

CREATE TABLE IF NOT EXISTS rcc_ticket_comments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    ticket_id INT UNSIGNED NOT NULL,
    author_user_id INT UNSIGNED NULL,
    author_agent_id INT UNSIGNED NULL,
    body TEXT NOT NULL,
    is_internal TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_rcc_ticket_comments_ticket (tenant_id, ticket_id, created_at),
    CONSTRAINT fk_rcc_ticket_comments_tenant FOREIGN KEY (tenant_id) REFERENCES rcc_tenants (id) ON DELETE CASCADE,
    CONSTRAINT fk_rcc_ticket_comments_ticket FOREIGN KEY (ticket_id) REFERENCES rcc_tickets (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rcc_ticket_attachments (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    ticket_id INT UNSIGNED NOT NULL,
    comment_id BIGINT UNSIGNED NULL,
    file_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(128) NOT NULL,
    file_size INT UNSIGNED NOT NULL DEFAULT 0,
    storage_path VARCHAR(512) NOT NULL,
    uploaded_by_user_id INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_rcc_ticket_attach_ticket (tenant_id, ticket_id),
    CONSTRAINT fk_rcc_ticket_attach_tenant FOREIGN KEY (tenant_id) REFERENCES rcc_tenants (id) ON DELETE CASCADE,
    CONSTRAINT fk_rcc_ticket_attach_ticket FOREIGN KEY (ticket_id) REFERENCES rcc_tickets (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rcc_ticket_sla (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    ticket_id INT UNSIGNED NOT NULL,
    first_response_due TIMESTAMP NULL,
    resolution_due TIMESTAMP NULL,
    first_response_met TINYINT(1) NULL,
    resolution_met TINYINT(1) NULL,
    breached_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_rcc_ticket_sla_ticket (tenant_id, ticket_id),
    CONSTRAINT fk_rcc_ticket_sla_tenant FOREIGN KEY (tenant_id) REFERENCES rcc_tenants (id) ON DELETE CASCADE,
    CONSTRAINT fk_rcc_ticket_sla_ticket FOREIGN KEY (ticket_id) REFERENCES rcc_tickets (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rcc_ticket_watchers (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    ticket_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NULL,
    agent_id INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_rcc_ticket_watcher (tenant_id, ticket_id, user_id, agent_id),
    KEY idx_rcc_ticket_watchers_ticket (tenant_id, ticket_id),
    CONSTRAINT fk_rcc_ticket_watchers_tenant FOREIGN KEY (tenant_id) REFERENCES rcc_tenants (id) ON DELETE CASCADE,
    CONSTRAINT fk_rcc_ticket_watchers_ticket FOREIGN KEY (ticket_id) REFERENCES rcc_tickets (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO rcc_ticket_priorities (tenant_id, code, name, name_ar, weight, color)
SELECT t.id, 'low', 'Low', 'منخفض', 25, '#94a3b8' FROM rcc_tenants t WHERE t.status = 'active';
INSERT IGNORE INTO rcc_ticket_priorities (tenant_id, code, name, name_ar, weight, color)
SELECT t.id, 'normal', 'Normal', 'عادي', 50, '#3b82f6' FROM rcc_tenants t WHERE t.status = 'active';
INSERT IGNORE INTO rcc_ticket_priorities (tenant_id, code, name, name_ar, weight, color)
SELECT t.id, 'high', 'High', 'عالي', 75, '#f59e0b' FROM rcc_tenants t WHERE t.status = 'active';
INSERT IGNORE INTO rcc_ticket_priorities (tenant_id, code, name, name_ar, weight, color)
SELECT t.id, 'urgent', 'Urgent', 'عاجل', 100, '#ef4444' FROM rcc_tenants t WHERE t.status = 'active';

INSERT IGNORE INTO rcc_ticket_statuses (tenant_id, code, name, name_ar, is_closed, sort_order)
SELECT t.id, 'open', 'Open', 'مفتوح', 0, 10 FROM rcc_tenants t WHERE t.status = 'active';
INSERT IGNORE INTO rcc_ticket_statuses (tenant_id, code, name, name_ar, is_closed, sort_order)
SELECT t.id, 'in_progress', 'In Progress', 'قيد المعالجة', 0, 20 FROM rcc_tenants t WHERE t.status = 'active';
INSERT IGNORE INTO rcc_ticket_statuses (tenant_id, code, name, name_ar, is_closed, sort_order)
SELECT t.id, 'pending', 'Pending', 'معلق', 0, 30 FROM rcc_tenants t WHERE t.status = 'active';
INSERT IGNORE INTO rcc_ticket_statuses (tenant_id, code, name, name_ar, is_closed, sort_order)
SELECT t.id, 'resolved', 'Resolved', 'تم الحل', 0, 40 FROM rcc_tenants t WHERE t.status = 'active';
INSERT IGNORE INTO rcc_ticket_statuses (tenant_id, code, name, name_ar, is_closed, sort_order)
SELECT t.id, 'closed', 'Closed', 'مغلق', 1, 50 FROM rcc_tenants t WHERE t.status = 'active';

INSERT IGNORE INTO rcc_permissions (slug, name, module) VALUES
('rcc.tickets.view', 'View Tickets', 'tickets'),
('rcc.tickets.create', 'Create Tickets', 'tickets'),
('rcc.tickets.assign', 'Assign Tickets', 'tickets'),
('rcc.tickets.escalate', 'Escalate Tickets', 'tickets'),
('rcc.tickets.merge', 'Merge Tickets', 'tickets'),
('rcc.tickets.admin', 'Ticket Administration', 'tickets');

INSERT IGNORE INTO rcc_role_permissions (role_id, permission_id)
SELECT 1, id FROM rcc_permissions WHERE slug LIKE 'rcc.tickets.%';

INSERT IGNORE INTO rcc_role_permissions (role_id, permission_id)
SELECT 2, id FROM rcc_permissions WHERE slug IN (
    'rcc.tickets.view', 'rcc.tickets.create', 'rcc.tickets.assign', 'rcc.tickets.escalate'
);

INSERT IGNORE INTO rcc_role_permissions (role_id, permission_id)
SELECT 3, id FROM rcc_permissions WHERE slug IN ('rcc.tickets.view', 'rcc.tickets.create');
