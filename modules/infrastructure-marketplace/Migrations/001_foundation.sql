-- Infrastructure marketplace foundation (optional). Execute only after DB review.
-- Charset/collation aligned with typical Ratib installs; adjust per environment.

CREATE TABLE IF NOT EXISTS `ratib_infra_catalog_item` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED DEFAULT NULL COMMENT 'NULL = globally visible template rows',
  `sku` VARCHAR(128) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `billing_code` VARCHAR(64) DEFAULT NULL,
  `metadata_json` JSON DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_ratib_infra_catalog_sku_scope` (`tenant_id`, `sku`),
  KEY `idx_ratib_infra_catalog_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ratib_infra_provisioning_job` (
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
  UNIQUE KEY `uniq_ratib_infra_job_public_id` (`public_id`),
  KEY `idx_ratib_infra_job_tenant` (`tenant_id`),
  KEY `idx_ratib_infra_job_agency` (`agency_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ratib_infra_provider_binding` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED DEFAULT NULL COMMENT 'NULL = platform default resolver row',
  `role` ENUM('hosting','registrar','dns','ssl') NOT NULL,
  `implementation_class` VARCHAR(255) NOT NULL COMMENT 'FQCN wired via ProviderRegistry env JSON',
  `options_ref` VARCHAR(255) DEFAULT NULL COMMENT 'pointer to KMS/secret ref; never plaintext',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_ratib_infra_binding_scope` (`tenant_id`, `role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
