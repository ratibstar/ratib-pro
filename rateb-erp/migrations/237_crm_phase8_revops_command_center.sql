-- Phase 8 — CRM Enterprise Platform Finalization + RevOps Command Center (additive only).
-- No DROP. No duplicate classic CRM entity tables. No Accounting/Invoice linkage.

-- Stage-level workflow governance (extends pipeline stages; not a separate workflow engine)
CREATE TABLE IF NOT EXISTS rateb_crm_stage_governance_rules (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_uuid CHAR(36) NOT NULL,
    company_id INT UNSIGNED NOT NULL,
    pipeline_id INT UNSIGNED NULL,
    stage_id INT UNSIGNED NOT NULL,
    required_fields_json TEXT NULL,
    required_actions_json TEXT NULL,
    approval_required TINYINT(1) NOT NULL DEFAULT 0,
    ownership_required TINYINT(1) NOT NULL DEFAULT 1,
    sla_hours INT UNSIGNED NULL,
    is_enabled TINYINT(1) NOT NULL DEFAULT 1,
    meta_json TEXT NULL,
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    UNIQUE KEY uq_crm_sgr_uuid (public_uuid),
    UNIQUE KEY uq_crm_sgr_stage (company_id, stage_id),
    INDEX idx_crm_sgr_pipeline (company_id, pipeline_id),
    CONSTRAINT fk_crm_sgr_tenant FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data quality score trend history
CREATE TABLE IF NOT EXISTS rateb_crm_quality_snapshots (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_uuid CHAR(36) NOT NULL,
    company_id INT UNSIGNED NOT NULL,
    completeness_score DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    quality_score DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    open_issues INT NOT NULL DEFAULT 0,
    resolved_issues INT NOT NULL DEFAULT 0,
    duplicate_count INT NOT NULL DEFAULT 0,
    missing_count INT NOT NULL DEFAULT 0,
    ownership_gaps INT NOT NULL DEFAULT 0,
    meta_json TEXT NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_crm_qs_uuid (public_uuid),
    INDEX idx_crm_qs_company (company_id, created_at),
    CONSTRAINT fk_crm_qs_tenant FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Saved RevOps / executive dashboards
CREATE TABLE IF NOT EXISTS rateb_crm_saved_dashboards (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_uuid CHAR(36) NOT NULL,
    company_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NULL,
    name VARCHAR(120) NOT NULL,
    role_key VARCHAR(40) NOT NULL DEFAULT 'executive',
    layout_json TEXT NULL,
    filters_json TEXT NULL,
    is_shared TINYINT(1) NOT NULL DEFAULT 0,
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    UNIQUE KEY uq_crm_sd_uuid (public_uuid),
    INDEX idx_crm_sd_company (company_id, role_key),
    INDEX idx_crm_sd_user (company_id, user_id),
    CONSTRAINT fk_crm_sd_tenant FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Scheduled reports (no new email provider — due markers + audit only)
CREATE TABLE IF NOT EXISTS rateb_crm_scheduled_reports (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_uuid CHAR(36) NOT NULL,
    company_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NULL,
    name VARCHAR(120) NOT NULL,
    report_key VARCHAR(60) NOT NULL DEFAULT 'funnel',
    frequency VARCHAR(20) NOT NULL DEFAULT 'weekly',
    filters_json TEXT NULL,
    is_enabled TINYINT(1) NOT NULL DEFAULT 1,
    next_run_at DATETIME NULL,
    last_run_at DATETIME NULL,
    last_status VARCHAR(40) NULL,
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    UNIQUE KEY uq_crm_sr_uuid (public_uuid),
    INDEX idx_crm_sr_due (company_id, is_enabled, next_run_at),
    CONSTRAINT fk_crm_sr_tenant FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Resolution note column on quality issues (additive)
SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_crm_data_quality_issues' AND COLUMN_NAME = 'resolution_note'
);
SET @sql := IF(
    @col_exists = 0,
    'ALTER TABLE rateb_crm_data_quality_issues ADD COLUMN resolution_note VARCHAR(255) NULL AFTER resolved_at',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

INSERT INTO rateb_permissions (name, name_ar, slug, module, description, description_ar) VALUES
('CRM RevOps Command Center', 'مركز عمليات الإيرادات', 'crm.revops.view', 'crm', 'View RevOps command center', 'عرض مركز عمليات الإيرادات'),
('CRM Executive Cockpit', 'لوحة الإدارة العليا CRM', 'crm.cockpit.view', 'crm', 'View executive CRM cockpit', 'عرض لوحة الإدارة العليا لـ CRM'),
('CRM Unified Search', 'بحث CRM الموحد', 'crm.search.view', 'crm', 'Use unified CRM search', 'استخدام بحث CRM الموحد'),
('CRM Reporting Center', 'مركز تقارير CRM', 'crm.reporting.center', 'crm', 'Manage saved dashboards and scheduled reports', 'إدارة لوحات وتقارير CRM المجدولة'),
('CRM Workflow Governance', 'حوكمة مسار CRM', 'crm.workflow.governance', 'crm', 'Manage CRM stage workflow governance rules', 'إدارة قواعد حوكمة مراحل CRM')
ON DUPLICATE KEY UPDATE name = VALUES(name), module = VALUES(module), description = VALUES(description);

INSERT IGNORE INTO rateb_role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM rateb_roles r
JOIN rateb_permissions p ON p.slug IN (
    'crm.revops.view', 'crm.cockpit.view', 'crm.search.view',
    'crm.reporting.center', 'crm.workflow.governance'
)
WHERE r.slug IN ('company-full-access', 'super-admin');

INSERT IGNORE INTO rateb_crm_governance_settings (public_uuid, company_id, setting_key, setting_json)
SELECT UUID(), c.id, 'workflow_governance', '{"enforce_on_stage_move":true,"default_ownership_required":true,"default_sla_hours":48}'
FROM rateb_companies c;

INSERT IGNORE INTO rateb_crm_governance_settings (public_uuid, company_id, setting_key, setting_json)
SELECT UUID(), c.id, 'duplicate_rules', '{"match_email":true,"match_phone":true,"match_company_name":true}'
FROM rateb_companies c;

INSERT IGNORE INTO rateb_crm_governance_settings (public_uuid, company_id, setting_key, setting_json)
SELECT UUID(), c.id, 'revops_alerts', '{"forecast_confidence_min":40,"sla_breach_hours":48,"customer_risk_levels":["high","critical"]}'
FROM rateb_companies c;
