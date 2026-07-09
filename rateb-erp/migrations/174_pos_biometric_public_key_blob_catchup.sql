-- Catch-up: WebAuthn attestation objects are binary; TEXT utf8mb4 rejects invalid UTF-8 (error 1366).
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

SET @tbl = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_webauthn_credentials');
SET @col = (SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_webauthn_credentials' AND COLUMN_NAME = 'public_key'
    LIMIT 1);
SET @sql = IF(@tbl > 0 AND @col IN ('text', 'varchar', 'char', 'tinytext'),
    'ALTER TABLE rateb_webauthn_credentials MODIFY COLUMN public_key MEDIUMBLOB NOT NULL',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
