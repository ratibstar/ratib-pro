-- Gulf data-management foundation. Does not alter Phase 1–5 tables.
-- Seeds only the six GCC market names. Does not insert vehicle or parts coverage.
--
-- Rollback, after an approved backup, and only when no later rows depend on these tables:
-- DROP TABLE IF EXISTS rateb_auto_ref_gulf_import_rows;
-- DROP TABLE IF EXISTS rateb_auto_ref_gulf_import_reports;
-- DROP TABLE IF EXISTS rateb_auto_ref_coverage_claims;
-- DROP TABLE IF EXISTS rateb_auto_ref_data_rights;
-- DROP TABLE IF EXISTS rateb_auto_ref_provider_markets;
-- DROP TABLE IF EXISTS rateb_auto_ref_data_providers;
-- Market rows SA, AE, KW, QA, BH, OM stay if any organization or authorization references them.
-- Delete a seeded market only after confirming it has no child rows.

SET NAMES utf8mb4;

INSERT INTO rateb_auto_ref_markets (code, name_en, name_ar, parent_market_id)
SELECT 'SA', 'Saudi Arabia', 'السعودية', NULL FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM rateb_auto_ref_markets WHERE code = 'SA');
INSERT INTO rateb_auto_ref_markets (code, name_en, name_ar, parent_market_id)
SELECT 'AE', 'United Arab Emirates', 'الإمارات', NULL FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM rateb_auto_ref_markets WHERE code = 'AE');
INSERT INTO rateb_auto_ref_markets (code, name_en, name_ar, parent_market_id)
SELECT 'KW', 'Kuwait', 'الكويت', NULL FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM rateb_auto_ref_markets WHERE code = 'KW');
INSERT INTO rateb_auto_ref_markets (code, name_en, name_ar, parent_market_id)
SELECT 'QA', 'Qatar', 'قطر', NULL FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM rateb_auto_ref_markets WHERE code = 'QA');
INSERT INTO rateb_auto_ref_markets (code, name_en, name_ar, parent_market_id)
SELECT 'BH', 'Bahrain', 'البحرين', NULL FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM rateb_auto_ref_markets WHERE code = 'BH');
INSERT INTO rateb_auto_ref_markets (code, name_en, name_ar, parent_market_id)
SELECT 'OM', 'Oman', 'عُمان', NULL FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM rateb_auto_ref_markets WHERE code = 'OM');

