-- RATEB ERP — login_barcode catch-up (idempotent; fixes user edit when 020 was skipped)
SET NAMES utf8mb4;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_users' AND COLUMN_NAME = 'login_barcode');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_users ADD COLUMN login_barcode VARCHAR(64) NULL DEFAULT NULL AFTER email, ADD UNIQUE KEY uq_users_login_barcode (login_barcode)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE rateb_users
SET login_barcode = CONCAT('ERP', LPAD(id, 6, '0'), 'USR')
WHERE status = 'active'
  AND (login_barcode IS NULL OR TRIM(login_barcode) = '');

SET @tbl = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_login_barcode_pairs');
SET @sql = IF(@tbl = 0,
    'CREATE TABLE rateb_login_barcode_pairs (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        token VARCHAR(64) NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT ''pending'',
        user_id INT UNSIGNED NULL,
        context_json TEXT NULL,
        expires_at DATETIME NOT NULL,
        created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_lbp_token (token),
        KEY idx_lbp_expires (expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
