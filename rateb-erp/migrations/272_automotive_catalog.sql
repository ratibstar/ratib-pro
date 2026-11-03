-- RATEB ERP — Automotive catalog (Phase 2).
-- Links to existing rateb_inventory rows. Does not alter inventory or stock tables.

SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

CREATE TABLE IF NOT EXISTS rateb_auto_parts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    inventory_id INT UNSIGNED NOT NULL,
    manufacturer VARCHAR(150) NULL,
    brand VARCHAR(150) NULL,
    oem_number VARCHAR(80) NULL,
    status ENUM('active','superseded','obsolete') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_auto_part_inventory (inventory_id),
    UNIQUE KEY uq_auto_part_company_inventory (company_id, inventory_id),
    UNIQUE KEY uq_auto_part_company_oem (company_id, oem_number),
    INDEX idx_auto_part_company (company_id),
    CONSTRAINT fk_auto_part_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_auto_part_inventory FOREIGN KEY (inventory_id) REFERENCES rateb_inventory(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_auto_part_numbers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    part_id INT UNSIGNED NOT NULL,
    code VARCHAR(80) NOT NULL,
    code_type ENUM('oem','aftermarket','supplier') NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_auto_number_company_code (company_id, code),
    INDEX idx_auto_number_part (part_id),
    CONSTRAINT fk_auto_number_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_auto_number_part FOREIGN KEY (part_id) REFERENCES rateb_auto_parts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_auto_barcodes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    part_id INT UNSIGNED NOT NULL,
    code VARCHAR(80) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_auto_barcode_company_code (company_id, code),
    INDEX idx_auto_barcode_part (part_id),
    CONSTRAINT fk_auto_barcode_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_auto_barcode_part FOREIGN KEY (part_id) REFERENCES rateb_auto_parts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_auto_vehicles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    make VARCHAR(80) NOT NULL,
    model VARCHAR(80) NOT NULL,
    model_year SMALLINT UNSIGNED NOT NULL,
    engine VARCHAR(80) NOT NULL DEFAULT '',
    trim VARCHAR(80) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_auto_vehicle (company_id, make, model, model_year, engine, trim),
    INDEX idx_auto_vehicle_company (company_id),
    CONSTRAINT fk_auto_vehicle_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_auto_fitment (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    part_id INT UNSIGNED NOT NULL,
    vehicle_id INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_auto_fitment (part_id, vehicle_id),
    INDEX idx_auto_fitment_company (company_id),
    CONSTRAINT fk_auto_fitment_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_auto_fitment_part FOREIGN KEY (part_id) REFERENCES rateb_auto_parts(id) ON DELETE CASCADE,
    CONSTRAINT fk_auto_fitment_vehicle FOREIGN KEY (vehicle_id) REFERENCES rateb_auto_vehicles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_auto_interchange (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    part_id INT UNSIGNED NOT NULL,
    alternate_inventory_id INT UNSIGNED NOT NULL,
    relation_type VARCHAR(40) NOT NULL DEFAULT 'interchange',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_auto_interchange (company_id, part_id, alternate_inventory_id),
    INDEX idx_auto_interchange_alt (alternate_inventory_id),
    CONSTRAINT fk_auto_interchange_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_auto_interchange_part FOREIGN KEY (part_id) REFERENCES rateb_auto_parts(id) ON DELETE CASCADE,
    CONSTRAINT fk_auto_interchange_inventory FOREIGN KEY (alternate_inventory_id) REFERENCES rateb_inventory(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
