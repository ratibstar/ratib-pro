-- Global EPC reference foundation. Does not alter existing Automotive or inventory tables.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS rateb_auto_ref_markets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(8) NOT NULL,
    name_en VARCHAR(80) NOT NULL,
    name_ar VARCHAR(80) NOT NULL,
    parent_market_id INT UNSIGNED NULL,
    UNIQUE KEY uq_auto_ref_market_code (code),
    CONSTRAINT fk_auto_ref_market_parent FOREIGN KEY (parent_market_id) REFERENCES rateb_auto_ref_markets(id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_auto_ref_orgs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    org_role VARCHAR(32) NOT NULL,
    parent_org_id INT UNSIGNED NULL,
    home_market_id INT UNSIGNED NOT NULL,
    name_en VARCHAR(150) NOT NULL,
    name_ar VARCHAR(150) NOT NULL,
    normalized_name VARCHAR(150) NOT NULL,
    status VARCHAR(16) NOT NULL DEFAULT 'active',
    UNIQUE KEY uq_auto_ref_org (org_role, normalized_name, home_market_id),
    CONSTRAINT fk_auto_ref_org_parent FOREIGN KEY (parent_org_id) REFERENCES rateb_auto_ref_orgs(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_auto_ref_org_market FOREIGN KEY (home_market_id) REFERENCES rateb_auto_ref_markets(id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_auto_ref_authorizations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    org_id INT UNSIGNED NOT NULL,
    territory_market_id INT UNSIGNED NOT NULL,
    source_note VARCHAR(150) NOT NULL,
    verified_on DATE NOT NULL,
    effective_on DATE NOT NULL,
    expires_on DATE NOT NULL,
    status VARCHAR(16) NOT NULL DEFAULT 'active',
    KEY idx_auto_ref_auth (org_id, territory_market_id, effective_on, expires_on),
    CONSTRAINT fk_auto_ref_auth_org FOREIGN KEY (org_id) REFERENCES rateb_auto_ref_orgs(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_auto_ref_auth_market FOREIGN KEY (territory_market_id) REFERENCES rateb_auto_ref_markets(id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_auto_ref_vehicle_brands (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    vehicle_mfr_org_id INT UNSIGNED NOT NULL,
    name_en VARCHAR(120) NOT NULL,
    name_ar VARCHAR(120) NOT NULL,
    normalized_name VARCHAR(120) NOT NULL,
    UNIQUE KEY uq_auto_ref_vehicle_brand (vehicle_mfr_org_id, normalized_name),
    CONSTRAINT fk_auto_ref_vehicle_brand_org FOREIGN KEY (vehicle_mfr_org_id) REFERENCES rateb_auto_ref_orgs(id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
