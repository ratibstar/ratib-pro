-- =============================================================================
-- Infrastructure marketplace — full migration bundle for ONE database
-- Target (example): admin_control_panel_db
-- =============================================================================
--
-- To remove duplicate rateb_infra_* from the main app DB after moving here:
--   007_drop_rateb_infra_from_admin_out.sql (edit USE if your main DB name differs).
--
-- (Optional legacy) ALL_for_admin_out.sql — same DDL on admin_out; prefer control DB only + 007 drop on main.
--
-- HOW TO RUN (phpMyAdmin or mysql CLI):
--   1. Select database `admin_control_panel_db` (or change USE below).
--   2. Import / execute this entire file once.
--
-- PHP (DatabaseConnectionFactory): rateb_infra_* use CONTROL_PANEL_DB_NAME by default
-- (admin_control_panel_db unless overridden). Optional override: RATEB_INFRA_DB_NAME
-- or RATEB_INFRA_DB_DSN for a different schema / credentials.
--
-- ORDER: 001 → 002 → 003 → 004 → 005 → 006 (do not reorder).
-- =============================================================================

USE `admin_control_panel_db`;

-- -----------------------------------------------------------------------------
-- 001_foundation.sql
-- -----------------------------------------------------------------------------
-- Infrastructure marketplace foundation (optional). Execute only after DB review.
-- Charset/collation aligned with typical RATEB installs; adjust per environment.

