-- Phase 3 — CRM enterprise maturity (additive only).
-- CREATE IF NOT EXISTS + guarded ADD COLUMN (nullable). No DROP / destructive ALTER.

CREATE TABLE IF NOT EXISTS rateb_crm_loss_reasons (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_uuid CHAR(36) NOT NULL,
    company_id INT UNSIGNED NOT NULL,
    code VARCHAR(40) NOT NULL,
    name VARCHAR(160) NOT NULL,
    name_ar VARCHAR(160) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    UNIQUE KEY uq_crm_loss_uuid (public_uuid),
    UNIQUE KEY uq_crm_loss_code (company_id, code),
    INDEX idx_crm_loss_company (company_id, status),
    CONSTRAINT fk_crm_loss_tenant FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_crm_opportunity_outcomes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    opportunity_id INT UNSIGNED NOT NULL,
    outcome VARCHAR(20) NOT NULL,
    loss_reason_id INT UNSIGNED NULL,
    amount DECIMAL(18,2) NOT NULL DEFAULT 0,
    probability_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
    expected_revenue DECIMAL(18,2) NOT NULL DEFAULT 0,
    notes VARCHAR(255) NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_crm_out_company (company_id, created_at),
    INDEX idx_crm_out_opp (opportunity_id),
    INDEX idx_crm_out_outcome (company_id, outcome),
    CONSTRAINT fk_crm_out_tenant FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_crm_activity_reminders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    activity_id INT UNSIGNED NULL,
    task_id INT UNSIGNED NULL,
    owner_user_id INT UNSIGNED NULL,
    due_at DATETIME NULL,
    reminder_at DATETIME NULL,
    priority VARCHAR(20) NOT NULL DEFAULT 'normal',
    reminded_at DATETIME NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'open',
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_crm_arem_company (company_id, status),
    INDEX idx_crm_arem_due (company_id, due_at),
    INDEX idx_crm_arem_reminder (company_id, reminder_at, reminded_at),
    CONSTRAINT fk_crm_arem_tenant FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_crm_automation_log (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    event_type VARCHAR(60) NOT NULL,
    entity_type VARCHAR(40) NULL,
    entity_id INT UNSIGNED NULL,
    user_id INT UNSIGNED NULL,
    payload_json TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_crm_autolog_company (company_id, created_at),
    INDEX idx_crm_autolog_event (company_id, event_type),
    CONSTRAINT fk_crm_autolog_tenant FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Optional nullable columns on opportunities (additive; skip if present)
SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'rateb_crm_opportunities'
      AND COLUMN_NAME = 'loss_reason_id'
);
SET @sql := IF(
    @col_exists = 0,
    'ALTER TABLE rateb_crm_opportunities ADD COLUMN loss_reason_id INT UNSIGNED NULL AFTER workflow_status, ADD COLUMN loss_notes VARCHAR(255) NULL AFTER loss_reason_id',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'rateb_crm_activities'
      AND COLUMN_NAME = 'due_at'
);
SET @sql := IF(
    @col_exists = 0,
    'ALTER TABLE rateb_crm_activities ADD COLUMN due_at DATETIME NULL AFTER activity_at, ADD COLUMN reminder_at DATETIME NULL AFTER due_at, ADD COLUMN priority VARCHAR(20) NOT NULL DEFAULT ''normal'' AFTER reminder_at',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

INSERT INTO rateb_permissions (name, name_ar, slug, module, description, description_ar) VALUES
('CRM Pipeline View', 'عرض مسار المبيعات', 'crm.pipeline.view', 'crm', 'View CRM pipelines', 'عرض مسارات المبيعات'),
('CRM Pipeline Manage', 'إدارة مسار المبيعات', 'crm.pipeline.manage', 'crm', 'Manage pipeline stages', 'إدارة مراحل مسار المبيعات'),
('CRM Pipeline Forecast', 'توقعات مسار المبيعات', 'crm.pipeline.forecast', 'crm', 'View pipeline forecasting', 'عرض توقعات مسار المبيعات'),
('CRM Activities View', 'عرض أنشطة CRM', 'crm.activities.view', 'crm', 'View CRM activities', 'عرض أنشطة إدارة علاقات العملاء'),
('CRM Activities Manage', 'إدارة أنشطة CRM', 'crm.activities.manage', 'crm', 'Manage CRM activities', 'إدارة أنشطة إدارة علاقات العملاء'),
('CRM Activities Assign', 'تعيين أنشطة CRM', 'crm.activities.assign', 'crm', 'Assign CRM activity owners', 'تعيين مالكي أنشطة CRM'),
('CRM Reports View', 'عرض تقارير CRM', 'crm.reports.view', 'crm', 'View CRM reports', 'عرض تقارير إدارة علاقات العملاء'),
('CRM Reports Export', 'تصدير تقارير CRM', 'crm.reports.export', 'crm', 'Export CRM reports', 'تصدير تقارير إدارة علاقات العملاء')
ON DUPLICATE KEY UPDATE name = VALUES(name), module = VALUES(module), description = VALUES(description);

INSERT IGNORE INTO rateb_role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM rateb_roles r
JOIN rateb_permissions p ON p.slug IN (
    'crm.pipeline.view', 'crm.pipeline.manage', 'crm.pipeline.forecast',
    'crm.activities.view', 'crm.activities.manage', 'crm.activities.assign',
    'crm.reports.view', 'crm.reports.export'
)
WHERE r.slug IN ('company-full-access', 'super-admin');
