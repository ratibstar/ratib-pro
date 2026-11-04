-- Global EPC catalog and company selections. Does not alter inventory or supplier tables.
-- Rollback after an approved backup, children first:
-- DROP TABLE IF EXISTS rateb_auto_company_supplier_maps;
-- DROP TABLE IF EXISTS rateb_auto_company_links;
-- DROP TABLE IF EXISTS rateb_auto_company_selections;
-- DROP TABLE IF EXISTS rateb_auto_ref_callouts;
-- DROP TABLE IF EXISTS rateb_auto_ref_diagrams;
-- DROP TABLE IF EXISTS rateb_auto_ref_assemblies;
-- DROP TABLE IF EXISTS rateb_auto_ref_systems;
-- DROP TABLE IF EXISTS rateb_auto_ref_fitment;
-- DROP TABLE IF EXISTS rateb_auto_ref_cross_refs;
-- DROP TABLE IF EXISTS rateb_auto_ref_supersessions;
-- DROP TABLE IF EXISTS rateb_auto_ref_part_numbers;
-- DROP TABLE IF EXISTS rateb_auto_ref_number_issuers;
-- DROP TABLE IF EXISTS rateb_auto_ref_number_schemes;
-- DROP TABLE IF EXISTS rateb_auto_ref_part_orgs;
-- DROP TABLE IF EXISTS rateb_auto_ref_parts;
-- DROP TABLE IF EXISTS rateb_auto_ref_parts_brands;
-- DROP TABLE IF EXISTS rateb_auto_ref_vehicles;
-- DROP TABLE IF EXISTS rateb_auto_ref_transmissions;
-- DROP TABLE IF EXISTS rateb_auto_ref_engines;
-- DROP TABLE IF EXISTS rateb_auto_ref_generations;
-- DROP TABLE IF EXISTS rateb_auto_ref_models;
-- DROP TABLE IF EXISTS rateb_auto_ref_countries;
-- DROP TABLE IF EXISTS rateb_auto_ref_import_rows;
-- DROP TABLE IF EXISTS rateb_auto_ref_import_batches;
-- DROP TABLE IF EXISTS rateb_auto_ref_audit;
-- DROP TABLE IF EXISTS rateb_auto_ref_sources;

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS rateb_auto_ref_sources (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    org_id INT UNSIGNED NOT NULL,
    dataset_name VARCHAR(150) NOT NULL,
    license_ref VARCHAR(150) NOT NULL,
    market_id INT UNSIGNED NOT NULL,
    permitted_use VARCHAR(150) NOT NULL,
    UNIQUE KEY uq_auto_ref_source (org_id, dataset_name, license_ref, market_id),
    CONSTRAINT fk_auto_ref_source_org FOREIGN KEY (org_id) REFERENCES rateb_auto_ref_orgs(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_auto_ref_source_market FOREIGN KEY (market_id) REFERENCES rateb_auto_ref_markets(id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_auto_ref_countries (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    market_id INT UNSIGNED NOT NULL,
    code VARCHAR(8) NOT NULL,
    name_en VARCHAR(80) NOT NULL,
    name_ar VARCHAR(80) NOT NULL,
    UNIQUE KEY uq_auto_ref_country_code (code),
    CONSTRAINT fk_auto_ref_country_market FOREIGN KEY (market_id) REFERENCES rateb_auto_ref_markets(id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_auto_ref_models (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    vehicle_brand_id INT UNSIGNED NOT NULL,
    name_en VARCHAR(120) NOT NULL,
    name_ar VARCHAR(120) NOT NULL,
    normalized_name VARCHAR(120) NOT NULL,
    status VARCHAR(16) NOT NULL DEFAULT 'active',
    UNIQUE KEY uq_auto_ref_model (vehicle_brand_id, normalized_name),
    CONSTRAINT fk_auto_ref_model_brand FOREIGN KEY (vehicle_brand_id) REFERENCES rateb_auto_ref_vehicle_brands(id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_auto_ref_generations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    model_id INT UNSIGNED NOT NULL,
    market_id INT UNSIGNED NOT NULL,
    name_en VARCHAR(120) NOT NULL,
    name_ar VARCHAR(120) NOT NULL,
    year_from SMALLINT UNSIGNED NOT NULL,
    year_to SMALLINT UNSIGNED NOT NULL,
    status VARCHAR(16) NOT NULL DEFAULT 'active',
    CONSTRAINT chk_auto_ref_generation_years CHECK (year_to >= year_from),
    UNIQUE KEY uq_auto_ref_generation (model_id, market_id, year_from, year_to),
    CONSTRAINT fk_auto_ref_generation_model FOREIGN KEY (model_id) REFERENCES rateb_auto_ref_models(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_auto_ref_generation_market FOREIGN KEY (market_id) REFERENCES rateb_auto_ref_markets(id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_auto_ref_engines (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(40) NOT NULL,
    name_en VARCHAR(120) NOT NULL,
    name_ar VARCHAR(120) NOT NULL,
    UNIQUE KEY uq_auto_ref_engine_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_auto_ref_transmissions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(40) NOT NULL,
    name_en VARCHAR(120) NOT NULL,
    name_ar VARCHAR(120) NOT NULL,
    UNIQUE KEY uq_auto_ref_transmission_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_auto_ref_vehicles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    generation_id INT UNSIGNED NOT NULL,
    engine_id INT UNSIGNED NOT NULL,
    transmission_id INT UNSIGNED NOT NULL,
    market_id INT UNSIGNED NOT NULL,
    status VARCHAR(16) NOT NULL DEFAULT 'active',
    UNIQUE KEY uq_auto_ref_vehicle (generation_id, engine_id, transmission_id, market_id),
    CONSTRAINT fk_auto_ref_vehicle_generation FOREIGN KEY (generation_id) REFERENCES rateb_auto_ref_generations(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_auto_ref_vehicle_engine FOREIGN KEY (engine_id) REFERENCES rateb_auto_ref_engines(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_auto_ref_vehicle_transmission FOREIGN KEY (transmission_id) REFERENCES rateb_auto_ref_transmissions(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_auto_ref_vehicle_market FOREIGN KEY (market_id) REFERENCES rateb_auto_ref_markets(id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_auto_ref_parts_brands (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    brand_owner_org_id INT UNSIGNED NOT NULL,
    name_en VARCHAR(120) NOT NULL,
    name_ar VARCHAR(120) NOT NULL,
    normalized_name VARCHAR(120) NOT NULL,
    UNIQUE KEY uq_auto_ref_parts_brand (brand_owner_org_id, normalized_name),
    CONSTRAINT fk_auto_ref_parts_brand_org FOREIGN KEY (brand_owner_org_id) REFERENCES rateb_auto_ref_orgs(id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_auto_ref_parts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    source_id INT UNSIGNED NULL,
    name_en VARCHAR(200) NOT NULL,
    name_ar VARCHAR(200) NOT NULL,
    normalized_name VARCHAR(200) NOT NULL,
    status VARCHAR(16) NOT NULL DEFAULT 'active',
    CONSTRAINT chk_auto_ref_part_status CHECK (status IN ('active', 'retired')),
    CONSTRAINT fk_auto_ref_part_source FOREIGN KEY (source_id) REFERENCES rateb_auto_ref_sources(id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_auto_ref_part_orgs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    part_id INT UNSIGNED NOT NULL,
    org_id INT UNSIGNED NOT NULL,
    relation_role VARCHAR(32) NOT NULL,
    UNIQUE KEY uq_auto_ref_part_org (part_id, org_id, relation_role),
    CONSTRAINT fk_auto_ref_part_org_part FOREIGN KEY (part_id) REFERENCES rateb_auto_ref_parts(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_auto_ref_part_org_org FOREIGN KEY (org_id) REFERENCES rateb_auto_ref_orgs(id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_auto_ref_number_schemes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    owner_org_id INT UNSIGNED NOT NULL,
    code VARCHAR(40) NOT NULL,
    name_en VARCHAR(120) NOT NULL,
    name_ar VARCHAR(120) NOT NULL,
    UNIQUE KEY uq_auto_ref_scheme (owner_org_id, code),
    CONSTRAINT fk_auto_ref_scheme_owner FOREIGN KEY (owner_org_id) REFERENCES rateb_auto_ref_orgs(id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_auto_ref_number_issuers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    org_id INT UNSIGNED NOT NULL,
    scheme_id INT UNSIGNED NOT NULL,
    UNIQUE KEY uq_auto_ref_issuer (org_id, scheme_id),
    CONSTRAINT fk_auto_ref_issuer_org FOREIGN KEY (org_id) REFERENCES rateb_auto_ref_orgs(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_auto_ref_issuer_scheme FOREIGN KEY (scheme_id) REFERENCES rateb_auto_ref_number_schemes(id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_auto_ref_part_numbers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    part_id INT UNSIGNED NOT NULL,
    issuer_id INT UNSIGNED NOT NULL,
    parts_brand_id INT UNSIGNED NOT NULL,
    scheme_id INT UNSIGNED NOT NULL,
    market_id INT UNSIGNED NOT NULL,
    number_type VARCHAR(24) NOT NULL,
    normalized_number VARCHAR(80) NOT NULL,
    CONSTRAINT chk_auto_ref_number_type CHECK (number_type IN ('oem', 'aftermarket', 'manufacturer')),
    UNIQUE KEY uq_auto_ref_part_number (issuer_id, parts_brand_id, scheme_id, number_type, normalized_number, market_id),
    CONSTRAINT fk_auto_ref_number_part FOREIGN KEY (part_id) REFERENCES rateb_auto_ref_parts(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_auto_ref_number_issuer FOREIGN KEY (issuer_id) REFERENCES rateb_auto_ref_number_issuers(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_auto_ref_number_brand FOREIGN KEY (parts_brand_id) REFERENCES rateb_auto_ref_parts_brands(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_auto_ref_number_scheme FOREIGN KEY (scheme_id) REFERENCES rateb_auto_ref_number_schemes(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_auto_ref_number_market FOREIGN KEY (market_id) REFERENCES rateb_auto_ref_markets(id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_auto_ref_supersessions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    from_part_id INT UNSIGNED NOT NULL,
    to_part_id INT UNSIGNED NOT NULL,
    effective_on DATE NOT NULL,
    UNIQUE KEY uq_auto_ref_supersession (from_part_id, to_part_id, effective_on),
    CONSTRAINT fk_auto_ref_super_from FOREIGN KEY (from_part_id) REFERENCES rateb_auto_ref_parts(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_auto_ref_super_to FOREIGN KEY (to_part_id) REFERENCES rateb_auto_ref_parts(id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_auto_ref_cross_refs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    from_part_id INT UNSIGNED NOT NULL,
    to_part_id INT UNSIGNED NOT NULL,
    relation_type VARCHAR(24) NOT NULL,
    UNIQUE KEY uq_auto_ref_cross (from_part_id, to_part_id, relation_type),
    CONSTRAINT fk_auto_ref_cross_from FOREIGN KEY (from_part_id) REFERENCES rateb_auto_ref_parts(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_auto_ref_cross_to FOREIGN KEY (to_part_id) REFERENCES rateb_auto_ref_parts(id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_auto_ref_fitment (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    part_id INT UNSIGNED NOT NULL,
    vehicle_id INT UNSIGNED NOT NULL,
    fitment_status VARCHAR(16) NOT NULL,
    source_id INT UNSIGNED NULL,
    CONSTRAINT chk_auto_ref_fitment_status CHECK (fitment_status IN ('confirmed', 'unverified', 'conflict', 'retired')),
    UNIQUE KEY uq_auto_ref_fitment (part_id, vehicle_id),
    CONSTRAINT fk_auto_ref_fitment_part FOREIGN KEY (part_id) REFERENCES rateb_auto_ref_parts(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_auto_ref_fitment_vehicle FOREIGN KEY (vehicle_id) REFERENCES rateb_auto_ref_vehicles(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_auto_ref_fitment_source FOREIGN KEY (source_id) REFERENCES rateb_auto_ref_sources(id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_auto_ref_systems (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(40) NOT NULL,
    name_en VARCHAR(120) NOT NULL,
    name_ar VARCHAR(120) NOT NULL,
    UNIQUE KEY uq_auto_ref_system_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_auto_ref_assemblies (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    system_id INT UNSIGNED NOT NULL,
    code VARCHAR(40) NOT NULL,
    name_en VARCHAR(120) NOT NULL,
    name_ar VARCHAR(120) NOT NULL,
    UNIQUE KEY uq_auto_ref_assembly (system_id, code),
    CONSTRAINT fk_auto_ref_assembly_system FOREIGN KEY (system_id) REFERENCES rateb_auto_ref_systems(id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_auto_ref_diagrams (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    assembly_id INT UNSIGNED NOT NULL,
    version_no INT UNSIGNED NOT NULL,
    status VARCHAR(16) NOT NULL DEFAULT 'draft',
    svg_text MEDIUMTEXT NULL,
    UNIQUE KEY uq_auto_ref_diagram_version (assembly_id, version_no),
    CONSTRAINT fk_auto_ref_diagram_assembly FOREIGN KEY (assembly_id) REFERENCES rateb_auto_ref_assemblies(id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_auto_ref_callouts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    diagram_id INT UNSIGNED NOT NULL,
    part_id INT UNSIGNED NOT NULL,
    callout_label VARCHAR(40) NOT NULL,
    UNIQUE KEY uq_auto_ref_callout (diagram_id, callout_label),
    CONSTRAINT fk_auto_ref_callout_diagram FOREIGN KEY (diagram_id) REFERENCES rateb_auto_ref_diagrams(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_auto_ref_callout_part FOREIGN KEY (part_id) REFERENCES rateb_auto_ref_parts(id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_auto_ref_audit (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    actor_user_id INT UNSIGNED NOT NULL,
    action VARCHAR(40) NOT NULL,
    entity_type VARCHAR(40) NOT NULL,
    entity_id INT UNSIGNED NOT NULL,
    batch_id INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_auto_ref_import_batches (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    source_id INT UNSIGNED NOT NULL,
    content_sha256 CHAR(64) NOT NULL,
    status VARCHAR(16) NOT NULL DEFAULT 'preview',
    row_count INT UNSIGNED NOT NULL DEFAULT 0,
    CONSTRAINT chk_auto_ref_import_status CHECK (status IN ('preview', 'approved', 'applied', 'rejected')),
    UNIQUE KEY uq_auto_ref_import_hash (content_sha256, status),
    CONSTRAINT fk_auto_ref_import_source FOREIGN KEY (source_id) REFERENCES rateb_auto_ref_sources(id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_auto_ref_import_rows (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    batch_id INT UNSIGNED NOT NULL,
    row_no INT UNSIGNED NOT NULL,
    raw_text VARCHAR(500) NOT NULL,
    normalized_text VARCHAR(500) NOT NULL,
    conflict_note VARCHAR(150) NULL,
    UNIQUE KEY uq_auto_ref_import_row (batch_id, row_no),
    CONSTRAINT fk_auto_ref_import_row_batch FOREIGN KEY (batch_id) REFERENCES rateb_auto_ref_import_batches(id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_auto_company_selections (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    entity_type VARCHAR(24) NOT NULL,
    entity_id INT UNSIGNED NOT NULL,
    UNIQUE KEY uq_auto_company_selection (company_id, entity_type, entity_id),
    KEY idx_auto_company_selection (company_id, entity_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_auto_company_links (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    global_part_id INT UNSIGNED NOT NULL,
    inventory_id INT UNSIGNED NOT NULL,
    UNIQUE KEY uq_auto_company_link (company_id, global_part_id, inventory_id),
    CONSTRAINT fk_auto_company_link_part FOREIGN KEY (global_part_id) REFERENCES rateb_auto_ref_parts(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_auto_company_link_inventory FOREIGN KEY (inventory_id) REFERENCES rateb_inventory(id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_auto_company_supplier_maps (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    global_part_id INT UNSIGNED NOT NULL,
    supplier_id INT UNSIGNED NOT NULL,
    supplier_number VARCHAR(80) NOT NULL,
    UNIQUE KEY uq_auto_company_supplier_map (company_id, supplier_id, supplier_number),
    CONSTRAINT fk_auto_company_map_part FOREIGN KEY (global_part_id) REFERENCES rateb_auto_ref_parts(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_auto_company_map_supplier FOREIGN KEY (supplier_id) REFERENCES rateb_suppliers(id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TRIGGER IF EXISTS trg_auto_ref_generation_bi;
CREATE TRIGGER trg_auto_ref_generation_bi
BEFORE INSERT ON rateb_auto_ref_generations
FOR EACH ROW
SET NEW.model_id = (
    SELECT m.id FROM rateb_auto_ref_models m
    WHERE m.id = NEW.model_id
      AND NEW.year_to >= NEW.year_from
      AND NOT EXISTS (
          SELECT 1 FROM rateb_auto_ref_generations g
          WHERE g.model_id = NEW.model_id
            AND g.market_id = NEW.market_id
            AND g.year_from <= NEW.year_to
            AND g.year_to >= NEW.year_from
      )
);

DROP TRIGGER IF EXISTS trg_auto_company_link_bi;
CREATE TRIGGER trg_auto_company_link_bi
BEFORE INSERT ON rateb_auto_company_links
FOR EACH ROW
SET NEW.inventory_id = (
    SELECT id FROM rateb_inventory
    WHERE id = NEW.inventory_id AND company_id = NEW.company_id
);

DROP TRIGGER IF EXISTS trg_auto_company_map_bi;
CREATE TRIGGER trg_auto_company_map_bi
BEFORE INSERT ON rateb_auto_company_supplier_maps
FOR EACH ROW
SET NEW.supplier_id = (
    SELECT id FROM rateb_suppliers
    WHERE id = NEW.supplier_id AND company_id = NEW.company_id
);