CREATE TABLE IF NOT EXISTS `rateb_infra_catalog_item` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED DEFAULT NULL COMMENT 'NULL = globally visible template rows',
  `sku` VARCHAR(128) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `billing_code` VARCHAR(64) DEFAULT NULL,
  `metadata_json` JSON DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_rateb_infra_catalog_sku_scope` (`tenant_id`, `sku`),
  KEY `idx_rateb_infra_catalog_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rateb_infra_provisioning_job` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `public_id` CHAR(36) NOT NULL,
  `tenant_id` BIGINT UNSIGNED DEFAULT NULL,
  `agency_id` BIGINT UNSIGNED DEFAULT NULL,
  `correlation_id` VARCHAR(64) DEFAULT NULL,
  `state` VARCHAR(32) NOT NULL DEFAULT 'queued',
  `steps_json` JSON NOT NULL,
  `payload_snapshot_json` JSON DEFAULT NULL,
  `last_error` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_rateb_infra_job_public_id` (`public_id`),
  KEY `idx_rateb_infra_job_tenant` (`tenant_id`),
  KEY `idx_rateb_infra_job_agency` (`agency_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rateb_infra_provider_binding` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED DEFAULT NULL COMMENT 'NULL = platform default resolver row',
  `role` ENUM('hosting','registrar','dns','ssl') NOT NULL,
  `implementation_class` VARCHAR(255) NOT NULL COMMENT 'FQCN wired via ProviderRegistry env JSON',
  `options_ref` VARCHAR(255) DEFAULT NULL COMMENT 'pointer to KMS/secret ref; never plaintext',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_rateb_infra_binding_scope` (`tenant_id`, `role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 002_operational_layer.sql
-- -----------------------------------------------------------------------------
-- Phase 2 operational layer. Reversible and isolated.
-- Uses pluralized table names requested for runtime operations.

CREATE TABLE IF NOT EXISTS `rateb_infra_catalog_items` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED DEFAULT NULL,
  `sku` VARCHAR(128) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `billing_code` VARCHAR(64) DEFAULT NULL,
  `visibility_scope` VARCHAR(32) NOT NULL DEFAULT 'tenant',
  `metadata_json` JSON DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_rateb_infra_catalog_items_sku_scope` (`tenant_id`, `sku`),
  KEY `idx_rateb_infra_catalog_items_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rateb_infra_provider_bindings` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED DEFAULT NULL,
  `agency_id` BIGINT UNSIGNED DEFAULT NULL,
  `role` ENUM('hosting','registrar','dns','ssl') NOT NULL,
  `implementation_class` VARCHAR(255) NOT NULL,
  `options_ref` VARCHAR(255) DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_rateb_infra_provider_bindings_scope` (`tenant_id`, `agency_id`, `role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rateb_infra_provisioning_jobs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `public_id` CHAR(36) NOT NULL,
  `tenant_id` BIGINT UNSIGNED DEFAULT NULL,
  `agency_id` BIGINT UNSIGNED DEFAULT NULL,
  `correlation_id` VARCHAR(64) DEFAULT NULL,
  `status` VARCHAR(32) NOT NULL DEFAULT 'QUEUED',
  `attempts` INT UNSIGNED NOT NULL DEFAULT 0,
  `max_attempts` INT UNSIGNED NOT NULL DEFAULT 5,
  `reconcile_required` TINYINT(1) NOT NULL DEFAULT 0,
  `available_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `locked_at` TIMESTAMP NULL DEFAULT NULL,
  `processed_at` TIMESTAMP NULL DEFAULT NULL,
  `steps_json` JSON NOT NULL,
  `payload_snapshot_json` JSON DEFAULT NULL,
  `last_error` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_rateb_infra_provisioning_jobs_public_id` (`public_id`),
  KEY `idx_rateb_infra_provisioning_jobs_queue` (`status`, `available_at`),
  KEY `idx_rateb_infra_provisioning_jobs_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rateb_infra_job_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `job_id` BIGINT UNSIGNED NOT NULL,
  `level` VARCHAR(16) NOT NULL,
  `message` VARCHAR(1000) NOT NULL,
  `context_json` JSON DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_rateb_infra_job_logs_job` (`job_id`),
  CONSTRAINT `fk_rateb_infra_job_logs_job`
      FOREIGN KEY (`job_id`) REFERENCES `rateb_infra_provisioning_jobs` (`id`)
      ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 003_execution_layer.sql
-- -----------------------------------------------------------------------------
-- Phase 3 execution layer additions (isolated + reversible).

CREATE TABLE IF NOT EXISTS `rateb_infra_worker_heartbeats` (
  `worker_name` VARCHAR(128) NOT NULL,
  `heartbeat_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `memory_bytes` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`worker_name`),
  KEY `idx_rateb_infra_worker_heartbeats_heartbeat` (`heartbeat_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rateb_infra_audit_entries` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `action_type` VARCHAR(64) NOT NULL,
  `actor_id` VARCHAR(128) NOT NULL,
  `tenant_id` BIGINT UNSIGNED DEFAULT NULL,
  `payload_json` JSON NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_rateb_infra_audit_action` (`action_type`),
  KEY `idx_rateb_infra_audit_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rateb_infra_secret_refs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `scope_key` VARCHAR(128) NOT NULL,
  `secret_key` VARCHAR(128) NOT NULL,
  `encrypted_value` TEXT NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_rateb_infra_secret_scope_key` (`scope_key`, `secret_key`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 004_state_machine_normalization.sql
-- -----------------------------------------------------------------------------
-- Phase 3 state-machine normalization.
-- Converts lowercase/legacy status values to strict uppercase lifecycle values.

UPDATE `rateb_infra_provisioning_jobs` SET `status` = 'QUEUED' WHERE UPPER(`status`) IN ('QUEUED', 'PENDING');
UPDATE `rateb_infra_provisioning_jobs` SET `status` = 'RUNNING' WHERE UPPER(`status`) IN ('RUNNING', 'PROCESSING');
UPDATE `rateb_infra_provisioning_jobs` SET `status` = 'RETRYING' WHERE UPPER(`status`) IN ('RETRYING', 'RETRY_SCHEDULED');
UPDATE `rateb_infra_provisioning_jobs` SET `status` = 'COMPLETED' WHERE UPPER(`status`) = 'COMPLETED';
UPDATE `rateb_infra_provisioning_jobs` SET `status` = 'DEAD_LETTER' WHERE UPPER(`status`) IN ('DEAD_LETTER', 'DEADLETTER');
UPDATE `rateb_infra_provisioning_jobs` SET `status` = 'FAILED' WHERE UPPER(`status`) = 'FAILED';
UPDATE `rateb_infra_provisioning_jobs` SET `status` = 'RECONCILING' WHERE UPPER(`status`) = 'RECONCILING';
UPDATE `rateb_infra_provisioning_jobs` SET `status` = 'CANCELLED' WHERE UPPER(`status`) = 'CANCELLED';
UPDATE `rateb_infra_provisioning_jobs` SET `status` = 'WAITING_EXTERNAL' WHERE UPPER(`status`) IN ('WAITING_EXTERNAL', 'WAITING');

-- -----------------------------------------------------------------------------
-- 005_provider_activation_marketplace.sql
-- -----------------------------------------------------------------------------
-- Phase 4 provider activation + marketplace exposure tables.

CREATE TABLE IF NOT EXISTS `rateb_infra_provider_activations` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `provider_type` ENUM('hosting','registrar','dns','ssl') NOT NULL,
  `provider_code` VARCHAR(64) NOT NULL,
  `provider_class` VARCHAR(255) NOT NULL,
  `tenant_id` BIGINT UNSIGNED DEFAULT NULL,
  `agency_id` BIGINT UNSIGNED DEFAULT NULL,
  `priority_weight` INT NOT NULL DEFAULT 100,
  `is_enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `updated_by` VARCHAR(120) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_rateb_infra_provider_activation_scope` (`provider_type`, `provider_code`, `tenant_id`, `agency_id`),
  KEY `idx_rateb_infra_provider_activation_enabled` (`provider_type`, `is_enabled`, `priority_weight`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rateb_infra_orders` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `public_id` CHAR(36) NOT NULL,
  `tenant_id` BIGINT UNSIGNED DEFAULT NULL,
  `agency_id` BIGINT UNSIGNED DEFAULT NULL,
  `sku` VARCHAR(128) NOT NULL,
  `status` VARCHAR(32) NOT NULL DEFAULT 'PENDING',
  `idempotency_key` VARCHAR(128) NOT NULL,
  `currency` VARCHAR(12) NOT NULL DEFAULT 'USD',
  `amount` DECIMAL(14,4) NOT NULL DEFAULT 0,
  `payload_json` JSON DEFAULT NULL,
  `provisioning_job_public_id` CHAR(36) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_rateb_infra_orders_public_id` (`public_id`),
  UNIQUE KEY `uniq_rateb_infra_orders_idempotency` (`idempotency_key`),
  KEY `idx_rateb_infra_orders_scope` (`tenant_id`, `agency_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rateb_infra_domain_search_cache` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `cache_key` VARCHAR(128) NOT NULL,
  `result_json` JSON NOT NULL,
  `expires_at` TIMESTAMP NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_rateb_infra_domain_search_cache_key` (`cache_key`),
  KEY `idx_rateb_infra_domain_search_cache_expiry` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rateb_infra_domain_search_rate` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `scope_key` VARCHAR(120) NOT NULL,
  `minute_bucket` CHAR(12) NOT NULL,
  `request_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_rateb_infra_domain_search_rate_scope_bucket` (`scope_key`, `minute_bucket`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rateb_infra_services` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `public_id` CHAR(36) NOT NULL,
  `tenant_id` BIGINT UNSIGNED DEFAULT NULL,
  `agency_id` BIGINT UNSIGNED DEFAULT NULL,
  `order_public_id` CHAR(36) DEFAULT NULL,
  `service_type` VARCHAR(32) NOT NULL,
  `resource_reference` VARCHAR(255) DEFAULT NULL,
  `lifecycle_state` VARCHAR(32) NOT NULL DEFAULT 'QUEUED',
  `renewal_at` TIMESTAMP NULL DEFAULT NULL,
  `expires_at` TIMESTAMP NULL DEFAULT NULL,
  `metadata_json` JSON DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_rateb_infra_services_public_id` (`public_id`),
  KEY `idx_rateb_infra_services_scope` (`tenant_id`, `agency_id`, `lifecycle_state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 006_release_safety.sql
-- -----------------------------------------------------------------------------
-- Phase 5.2 release safety and deployment audit structures.

CREATE TABLE IF NOT EXISTS `rateb_infra_deployment_audits` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `release_id` VARCHAR(128) NOT NULL,
  `environment` VARCHAR(32) NOT NULL DEFAULT 'production',
  `prelaunch_status` VARCHAR(16) NOT NULL,
  `prelaunch_score` INT NOT NULL DEFAULT 0,
  `matrix_json` JSON NOT NULL,
  `snapshot_json` JSON NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_rateb_infra_deployment_audits_release` (`release_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 008_provider_secrets_and_events.sql
-- -----------------------------------------------------------------------------
-- Additive provider security + observability layer.

CREATE TABLE IF NOT EXISTS `rateb_infra_provider_secrets` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `provider_scope` VARCHAR(64) NOT NULL,
  `secret_key` VARCHAR(128) NOT NULL,
  `encrypted_value` LONGTEXT NOT NULL,
  `tenant_id` BIGINT UNSIGNED DEFAULT NULL,
  `agency_id` BIGINT UNSIGNED DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `updated_by` VARCHAR(120) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_rateb_infra_provider_secret_scope` (`provider_scope`, `secret_key`, `tenant_id`, `agency_id`),
  KEY `idx_rateb_infra_provider_secret_active` (`provider_scope`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rateb_infra_provider_events` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `provider_type` VARCHAR(32) NOT NULL,
  `provider_code` VARCHAR(64) NOT NULL,
  `event_name` VARCHAR(64) NOT NULL,
  `request_id` VARCHAR(80) DEFAULT NULL,
  `operation_name` VARCHAR(80) DEFAULT NULL,
  `status` VARCHAR(32) NOT NULL DEFAULT 'unknown',
  `duration_ms` INT UNSIGNED DEFAULT NULL,
  `retry_count` INT UNSIGNED DEFAULT NULL,
  `tenant_id` BIGINT UNSIGNED DEFAULT NULL,
  `agency_id` BIGINT UNSIGNED DEFAULT NULL,
  `error_message` VARCHAR(500) DEFAULT NULL,
  `payload_json` JSON DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_rateb_infra_provider_events_provider` (`provider_type`, `provider_code`, `event_name`),
  KEY `idx_rateb_infra_provider_events_created` (`created_at`),
  KEY `idx_rateb_infra_provider_events_scope` (`tenant_id`, `agency_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- End of bundle. Optional: verify
--   SHOW TABLES LIKE 'rateb_infra_%';
-- =============================================================================
