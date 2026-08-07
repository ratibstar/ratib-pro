-- Phase 6 — CRM Enterprise Intelligence + Sales Execution (additive only).
-- No DROP. No duplicate classic CRM entity tables.

-- Opportunity intelligence scores (rules-based, no external AI)
SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_crm_opportunities' AND COLUMN_NAME = 'intelligence_score'
);
SET @sql := IF(
    @col_exists = 0,
    'ALTER TABLE rateb_crm_opportunities
        ADD COLUMN intelligence_score INT NOT NULL DEFAULT 0 AFTER probability_percent,
        ADD COLUMN engagement_score INT NOT NULL DEFAULT 0 AFTER intelligence_score,
        ADD COLUMN risk_level VARCHAR(20) NOT NULL DEFAULT ''low'' AFTER engagement_score,
        ADD COLUMN recommended_probability DECIMAL(5,2) NULL AFTER risk_level,
        ADD COLUMN is_stale TINYINT(1) NOT NULL DEFAULT 0 AFTER recommended_probability,
        ADD COLUMN score_updated_at DATETIME NULL AFTER is_stale',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Customer health on existing master (no Subscription/Accounting)
SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_customers' AND COLUMN_NAME = 'crm_engagement_score'
);
SET @sql := IF(
    @col_exists = 0,
    'ALTER TABLE rateb_customers
        ADD COLUMN crm_engagement_score INT NOT NULL DEFAULT 0 AFTER crm_activity_score,
        ADD COLUMN crm_health_score INT NOT NULL DEFAULT 0 AFTER crm_engagement_score,
        ADD COLUMN crm_health_status VARCHAR(40) NOT NULL DEFAULT ''unknown'' AFTER crm_health_score,
        ADD COLUMN crm_renewal_risk VARCHAR(20) NOT NULL DEFAULT ''low'' AFTER crm_health_status',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Automation rules engine: structured conditions + actions (reuse existing rules table)
SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_crm_automation_rules' AND COLUMN_NAME = 'condition_json'
);
SET @sql := IF(
    @col_exists = 0,
    'ALTER TABLE rateb_crm_automation_rules
        ADD COLUMN condition_json TEXT NULL AFTER config_json,
        ADD COLUMN action_json TEXT NULL AFTER condition_json',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Score change audit trail
CREATE TABLE IF NOT EXISTS rateb_crm_score_history (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_uuid CHAR(36) NOT NULL,
    company_id INT UNSIGNED NOT NULL,
    entity_type VARCHAR(40) NOT NULL,
    entity_id INT UNSIGNED NOT NULL,
    score_type VARCHAR(40) NOT NULL,
    from_value VARCHAR(40) NULL,
    to_value VARCHAR(40) NOT NULL,
    meta_json TEXT NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_crm_score_uuid (public_uuid),
    INDEX idx_crm_score_entity (company_id, entity_type, entity_id, created_at),
    INDEX idx_crm_score_type (company_id, score_type),
    CONSTRAINT fk_crm_score_tenant FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Saved report filters
CREATE TABLE IF NOT EXISTS rateb_crm_saved_report_filters (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_uuid CHAR(36) NOT NULL,
    company_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    name VARCHAR(160) NOT NULL,
    report_key VARCHAR(60) NOT NULL DEFAULT 'reports',
    filters_json TEXT NOT NULL,
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    UNIQUE KEY uq_crm_srf_uuid (public_uuid),
    INDEX idx_crm_srf_user (company_id, user_id, report_key),
    CONSTRAINT fk_crm_srf_tenant FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO rateb_permissions (name, name_ar, slug, module, description, description_ar) VALUES
('CRM Workspace View', 'عرض مساحة عمل المبيعات', 'crm.workspace.view', 'crm', 'View sales execution workspace', 'عرض مساحة تنفيذ المبيعات'),
('CRM Intelligence View', 'عرض ذكاء الفرص', 'crm.intelligence.view', 'crm', 'View opportunity/activity intelligence', 'عرض ذكاء الفرص والأنشطة'),
('CRM Dashboards View', 'عرض لوحات CRM', 'crm.dashboards.view', 'crm', 'View advanced CRM dashboards', 'عرض لوحات CRM المتقدمة'),
('CRM Export Manage', 'تصدير تقارير CRM', 'crm.export.manage', 'crm', 'Export CRM reports and manage saved filters', 'تصدير تقارير CRM وإدارة الفلاتر المحفوظة')
ON DUPLICATE KEY UPDATE name = VALUES(name), module = VALUES(module), description = VALUES(description);

INSERT IGNORE INTO rateb_role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM rateb_roles r
JOIN rateb_permissions p ON p.slug IN (
    'crm.workspace.view', 'crm.intelligence.view', 'crm.dashboards.view', 'crm.export.manage'
)
WHERE r.slug IN ('company-full-access', 'super-admin');

-- Default structured conditions/actions for existing rules (idempotent best-effort)
UPDATE rateb_crm_automation_rules
SET condition_json = COALESCE(condition_json, '{"type":"always"}'),
    action_json = COALESCE(action_json, '{"type":"notify"}')
WHERE deleted_at IS NULL;
