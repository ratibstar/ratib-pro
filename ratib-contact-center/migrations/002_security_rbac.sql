-- RATIB Contact Center — 002 security & RBAC

CREATE TABLE IF NOT EXISTS rcc_users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    email VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    locale VARCHAR(8) NOT NULL DEFAULT 'ar',
    status ENUM('active','disabled','locked') NOT NULL DEFAULT 'active',
    last_login_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_rcc_users_tenant_email (tenant_id, email),
    KEY idx_rcc_users_tenant (tenant_id),
    CONSTRAINT fk_rcc_users_tenant FOREIGN KEY (tenant_id) REFERENCES rcc_tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rcc_roles (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NULL,
    slug VARCHAR(64) NOT NULL,
    name VARCHAR(128) NOT NULL,
    name_ar VARCHAR(128) NULL,
    is_system TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_rcc_roles_slug_tenant (slug, tenant_id),
    KEY idx_rcc_roles_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rcc_permissions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug VARCHAR(128) NOT NULL,
    name VARCHAR(255) NOT NULL,
    module VARCHAR(64) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_rcc_permissions_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rcc_role_permissions (
    role_id INT UNSIGNED NOT NULL,
    permission_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    CONSTRAINT fk_rcc_role_perm_role FOREIGN KEY (role_id) REFERENCES rcc_roles (id) ON DELETE CASCADE,
    CONSTRAINT fk_rcc_role_perm_perm FOREIGN KEY (permission_id) REFERENCES rcc_permissions (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rcc_user_roles (
    user_id INT UNSIGNED NOT NULL,
    role_id INT UNSIGNED NOT NULL,
    tenant_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (user_id, role_id),
    KEY idx_rcc_user_roles_tenant (tenant_id),
    CONSTRAINT fk_rcc_user_roles_user FOREIGN KEY (user_id) REFERENCES rcc_users (id) ON DELETE CASCADE,
    CONSTRAINT fk_rcc_user_roles_role FOREIGN KEY (role_id) REFERENCES rcc_roles (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rcc_sessions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    tenant_id INT UNSIGNED NOT NULL,
    agent_id INT UNSIGNED NULL,
    session_token CHAR(64) NOT NULL,
    csrf_token CHAR(64) NOT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(512) NULL,
    expires_at TIMESTAMP NOT NULL,
    last_activity_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_rcc_sessions_token (session_token),
    KEY idx_rcc_sessions_user (user_id),
    KEY idx_rcc_sessions_tenant (tenant_id),
    KEY idx_rcc_sessions_expires (expires_at),
    CONSTRAINT fk_rcc_sessions_user FOREIGN KEY (user_id) REFERENCES rcc_users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rcc_api_tokens (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NULL,
    agent_id INT UNSIGNED NULL,
    name VARCHAR(128) NOT NULL,
    token_hash CHAR(64) NOT NULL,
    scopes JSON NULL,
    expires_at TIMESTAMP NULL,
    last_used_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_rcc_api_tokens_hash (token_hash),
    KEY idx_rcc_api_tokens_tenant (tenant_id),
    CONSTRAINT fk_rcc_api_tokens_tenant FOREIGN KEY (tenant_id) REFERENCES rcc_tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rcc_audit_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NULL,
    action VARCHAR(128) NOT NULL,
    resource_type VARCHAR(64) NULL,
    resource_id INT UNSIGNED NULL,
    ip_address VARCHAR(45) NULL,
    payload JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_rcc_audit_tenant (tenant_id),
    KEY idx_rcc_audit_action (tenant_id, action),
    CONSTRAINT fk_rcc_audit_tenant FOREIGN KEY (tenant_id) REFERENCES rcc_tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO rcc_roles (id, tenant_id, slug, name, name_ar, is_system) VALUES
(1, NULL, 'admin', 'Administrator', 'مدير النظام', 1),
(2, NULL, 'supervisor', 'Supervisor', 'مشرف', 1),
(3, NULL, 'agent', 'Agent', 'وكيل', 1);

INSERT IGNORE INTO rcc_permissions (slug, name, module) VALUES
('rcc.access', 'Access Contact Center', 'core'),
('rcc.agent.desktop', 'Agent Desktop', 'agent'),
('rcc.supervisor.dashboard', 'Supervisor Dashboard', 'supervisor'),
('rcc.admin.settings', 'Admin Settings', 'admin'),
('rcc.calls.manage', 'Manage Calls', 'calls'),
('rcc.inbox.manage', 'Manage Inbox', 'inbox'),
('rcc.reports.view', 'View Reports', 'reports'),
('rcc.reports.export', 'Export Reports', 'reports');

INSERT IGNORE INTO rcc_role_permissions (role_id, permission_id)
SELECT 1, id FROM rcc_permissions;

INSERT IGNORE INTO rcc_role_permissions (role_id, permission_id)
SELECT 2, id FROM rcc_permissions WHERE slug IN ('rcc.access','rcc.agent.desktop','rcc.supervisor.dashboard','rcc.calls.manage','rcc.inbox.manage','rcc.reports.view','rcc.reports.export');

INSERT IGNORE INTO rcc_role_permissions (role_id, permission_id)
SELECT 3, id FROM rcc_permissions WHERE slug IN ('rcc.access','rcc.agent.desktop','rcc.calls.manage','rcc.inbox.manage');
