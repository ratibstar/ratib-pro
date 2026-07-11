-- Phase P2 — Enterprise Warm Offline Identity Hardening (additive).
-- Does not alter Offline Foundation / IndexedDB / SDK contracts.
-- Next after 193_business_intelligence_platform.sql

SET NAMES utf8mb4;

-- Device trust columns on existing registry
SET @tbl := 'rateb_offline_devices';
SET @db := DATABASE();

SET @sql := (
  SELECT IF(
    EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@tbl AND COLUMN_NAME='fingerprint'),
    'SELECT 1',
    'ALTER TABLE rateb_offline_devices ADD COLUMN fingerprint VARCHAR(128) NULL AFTER label'
  )
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (
  SELECT IF(
    EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@tbl AND COLUMN_NAME='nickname'),
    'SELECT 1',
    'ALTER TABLE rateb_offline_devices ADD COLUMN nickname VARCHAR(120) NULL AFTER fingerprint'
  )
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (
  SELECT IF(
    EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@tbl AND COLUMN_NAME='trust_status'),
    'SELECT 1',
    'ALTER TABLE rateb_offline_devices ADD COLUMN trust_status VARCHAR(32) NOT NULL DEFAULT ''trusted'' AFTER status'
  )
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (
  SELECT IF(
    EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@tbl AND COLUMN_NAME='last_online_at'),
    'SELECT 1',
    'ALTER TABLE rateb_offline_devices ADD COLUMN last_online_at DATETIME NULL AFTER last_seen_at'
  )
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (
  SELECT IF(
    EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@tbl AND COLUMN_NAME='last_replay_at'),
    'SELECT 1',
    'ALTER TABLE rateb_offline_devices ADD COLUMN last_replay_at DATETIME NULL AFTER last_online_at'
  )
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (
  SELECT IF(
    EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@tbl AND COLUMN_NAME='last_unlock_at'),
    'SELECT 1',
    'ALTER TABLE rateb_offline_devices ADD COLUMN last_unlock_at DATETIME NULL AFTER last_replay_at'
  )
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (
  SELECT IF(
    EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@tbl AND COLUMN_NAME='last_logout_at'),
    'SELECT 1',
    'ALTER TABLE rateb_offline_devices ADD COLUMN last_logout_at DATETIME NULL AFTER last_unlock_at'
  )
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (
  SELECT IF(
    EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@tbl AND COLUMN_NAME='identity_expires_at'),
    'SELECT 1',
    'ALTER TABLE rateb_offline_devices ADD COLUMN identity_expires_at INT UNSIGNED NULL AFTER last_logout_at'
  )
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (
  SELECT IF(
    EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@tbl AND COLUMN_NAME='identity_version'),
    'SELECT 1',
    'ALTER TABLE rateb_offline_devices ADD COLUMN identity_version INT UNSIGNED NOT NULL DEFAULT 1 AFTER identity_expires_at'
  )
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (
  SELECT IF(
    EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@tbl AND COLUMN_NAME='identity_jti'),
    'SELECT 1',
    'ALTER TABLE rateb_offline_devices ADD COLUMN identity_jti VARCHAR(64) NULL AFTER identity_version'
  )
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (
  SELECT IF(
    EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@tbl AND COLUMN_NAME='force_logout_at'),
    'SELECT 1',
    'ALTER TABLE rateb_offline_devices ADD COLUMN force_logout_at DATETIME NULL AFTER identity_jti'
  )
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (
  SELECT IF(
    EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@tbl AND COLUMN_NAME='vault_integrity'),
    'SELECT 1',
    'ALTER TABLE rateb_offline_devices ADD COLUMN vault_integrity VARCHAR(128) NULL AFTER force_logout_at'
  )
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

CREATE TABLE IF NOT EXISTS rateb_offline_identity_audit (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  company_id INT UNSIGNED NOT NULL,
  branch_id INT UNSIGNED NULL,
  user_id INT UNSIGNED NULL,
  device_id VARCHAR(64) NULL,
  event_type VARCHAR(64) NOT NULL,
  detail_json TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_offline_id_audit_company (company_id, created_at),
  KEY idx_offline_id_audit_device (company_id, device_id),
  KEY idx_offline_id_audit_event (event_type),
  CONSTRAINT fk_offline_id_audit_company FOREIGN KEY (company_id) REFERENCES rateb_companies (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_offline_identity_nonces (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  company_id INT UNSIGNED NOT NULL,
  device_id VARCHAR(64) NOT NULL,
  jti VARCHAR(64) NOT NULL,
  identity_version INT UNSIGNED NOT NULL DEFAULT 1,
  status VARCHAR(16) NOT NULL DEFAULT 'active',
  issued_at INT UNSIGNED NOT NULL,
  expires_at INT UNSIGNED NOT NULL,
  invalidated_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_offline_identity_jti (company_id, jti),
  KEY idx_offline_identity_nonce_device (company_id, device_id, status),
  CONSTRAINT fk_offline_identity_nonce_company FOREIGN KEY (company_id) REFERENCES rateb_companies (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO rateb_permissions (slug, name, module, created_at)
SELECT 'offline.devices.view', 'View offline devices', 'offline', NOW()
WHERE NOT EXISTS (SELECT 1 FROM rateb_permissions WHERE slug = 'offline.devices.view');

INSERT INTO rateb_permissions (slug, name, module, created_at)
SELECT 'offline.devices.manage', 'Manage offline devices', 'offline', NOW()
WHERE NOT EXISTS (SELECT 1 FROM rateb_permissions WHERE slug = 'offline.devices.manage');

INSERT IGNORE INTO rateb_role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM rateb_roles r
CROSS JOIN rateb_permissions p
WHERE r.slug IN ('company_admin', 'admin', 'owner')
  AND p.slug IN ('offline.devices.view', 'offline.devices.manage');