CREATE TABLE IF NOT EXISTS rateb_auto_ref_data_providers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(40) NOT NULL,
    provider_type VARCHAR(24) NOT NULL,
    name_en VARCHAR(150) NOT NULL,
    name_ar VARCHAR(150) NOT NULL,
    website VARCHAR(200) NULL,
    contact VARCHAR(200) NULL,
    categories VARCHAR(200) NOT NULL,
    source_note VARCHAR(250) NOT NULL,
    status VARCHAR(16) NOT NULL DEFAULT 'pending',
    review_note VARCHAR(300) NULL,
    org_id INT UNSIGNED NULL,
    UNIQUE KEY uq_auto_ref_provider_code (code),
    CONSTRAINT chk_auto_ref_provider_type CHECK (provider_type IN ('data_provider', 'manufacturer', 'data_owner')),
    CONSTRAINT chk_auto_ref_provider_status CHECK (status IN ('active', 'pending', 'suspended', 'rejected')),
    CONSTRAINT fk_auto_ref_provider_org FOREIGN KEY (org_id) REFERENCES rateb_auto_ref_orgs(id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_auto_ref_provider_markets (
    provider_id INT UNSIGNED NOT NULL,
    market_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (provider_id, market_id),
    CONSTRAINT fk_auto_ref_pm_provider FOREIGN KEY (provider_id) REFERENCES rateb_auto_ref_data_providers(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_auto_ref_pm_market FOREIGN KEY (market_id) REFERENCES rateb_auto_ref_markets(id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_auto_ref_data_rights (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    provider_id INT UNSIGNED NOT NULL,
    evidence VARCHAR(300) NOT NULL,
    store_local VARCHAR(8) NOT NULL DEFAULT 'unknown',
    process_internal VARCHAR(8) NOT NULL DEFAULT 'unknown',
    commercial_display VARCHAR(8) NOT NULL DEFAULT 'unknown',
    saas_multi VARCHAR(8) NOT NULL DEFAULT 'unknown',
    redistribute VARCHAR(8) NOT NULL DEFAULT 'unknown',
    access_mode VARCHAR(8) NOT NULL DEFAULT 'unknown',
    diagrams VARCHAR(8) NOT NULL DEFAULT 'unknown',
    territory_codes VARCHAR(40) NOT NULL DEFAULT '',
    effective_on DATE NULL,
    expires_on DATE NULL,
    terminated_on DATE NULL,
    CONSTRAINT chk_auto_ref_rights_store CHECK (store_local IN ('unknown', 'yes', 'no')),
    CONSTRAINT chk_auto_ref_rights_process CHECK (process_internal IN ('unknown', 'yes', 'no')),
    CONSTRAINT chk_auto_ref_rights_display CHECK (commercial_display IN ('unknown', 'yes', 'no')),
    CONSTRAINT chk_auto_ref_rights_saas CHECK (saas_multi IN ('unknown', 'yes', 'no')),
    CONSTRAINT chk_auto_ref_rights_redist CHECK (redistribute IN ('unknown', 'yes', 'no')),
    CONSTRAINT chk_auto_ref_rights_access CHECK (access_mode IN ('unknown', 'api', 'bulk', 'both')),
    CONSTRAINT chk_auto_ref_rights_diagrams CHECK (diagrams IN ('unknown', 'yes', 'no')),
    KEY idx_auto_ref_rights_provider (provider_id),
    CONSTRAINT fk_auto_ref_rights_provider FOREIGN KEY (provider_id) REFERENCES rateb_auto_ref_data_providers(id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_auto_ref_coverage_claims (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    market_id INT UNSIGNED NOT NULL,
    provider_id INT UNSIGNED NOT NULL,
    brand_name VARCHAR(80) NULL,
    model_name VARCHAR(80) NULL,
    year_from SMALLINT UNSIGNED NULL,
    year_to SMALLINT UNSIGNED NULL,
    vehicle_category VARCHAR(24) NULL,
    data_category VARCHAR(24) NOT NULL,
    verification VARCHAR(16) NOT NULL,
    license_status VARCHAR(16) NOT NULL,
    CONSTRAINT chk_auto_ref_cov_category CHECK (data_category IN (
        'vehicle_identity', 'oem_parts', 'diagrams', 'fitment', 'aftermarket', 'vin_decoding'
    )),
    CONSTRAINT chk_auto_ref_cov_verify CHECK (verification IN ('verified', 'partial', 'unverified', 'unavailable')),
    CONSTRAINT chk_auto_ref_cov_license CHECK (license_status IN ('sufficient', 'unknown', 'insufficient', 'expired')),
    KEY idx_auto_ref_cov_market (market_id, data_category, verification),
    CONSTRAINT fk_auto_ref_cov_market FOREIGN KEY (market_id) REFERENCES rateb_auto_ref_markets(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_auto_ref_cov_provider FOREIGN KEY (provider_id) REFERENCES rateb_auto_ref_data_providers(id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_auto_ref_gulf_import_reports (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    provider_id INT UNSIGNED NOT NULL,
    rights_id INT UNSIGNED NOT NULL,
    file_sha256 CHAR(64) NOT NULL,
    status VARCHAR(16) NOT NULL,
    inserted_count INT UNSIGNED NOT NULL,
    skipped_count INT UNSIGNED NOT NULL,
    rejected_count INT UNSIGNED NOT NULL,
    failed_count INT UNSIGNED NOT NULL,
    UNIQUE KEY uq_auto_ref_gulf_import_hash (file_sha256),
    CONSTRAINT fk_auto_ref_gulf_import_provider FOREIGN KEY (provider_id) REFERENCES rateb_auto_ref_data_providers(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_auto_ref_gulf_import_rights FOREIGN KEY (rights_id) REFERENCES rateb_auto_ref_data_rights(id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_auto_ref_gulf_import_rows (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    report_id INT UNSIGNED NOT NULL,
    identity_key VARCHAR(80) NOT NULL,
    label VARCHAR(150) NOT NULL,
    UNIQUE KEY uq_auto_ref_gulf_import_identity (report_id, identity_key),
    CONSTRAINT fk_auto_ref_gulf_import_row FOREIGN KEY (report_id) REFERENCES rateb_auto_ref_gulf_import_reports(id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
