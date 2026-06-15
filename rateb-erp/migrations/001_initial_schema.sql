-- RATEB ERP - Initial Schema Migration
-- Compatible with MySQL 5.7+ / MariaDB 10.3+
-- Run: mysql -u user -p database < migrations/001_initial_schema.sql

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS rateb_plans (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(80) NOT NULL UNIQUE,
    description TEXT NULL,
    price_monthly DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    price_yearly DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    max_users INT UNSIGNED NOT NULL DEFAULT 10,
    max_storage_mb INT UNSIGNED NOT NULL DEFAULT 1024,
    modules JSON NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_companies (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    slug VARCHAR(120) NOT NULL UNIQUE,
    email VARCHAR(190) NOT NULL,
    phone VARCHAR(40) NULL,
    address TEXT NULL,
    country VARCHAR(80) NULL,
    logo_path VARCHAR(255) NULL,
    status ENUM('active','suspended','pending') NOT NULL DEFAULT 'pending',
    plan_id INT UNSIGNED NULL,
    storage_limit_mb INT UNSIGNED NOT NULL DEFAULT 1024,
    user_limit INT UNSIGNED NOT NULL DEFAULT 10,
    modules JSON NULL,
    settings JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_companies_status (status),
    INDEX idx_companies_plan (plan_id),
    CONSTRAINT fk_companies_plan FOREIGN KEY (plan_id) REFERENCES rateb_plans(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NULL,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(40) NULL,
    avatar_path VARCHAR(255) NULL,
    is_super_admin TINYINT(1) NOT NULL DEFAULT 0,
    status ENUM('active','inactive','suspended') NOT NULL DEFAULT 'active',
    two_factor_secret VARCHAR(255) NULL,
    two_factor_enabled TINYINT(1) NOT NULL DEFAULT 0,
    locale VARCHAR(5) NOT NULL DEFAULT 'en',
    last_login_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_users_company (company_id),
    CONSTRAINT fk_users_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_roles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NULL,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL,
    description VARCHAR(255) NULL,
    is_system TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_roles_slug (slug),
    INDEX idx_roles_company (company_id),
    CONSTRAINT fk_roles_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_permissions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(120) NOT NULL UNIQUE,
    module VARCHAR(80) NOT NULL,
    description VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_role_permissions (
    role_id INT UNSIGNED NOT NULL,
    permission_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    CONSTRAINT fk_rp_role FOREIGN KEY (role_id) REFERENCES rateb_roles(id) ON DELETE CASCADE,
    CONSTRAINT fk_rp_permission FOREIGN KEY (permission_id) REFERENCES rateb_permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_user_roles (
    user_id INT UNSIGNED NOT NULL,
    role_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (user_id, role_id),
    CONSTRAINT fk_ur_user FOREIGN KEY (user_id) REFERENCES rateb_users(id) ON DELETE CASCADE,
    CONSTRAINT fk_ur_role FOREIGN KEY (role_id) REFERENCES rateb_roles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_subscriptions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    plan_id INT UNSIGNED NOT NULL,
    status ENUM('active','cancelled','expired','trial') NOT NULL DEFAULT 'trial',
    billing_cycle ENUM('monthly','yearly') NOT NULL DEFAULT 'monthly',
    amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    starts_at DATE NOT NULL,
    ends_at DATE NULL,
    auto_renew TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_subscriptions_company (company_id),
    CONSTRAINT fk_subscriptions_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_subscriptions_plan FOREIGN KEY (plan_id) REFERENCES rateb_plans(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_payments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    subscription_id INT UNSIGNED NULL,
    invoice_id INT UNSIGNED NULL,
    amount DECIMAL(12,2) NOT NULL,
    currency VARCHAR(3) NOT NULL DEFAULT 'SAR',
    method VARCHAR(50) NULL,
    reference_no VARCHAR(120) NULL,
    status ENUM('pending','completed','failed','refunded') NOT NULL DEFAULT 'pending',
    paid_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_payments_company (company_id),
    INDEX idx_payments_invoice (invoice_id),
    CONSTRAINT fk_payments_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_invoices (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    subscription_id INT UNSIGNED NULL,
    invoice_no VARCHAR(50) NOT NULL UNIQUE,
    invoice_type VARCHAR(50) NOT NULL DEFAULT 'tax',
    po_number VARCHAR(80) NULL,
    amount DECIMAL(12,2) NOT NULL,
    tax_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    total_amount DECIMAL(12,2) NOT NULL,
    currency VARCHAR(3) NOT NULL DEFAULT 'SAR',
    discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    discount_type ENUM('value','percent') NOT NULL DEFAULT 'value',
    tax_rate DECIMAL(5,2) NOT NULL DEFAULT 15.00,
    payment_terms_days INT UNSIGNED NOT NULL DEFAULT 30,
    payment_method VARCHAR(50) NULL,
    status ENUM('draft','sent','paid','overdue','cancelled') NOT NULL DEFAULT 'draft',
    notes TEXT NULL,
    payment_status ENUM('unpaid','partial','paid') NOT NULL DEFAULT 'unpaid',
    sent_at DATETIME NULL,
    due_date DATE NULL,
    issued_at DATE NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_invoices_company (company_id),
    CONSTRAINT fk_invoices_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_invoice_lines (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT UNSIGNED NOT NULL,
    line_no INT UNSIGNED NOT NULL DEFAULT 1,
    item_name VARCHAR(255) NOT NULL,
    description VARCHAR(500) NULL,
    quantity DECIMAL(12,3) NOT NULL DEFAULT 1.000,
    unit VARCHAR(30) NOT NULL DEFAULT 'unit',
    unit_price DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    tax_rate DECIMAL(5,2) NOT NULL DEFAULT 15.00,
    excluding_tax TINYINT(1) NOT NULL DEFAULT 1,
    line_subtotal DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    tax_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    line_total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_invoice_lines_invoice (invoice_id),
    CONSTRAINT fk_invoice_lines_invoice FOREIGN KEY (invoice_id) REFERENCES rateb_invoices(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_suppliers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    name VARCHAR(200) NOT NULL,
    code VARCHAR(50) NULL,
    email VARCHAR(190) NULL,
    phone VARCHAR(40) NULL,
    address TEXT NULL,
    rating DECIMAL(3,2) NULL DEFAULT 0.00,
    status ENUM('active','inactive','blacklisted') NOT NULL DEFAULT 'active',
    notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_suppliers_company (company_id),
    CONSTRAINT fk_suppliers_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_purchase_requests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    request_no VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    department VARCHAR(120) NULL,
    priority ENUM('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
    status ENUM('draft','submitted','approved','rejected','cancelled') NOT NULL DEFAULT 'draft',
    requested_by INT UNSIGNED NULL,
    approved_by INT UNSIGNED NULL,
    total_estimated DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_pr_company_no (company_id, request_no),
    INDEX idx_pr_company (company_id),
    CONSTRAINT fk_pr_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_purchase_orders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    order_no VARCHAR(50) NOT NULL,
    supplier_id INT UNSIGNED NULL,
    purchase_request_id INT UNSIGNED NULL,
    status ENUM('draft','sent','confirmed','partial','received','cancelled') NOT NULL DEFAULT 'draft',
    order_date DATE NOT NULL,
    expected_date DATE NULL,
    subtotal DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    tax_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    total_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_po_company_no (company_id, order_no),
    INDEX idx_po_company (company_id),
    CONSTRAINT fk_po_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_po_supplier FOREIGN KEY (supplier_id) REFERENCES rateb_suppliers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_purchase_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    purchase_order_id INT UNSIGNED NOT NULL,
    item_name VARCHAR(255) NOT NULL,
    sku VARCHAR(80) NULL,
    quantity DECIMAL(12,3) NOT NULL DEFAULT 1.000,
    unit VARCHAR(30) NOT NULL DEFAULT 'unit',
    unit_price DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    total_price DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pi_company (company_id),
    CONSTRAINT fk_pi_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_pi_po FOREIGN KEY (purchase_order_id) REFERENCES rateb_purchase_orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_rfq (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    rfq_no VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    status ENUM('draft','published','closed','awarded','cancelled') NOT NULL DEFAULT 'draft',
    deadline DATE NULL,
    description TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_rfq_company_no (company_id, rfq_no),
    CONSTRAINT fk_rfq_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_supplier_quotations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    rfq_id INT UNSIGNED NOT NULL,
    supplier_id INT UNSIGNED NOT NULL,
    quotation_no VARCHAR(50) NOT NULL,
    amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    status ENUM('submitted','under_review','accepted','rejected') NOT NULL DEFAULT 'submitted',
    valid_until DATE NULL,
    notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_sq_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_sq_rfq FOREIGN KEY (rfq_id) REFERENCES rateb_rfq(id) ON DELETE CASCADE,
    CONSTRAINT fk_sq_supplier FOREIGN KEY (supplier_id) REFERENCES rateb_suppliers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_warehouses (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    name VARCHAR(150) NOT NULL,
    code VARCHAR(50) NULL,
    location VARCHAR(255) NULL,
    manager_name VARCHAR(150) NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_wh_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_inventory (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    warehouse_id INT UNSIGNED NULL,
    item_name VARCHAR(255) NOT NULL,
    sku VARCHAR(80) NULL,
    category VARCHAR(120) NULL,
    quantity DECIMAL(12,3) NOT NULL DEFAULT 0.000,
    unit VARCHAR(30) NOT NULL DEFAULT 'unit',
    unit_cost DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    reorder_level DECIMAL(12,3) NOT NULL DEFAULT 0.000,
    expiry_date DATE NULL,
    status ENUM('active','inactive','expired') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_inv_company (company_id),
    CONSTRAINT fk_inv_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_inv_warehouse FOREIGN KEY (warehouse_id) REFERENCES rateb_warehouses(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_stock_movements (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    inventory_id INT UNSIGNED NOT NULL,
    warehouse_id INT UNSIGNED NULL,
    movement_type ENUM('in','out','transfer','adjustment') NOT NULL,
    quantity DECIMAL(12,3) NOT NULL,
    reference_type VARCHAR(50) NULL,
    reference_id INT UNSIGNED NULL,
    notes TEXT NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_sm_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_sm_inventory FOREIGN KEY (inventory_id) REFERENCES rateb_inventory(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_assets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    asset_tag VARCHAR(80) NOT NULL,
    name VARCHAR(200) NOT NULL,
    category VARCHAR(120) NULL,
    purchase_date DATE NULL,
    purchase_cost DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    current_value DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    location VARCHAR(255) NULL,
    status ENUM('active','maintenance','retired','disposed') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_assets_company_tag (company_id, asset_tag),
    CONSTRAINT fk_assets_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_medical_devices (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    asset_id INT UNSIGNED NULL,
    device_name VARCHAR(200) NOT NULL,
    manufacturer VARCHAR(150) NULL,
    model_no VARCHAR(100) NULL,
    serial_no VARCHAR(100) NULL,
    calibration_due DATE NULL,
    maintenance_due DATE NULL,
    regulatory_status VARCHAR(80) NULL,
    status ENUM('operational','maintenance','out_of_service') NOT NULL DEFAULT 'operational',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_md_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_md_asset FOREIGN KEY (asset_id) REFERENCES rateb_assets(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_contracts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    contract_no VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    supplier_id INT UNSIGNED NULL,
    contract_type VARCHAR(80) NULL,
    start_date DATE NOT NULL,
    end_date DATE NULL,
    value DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    status ENUM('draft','active','expired','terminated') NOT NULL DEFAULT 'draft',
    document_path VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_contracts_company_no (company_id, contract_no),
    CONSTRAINT fk_contracts_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_tenders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    tender_no VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    publish_date DATE NULL,
    closing_date DATE NULL,
    estimated_value DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    status ENUM('draft','open','closed','awarded','cancelled') NOT NULL DEFAULT 'draft',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_tenders_company_no (company_id, tender_no),
    CONSTRAINT fk_tenders_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_notifications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NULL,
    user_id INT UNSIGNED NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type VARCHAR(50) NOT NULL DEFAULT 'info',
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_notif_user (user_id),
    INDEX idx_notif_company (company_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NULL,
    user_id INT UNSIGNED NULL,
    action VARCHAR(80) NOT NULL,
    entity_type VARCHAR(80) NULL,
    entity_id INT UNSIGNED NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    payload JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_audit_company (company_id),
    INDEX idx_audit_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_login_activity (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    email VARCHAR(190) NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    success TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_login_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_api_tokens (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    company_id INT UNSIGNED NULL,
    token_hash VARCHAR(64) NOT NULL UNIQUE,
    name VARCHAR(120) NOT NULL,
    abilities JSON NULL,
    last_used_at DATETIME NULL,
    expires_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_tokens_user FOREIGN KEY (user_id) REFERENCES rateb_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_email_templates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(80) NOT NULL UNIQUE,
    subject VARCHAR(255) NOT NULL,
    body_html MEDIUMTEXT NOT NULL,
    body_text MEDIUMTEXT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_sms_templates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(80) NOT NULL UNIQUE,
    body VARCHAR(500) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_support_tickets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NULL,
    user_id INT UNSIGNED NULL,
    ticket_no VARCHAR(50) NOT NULL UNIQUE,
    subject VARCHAR(255) NOT NULL,
    priority ENUM('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
    status ENUM('open','in_progress','resolved','closed') NOT NULL DEFAULT 'open',
    message TEXT NOT NULL,
    assigned_to INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_system_settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT NULL,
    setting_group VARCHAR(50) NOT NULL DEFAULT 'general',
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
