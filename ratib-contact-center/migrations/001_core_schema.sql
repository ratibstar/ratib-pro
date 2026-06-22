-- RATIB Contact Center — 001 core schema
-- MySQL 8+ | Safe to run once per environment (tracked in rcc_migration_log)

CREATE TABLE IF NOT EXISTS rcc_migration_log (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    migration VARCHAR(128) NOT NULL,
    batch INT UNSIGNED NOT NULL DEFAULT 1,
    applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_rcc_migration_log_name (migration)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rcc_tenants (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(64) NOT NULL,
    name VARCHAR(255) NOT NULL,
    name_ar VARCHAR(255) NULL,
    erp_company_id INT UNSIGNED NULL,
    locale VARCHAR(8) NOT NULL DEFAULT 'ar',
    timezone VARCHAR(64) NOT NULL DEFAULT 'Asia/Riyadh',
    status ENUM('active','suspended','archived') NOT NULL DEFAULT 'active',
    settings_json JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_rcc_tenants_code (code),
    KEY idx_rcc_tenants_erp (erp_company_id),
    KEY idx_rcc_tenants_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rcc_contact_companies (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    name_ar VARCHAR(255) NULL,
    phone VARCHAR(40) NULL,
    email VARCHAR(255) NULL,
    tier VARCHAR(32) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_rcc_contact_companies_tenant (tenant_id),
    CONSTRAINT fk_rcc_contact_companies_tenant FOREIGN KEY (tenant_id) REFERENCES rcc_tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rcc_contacts (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    company_id INT UNSIGNED NULL,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NULL,
    phone_primary VARCHAR(40) NULL,
    contact_type VARCHAR(32) NOT NULL DEFAULT 'standard',
    status VARCHAR(32) NOT NULL DEFAULT 'active',
    deleted_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_rcc_contacts_tenant (tenant_id),
    KEY idx_rcc_contacts_phone (tenant_id, phone_primary),
    KEY idx_rcc_contacts_email (tenant_id, email),
    CONSTRAINT fk_rcc_contacts_tenant FOREIGN KEY (tenant_id) REFERENCES rcc_tenants (id) ON DELETE CASCADE,
    CONSTRAINT fk_rcc_contacts_company FOREIGN KEY (company_id) REFERENCES rcc_contact_companies (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rcc_settings (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    group_key VARCHAR(64) NOT NULL,
    setting_key VARCHAR(128) NOT NULL,
    setting_value TEXT NULL,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_rcc_settings_tenant_key (tenant_id, group_key, setting_key),
    KEY idx_rcc_settings_tenant (tenant_id),
    CONSTRAINT fk_rcc_settings_tenant FOREIGN KEY (tenant_id) REFERENCES rcc_tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rcc_calls (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    uuid CHAR(36) NOT NULL,
    direction ENUM('inbound','outbound','internal') NOT NULL DEFAULT 'inbound',
    status VARCHAR(32) NOT NULL DEFAULT 'new',
    caller_number VARCHAR(40) NULL,
    callee_number VARCHAR(40) NULL,
    channel_id VARCHAR(128) NULL,
    queue_id INT UNSIGNED NULL,
    agent_id INT UNSIGNED NULL,
    conversation_id INT UNSIGNED NULL,
    priority_score DECIMAL(8,3) NULL,
    routing_reason VARCHAR(255) NULL,
    started_at TIMESTAMP(3) NULL,
    connected_at TIMESTAMP(3) NULL,
    ended_at TIMESTAMP(3) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_rcc_calls_uuid (uuid),
    KEY idx_rcc_calls_tenant (tenant_id),
    KEY idx_rcc_calls_tenant_status (tenant_id, status),
    KEY idx_rcc_calls_queue (tenant_id, queue_id, status),
    KEY idx_rcc_calls_agent (tenant_id, agent_id),
    KEY idx_rcc_calls_conversation (conversation_id),
    CONSTRAINT fk_rcc_calls_tenant FOREIGN KEY (tenant_id) REFERENCES rcc_tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rcc_erp_activity_log (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    erp_company_id INT UNSIGNED NULL,
    event_uuid CHAR(36) NOT NULL,
    activity_type VARCHAR(64) NOT NULL,
    reference_type VARCHAR(32) NULL,
    reference_id INT UNSIGNED NULL,
    summary VARCHAR(512) NOT NULL,
    payload JSON NULL,
    created_at TIMESTAMP(3) NOT NULL,
    PRIMARY KEY (id),
    KEY idx_rcc_erp_activity_tenant (tenant_id),
    KEY idx_rcc_erp_activity_uuid (event_uuid),
    KEY idx_rcc_erp_activity_type (tenant_id, activity_type),
    CONSTRAINT fk_rcc_erp_activity_tenant FOREIGN KEY (tenant_id) REFERENCES rcc_tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
