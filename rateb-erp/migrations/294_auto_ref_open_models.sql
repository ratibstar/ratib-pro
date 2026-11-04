-- Source-native open vehicle references. Does not alter Phase 1 or Phase 2 tables.
-- No company_id. Arabic names and years stay optional.
--
-- Rollback procedure. Do not run it as part of this migration.
-- 294 has no foreign keys to 291, 292, or 293. It can roll back independently.
-- If later rows were added by an approved import, take a backup first and export
-- those rows before dropping anything. This rollback removes only these five tables.
-- Execution order, children first:
-- DROP TABLE IF EXISTS rateb_auto_ref_open_model_markets;
-- DROP TABLE IF EXISTS rateb_auto_ref_open_model_licenses;
-- DROP TABLE IF EXISTS rateb_auto_ref_open_model_sources;
-- DROP TABLE IF EXISTS rateb_auto_ref_open_models;
-- DROP TABLE IF EXISTS rateb_auto_ref_open_releases;

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS rateb_auto_ref_open_releases (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    provider_code VARCHAR(40) NOT NULL,
    version_label VARCHAR(40) NOT NULL,
    retrieved_on DATE NOT NULL,
    attribution TEXT NOT NULL,
    file_sha256 CHAR(64) NULL,
    UNIQUE KEY uq_auto_ref_open_release (provider_code, version_label)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_auto_ref_open_models (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    release_id INT UNSIGNED NOT NULL,
    source_record_id VARCHAR(160) NOT NULL,
    kind VARCHAR(24) NOT NULL,
    make_slug VARCHAR(80) NOT NULL,
    model_slug VARCHAR(80) NOT NULL,
    name_en VARCHAR(160) NOT NULL,
    name_ar VARCHAR(160) NULL,
    record_sha256 CHAR(64) NOT NULL,
    CONSTRAINT chk_auto_ref_open_kind CHECK (kind IN ('car', 'motorcycle', 'moped', 'van', 'truck', 'bus')),
    UNIQUE KEY uq_auto_ref_open_model (release_id, kind, source_record_id),
    CONSTRAINT fk_auto_ref_open_model_release FOREIGN KEY (release_id) REFERENCES rateb_auto_ref_open_releases(id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_auto_ref_open_model_sources (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    model_id INT UNSIGNED NOT NULL,
    origin_code VARCHAR(40) NOT NULL,
    license_code VARCHAR(80) NOT NULL,
    UNIQUE KEY uq_auto_ref_open_source (model_id, origin_code),
    CONSTRAINT fk_auto_ref_open_source_model FOREIGN KEY (model_id) REFERENCES rateb_auto_ref_open_models(id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_auto_ref_open_model_licenses (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    model_id INT UNSIGNED NOT NULL,
    license_code VARCHAR(80) NOT NULL,
    UNIQUE KEY uq_auto_ref_open_license (model_id, license_code),
    CONSTRAINT fk_auto_ref_open_license_model FOREIGN KEY (model_id) REFERENCES rateb_auto_ref_open_models(id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_auto_ref_open_model_markets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    model_id INT UNSIGNED NOT NULL,
    market_code VARCHAR(8) NOT NULL,
    origin_code VARCHAR(40) NOT NULL,
    evidence VARCHAR(40) NOT NULL,
    UNIQUE KEY uq_auto_ref_open_market (model_id, market_code, origin_code),
    CONSTRAINT fk_auto_ref_open_market_model FOREIGN KEY (model_id) REFERENCES rateb_auto_ref_open_models(id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
