-- 008_provider_secrets_and_events.sql
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
