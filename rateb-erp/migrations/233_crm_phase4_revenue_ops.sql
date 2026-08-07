-- Phase 4 — CRM + Revenue Operations (additive only).
-- No DROP. No duplicate classic CRM entity tables.
-- Guarded ADD COLUMN for quotation intelligence fields.

CREATE TABLE IF NOT EXISTS rateb_crm_revenue_events (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_uuid CHAR(36) NOT NULL,
    company_id INT UNSIGNED NOT NULL,
    event_type VARCHAR(60) NOT NULL,
    amount DECIMAL(18,2) NOT NULL DEFAULT 0,
    currency_code CHAR(3) NOT NULL DEFAULT 'SAR',
    lead_id INT UNSIGNED NULL,
    opportunity_id INT UNSIGNED NULL,
    quotation_id INT UNSIGNED NULL,
    customer_id INT UNSIGNED NULL,
    period_key CHAR(7) NOT NULL,
    meta_json TEXT NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_crm_rev_uuid (public_uuid),
    INDEX idx_crm_rev_company (company_id, period_key),
    INDEX idx_crm_rev_customer (company_id, customer_id),
    INDEX idx_crm_rev_quote (quotation_id),
    CONSTRAINT fk_crm_rev_tenant FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_crm_forecast_snapshots (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_uuid CHAR(36) NOT NULL,
    company_id INT UNSIGNED NOT NULL,
    period_key CHAR(7) NOT NULL,
    pipeline_id INT UNSIGNED NULL,
    owner_user_id INT UNSIGNED NULL,
    open_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
    weighted_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
    won_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
    lost_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
    opportunity_count INT UNSIGNED NOT NULL DEFAULT 0,
    meta_json TEXT NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_crm_fc_uuid (public_uuid),
    INDEX idx_crm_fc_period (company_id, period_key),
    INDEX idx_crm_fc_owner (company_id, owner_user_id, period_key),
    CONSTRAINT fk_crm_fc_tenant FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_crm_activity_types (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_uuid CHAR(36) NOT NULL,
    company_id INT UNSIGNED NOT NULL,
    code VARCHAR(40) NOT NULL,
    name VARCHAR(160) NOT NULL,
    name_ar VARCHAR(160) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    UNIQUE KEY uq_crm_atype_uuid (public_uuid),
    UNIQUE KEY uq_crm_atype_code (company_id, code),
    INDEX idx_crm_atype_company (company_id, is_active),
    CONSTRAINT fk_crm_atype_tenant FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_crm_automation_rules (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_uuid CHAR(36) NOT NULL,
    company_id INT UNSIGNED NOT NULL,
    rule_key VARCHAR(60) NOT NULL,
    name VARCHAR(160) NOT NULL,
    is_enabled TINYINT(1) NOT NULL DEFAULT 1,
    config_json TEXT NULL,
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    UNIQUE KEY uq_crm_arule_uuid (public_uuid),
    UNIQUE KEY uq_crm_arule_key (company_id, rule_key),
    INDEX idx_crm_arule_company (company_id, is_enabled),
    CONSTRAINT fk_crm_arule_tenant FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Quotation intelligence columns (skip if present)
SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_crm_quotations' AND COLUMN_NAME = 'version_no'
);
SET @sql := IF(
    @col_exists = 0,
    'ALTER TABLE rateb_crm_quotations
        ADD COLUMN version_no INT UNSIGNED NOT NULL DEFAULT 1 AFTER notes,
        ADD COLUMN parent_quotation_id INT UNSIGNED NULL AFTER version_no,
        ADD COLUMN root_quotation_id INT UNSIGNED NULL AFTER parent_quotation_id,
        ADD COLUMN approval_status VARCHAR(40) NOT NULL DEFAULT ''none'' AFTER root_quotation_id,
        ADD COLUMN approved_by INT UNSIGNED NULL AFTER approval_status,
        ADD COLUMN approved_at DATETIME NULL AFTER approved_by',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

INSERT INTO rateb_permissions (name, name_ar, slug, module, description, description_ar) VALUES
('CRM Quote Approve', 'اعتماد عروض الأسعار', 'crm.quote.approve', 'crm', 'Approve CRM quotations', 'اعتماد عروض أسعار المبيعات'),
('CRM Quote Version', 'نسخ عروض الأسعار', 'crm.quote.version', 'crm', 'Version / duplicate quotations', 'إنشاء نسخ من عروض الأسعار'),
('CRM Forecast Manage', 'إدارة توقعات المبيعات', 'crm.forecast.manage', 'crm', 'Manage CRM forecast snapshots', 'إدارة لقطات توقعات المبيعات'),
('CRM Revenue View', 'عرض تتبع الإيرادات', 'crm.revenue.view', 'crm', 'View CRM revenue tracking', 'عرض تتبع إيرادات CRM'),
('CRM Config Manage', 'إعدادات CRM', 'crm.config.manage', 'crm', 'Manage CRM admin configuration', 'إدارة إعدادات CRM')
ON DUPLICATE KEY UPDATE name = VALUES(name), module = VALUES(module), description = VALUES(description);

INSERT IGNORE INTO rateb_role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM rateb_roles r
JOIN rateb_permissions p ON p.slug IN (
    'crm.quote.approve', 'crm.quote.version', 'crm.forecast.manage',
    'crm.revenue.view', 'crm.config.manage'
)
WHERE r.slug IN ('company-full-access', 'super-admin');

-- Default automation rules (idempotent per company via IGNORE on unique key — seed for all companies)
INSERT IGNORE INTO rateb_crm_automation_rules (public_uuid, company_id, rule_key, name, is_enabled, config_json)
SELECT UUID(), c.id, 'follow_up_overdue', 'Follow-up overdue', 1, '{"days":0}'
FROM rateb_companies c;
INSERT IGNORE INTO rateb_crm_automation_rules (public_uuid, company_id, rule_key, name, is_enabled, config_json)
SELECT UUID(), c.id, 'quote_expiry', 'Quote expiry alerts', 1, '{"days_ahead":3}'
FROM rateb_companies c;
INSERT IGNORE INTO rateb_crm_automation_rules (public_uuid, company_id, rule_key, name, is_enabled, config_json)
SELECT UUID(), c.id, 'opportunity_inactivity', 'Opportunity inactivity', 1, '{"days":14}'
FROM rateb_companies c;
INSERT IGNORE INTO rateb_crm_automation_rules (public_uuid, company_id, rule_key, name, is_enabled, config_json)
SELECT UUID(), c.id, 'stage_change', 'Stage change alerts', 1, '{}'
FROM rateb_companies c;

INSERT IGNORE INTO rateb_crm_activity_types (public_uuid, company_id, code, name, name_ar, is_active, sort_order)
SELECT UUID(), c.id, v.code, v.name, v.name_ar, 1, v.sort_order
FROM rateb_companies c
CROSS JOIN (
    SELECT 'note' AS code, 'Note' AS name, 'ملاحظة' AS name_ar, 1 AS sort_order
    UNION ALL SELECT 'call', 'Call', 'مكالمة', 2
    UNION ALL SELECT 'meeting', 'Meeting', 'اجتماع', 3
    UNION ALL SELECT 'task', 'Task', 'مهمة', 4
    UNION ALL SELECT 'follow_up', 'Follow-up', 'متابعة', 5
    UNION ALL SELECT 'other', 'Other', 'أخرى', 6
) v;
