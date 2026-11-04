-- Global EPC reference foundation. Does not alter existing Automotive or inventory tables.
-- Rollback, in order, only after an approved backup:
-- DROP TRIGGER IF EXISTS trg_auto_ref_org_bu;
-- DROP TRIGGER IF EXISTS trg_auto_ref_auth_bu;
-- DROP TRIGGER IF EXISTS trg_auto_ref_auth_bi;
-- DROP TRIGGER IF EXISTS trg_auto_ref_brand_bu;
-- DROP TRIGGER IF EXISTS trg_auto_ref_brand_bi;
-- DROP TABLE IF EXISTS rateb_auto_ref_vehicle_brands;
-- DROP TABLE IF EXISTS rateb_auto_ref_authorizations;
-- DROP TABLE IF EXISTS rateb_auto_ref_orgs;
-- DROP TABLE IF EXISTS rateb_auto_ref_markets;

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
    CONSTRAINT chk_auto_ref_org_role CHECK (org_role IN (
        'vehicle_manufacturer', 'parts_manufacturer', 'brand_owner',
        'distributor', 'authorized_dealer', 'branch'
    )),
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
    CONSTRAINT chk_auto_ref_auth_dates CHECK (expires_on > effective_on),
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

ALTER TABLE rateb_auto_ref_orgs DROP CONSTRAINT IF EXISTS chk_auto_ref_org_role;
ALTER TABLE rateb_auto_ref_orgs ADD CONSTRAINT chk_auto_ref_org_role CHECK (org_role IN (
    'vehicle_manufacturer', 'parts_manufacturer', 'brand_owner',
    'distributor', 'authorized_dealer', 'branch'
));

ALTER TABLE rateb_auto_ref_authorizations DROP CONSTRAINT IF EXISTS chk_auto_ref_auth_dates;
ALTER TABLE rateb_auto_ref_authorizations ADD CONSTRAINT chk_auto_ref_auth_dates CHECK (expires_on > effective_on);

DROP TRIGGER IF EXISTS trg_auto_ref_brand_bi;
CREATE TRIGGER trg_auto_ref_brand_bi
BEFORE INSERT ON rateb_auto_ref_vehicle_brands
FOR EACH ROW
SET NEW.vehicle_mfr_org_id = (
    SELECT id FROM rateb_auto_ref_orgs
    WHERE id = NEW.vehicle_mfr_org_id AND org_role = 'vehicle_manufacturer'
);

DROP TRIGGER IF EXISTS trg_auto_ref_brand_bu;
CREATE TRIGGER trg_auto_ref_brand_bu
BEFORE UPDATE ON rateb_auto_ref_vehicle_brands
FOR EACH ROW
SET NEW.vehicle_mfr_org_id = (
    SELECT id FROM rateb_auto_ref_orgs
    WHERE id = NEW.vehicle_mfr_org_id AND org_role = 'vehicle_manufacturer'
);

DROP TRIGGER IF EXISTS trg_auto_ref_org_bu;
CREATE TRIGGER trg_auto_ref_org_bu
BEFORE UPDATE ON rateb_auto_ref_orgs
FOR EACH ROW
SET NEW.org_role = (
    SELECT CASE
        WHEN OLD.org_role = 'vehicle_manufacturer'
         AND NEW.org_role <> 'vehicle_manufacturer'
         AND EXISTS (
            SELECT 1 FROM rateb_auto_ref_vehicle_brands b
            WHERE b.vehicle_mfr_org_id = OLD.id
         )
        THEN NULL
        WHEN OLD.org_role IN ('distributor', 'authorized_dealer', 'branch')
         AND NEW.org_role NOT IN ('distributor', 'authorized_dealer', 'branch')
         AND EXISTS (
            SELECT 1 FROM rateb_auto_ref_authorizations a
            WHERE a.org_id = OLD.id
         )
        THEN NULL
        ELSE NEW.org_role
    END
);

DROP TRIGGER IF EXISTS trg_auto_ref_auth_bi;
CREATE TRIGGER trg_auto_ref_auth_bi
BEFORE INSERT ON rateb_auto_ref_authorizations
FOR EACH ROW
SET NEW.org_id = (
    SELECT o.id FROM rateb_auto_ref_orgs o
    WHERE o.id = NEW.org_id
      AND o.org_role IN ('distributor', 'authorized_dealer', 'branch')
      AND NEW.expires_on > NEW.effective_on
      AND NOT EXISTS (
          SELECT 1 FROM rateb_auto_ref_authorizations a
          WHERE a.org_id = NEW.org_id
            AND a.territory_market_id = NEW.territory_market_id
            AND a.effective_on < NEW.expires_on
            AND a.expires_on > NEW.effective_on
      )
);

DROP TRIGGER IF EXISTS trg_auto_ref_auth_bu;
CREATE TRIGGER trg_auto_ref_auth_bu
BEFORE UPDATE ON rateb_auto_ref_authorizations
FOR EACH ROW
SET NEW.org_id = (
    SELECT o.id FROM rateb_auto_ref_orgs o
    WHERE o.id = NEW.org_id
      AND o.org_role IN ('distributor', 'authorized_dealer', 'branch')
      AND NEW.expires_on > NEW.effective_on
      AND NOT EXISTS (
          SELECT 1 FROM rateb_auto_ref_authorizations a
          WHERE a.org_id = NEW.org_id
            AND a.territory_market_id = NEW.territory_market_id
            AND a.id <> NEW.id
            AND a.effective_on < NEW.expires_on
            AND a.expires_on > NEW.effective_on
      )
);
