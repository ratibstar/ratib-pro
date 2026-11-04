-- US NHTSA and EPA reference tables. No company_id. Does not alter Phase 1-4 tables.
--
-- Rollback procedure. Do not run it as part of this migration.
-- These tables have no foreign keys to VehiclesDB or Phase 1-2 tables.
-- Execution order:
-- DROP TABLE IF EXISTS rateb_auto_ref_us_epa_vehicles;
-- DROP TABLE IF EXISTS rateb_auto_ref_us_nhtsa_manufacturers;
-- DROP TABLE IF EXISTS rateb_auto_ref_us_nhtsa_models;
-- DROP TABLE IF EXISTS rateb_auto_ref_us_nhtsa_makes;
-- DROP TABLE IF EXISTS rateb_auto_ref_us_releases;

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS rateb_auto_ref_us_releases (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    provider_code VARCHAR(20) NOT NULL,
    dataset_code VARCHAR(40) NOT NULL,
    version_label VARCHAR(80) NOT NULL,
    retrieved_on DATE NOT NULL,
    attribution TEXT NOT NULL,
    license_evidence VARCHAR(255) NOT NULL,
    file_sha256 CHAR(64) NOT NULL,
    market_scope VARCHAR(40) NOT NULL DEFAULT 'US',
    UNIQUE KEY uq_auto_ref_us_release (provider_code, dataset_code, file_sha256)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_auto_ref_us_nhtsa_makes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    release_id INT UNSIGNED NOT NULL,
    make_id INT UNSIGNED NOT NULL,
    make_name VARCHAR(160) NOT NULL,
    UNIQUE KEY uq_auto_ref_us_nhtsa_make (release_id, make_id),
    KEY idx_auto_ref_us_nhtsa_make_name (release_id, make_name),
    CONSTRAINT fk_auto_ref_us_nhtsa_make_release FOREIGN KEY (release_id) REFERENCES rateb_auto_ref_us_releases(id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_auto_ref_us_nhtsa_models (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    release_id INT UNSIGNED NOT NULL,
    make_id INT UNSIGNED NOT NULL,
    make_name VARCHAR(160) NOT NULL,
    model_id INT UNSIGNED NOT NULL,
    model_name VARCHAR(160) NOT NULL,
    UNIQUE KEY uq_auto_ref_us_nhtsa_model (release_id, make_id, model_id),
    KEY idx_auto_ref_us_nhtsa_model_name (release_id, make_name, model_name),
    CONSTRAINT fk_auto_ref_us_nhtsa_model_release FOREIGN KEY (release_id) REFERENCES rateb_auto_ref_us_releases(id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_auto_ref_us_nhtsa_manufacturers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    release_id INT UNSIGNED NOT NULL,
    mfr_id INT UNSIGNED NOT NULL,
    mfr_name VARCHAR(200) NOT NULL,
    common_name VARCHAR(160) NULL,
    country_name VARCHAR(120) NOT NULL,
    vehicle_types TEXT NOT NULL,
    UNIQUE KEY uq_auto_ref_us_nhtsa_mfr (release_id, mfr_id),
    KEY idx_auto_ref_us_nhtsa_mfr_name (release_id, mfr_name),
    CONSTRAINT fk_auto_ref_us_nhtsa_mfr_release FOREIGN KEY (release_id) REFERENCES rateb_auto_ref_us_releases(id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_auto_ref_us_epa_vehicles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    release_id INT UNSIGNED NOT NULL,
    epa_id INT UNSIGNED NOT NULL,
    model_year SMALLINT UNSIGNED NOT NULL,
    make_name VARCHAR(80) NOT NULL,
    model_name VARCHAR(160) NOT NULL,
    base_model VARCHAR(80) NULL,
    vclass VARCHAR(80) NOT NULL,
    fuel_type1 VARCHAR(80) NOT NULL,
    cylinders VARCHAR(8) NULL,
    displ VARCHAR(16) NULL,
    trany VARCHAR(80) NULL,
    drive VARCHAR(80) NULL,
    city_mpg VARCHAR(16) NOT NULL,
    highway_mpg VARCHAR(16) NOT NULL,
    comb_mpg VARCHAR(16) NOT NULL,
    eng_id VARCHAR(16) NULL,
    line_sha256 CHAR(64) NOT NULL,
    UNIQUE KEY uq_auto_ref_us_epa (release_id, epa_id),
    KEY idx_auto_ref_us_epa_browse (release_id, model_year, make_name, model_name),
    KEY idx_auto_ref_us_epa_fuel (release_id, fuel_type1, vclass),
    CONSTRAINT fk_auto_ref_us_epa_release FOREIGN KEY (release_id) REFERENCES rateb_auto_ref_us_releases(id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
