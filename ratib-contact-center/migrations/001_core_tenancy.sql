-- RATIB Contact Center — core tenancy (required for IVR FK)
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

CREATE TABLE IF NOT EXISTS rcc_tenants (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    erp_company_id INT UNSIGNED NOT NULL,
    name VARCHAR(200) NOT NULL,
    slug VARCHAR(120) NOT NULL,
    default_locale ENUM('en','ar') NOT NULL DEFAULT 'ar',
    status ENUM('active','suspended','pending') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_rcc_tenant_erp (erp_company_id),
    UNIQUE KEY uk_rcc_tenant_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rcc_migration_log (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    migration VARCHAR(120) NOT NULL,
    batch INT UNSIGNED NOT NULL DEFAULT 1,
    executed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_rcc_migration (migration)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rcc_calls (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    uuid CHAR(36) NOT NULL,
    direction ENUM('inbound','outbound') NOT NULL DEFAULT 'inbound',
    status VARCHAR(40) NOT NULL DEFAULT 'ringing',
    caller_number VARCHAR(40) NOT NULL,
    callee_number VARCHAR(40) NOT NULL,
    channel_id VARCHAR(120) NULL,
    queue_id INT UNSIGNED NULL,
    started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ended_at DATETIME NULL,
    UNIQUE KEY uk_rcc_call_uuid (uuid),
    INDEX idx_rcc_call_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
