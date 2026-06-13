-- ERP barcode login — per-user badge + cross-device pairing
SET NAMES utf8mb4;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_users' AND COLUMN_NAME = 'login_barcode');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_users ADD COLUMN login_barcode VARCHAR(64) NULL DEFAULT NULL AFTER email, ADD UNIQUE KEY uq_users_login_barcode (login_barcode)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Backfill login barcodes for active users without one
UPDATE rateb_users
SET login_barcode = CONCAT('ERP', LPAD(id, 6, '0'), 'USR')
WHERE status = 'active'
  AND (login_barcode IS NULL OR TRIM(login_barcode) = '')
  AND id > 0;

CREATE TABLE IF NOT EXISTS rateb_login_barcode_pairs (
    token CHAR(32) NOT NULL PRIMARY KEY,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    context_json MEDIUMTEXT NULL,
    user_id INT UNSIGNED NULL,
    expires_at INT UNSIGNED NOT NULL,
    created_at INT UNSIGNED NOT NULL,
    KEY idx_rateb_barcode_pairs_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
