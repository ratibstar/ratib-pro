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

