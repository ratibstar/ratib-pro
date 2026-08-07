-- Phase 7 — CRM Revenue Intelligence + Enterprise Governance (additive only).
-- No DROP. No duplicate classic CRM entity tables. No Accounting/Invoice linkage.

-- Forecast change history + richer snapshot metadata
SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_crm_forecast_snapshots' AND COLUMN_NAME = 'period_type'
);
SET @sql := IF(
    @col_exists = 0,
    'ALTER TABLE rateb_crm_forecast_snapshots
        ADD COLUMN period_type VARCHAR(20) NOT NULL DEFAULT ''month'' AFTER period_key,
        ADD COLUMN team_id INT UNSIGNED NULL AFTER owner_user_id,
        ADD COLUMN confidence_score DECIMAL(5,2) NOT NULL DEFAULT 50.00 AFTER opportunity_count,
        ADD COLUMN forecast_scope VARCHAR(40) NOT NULL DEFAULT ''company'' AFTER confidence_score',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS rateb_crm_forecast_change_log (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_uuid CHAR(36) NOT NULL,
    company_id INT UNSIGNED NOT NULL,
    snapshot_id INT UNSIGNED NULL,
    period_key CHAR(7) NOT NULL,
    period_type VARCHAR(20) NOT NULL DEFAULT 'month',
    change_type VARCHAR(40) NOT NULL DEFAULT 'snapshot',
    from_weighted DECIMAL(18,2) NULL,
    to_weighted DECIMAL(18,2) NULL,
    from_confidence DECIMAL(5,2) NULL,
    to_confidence DECIMAL(5,2) NULL,
    team_id INT UNSIGNED NULL,
    owner_user_id INT UNSIGNED NULL,
    meta_json TEXT NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_crm_fcl_uuid (public_uuid),
    INDEX idx_crm_fcl_period (company_id, period_key, period_type),
    INDEX idx_crm_fcl_snapshot (snapshot_id),
    CONSTRAINT fk_crm_fcl_tenant FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Enterprise governance settings (one logical row set per company)
CREATE TABLE IF NOT EXISTS rateb_crm_governance_settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_uuid CHAR(36) NOT NULL,
    company_id INT UNSIGNED NOT NULL,
    setting_key VARCHAR(60) NOT NULL,
    setting_json TEXT NOT NULL,
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    UNIQUE KEY uq_crm_gov_uuid (public_uuid),
    UNIQUE KEY uq_crm_gov_key (company_id, setting_key),
    INDEX idx_crm_gov_company (company_id),
    CONSTRAINT fk_crm_gov_tenant FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data quality findings
CREATE TABLE IF NOT EXISTS rateb_crm_data_quality_issues (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_uuid CHAR(36) NOT NULL,
    company_id INT UNSIGNED NOT NULL,
    entity_type VARCHAR(40) NOT NULL,
    entity_id INT UNSIGNED NOT NULL,
    issue_code VARCHAR(60) NOT NULL,
    severity VARCHAR(20) NOT NULL DEFAULT 'medium',
    message VARCHAR(255) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'open',
    meta_json TEXT NULL,
    resolved_by INT UNSIGNED NULL,
    resolved_at DATETIME NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_crm_dqi_uuid (public_uuid),
    INDEX idx_crm_dqi_open (company_id, status, severity),
    INDEX idx_crm_dqi_entity (company_id, entity_type, entity_id),
    CONSTRAINT fk_crm_dqi_tenant FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Customer health history (trends; no Subscription/Accounting)
CREATE TABLE IF NOT EXISTS rateb_crm_health_history (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_uuid CHAR(36) NOT NULL,
    company_id INT UNSIGNED NOT NULL,
    customer_id INT UNSIGNED NOT NULL,
    activity_score INT NOT NULL DEFAULT 0,
    engagement_score INT NOT NULL DEFAULT 0,
    health_score INT NOT NULL DEFAULT 0,
    health_status VARCHAR(40) NOT NULL DEFAULT 'unknown',
    renewal_risk VARCHAR(20) NOT NULL DEFAULT 'low',
    meta_json TEXT NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_crm_hh_uuid (public_uuid),
    INDEX idx_crm_hh_customer (company_id, customer_id, created_at),
    CONSTRAINT fk_crm_hh_tenant FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO rateb_permissions (name, name_ar, slug, module, description, description_ar) VALUES
('CRM Governance View', 'عرض حوكمة CRM', 'crm.governance.view', 'crm', 'View CRM governance and data quality', 'عرض حوكمة CRM وجودة البيانات'),
('CRM Governance Manage', 'إدارة حوكمة CRM', 'crm.governance.manage', 'crm', 'Manage CRM governance settings and fixes', 'إدارة إعدادات حوكمة CRM والإصلاحات'),
('CRM Enterprise Forecast', 'توقعات مؤسسية', 'crm.forecast.enterprise', 'crm', 'Manage enterprise CRM forecasts', 'إدارة توقعات CRM المؤسسية'),
('CRM Performance View', 'عرض أداء المبيعات', 'crm.performance.view', 'crm', 'View sales performance management reports', 'عرض تقارير إدارة أداء المبيعات'),
('CRM Revenue Intelligence', 'ذكاء الإيرادات', 'crm.revenue.intel', 'crm', 'View CRM revenue intelligence analytics', 'عرض تحليلات ذكاء إيرادات CRM')
ON DUPLICATE KEY UPDATE name = VALUES(name), module = VALUES(module), description = VALUES(description);

INSERT IGNORE INTO rateb_role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM rateb_roles r
JOIN rateb_permissions p ON p.slug IN (
    'crm.governance.view', 'crm.governance.manage', 'crm.forecast.enterprise',
    'crm.performance.view', 'crm.revenue.intel'
)
WHERE r.slug IN ('company-full-access', 'super-admin');

-- Default governance settings per company
INSERT IGNORE INTO rateb_crm_governance_settings (public_uuid, company_id, setting_key, setting_json)
SELECT UUID(), c.id, 'required_fields', '{"lead":["title","owner_user_id"],"opportunity":["name","owner_user_id","amount","stage_id"],"customer":["name"]}'
FROM rateb_companies c;
INSERT IGNORE INTO rateb_crm_governance_settings (public_uuid, company_id, setting_key, setting_json)
SELECT UUID(), c.id, 'pipeline_rules', '{"require_loss_reason":true,"max_stale_days":21}'
FROM rateb_companies c;
INSERT IGNORE INTO rateb_crm_governance_settings (public_uuid, company_id, setting_key, setting_json)
SELECT UUID(), c.id, 'automation_governance', '{"require_condition_json":true,"max_always_rules":3}'
FROM rateb_companies c;
INSERT IGNORE INTO rateb_crm_governance_settings (public_uuid, company_id, setting_key, setting_json)
SELECT UUID(), c.id, 'export_policy', '{"allow_csv":true,"require_permission":"crm.export.manage","audit_required":true}'
FROM rateb_companies c;
