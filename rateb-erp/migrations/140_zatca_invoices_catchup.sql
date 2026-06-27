-- ZATCA invoice columns and tax profile catch-up (140)
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS rateb_company_tax_profiles (
    company_id INT UNSIGNED NOT NULL PRIMARY KEY,
    vat_number VARCHAR(15) NULL,
    cr_number VARCHAR(20) NULL,
    legal_name_ar VARCHAR(255) NULL,
    legal_name_en VARCHAR(255) NULL,
    street VARCHAR(200) NULL,
    building_no VARCHAR(20) NULL,
    city VARCHAR(100) NULL,
    postal_code VARCHAR(10) NULL,
    zatca_enabled TINYINT(1) NOT NULL DEFAULT 0,
    zatca_environment ENUM('sandbox','production') NOT NULL DEFAULT 'sandbox',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_ctp_company_140 FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_invoices' AND COLUMN_NAME = 'zatca_uuid');
SET @has_issued = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_invoices' AND COLUMN_NAME = 'issued_at');
SET @sql = IF(@col = 0,
    IF(@has_issued > 0,
        'ALTER TABLE rateb_invoices ADD COLUMN zatca_uuid VARCHAR(36) NULL AFTER issued_at',
        'ALTER TABLE rateb_invoices ADD COLUMN zatca_uuid VARCHAR(36) NULL'),
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_invoices' AND COLUMN_NAME = 'zatca_qr');
SET @has_uuid = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_invoices' AND COLUMN_NAME = 'zatca_uuid');
SET @sql = IF(@col = 0,
    IF(@has_uuid > 0,
        'ALTER TABLE rateb_invoices ADD COLUMN zatca_qr TEXT NULL AFTER zatca_uuid',
        'ALTER TABLE rateb_invoices ADD COLUMN zatca_qr TEXT NULL'),
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_invoices' AND COLUMN_NAME = 'zatca_status');
SET @has_qr = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_invoices' AND COLUMN_NAME = 'zatca_qr');
SET @sql = IF(@col = 0,
    IF(@has_qr > 0,
        'ALTER TABLE rateb_invoices ADD COLUMN zatca_status ENUM(''not_applicable'',''draft'',''cleared'') NOT NULL DEFAULT ''not_applicable'' AFTER zatca_qr',
        'ALTER TABLE rateb_invoices ADD COLUMN zatca_status ENUM(''not_applicable'',''draft'',''cleared'') NOT NULL DEFAULT ''not_applicable'''),
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
