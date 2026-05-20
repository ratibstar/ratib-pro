-- 005_provider_activation_marketplace.sql
-- Phase 4 provider activation + marketplace domain search tables.
-- Run on CONTROL_PANEL_DB_NAME (e.g. outratib_control_panel_db). Safe to re-run.

CREATE TABLE IF NOT EXISTS `ratib_infra_provider_activations` (
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
  UNIQUE KEY `uniq_ratib_infra_provider_activation_scope` (`provider_type`, `provider_code`, `tenant_id`, `agency_id`),
  KEY `idx_ratib_infra_provider_activation_enabled` (`provider_type`, `is_enabled`, `priority_weight`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ratib_infra_domain_search_cache` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `cache_key` VARCHAR(128) NOT NULL,
  `result_json` JSON NOT NULL,
  `expires_at` TIMESTAMP NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_ratib_infra_domain_search_cache_key` (`cache_key`),
  KEY `idx_ratib_infra_domain_search_cache_expiry` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ratib_infra_domain_search_rate` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `scope_key` VARCHAR(120) NOT NULL,
  `minute_bucket` CHAR(12) NOT NULL,
  `request_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_ratib_infra_domain_search_rate_scope_bucket` (`scope_key`, `minute_bucket`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
