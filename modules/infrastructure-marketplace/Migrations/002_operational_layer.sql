-- Phase 2 operational layer. Reversible and isolated.
-- Uses pluralized table names requested for runtime operations.

CREATE TABLE IF NOT EXISTS `ratib_infra_catalog_items` (
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
  UNIQUE KEY `uniq_ratib_infra_catalog_items_sku_scope` (`tenant_id`, `sku`),
  KEY `idx_ratib_infra_catalog_items_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ratib_infra_provider_bindings` (
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
  UNIQUE KEY `uniq_ratib_infra_provider_bindings_scope` (`tenant_id`, `agency_id`, `role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ratib_infra_provisioning_jobs` (
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
  UNIQUE KEY `uniq_ratib_infra_provisioning_jobs_public_id` (`public_id`),
  KEY `idx_ratib_infra_provisioning_jobs_queue` (`status`, `available_at`),
  KEY `idx_ratib_infra_provisioning_jobs_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ratib_infra_job_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `job_id` BIGINT UNSIGNED NOT NULL,
  `level` VARCHAR(16) NOT NULL,
  `message` VARCHAR(1000) NOT NULL,
  `context_json` JSON DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ratib_infra_job_logs_job` (`job_id`),
  CONSTRAINT `fk_ratib_infra_job_logs_job`
      FOREIGN KEY (`job_id`) REFERENCES `ratib_infra_provisioning_jobs` (`id`)
      ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

