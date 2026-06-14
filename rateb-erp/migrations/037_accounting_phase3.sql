-- RATEB ERP — Accounting phase 3: cost centers, period lock, ZATCA foundation
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS rateb_cost_centers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    code VARCHAR(20) NOT NULL,
    name VARCHAR(200) NOT NULL,
    name_ar VARCHAR(200) NULL,
    parent_id INT UNSIGNED NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_cc_company_code (company_id, code),
    INDEX idx_cc_company (company_id),
    CONSTRAINT fk_cc_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE rateb_journal_lines
    ADD COLUMN cost_center_id INT UNSIGNED NULL AFTER account_id,
    ADD INDEX idx_jl_cost_center (cost_center_id);

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
    CONSTRAINT fk_ctp_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE rateb_invoices
    ADD COLUMN zatca_uuid VARCHAR(36) NULL AFTER issued_at,
    ADD COLUMN zatca_qr TEXT NULL AFTER zatca_uuid,
    ADD COLUMN zatca_status ENUM('not_applicable','draft','cleared') NOT NULL DEFAULT 'not_applicable' AFTER zatca_qr;
