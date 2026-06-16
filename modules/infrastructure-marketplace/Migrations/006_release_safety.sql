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

