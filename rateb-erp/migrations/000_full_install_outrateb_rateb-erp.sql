-- =============================================================================
-- RATEB ERP — Full database install for admin_rateb-erp
-- =============================================================================
-- Database: admin_rateb-erp
-- Import via cPanel phpMyAdmin → select database → Import → choose this file
--
-- WARNING: Drops all existing rateb_* tables before recreating (fresh install).
-- Default login after import: admin@rateb.sa / password
-- =============================================================================

USE `admin_rateb-erp`;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `rateb_journal_lines`;
DROP TABLE IF EXISTS `rateb_journal_entries`;
DROP TABLE IF EXISTS `rateb_chart_of_accounts`;
DROP TABLE IF EXISTS `rateb_supplier_evaluations`;
DROP TABLE IF EXISTS `rateb_user_roles`;
DROP TABLE IF EXISTS `rateb_role_permissions`;
DROP TABLE IF EXISTS `rateb_api_tokens`;
DROP TABLE IF EXISTS `rateb_stock_movements`;
DROP TABLE IF EXISTS `rateb_purchase_items`;
DROP TABLE IF EXISTS `rateb_supplier_quotations`;
DROP TABLE IF EXISTS `rateb_medical_devices`;
DROP TABLE IF EXISTS `rateb_purchase_orders`;
DROP TABLE IF EXISTS `rateb_purchase_requests`;
DROP TABLE IF EXISTS `rateb_subscriptions`;
DROP TABLE IF EXISTS `rateb_payments`;
DROP TABLE IF EXISTS `rateb_invoice_lines`;
DROP TABLE IF EXISTS `rateb_invoices`;
DROP TABLE IF EXISTS `rateb_inventory`;
DROP TABLE IF EXISTS `rateb_warehouses`;
DROP TABLE IF EXISTS `rateb_rfq`;
DROP TABLE IF EXISTS `rateb_contracts`;
DROP TABLE IF EXISTS `rateb_tenders`;
DROP TABLE IF EXISTS `rateb_assets`;
DROP TABLE IF EXISTS `rateb_suppliers`;
DROP TABLE IF EXISTS `rateb_users`;
DROP TABLE IF EXISTS `rateb_roles`;
DROP TABLE IF EXISTS `rateb_permissions`;
DROP TABLE IF EXISTS `rateb_companies`;
DROP TABLE IF EXISTS `rateb_plans`;
DROP TABLE IF EXISTS `rateb_notifications`;
DROP TABLE IF EXISTS `rateb_audit_logs`;
DROP TABLE IF EXISTS `rateb_login_activity`;
DROP TABLE IF EXISTS `rateb_email_templates`;
DROP TABLE IF EXISTS `rateb_sms_templates`;
DROP TABLE IF EXISTS `rateb_support_tickets`;
DROP TABLE IF EXISTS `rateb_system_settings`;

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
    failed_attempts INT UNSIGNED NOT NULL DEFAULT 0,
    locked_until DATETIME NULL,
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
    name_ar VARCHAR(120) NULL,
    slug VARCHAR(120) NOT NULL UNIQUE,
    module VARCHAR(80) NOT NULL,
    description VARCHAR(255) NULL,
    description_ar VARCHAR(255) NULL,
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
    classification_id INT UNSIGNED NULL,
    performance_kpi DECIMAL(5,2) NULL,
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
    category_id INT UNSIGNED NULL,
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


