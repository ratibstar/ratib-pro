-- RATEB ERP — Logistics Module Phase 4 (Driver API support tables)
-- Additive only: CREATE TABLE IF NOT EXISTS.
-- Does not alter Offline Engine / Core Services / Inventory / HR.
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

CREATE TABLE IF NOT EXISTS rateb_logistics_driver_locations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    driver_id INT UNSIGNED NOT NULL,
    trip_id INT UNSIGNED NULL,
    shipment_id INT UNSIGNED NULL,
    gps_lat DECIMAL(10,7) NOT NULL,
    gps_long DECIMAL(10,7) NOT NULL,
    recorded_at DATETIME NOT NULL,
    client_timestamp VARCHAR(64) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_logistics_loc_driver (company_id, driver_id, recorded_at),
    KEY idx_logistics_loc_trip (company_id, trip_id, recorded_at),
    CONSTRAINT fk_logistics_loc_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_logistics_loc_driver FOREIGN KEY (driver_id) REFERENCES rateb_logistics_drivers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_logistics_api_idempotency (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    driver_id INT UNSIGNED NOT NULL,
    idempotency_key VARCHAR(128) NOT NULL,
    endpoint VARCHAR(120) NOT NULL,
    request_hash CHAR(64) NOT NULL,
    response_code SMALLINT UNSIGNED NOT NULL DEFAULT 200,
    response_body MEDIUMTEXT NOT NULL,
    client_timestamp VARCHAR(64) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_logistics_api_idem (company_id, driver_id, idempotency_key),
    KEY idx_logistics_api_idem_endpoint (company_id, endpoint, created_at),
    CONSTRAINT fk_logistics_api_idem_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_logistics_api_idem_driver FOREIGN KEY (driver_id) REFERENCES rateb_logistics_drivers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
