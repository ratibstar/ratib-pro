-- Phase 9 — CRM Intelligence readiness + Enterprise Optimization (additive only).
-- No DROP. Rules/heuristics only (no third-party model APIs). No Accounting/Invoice linkage.
-- No duplicate CRM entity tables.

-- Configurable predictive rules (rules engine only)
CREATE TABLE IF NOT EXISTS rateb_crm_predictive_rules (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_uuid CHAR(36) NOT NULL,
    company_id INT UNSIGNED NOT NULL,
    rule_key VARCHAR(60) NOT NULL,
    name VARCHAR(120) NOT NULL,
    rule_type VARCHAR(40) NOT NULL,
    config_json TEXT NOT NULL,
    priority INT NOT NULL DEFAULT 100,
    is_enabled TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    UNIQUE KEY uq_crm_pr_uuid (public_uuid),
    UNIQUE KEY uq_crm_pr_key (company_id, rule_key),
    INDEX idx_crm_pr_type (company_id, rule_type, is_enabled),
    CONSTRAINT fk_crm_pr_tenant FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Executive / intelligence insight cards
CREATE TABLE IF NOT EXISTS rateb_crm_intelligence_insights (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_uuid CHAR(36) NOT NULL,
    company_id INT UNSIGNED NOT NULL,
    insight_type VARCHAR(40) NOT NULL,
    severity VARCHAR(20) NOT NULL DEFAULT 'info',
    title VARCHAR(190) NOT NULL,
    body VARCHAR(500) NULL,
    entity_type VARCHAR(40) NULL,
    entity_id INT UNSIGNED NULL,
    score DECIMAL(8,2) NULL,
    meta_json TEXT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'open',
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    dismissed_at DATETIME NULL,
    UNIQUE KEY uq_crm_ii_uuid (public_uuid),
    INDEX idx_crm_ii_open (company_id, status, severity),
    INDEX idx_crm_ii_type (company_id, insight_type, created_at),
    CONSTRAINT fk_crm_ii_tenant FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Duplicate merge workflow (audit trail)
CREATE TABLE IF NOT EXISTS rateb_crm_merge_requests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_uuid CHAR(36) NOT NULL,
    company_id INT UNSIGNED NOT NULL,
    entity_type VARCHAR(40) NOT NULL,
    source_id INT UNSIGNED NOT NULL,
    target_id INT UNSIGNED NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    reason VARCHAR(255) NULL,
    merge_json TEXT NULL,
    created_by INT UNSIGNED NULL,
    resolved_by INT UNSIGNED NULL,
    resolved_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_crm_mr_uuid (public_uuid),
    INDEX idx_crm_mr_status (company_id, status, entity_type),
    INDEX idx_crm_mr_pair (company_id, entity_type, source_id, target_id),
    CONSTRAINT fk_crm_mr_tenant FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data freshness snapshots
CREATE TABLE IF NOT EXISTS rateb_crm_freshness_snapshots (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_uuid CHAR(36) NOT NULL,
    company_id INT UNSIGNED NOT NULL,
    freshness_score DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    stale_leads INT NOT NULL DEFAULT 0,
    stale_opportunities INT NOT NULL DEFAULT 0,
    stale_customers INT NOT NULL DEFAULT 0,
    meta_json TEXT NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_crm_fs_uuid (public_uuid),
    INDEX idx_crm_fs_company (company_id, created_at),
    CONSTRAINT fk_crm_fs_tenant FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Performance indexes (additive; skip if already present)
SET @idx := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_crm_opportunities' AND INDEX_NAME = 'idx_crm_opp_stale_status');
SET @sql := IF(@idx = 0, 'CREATE INDEX idx_crm_opp_stale_status ON rateb_crm_opportunities (company_id, workflow_status, is_stale, updated_at)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_crm_opportunities' AND INDEX_NAME = 'idx_crm_opp_intel_score');
SET @sql := IF(@idx = 0, 'CREATE INDEX idx_crm_opp_intel_score ON rateb_crm_opportunities (company_id, intelligence_score, risk_level)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_crm_leads' AND INDEX_NAME = 'idx_crm_lead_email');
SET @sql := IF(@idx = 0, 'CREATE INDEX idx_crm_lead_email ON rateb_crm_leads (company_id, email)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_crm_leads' AND INDEX_NAME = 'idx_crm_lead_phone');
SET @sql := IF(@idx = 0, 'CREATE INDEX idx_crm_lead_phone ON rateb_crm_leads (company_id, phone)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_crm_activities' AND INDEX_NAME = 'idx_crm_act_opp_time');
SET @sql := IF(@idx = 0, 'CREATE INDEX idx_crm_act_opp_time ON rateb_crm_activities (company_id, opportunity_id, activity_at)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_crm_activities' AND INDEX_NAME = 'idx_crm_act_owner_time');
SET @sql := IF(@idx = 0, 'CREATE INDEX idx_crm_act_owner_time ON rateb_crm_activities (company_id, owner_user_id, activity_at)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_crm_contacts' AND INDEX_NAME = 'idx_crm_ct_email');
SET @sql := IF(@idx = 0, 'CREATE INDEX idx_crm_ct_email ON rateb_crm_contacts (company_id, email)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_customers' AND COLUMN_NAME = 'crm_renewal_risk');
SET @idx := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_customers' AND INDEX_NAME = 'idx_cust_crm_risk');
SET @sql := IF(@col > 0 AND @idx = 0, 'CREATE INDEX idx_cust_crm_risk ON rateb_customers (company_id, crm_renewal_risk, crm_health_score)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

INSERT INTO rateb_permissions (name, name_ar, slug, module, description, description_ar) VALUES
('CRM Insights View', 'عرض رؤى CRM', 'crm.insights.view', 'crm', 'View executive CRM insights', 'عرض رؤى CRM التنفيذية'),
('CRM Predictive Manage', 'إدارة القواعد التنبؤية', 'crm.predictive.manage', 'crm', 'Manage CRM predictive rules', 'إدارة قواعد CRM التنبؤية'),
('CRM Merge Manage', 'إدارة دمج التكرارات', 'crm.merge.manage', 'crm', 'Manage CRM duplicate merge workflow', 'إدارة سير عمل دمج تكرارات CRM'),
('CRM Intelligence Advanced', 'ذكاء CRM المتقدم', 'crm.intelligence.advanced', 'crm', 'Run advanced CRM intelligence layer', 'تشغيل طبقة ذكاء CRM المتقدمة')
ON DUPLICATE KEY UPDATE name = VALUES(name), module = VALUES(module), description = VALUES(description);

INSERT IGNORE INTO rateb_role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM rateb_roles r
JOIN rateb_permissions p ON p.slug IN (
    'crm.insights.view', 'crm.predictive.manage', 'crm.merge.manage', 'crm.intelligence.advanced'
)
WHERE r.slug IN ('company-full-access', 'super-admin');

-- Default predictive rules per company
INSERT IGNORE INTO rateb_crm_predictive_rules (public_uuid, company_id, rule_key, name, rule_type, config_json, priority)
SELECT UUID(), c.id, 'high_probability', 'High probability opportunities', 'high_probability',
       '{"min_probability":70,"min_intelligence":60,"min_engagement":40}', 10
FROM rateb_companies c;
INSERT IGNORE INTO rateb_crm_predictive_rules (public_uuid, company_id, rule_key, name, rule_type, config_json, priority)
SELECT UUID(), c.id, 'stale_pipeline', 'Stale pipeline detection', 'stale_pipeline',
       '{"stale_days":14,"stage_days":21}', 20
FROM rateb_companies c;
INSERT IGNORE INTO rateb_crm_predictive_rules (public_uuid, company_id, rule_key, name, rule_type, config_json, priority)
SELECT UUID(), c.id, 'churn_risk', 'Customer churn risk', 'churn_risk',
       '{"risk_levels":["high","critical"],"max_health_score":40}', 30
FROM rateb_companies c;
INSERT IGNORE INTO rateb_crm_predictive_rules (public_uuid, company_id, rule_key, name, rule_type, config_json, priority)
SELECT UUID(), c.id, 'follow_up_priority', 'Follow-up priority', 'follow_up_priority',
       '{"overdue_hours":24,"high_value_amount":50000}', 40
FROM rateb_companies c;

INSERT IGNORE INTO rateb_crm_governance_settings (public_uuid, company_id, setting_key, setting_json)
SELECT UUID(), c.id, 'intelligence_thresholds', '{"anomaly_amount_multiplier":3,"score_drop_alert":20,"freshness_stale_days":30}'
FROM rateb_companies c;
