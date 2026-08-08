-- Phase 10 — CRM Production Hardening (additive / guarded only).
-- No DROP. No data deletion. No duplicate CRM entity tables. No Accounting/Invoice linkage.

-- Quote expiry scan index
SET @idx := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_crm_quotations' AND INDEX_NAME = 'idx_crm_quote_status_valid'
);
SET @sql := IF(
    @idx = 0,
    'CREATE INDEX idx_crm_quote_status_valid ON rateb_crm_quotations (company_id, status, valid_until)',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Automation log lookup for cooldown / dedupe
SET @idx := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_crm_automation_log' AND INDEX_NAME = 'idx_crm_alog_cooldown'
);
SET @sql := IF(
    @idx = 0,
    'CREATE INDEX idx_crm_alog_cooldown ON rateb_crm_automation_log (company_id, event_type, entity_type, entity_id, created_at)',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Opportunity board / inactivity helpers
SET @idx := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_crm_opportunities' AND INDEX_NAME = 'idx_crm_opp_pipe_updated'
);
SET @sql := IF(
    @idx = 0,
    'CREATE INDEX idx_crm_opp_pipe_updated ON rateb_crm_opportunities (company_id, pipeline_id, deleted_at, updated_at)',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Tasks reminder/due scans
SET @idx := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_crm_tasks' AND INDEX_NAME = 'idx_crm_task_reminder'
);
SET @sql := IF(
    @idx = 0,
    'CREATE INDEX idx_crm_task_reminder ON rateb_crm_tasks (company_id, status, reminder_at)',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

INSERT INTO rateb_permissions (name, name_ar, slug, module, description, description_ar) VALUES
('CRM RevOps Run', 'تشغيل أتمتة RevOps', 'crm.revops.run', 'crm', 'Run RevOps automation jobs', 'تشغيل مهام أتمتة عمليات الإيرادات'),
('CRM Insights Manage', 'إدارة رؤى CRM', 'crm.insights.manage', 'crm', 'Manage/dismiss CRM executive insights', 'إدارة وإغلاق رؤى CRM التنفيذية')
ON DUPLICATE KEY UPDATE name = VALUES(name), module = VALUES(module), description = VALUES(description);

INSERT IGNORE INTO rateb_role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM rateb_roles r
JOIN rateb_permissions p ON p.slug IN ('crm.revops.run', 'crm.insights.manage')
WHERE r.slug IN ('company-full-access', 'super-admin');

-- Safer automation defaults (do not overwrite existing company settings)
INSERT IGNORE INTO rateb_crm_governance_settings (public_uuid, company_id, setting_key, setting_json)
SELECT UUID(), c.id, 'automation_safety',
       '{"notification_cooldown_hours":24,"run_lock_minutes":10,"max_notifies_per_run":100,"include_legacy_in_revops":false,"block_always_rules_over_max":true}'
FROM rateb_companies c;