CREATE TABLE IF NOT EXISTS rateb_supplier_evaluations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    supplier_id INT UNSIGNED NOT NULL,
    evaluated_by INT UNSIGNED NULL,
    evaluation_date DATE NOT NULL,
    quality_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
    delivery_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
    price_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
    service_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
    overall_score DECIMAL(4,2) NOT NULL DEFAULT 0.00,
    comments TEXT NULL,
    status ENUM('draft','published','archived') NOT NULL DEFAULT 'published',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_eval_company (company_id),
    INDEX idx_eval_supplier (supplier_id),
    CONSTRAINT fk_eval_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_eval_supplier FOREIGN KEY (supplier_id) REFERENCES rateb_suppliers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_chart_of_accounts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NULL,
    code VARCHAR(20) NOT NULL,
    name VARCHAR(200) NOT NULL,
    name_ar VARCHAR(200) NULL,
    account_type ENUM('asset','liability','equity','revenue','expense') NOT NULL DEFAULT 'asset',
    parent_id INT UNSIGNED NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_coa_company_code (company_id, code),
    INDEX idx_coa_company (company_id),
    CONSTRAINT fk_coa_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_journal_entries (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NULL,
    entry_no VARCHAR(50) NOT NULL,
    entry_date DATE NOT NULL,
    description VARCHAR(500) NOT NULL,
    description_ar VARCHAR(500) NULL,
    source_type ENUM('manual','invoice','payment','purchase_order','subscription') NOT NULL DEFAULT 'manual',
    source_id INT UNSIGNED NULL,
    status ENUM('draft','posted','void') NOT NULL DEFAULT 'draft',
    created_by INT UNSIGNED NULL,
    posted_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_journal_company_no (company_id, entry_no),
    INDEX idx_journal_company (company_id),
    INDEX idx_journal_source (source_type, source_id),
    CONSTRAINT fk_journal_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_journal_lines (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    journal_entry_id INT UNSIGNED NOT NULL,
    account_id INT UNSIGNED NOT NULL,
    debit DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    credit DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    memo VARCHAR(255) NULL,
    INDEX idx_jl_entry (journal_entry_id),
    INDEX idx_jl_account (account_id),
    CONSTRAINT fk_jl_entry FOREIGN KEY (journal_entry_id) REFERENCES rateb_journal_entries(id) ON DELETE CASCADE,
    CONSTRAINT fk_jl_account FOREIGN KEY (account_id) REFERENCES rateb_chart_of_accounts(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Human Resources (067 + 068 + 074)
-- -----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS rateb_hr_departments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    name VARCHAR(150) NOT NULL,
    code VARCHAR(40) NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_hr_dept_company (company_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_employees (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    employee_code VARCHAR(40) NOT NULL,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(190) NULL,
    phone VARCHAR(40) NULL,
    national_id VARCHAR(40) NULL,
    department_id INT UNSIGNED NULL,
    job_title VARCHAR(120) NULL,
    hire_date DATE NULL,
    salary_base DECIMAL(12,2) NOT NULL DEFAULT 0,
    user_id INT UNSIGNED NULL,
    status ENUM('active','inactive','terminated') NOT NULL DEFAULT 'active',
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_employee_code (company_id, employee_code),
    INDEX idx_employee_company (company_id),
    INDEX idx_employee_dept (department_id),
    INDEX idx_employee_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_attendance_records (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    employee_id INT UNSIGNED NOT NULL,
    attendance_date DATE NOT NULL,
    check_in TIME NULL,
    check_out TIME NULL,
    status ENUM('present','absent','late','leave','holiday') NOT NULL DEFAULT 'present',
    notes VARCHAR(500) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_attendance_day (company_id, employee_id, attendance_date),
    INDEX idx_attendance_company (company_id),
    INDEX idx_attendance_date (attendance_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_leave_types (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    name VARCHAR(120) NOT NULL,
    paid TINYINT(1) NOT NULL DEFAULT 1,
    days_per_year DECIMAL(5,1) NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_leave_type_company (company_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_leave_requests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    employee_id INT UNSIGNED NOT NULL,
    leave_type_id INT UNSIGNED NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    days DECIMAL(5,1) NOT NULL DEFAULT 1,
    reason TEXT NULL,
    status ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
    approved_by INT UNSIGNED NULL,
    approved_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_leave_req_company (company_id),
    INDEX idx_leave_req_employee (employee_id),
    INDEX idx_leave_req_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_payroll_periods (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    period_year SMALLINT UNSIGNED NOT NULL,
    period_month TINYINT UNSIGNED NOT NULL,
    status ENUM('draft','approved','posted') NOT NULL DEFAULT 'draft',
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_payroll_period (company_id, period_year, period_month),
    INDEX idx_payroll_company (company_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_payroll_lines (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    period_id INT UNSIGNED NOT NULL,
    employee_id INT UNSIGNED NOT NULL,
    basic_salary DECIMAL(12,2) NOT NULL DEFAULT 0,
    allowances DECIMAL(12,2) NOT NULL DEFAULT 0,
    deductions DECIMAL(12,2) NOT NULL DEFAULT 0,
    net_salary DECIMAL(12,2) NOT NULL DEFAULT 0,
    notes VARCHAR(500) NULL,
    UNIQUE KEY uk_payroll_line (period_id, employee_id),
    INDEX idx_payroll_line_company (company_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_leave_balances (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    employee_id INT UNSIGNED NOT NULL,
    leave_type_id INT UNSIGNED NOT NULL,
    balance_year SMALLINT UNSIGNED NOT NULL,
    entitled_days DECIMAL(5,1) NOT NULL DEFAULT 0,
    used_days DECIMAL(5,1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_leave_balance (company_id, employee_id, leave_type_id, balance_year),
    INDEX idx_leave_bal_company (company_id),
    INDEX idx_leave_bal_employee (employee_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_hr_holidays (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    name VARCHAR(150) NOT NULL,
    holiday_date DATE NOT NULL,
    is_recurring TINYINT(1) NOT NULL DEFAULT 0,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    notes VARCHAR(500) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_hr_holiday_company (company_id),
    INDEX idx_hr_holiday_date (holiday_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_hr_workplaces (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    name VARCHAR(150) NOT NULL,
    address VARCHAR(255) NULL,
    latitude DECIMAL(10,7) NULL,
    longitude DECIMAL(10,7) NULL,
    radius_meters INT UNSIGNED NOT NULL DEFAULT 100,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_hr_workplace_company (company_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_hr_permission_requests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    employee_id INT UNSIGNED NOT NULL,
    permission_date DATE NOT NULL,
    time_from TIME NOT NULL,
    time_to TIME NOT NULL,
    reason TEXT NULL,
    status ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
    approved_by INT UNSIGNED NULL,
    approved_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_hr_perm_company (company_id),
    INDEX idx_hr_perm_employee (employee_id),
    INDEX idx_hr_perm_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_hr_loan_types (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    name VARCHAR(120) NOT NULL,
    max_amount DECIMAL(12,2) NULL,
    max_installments SMALLINT UNSIGNED NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_hr_loan_type_company (company_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_hr_loans (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    loan_code VARCHAR(40) NOT NULL,
    employee_id INT UNSIGNED NOT NULL,
    loan_type_id INT UNSIGNED NOT NULL,
    principal DECIMAL(12,2) NOT NULL DEFAULT 0,
    installment_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    installments_count SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    paid_installments SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    start_date DATE NOT NULL,
    status ENUM('active','paid','cancelled') NOT NULL DEFAULT 'active',
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_hr_loan_code (company_id, loan_code),
    INDEX idx_hr_loan_company (company_id),
    INDEX idx_hr_loan_employee (employee_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_hr_payroll_components (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    code VARCHAR(40) NOT NULL,
    name VARCHAR(120) NOT NULL,
    component_type ENUM('allowance','deduction') NOT NULL DEFAULT 'allowance',
    calc_type ENUM('fixed','percent') NOT NULL DEFAULT 'fixed',
    default_value DECIMAL(12,2) NOT NULL DEFAULT 0,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_hr_pay_comp_code (company_id, code),
    INDEX idx_hr_pay_comp_company (company_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_hr_payroll_structures (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    employee_id INT UNSIGNED NOT NULL,
    component_id INT UNSIGNED NOT NULL,
    value DECIMAL(12,2) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_hr_pay_structure (company_id, employee_id, component_id),
    INDEX idx_hr_pay_struct_company (company_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_hr_employee_requests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    request_no VARCHAR(40) NOT NULL,
    employee_id INT UNSIGNED NOT NULL,
    request_type VARCHAR(80) NOT NULL,
    request_date DATE NOT NULL,
    status ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
    processed_by INT UNSIGNED NULL,
    processed_at DATETIME NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_hr_emp_req_no (company_id, request_no),
    INDEX idx_hr_emp_req_company (company_id),
    INDEX idx_hr_emp_req_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_hr_fleet (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    plate_number VARCHAR(20) NOT NULL,
    brand VARCHAR(80) NULL,
    model VARCHAR(80) NULL,
    model_year SMALLINT UNSIGNED NULL,
    assigned_employee_id INT UNSIGNED NULL,
    status ENUM('active','maintenance','inactive') NOT NULL DEFAULT 'active',
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_hr_fleet_plate (company_id, plate_number),
    INDEX idx_hr_fleet_company (company_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_hr_documents (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    employee_id INT UNSIGNED NULL,
    title VARCHAR(200) NOT NULL,
    doc_type VARCHAR(80) NOT NULL DEFAULT 'general',
    issue_date DATE NULL,
    expiry_date DATE NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_hr_doc_company (company_id),
    INDEX idx_hr_doc_employee (employee_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Seed data
-- -----------------------------------------------------------------------------

INSERT INTO rateb_plans (name, slug, description, price_monthly, price_yearly, max_users, max_storage_mb, modules, is_active)
VALUES
('Starter', 'starter', 'Essential procurement for small clinics', 299.00, 2990.00, 5, 512, '["procurement","inventory","suppliers"]', 1),
('Professional', 'professional', 'Full procurement and inventory suite', 799.00, 7990.00, 25, 2048, '["procurement","inventory","suppliers","assets","contracts","reports","accounting","hr"]', 1),
('Enterprise', 'enterprise', 'Complete healthcare ERP with all modules', 1999.00, 19990.00, 100, 10240, '["procurement","inventory","suppliers","assets","contracts","tenders","reports","medical_devices","accounting","hr"]', 1);

INSERT INTO rateb_permissions (name, name_ar, slug, module, description, description_ar) VALUES
('View Dashboard', 'عرض لوحة التحكم', 'dashboard.view', 'dashboard', 'Access dashboard', 'الوصول إلى لوحة التحكم'),
('Manage Companies', 'إدارة الشركات', 'companies.manage', 'companies', 'Full company management', 'إدارة كاملة للشركات'),
('View Companies', 'عرض الشركات', 'companies.view', 'companies', 'View companies', 'عرض قائمة الشركات'),
('Manage Subscriptions', 'إدارة الاشتراكات', 'subscriptions.manage', 'subscriptions', 'Manage subscriptions', 'إدارة اشتراكات الشركات'),
('Manage Plans', 'إدارة الباقات', 'plans.manage', 'plans', 'Manage plans', 'إدارة باقات الاشتراك'),
('Manage Users', 'إدارة المستخدمين', 'users.manage', 'users', 'Manage users', 'إدارة مستخدمي المنصة'),
('Manage Roles', 'إدارة الأدوار', 'roles.manage', 'roles', 'Manage roles', 'إدارة أدوار المستخدمين'),
('Manage Permissions', 'إدارة الصلاحيات', 'permissions.manage', 'permissions', 'Manage permissions', 'إدارة صلاحيات النظام'),
('Manage Procurement', 'إدارة المشتريات', 'procurement.manage', 'procurement', 'Manage procurement', 'إدارة عمليات الشراء'),
('Manage Inventory', 'إدارة المخزون', 'inventory.manage', 'inventory', 'Manage inventory', 'إدارة المخزون والمستودعات'),
('Manage Suppliers', 'إدارة الموردين', 'suppliers.manage', 'suppliers', 'Manage suppliers', 'إدارة سجل الموردين'),
('Manage Assets', 'إدارة الأصول', 'assets.manage', 'assets', 'Manage assets', 'إدارة الأصول الثابتة'),
('Manage Contracts', 'إدارة العقود', 'contracts.manage', 'contracts', 'Manage contracts', 'إدارة العقود'),
('Manage Tenders', 'إدارة المناقصات', 'tenders.manage', 'tenders', 'Manage tenders', 'إدارة المناقصات'),
('View Reports', 'عرض التقارير', 'reports.view', 'reports', 'View reports', 'عرض تقارير المنصة'),
('Manage Settings', 'إدارة الإعدادات', 'settings.manage', 'settings', 'Manage system settings', 'إدارة إعدادات النظام'),
('Manage Supplier Evaluations', 'إدارة تقييم الموردين', 'evaluations.manage', 'suppliers', 'Create and manage supplier evaluations', 'إنشاء وإدارة تقييمات الموردين'),
('View Supplier Evaluations', 'عرض تقييم الموردين', 'evaluations.view', 'suppliers', 'View supplier evaluation records', 'عرض سجلات تقييم الموردين'),
('Manage Company Plans', 'إدارة باقات الشركات', 'company_plans.manage', 'companies', 'Edit company plan limits and modules', 'تعديل حدود الباقة والوحدات للشركات'),
('Manage Access Control', 'إدارة التحكم بالوصول', 'access.manage', 'access', 'Full users, roles, permissions control', 'التحكم الكامل بالمستخدمين والأدوار والصلاحيات'),
('View Accounting', 'عرض الحسابات', 'accounting.view', 'accounting', 'View chart of accounts and journals', 'عرض دليل الحسابات والقيود'),
('Manage Accounting', 'إدارة الحسابات', 'accounting.manage', 'accounting', 'Manage chart of accounts and journal entries', 'إدارة دليل الحسابات والقيود اليومية'),
('Post Journal Entries', 'ترحيل القيود', 'accounting.post', 'accounting', 'Post and void journal entries', 'ترحيل وإلغاء القيود المحاسبية'),
('View HR', 'عرض الموارد البشرية', 'hr.view', 'hr', 'View HR dashboard and employee lists', 'عرض لوحة الموارد البشرية وقوائم الموظفين'),
('Manage HR', 'إدارة الموارد البشرية', 'hr.manage', 'hr', 'Manage HR records, attendance, and payroll', 'إدارة سجلات الموارد البشرية والحضور والرواتب');

INSERT INTO rateb_roles (company_id, name, slug, description, is_system)
SELECT NULL, 'Super Admin', 'super-admin', 'Platform super administrator', 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM rateb_roles WHERE slug = 'super-admin');

INSERT INTO rateb_roles (company_id, name, slug, description, is_system)
SELECT NULL, 'Accountant', 'accountant', 'Accounting and reports access', 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM rateb_roles WHERE slug = 'accountant');

INSERT INTO rateb_roles (company_id, name, slug, description, is_system)
SELECT NULL, 'Access Manager', 'access-manager', 'Users and roles management', 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM rateb_roles WHERE slug = 'access-manager');

INSERT INTO rateb_role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM rateb_roles r CROSS JOIN rateb_permissions p WHERE r.slug = 'super-admin';

INSERT INTO rateb_role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM rateb_roles r
JOIN rateb_permissions p ON p.slug IN ('accounting.view', 'accounting.manage', 'accounting.post', 'reports.view', 'dashboard.view')
WHERE r.slug = 'accountant';

INSERT INTO rateb_role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM rateb_roles r
JOIN rateb_permissions p ON p.slug IN ('access.manage', 'users.manage', 'roles.manage', 'permissions.manage', 'dashboard.view')
WHERE r.slug = 'access-manager';

INSERT INTO rateb_users (company_id, name, email, password, is_super_admin, status, locale)
VALUES (NULL, 'Super Admin', 'admin@rateb.sa', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 'active', 'ar');

INSERT INTO rateb_user_roles (user_id, role_id)
SELECT u.id, r.id FROM rateb_users u JOIN rateb_roles r ON r.slug = 'super-admin' WHERE u.email = 'admin@rateb.sa';

INSERT INTO rateb_system_settings (setting_key, setting_value, setting_group) VALUES
('app_name', 'RATEB ERP', 'general'),
('default_locale', 'ar', 'general'),
('default_currency', 'SAR', 'billing'),
('support_email', 'support@rateb.sa', 'general');

INSERT INTO rateb_email_templates (slug, subject, body_html, body_text, is_active) VALUES
('welcome', 'Welcome to RATEB ERP', '<p>Welcome to RATEB ERP platform.</p>', 'Welcome to RATEB ERP platform.', 1),
('password_reset', 'Password Reset', '<p>Your password reset link.</p>', 'Your password reset link.', 1),
('invoice_sent', 'Invoice {invoice_no} — {company}', '<p>Hello {company},</p><p>Invoice <strong>{invoice_no}</strong> has been issued for <strong>{total} {currency}</strong>.</p><p>Due date: {due_date}</p><p><a href="{preview_url}">View invoice</a></p>', 'Invoice {invoice_no} — {total} {currency} — due {due_date}', 1),
('invoice_due_reminder', 'Reminder: invoice {invoice_no} due soon', '<p>Reminder for invoice <strong>{invoice_no}</strong> — <strong>{total} {currency}</strong>.</p><p>Due date: {due_date}</p>', 'Invoice reminder {invoice_no} — {due_date}', 1),
('invoice_overdue_notice', 'Overdue invoice: {invoice_no}', '<p>Invoice <strong>{invoice_no}</strong> is overdue (due {due_date}).</p><p>Amount due: <strong>{total} {currency}</strong></p>', 'Overdue invoice {invoice_no}', 1);

INSERT INTO rateb_sms_templates (slug, body, is_active) VALUES
('otp', 'Your RATEB verification code is: {code}', 1),
('alert', 'RATEB Alert: {message}', 1);

SET FOREIGN_KEY_CHECKS = 1;
