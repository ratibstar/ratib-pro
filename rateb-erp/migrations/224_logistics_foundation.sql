-- RATEB ERP — Logistics Module Phase 1 (foundation)
-- Additive only: CREATE TABLE IF NOT EXISTS + permission inserts.
-- Does not alter Inventory / HR Fleet / Accounting / Sales / Core Services.
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- ---------------------------------------------------------------------------
-- Drivers (linked to existing rateb_employees — no new employee table)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS rateb_logistics_drivers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    branch_id INT UNSIGNED NULL,
    employee_id INT UNSIGNED NOT NULL,
    license_number VARCHAR(80) NULL,
    license_type VARCHAR(40) NULL,
    license_expiry DATE NULL,
    status ENUM('active','inactive','suspended') NOT NULL DEFAULT 'active',
    notes TEXT NULL,
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_logistics_driver_employee (company_id, employee_id),
    KEY idx_logistics_drivers_company_status (company_id, status, created_at),
    KEY idx_logistics_drivers_license_expiry (company_id, license_expiry),
    CONSTRAINT fk_logistics_drivers_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Fleet (operational logistics vehicles — distinct from rateb_hr_fleet)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS rateb_logistics_vehicles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    branch_id INT UNSIGNED NULL,
    plate_number VARCHAR(40) NOT NULL,
    vehicle_type VARCHAR(80) NULL,
    brand VARCHAR(80) NULL,
    model VARCHAR(80) NULL,
    year SMALLINT UNSIGNED NULL,
    capacity DECIMAL(12,2) NULL,
    status ENUM('available','assigned','maintenance','inactive') NOT NULL DEFAULT 'available',
    current_driver_id INT UNSIGNED NULL,
    notes TEXT NULL,
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_logistics_vehicle_plate (company_id, plate_number),
    KEY idx_logistics_vehicles_company_status (company_id, status, created_at),
    KEY idx_logistics_vehicles_driver (company_id, current_driver_id),
    CONSTRAINT fk_logistics_vehicles_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_logistics_vehicles_driver FOREIGN KEY (current_driver_id) REFERENCES rateb_logistics_drivers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Routes (named corridors / path templates)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS rateb_logistics_routes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    branch_id INT UNSIGNED NULL,
    code VARCHAR(40) NOT NULL,
    name VARCHAR(160) NOT NULL,
    name_ar VARCHAR(160) NULL,
    origin VARCHAR(255) NULL,
    destination VARCHAR(255) NULL,
    distance_km DECIMAL(12,2) NULL,
    estimated_minutes INT UNSIGNED NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    notes TEXT NULL,
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_logistics_route_code (company_id, code),
    KEY idx_logistics_routes_company_status (company_id, status, created_at),
    CONSTRAINT fk_logistics_routes_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Delivery orders (fills Sales DO gap — optional order_id external reference)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS rateb_logistics_delivery_orders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    branch_id INT UNSIGNED NULL,
    customer_id INT UNSIGNED NULL,
    order_id INT UNSIGNED NULL,
    order_ref VARCHAR(80) NULL,
    delivery_no VARCHAR(40) NOT NULL,
    pickup_location VARCHAR(255) NULL,
    delivery_location VARCHAR(255) NULL,
    planned_date DATE NULL,
    status ENUM('draft','confirmed','dispatched','completed','cancelled') NOT NULL DEFAULT 'draft',
    notes TEXT NULL,
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_logistics_do_no (company_id, delivery_no),
    KEY idx_logistics_do_company_status (company_id, status, created_at),
    KEY idx_logistics_do_customer (company_id, customer_id),
    KEY idx_logistics_do_order (company_id, order_id),
    CONSTRAINT fk_logistics_do_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Trips
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS rateb_logistics_trips (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    branch_id INT UNSIGNED NULL,
    driver_id INT UNSIGNED NULL,
    vehicle_id INT UNSIGNED NULL,
    route_id INT UNSIGNED NULL,
    origin VARCHAR(255) NULL,
    destination VARCHAR(255) NULL,
    planned_date DATE NULL,
    start_time DATETIME NULL,
    end_time DATETIME NULL,
    status ENUM('draft','assigned','started','completed','cancelled') NOT NULL DEFAULT 'draft',
    notes TEXT NULL,
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_logistics_trips_company_status (company_id, status, planned_date),
    KEY idx_logistics_trips_driver (company_id, driver_id, status),
    KEY idx_logistics_trips_vehicle (company_id, vehicle_id, status),
    KEY idx_logistics_trips_route (company_id, route_id),
    CONSTRAINT fk_logistics_trips_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_logistics_trips_driver FOREIGN KEY (driver_id) REFERENCES rateb_logistics_drivers(id) ON DELETE SET NULL,
    CONSTRAINT fk_logistics_trips_vehicle FOREIGN KEY (vehicle_id) REFERENCES rateb_logistics_vehicles(id) ON DELETE SET NULL,
    CONSTRAINT fk_logistics_trips_route FOREIGN KEY (route_id) REFERENCES rateb_logistics_routes(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Shipments
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS rateb_logistics_shipments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    branch_id INT UNSIGNED NULL,
    customer_id INT UNSIGNED NULL,
    order_id INT UNSIGNED NULL,
    delivery_order_id INT UNSIGNED NULL,
    trip_id INT UNSIGNED NULL,
    tracking_number VARCHAR(64) NOT NULL,
    pickup_location VARCHAR(255) NULL,
    delivery_location VARCHAR(255) NULL,
    status ENUM('created','picked','packed','shipped','out_for_delivery','delivered','failed') NOT NULL DEFAULT 'created',
    dispatched_at DATETIME NULL,
    delivered_at DATETIME NULL,
    notes TEXT NULL,
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_logistics_shipment_tracking (company_id, tracking_number),
    KEY idx_logistics_shipments_company_status (company_id, status, created_at),
    KEY idx_logistics_shipments_customer (company_id, customer_id),
    KEY idx_logistics_shipments_trip (company_id, trip_id),
    KEY idx_logistics_shipments_do (company_id, delivery_order_id),
    CONSTRAINT fk_logistics_shipments_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_logistics_shipments_do FOREIGN KEY (delivery_order_id) REFERENCES rateb_logistics_delivery_orders(id) ON DELETE SET NULL,
    CONSTRAINT fk_logistics_shipments_trip FOREIGN KEY (trip_id) REFERENCES rateb_logistics_trips(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Proof of delivery
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS rateb_logistics_delivery_proofs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    shipment_id INT UNSIGNED NOT NULL,
    receiver_name VARCHAR(160) NULL,
    signature_file VARCHAR(255) NULL,
    photo_file VARCHAR(255) NULL,
    gps_lat DECIMAL(10,7) NULL,
    gps_long DECIMAL(10,7) NULL,
    delivered_at DATETIME NULL,
    notes TEXT NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_logistics_pod_shipment (company_id, shipment_id),
    KEY idx_logistics_pod_company_delivered (company_id, delivered_at),
    CONSTRAINT fk_logistics_pod_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_logistics_pod_shipment FOREIGN KEY (shipment_id) REFERENCES rateb_logistics_shipments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Logistics expenses (posted via AccountingService in later phases)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS rateb_logistics_expenses (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    branch_id INT UNSIGNED NULL,
    trip_id INT UNSIGNED NULL,
    vehicle_id INT UNSIGNED NULL,
    driver_id INT UNSIGNED NULL,
    expense_type ENUM('fuel','maintenance','driver_payment','transport_cost','other') NOT NULL DEFAULT 'other',
    amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    currency CHAR(3) NOT NULL DEFAULT 'SAR',
    expense_date DATE NOT NULL,
    description VARCHAR(255) NULL,
    journal_entry_id INT UNSIGNED NULL,
    status ENUM('draft','posted','cancelled') NOT NULL DEFAULT 'draft',
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_logistics_expenses_company_status (company_id, status, expense_date),
    KEY idx_logistics_expenses_type (company_id, expense_type, expense_date),
    KEY idx_logistics_expenses_trip (company_id, trip_id),
    KEY idx_logistics_expenses_vehicle (company_id, vehicle_id),
    CONSTRAINT fk_logistics_expenses_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_logistics_expenses_trip FOREIGN KEY (trip_id) REFERENCES rateb_logistics_trips(id) ON DELETE SET NULL,
    CONSTRAINT fk_logistics_expenses_vehicle FOREIGN KEY (vehicle_id) REFERENCES rateb_logistics_vehicles(id) ON DELETE SET NULL,
    CONSTRAINT fk_logistics_expenses_driver FOREIGN KEY (driver_id) REFERENCES rateb_logistics_drivers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Permissions
-- ---------------------------------------------------------------------------
INSERT INTO rateb_permissions (name, name_ar, slug, module, description, description_ar) VALUES
('View Logistics', 'عرض الخدمات اللوجستية', 'logistics.view', 'logistics', 'View logistics records', 'عرض سجلات الخدمات اللوجستية'),
('Manage Logistics', 'إدارة الخدمات اللوجستية', 'logistics.manage', 'logistics', 'Manage logistics operations', 'إدارة عمليات الخدمات اللوجستية'),
('Dispatch Logistics', 'ترحيل الشحنات', 'logistics.dispatch', 'logistics', 'Dispatch shipments and stock movements', 'ترحيل الشحنات وحركات المخزون'),
('Logistics Driver', 'سائق لوجستي', 'logistics.driver', 'logistics', 'Driver mobile trip and delivery actions', 'إجراءات السائق للرحلات والتسليم'),
('Logistics Expenses', 'مصروفات اللوجستيات', 'logistics.expense', 'logistics', 'Manage logistics expenses', 'إدارة مصروفات الخدمات اللوجستية'),
('Logistics Reports', 'تقارير اللوجستيات', 'logistics.report', 'logistics', 'View logistics reports', 'عرض تقارير الخدمات اللوجستية')
ON DUPLICATE KEY UPDATE name = VALUES(name), module = VALUES(module), description = VALUES(description);

INSERT IGNORE INTO rateb_role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM rateb_roles r
JOIN rateb_permissions p ON p.slug IN (
    'logistics.view',
    'logistics.manage',
    'logistics.dispatch',
    'logistics.driver',
    'logistics.expense',
    'logistics.report'
)
WHERE r.slug IN ('company-full-access', 'super-admin');
