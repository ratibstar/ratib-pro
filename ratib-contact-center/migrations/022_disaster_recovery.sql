-- RATIB Contact Center — 022 disaster recovery & HA monitoring (Phase 11)

CREATE TABLE IF NOT EXISTS rcc_backups (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NULL,
    backup_type ENUM('full','incremental','tenant','schema') NOT NULL DEFAULT 'full',
    storage_path VARCHAR(512) NOT NULL,
    file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
    checksum_sha256 CHAR(64) NULL,
    status ENUM('running','completed','failed','expired') NOT NULL DEFAULT 'running',
    started_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    expires_at TIMESTAMP NULL,
    metadata_json JSON NULL,
    created_by_user_id INT UNSIGNED NULL,
    PRIMARY KEY (id),
    KEY idx_rcc_backups_tenant (tenant_id, started_at),
    KEY idx_rcc_backups_status (status, expires_at),
    CONSTRAINT fk_rcc_backups_tenant FOREIGN KEY (tenant_id) REFERENCES rcc_tenants (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rcc_backup_verifications (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    backup_id INT UNSIGNED NOT NULL,
    status ENUM('pending','passed','failed') NOT NULL DEFAULT 'pending',
    tables_checked INT UNSIGNED NOT NULL DEFAULT 0,
    row_count BIGINT UNSIGNED NULL,
    error_message TEXT NULL,
    verified_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_rcc_backup_verify_backup (backup_id),
    CONSTRAINT fk_rcc_backup_verify_backup FOREIGN KEY (backup_id) REFERENCES rcc_backups (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rcc_restore_jobs (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NULL,
    backup_id INT UNSIGNED NOT NULL,
    restore_point TIMESTAMP NULL,
    status ENUM('queued','running','completed','failed','cancelled') NOT NULL DEFAULT 'queued',
    initiated_by_user_id INT UNSIGNED NULL,
    approved_by_user_id INT UNSIGNED NULL,
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    error_message TEXT NULL,
    metadata_json JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_rcc_restore_jobs_status (status, created_at),
    CONSTRAINT fk_rcc_restore_jobs_backup FOREIGN KEY (backup_id) REFERENCES rcc_backups (id),
    CONSTRAINT fk_rcc_restore_jobs_tenant FOREIGN KEY (tenant_id) REFERENCES rcc_tenants (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rcc_failover_events (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    cluster_id INT UNSIGNED NULL,
    event_type ENUM('health_check','failover','failback','drill') NOT NULL,
    from_node VARCHAR(128) NULL,
    to_node VARCHAR(128) NULL,
    status ENUM('started','completed','failed') NOT NULL DEFAULT 'started',
    details_json JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    PRIMARY KEY (id),
    KEY idx_rcc_failover_cluster (cluster_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rcc_pbx_clusters (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NULL,
    name VARCHAR(128) NOT NULL,
    region VARCHAR(64) NOT NULL DEFAULT 'ksa-central',
    status ENUM('active','degraded','failover','maintenance') NOT NULL DEFAULT 'active',
    primary_node_id INT UNSIGNED NULL,
    ha_mode ENUM('active_passive','active_active') NOT NULL DEFAULT 'active_passive',
    metadata_json JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_rcc_pbx_clusters_tenant (tenant_id),
    CONSTRAINT fk_rcc_pbx_clusters_tenant FOREIGN KEY (tenant_id) REFERENCES rcc_tenants (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rcc_pbx_cluster_nodes (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    cluster_id INT UNSIGNED NOT NULL,
    node_name VARCHAR(128) NOT NULL,
    host VARCHAR(255) NOT NULL,
    ami_port INT UNSIGNED NOT NULL DEFAULT 5038,
    sip_port INT UNSIGNED NOT NULL DEFAULT 5060,
    role ENUM('primary','secondary','standby') NOT NULL DEFAULT 'standby',
    health_status ENUM('up','down','unknown') NOT NULL DEFAULT 'unknown',
    last_health_at TIMESTAMP NULL,
    weight INT UNSIGNED NOT NULL DEFAULT 100,
    metadata_json JSON NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_rcc_pbx_cluster_node (cluster_id, node_name),
    CONSTRAINT fk_rcc_pbx_cluster_nodes_cluster FOREIGN KEY (cluster_id) REFERENCES rcc_pbx_clusters (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE rcc_failover_events
    ADD CONSTRAINT fk_rcc_failover_cluster FOREIGN KEY (cluster_id) REFERENCES rcc_pbx_clusters (id) ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS rcc_monitors (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NULL,
    monitor_type ENUM('uptime','pbx','database','queue','sip') NOT NULL,
    name VARCHAR(128) NOT NULL,
    target VARCHAR(512) NOT NULL,
    interval_seconds INT UNSIGNED NOT NULL DEFAULT 60,
    timeout_seconds INT UNSIGNED NOT NULL DEFAULT 10,
    is_enabled TINYINT(1) NOT NULL DEFAULT 1,
    alert_threshold INT UNSIGNED NOT NULL DEFAULT 3,
    metadata_json JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_rcc_monitors_type (monitor_type, is_enabled),
    CONSTRAINT fk_rcc_monitors_tenant FOREIGN KEY (tenant_id) REFERENCES rcc_tenants (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rcc_monitor_checks (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    monitor_id INT UNSIGNED NOT NULL,
    status ENUM('up','down','degraded') NOT NULL,
    response_ms INT UNSIGNED NULL,
    message VARCHAR(512) NULL,
    checked_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_rcc_monitor_checks_monitor (monitor_id, checked_at),
    CONSTRAINT fk_rcc_monitor_checks_monitor FOREIGN KEY (monitor_id) REFERENCES rcc_monitors (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO rcc_monitors (tenant_id, monitor_type, name, target, interval_seconds) VALUES
(NULL, 'uptime', 'RCC API Health', '/api/v1/health.php', 60),
(NULL, 'database', 'RCC MySQL', 'mysql:rcc', 60),
(NULL, 'pbx', 'AMI Gateway', 'ami:5038', 30),
(NULL, 'queue', 'Default Queue', 'queue:default', 30),
(NULL, 'sip', 'SIP Registrar', 'sip:5060', 60);

INSERT IGNORE INTO rcc_permissions (slug, name, module) VALUES
('rcc.backup.view', 'View Backups', 'dr'),
('rcc.backup.manage', 'Manage Backups', 'dr'),
('rcc.backup.restore', 'Restore Backups', 'dr'),
('rcc.monitoring.view', 'View Monitoring', 'dr'),
('rcc.monitoring.manage', 'Manage Monitoring', 'dr'),
('rcc.pbx.cluster', 'Manage PBX Clusters', 'dr');

INSERT IGNORE INTO rcc_role_permissions (role_id, permission_id)
SELECT 1, id FROM rcc_permissions WHERE slug LIKE 'rcc.backup.%' OR slug LIKE 'rcc.monitoring.%' OR slug = 'rcc.pbx.cluster';

INSERT IGNORE INTO rcc_role_permissions (role_id, permission_id)
SELECT 2, id FROM rcc_permissions WHERE slug IN ('rcc.backup.view', 'rcc.monitoring.view');
