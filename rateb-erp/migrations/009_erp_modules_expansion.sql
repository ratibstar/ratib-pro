-- RATEB ERP — Module expansion (procurement, inventory, suppliers, contracts, assets, devices, workflows, documents, phase2)
SET NAMES utf8mb4;

-- ---------------------------------------------------------------------------
-- Inventory enhancements
-- ---------------------------------------------------------------------------
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_inventory' AND COLUMN_NAME = 'barcode');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_inventory
        ADD COLUMN category_id INT UNSIGNED NULL AFTER category,
        ADD COLUMN barcode VARCHAR(80) NULL AFTER sku,
        ADD COLUMN qr_code VARCHAR(255) NULL AFTER barcode,
        ADD COLUMN min_stock DECIMAL(12,3) NOT NULL DEFAULT 0.000 AFTER reorder_level,
        ADD COLUMN max_stock DECIMAL(12,3) NULL AFTER min_stock,
        ADD INDEX idx_inv_barcode (barcode)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS rateb_product_categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    name VARCHAR(150) NOT NULL,
    name_ar VARCHAR(150) NULL,
    parent_id INT UNSIGNED NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pc_company (company_id),
    CONSTRAINT fk_pc_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_inventory_batches (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    inventory_id INT UNSIGNED NOT NULL,
    batch_no VARCHAR(80) NOT NULL,
    quantity DECIMAL(12,3) NOT NULL DEFAULT 0.000,
    expiry_date DATE NULL,
    warehouse_id INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_batch_company (company_id),
    INDEX idx_batch_inv (inventory_id),
    CONSTRAINT fk_batch_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_batch_inv FOREIGN KEY (inventory_id) REFERENCES rateb_inventory(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_inventory_audits (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    warehouse_id INT UNSIGNED NULL,
    audit_no VARCHAR(50) NOT NULL,
    audit_date DATE NOT NULL,
    status ENUM('draft','in_progress','completed','cancelled') NOT NULL DEFAULT 'draft',
    notes TEXT NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_audit_company_no (company_id, audit_no),
    CONSTRAINT fk_audit_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_inventory_audit_lines (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    audit_id INT UNSIGNED NOT NULL,
    inventory_id INT UNSIGNED NOT NULL,
    system_qty DECIMAL(12,3) NOT NULL DEFAULT 0.000,
    counted_qty DECIMAL(12,3) NOT NULL DEFAULT 0.000,
    variance DECIMAL(12,3) NOT NULL DEFAULT 0.000,
    notes VARCHAR(255) NULL,
    CONSTRAINT fk_audit_line_audit FOREIGN KEY (audit_id) REFERENCES rateb_inventory_audits(id) ON DELETE CASCADE,
    CONSTRAINT fk_audit_line_inv FOREIGN KEY (inventory_id) REFERENCES rateb_inventory(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Procurement line items & tender comparison
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS rateb_purchase_request_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    purchase_request_id INT UNSIGNED NOT NULL,
    item_name VARCHAR(255) NOT NULL,
    sku VARCHAR(80) NULL,
    quantity DECIMAL(12,3) NOT NULL DEFAULT 1.000,
    unit VARCHAR(30) NOT NULL DEFAULT 'unit',
    unit_price DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    total_price DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pri_company (company_id),
    CONSTRAINT fk_pri_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_pri_pr FOREIGN KEY (purchase_request_id) REFERENCES rateb_purchase_requests(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_tender_comparisons (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    tender_id INT UNSIGNED NOT NULL,
    supplier_id INT UNSIGNED NOT NULL,
    quotation_id INT UNSIGNED NULL,
    rank_order TINYINT UNSIGNED NOT NULL DEFAULT 0,
    score DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tc_company (company_id),
    CONSTRAINT fk_tc_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_tc_tender FOREIGN KEY (tender_id) REFERENCES rateb_tenders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Approval workflows
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS rateb_approval_workflows (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NULL,
    name VARCHAR(150) NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_aw_company (company_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_approval_workflow_steps (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    workflow_id INT UNSIGNED NOT NULL,
    step_order TINYINT UNSIGNED NOT NULL DEFAULT 1,
    role_id INT UNSIGNED NULL,
    approver_user_id INT UNSIGNED NULL,
    label VARCHAR(120) NOT NULL,
    CONSTRAINT fk_aws_workflow FOREIGN KEY (workflow_id) REFERENCES rateb_approval_workflows(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_approval_instances (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    workflow_id INT UNSIGNED NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    entity_id INT UNSIGNED NOT NULL,
    current_step TINYINT UNSIGNED NOT NULL DEFAULT 1,
    status ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
    submitted_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ai_entity (entity_type, entity_id),
    CONSTRAINT fk_ai_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_approval_actions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    instance_id INT UNSIGNED NOT NULL,
    step_order TINYINT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NULL,
    action ENUM('submit','approve','reject','comment') NOT NULL,
    comment TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_aa_instance FOREIGN KEY (instance_id) REFERENCES rateb_approval_instances(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Document management
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS rateb_documents (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    entity_id INT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    mime_type VARCHAR(120) NULL,
    file_size INT UNSIGNED NOT NULL DEFAULT 0,
    uploaded_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_doc_entity (company_id, entity_type, entity_id),
    CONSTRAINT fk_doc_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Supplier extensions
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS rateb_supplier_classifications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(80) NOT NULL,
    color VARCHAR(20) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_sc_company_slug (company_id, slug),
    CONSTRAINT fk_sc_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_suppliers' AND COLUMN_NAME = 'classification_id');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_suppliers ADD COLUMN classification_id INT UNSIGNED NULL AFTER rating, ADD COLUMN performance_kpi DECIMAL(5,2) NULL AFTER classification_id',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS rateb_supplier_communications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    supplier_id INT UNSIGNED NOT NULL,
    channel ENUM('email','phone','meeting','note') NOT NULL DEFAULT 'note',
    subject VARCHAR(255) NULL,
    body TEXT NOT NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_scomm_supplier (supplier_id),
    CONSTRAINT fk_scomm_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_scomm_supplier FOREIGN KEY (supplier_id) REFERENCES rateb_suppliers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Contract extensions
-- ---------------------------------------------------------------------------
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_contracts' AND COLUMN_NAME = 'renewal_date');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_contracts
        ADD COLUMN renewal_date DATE NULL AFTER end_date,
        ADD COLUMN alert_days INT UNSIGNED NOT NULL DEFAULT 30 AFTER renewal_date,
        ADD COLUMN approval_status ENUM(''draft'',''pending'',''approved'',''rejected'') NOT NULL DEFAULT ''draft'' AFTER status',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS rateb_contract_renewals (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    contract_id INT UNSIGNED NOT NULL,
    renewal_date DATE NOT NULL,
    new_end_date DATE NULL,
    new_value DECIMAL(14,2) NULL,
    status ENUM('planned','completed','cancelled') NOT NULL DEFAULT 'planned',
    notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_cr_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_cr_contract FOREIGN KEY (contract_id) REFERENCES rateb_contracts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Asset extensions
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS rateb_asset_categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    name VARCHAR(150) NOT NULL,
    name_ar VARCHAR(150) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_acat_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_assets' AND COLUMN_NAME = 'category_id');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_assets ADD COLUMN category_id INT UNSIGNED NULL AFTER category, ADD COLUMN assigned_to VARCHAR(150) NULL AFTER location',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS rateb_asset_assignments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    asset_id INT UNSIGNED NOT NULL,
    assigned_to VARCHAR(150) NOT NULL,
    department VARCHAR(120) NULL,
    assigned_at DATE NOT NULL,
    returned_at DATE NULL,
    notes TEXT NULL,
    CONSTRAINT fk_aas_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_aas_asset FOREIGN KEY (asset_id) REFERENCES rateb_assets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_asset_maintenance (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    asset_id INT UNSIGNED NOT NULL,
    maintenance_type VARCHAR(80) NOT NULL,
    scheduled_date DATE NULL,
    completed_date DATE NULL,
    cost DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    status ENUM('scheduled','in_progress','completed','cancelled') NOT NULL DEFAULT 'scheduled',
    notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_am_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_am_asset FOREIGN KEY (asset_id) REFERENCES rateb_assets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_asset_depreciation (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    asset_id INT UNSIGNED NOT NULL,
    period_date DATE NOT NULL,
    amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    book_value DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_ad_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_ad_asset FOREIGN KEY (asset_id) REFERENCES rateb_assets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Medical device extensions
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS rateb_device_categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    name VARCHAR(150) NOT NULL,
    name_ar VARCHAR(150) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_dcat_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_medical_devices' AND COLUMN_NAME = 'category_id');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_medical_devices ADD COLUMN category_id INT UNSIGNED NULL AFTER device_name, ADD COLUMN warranty_expiry DATE NULL AFTER maintenance_due',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS rateb_device_service_history (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    device_id INT UNSIGNED NOT NULL,
    service_date DATE NOT NULL,
    service_type VARCHAR(80) NOT NULL,
    provider VARCHAR(150) NULL,
    cost DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_dsh_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_dsh_device FOREIGN KEY (device_id) REFERENCES rateb_medical_devices(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_device_spare_parts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    device_id INT UNSIGNED NOT NULL,
    part_name VARCHAR(200) NOT NULL,
    part_no VARCHAR(80) NULL,
    quantity DECIMAL(12,3) NOT NULL DEFAULT 0.000,
    reorder_level DECIMAL(12,3) NOT NULL DEFAULT 0.000,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_dsp_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_dsp_device FOREIGN KEY (device_id) REFERENCES rateb_medical_devices(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Auth, notifications queue
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS rateb_password_resets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    token_hash VARCHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pr_user (user_id),
    CONSTRAINT fk_pr_user FOREIGN KEY (user_id) REFERENCES rateb_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_notification_queue (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NULL,
    user_id INT UNSIGNED NULL,
    channel ENUM('email','sms','in_app') NOT NULL DEFAULT 'in_app',
    template_slug VARCHAR(80) NULL,
    recipient VARCHAR(190) NULL,
    subject VARCHAR(255) NULL,
    body TEXT NOT NULL,
    status ENUM('pending','sent','failed') NOT NULL DEFAULT 'pending',
    sent_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_nq_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_notifications' AND COLUMN_NAME = 'trigger_type');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_notifications ADD COLUMN trigger_type VARCHAR(50) NULL AFTER type, ADD COLUMN entity_type VARCHAR(50) NULL AFTER trigger_type, ADD COLUMN entity_id INT UNSIGNED NULL AFTER entity_type',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------------
-- Phase 2 modules (schema only — disabled by default via system_settings)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS rateb_lims_samples (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    sample_no VARCHAR(50) NOT NULL,
    patient_ref VARCHAR(100) NULL,
    sample_type VARCHAR(80) NOT NULL,
    status ENUM('received','processing','completed','cancelled') NOT NULL DEFAULT 'received',
    received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_lims_sample (company_id, sample_no),
    CONSTRAINT fk_lims_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_lims_results (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sample_id INT UNSIGNED NOT NULL,
    test_name VARCHAR(150) NOT NULL,
    result_value VARCHAR(255) NULL,
    unit VARCHAR(30) NULL,
    status ENUM('pending','verified','released') NOT NULL DEFAULT 'pending',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_lims_result_sample FOREIGN KEY (sample_id) REFERENCES rateb_lims_samples(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_blood_donors (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    donor_no VARCHAR(50) NOT NULL,
    name VARCHAR(150) NOT NULL,
    blood_group VARCHAR(5) NOT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_blood_donor (company_id, donor_no),
    CONSTRAINT fk_bd_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_blood_units (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    unit_no VARCHAR(50) NOT NULL,
    blood_group VARCHAR(5) NOT NULL,
    donor_id INT UNSIGNED NULL,
    collected_at DATETIME NOT NULL,
    expiry_at DATETIME NOT NULL,
    status ENUM('available','reserved','used','expired','discarded') NOT NULL DEFAULT 'available',
    UNIQUE KEY uq_blood_unit (company_id, unit_no),
    CONSTRAINT fk_bu_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_pharmacy_prescriptions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    prescription_no VARCHAR(50) NOT NULL,
    patient_ref VARCHAR(100) NULL,
    status ENUM('pending','dispensed','cancelled') NOT NULL DEFAULT 'pending',
    prescribed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_rx (company_id, prescription_no),
    CONSTRAINT fk_rx_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_pharmacy_dispenses (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    prescription_id INT UNSIGNED NOT NULL,
    drug_name VARCHAR(200) NOT NULL,
    quantity DECIMAL(12,3) NOT NULL DEFAULT 1.000,
    dispensed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_disp_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_disp_rx FOREIGN KEY (prescription_id) REFERENCES rateb_pharmacy_prescriptions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO rateb_system_settings (setting_key, setting_value, setting_group) VALUES
('phase2_modules', '["lims","blood_bank","pharmacy"]', 'modules'),
('phase2_enabled', '0', 'modules'),
('smtp_from_email', 'noreply@rateb.sa', 'mail'),
('smtp_from_name', 'RTAB ERP', 'mail')
ON DUPLICATE KEY UPDATE setting_key = setting_key;
