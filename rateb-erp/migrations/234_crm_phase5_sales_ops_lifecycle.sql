-- Phase 5 — CRM Enterprise Sales Operations + Customer Lifecycle (additive only).
-- No DROP. No duplicate classic CRM entity tables. Reuses rateb_customers.

-- Sales teams
CREATE TABLE IF NOT EXISTS rateb_crm_sales_teams (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_uuid CHAR(36) NOT NULL,
    company_id INT UNSIGNED NOT NULL,
    code VARCHAR(40) NOT NULL,
    name VARCHAR(160) NOT NULL,
    name_ar VARCHAR(160) NULL,
    manager_user_id INT UNSIGNED NULL,
    territory_id INT UNSIGNED NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'active',
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    UNIQUE KEY uq_crm_steam_uuid (public_uuid),
    UNIQUE KEY uq_crm_steam_code (company_id, code),
    INDEX idx_crm_steam_company (company_id, status),
    CONSTRAINT fk_crm_steam_tenant FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_crm_sales_team_members (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_uuid CHAR(36) NOT NULL,
    company_id INT UNSIGNED NOT NULL,
    team_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    role_code VARCHAR(40) NOT NULL DEFAULT 'member',
    is_primary TINYINT(1) NOT NULL DEFAULT 0,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    UNIQUE KEY uq_crm_stmem_uuid (public_uuid),
    UNIQUE KEY uq_crm_stmem_user (company_id, team_id, user_id),
    INDEX idx_crm_stmem_team (team_id, deleted_at),
    INDEX idx_crm_stmem_user (company_id, user_id),
    CONSTRAINT fk_crm_stmem_tenant FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_crm_stmem_team FOREIGN KEY (team_id) REFERENCES rateb_crm_sales_teams(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_crm_territories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_uuid CHAR(36) NOT NULL,
    company_id INT UNSIGNED NOT NULL,
    code VARCHAR(40) NOT NULL,
    name VARCHAR(160) NOT NULL,
    name_ar VARCHAR(160) NULL,
    region VARCHAR(120) NULL,
    owner_user_id INT UNSIGNED NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'active',
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    UNIQUE KEY uq_crm_terr_uuid (public_uuid),
    UNIQUE KEY uq_crm_terr_code (company_id, code),
    INDEX idx_crm_terr_company (company_id, status),
    CONSTRAINT fk_crm_terr_tenant FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_crm_ownership_rules (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_uuid CHAR(36) NOT NULL,
    company_id INT UNSIGNED NOT NULL,
    rule_key VARCHAR(60) NOT NULL,
    name VARCHAR(160) NOT NULL,
    entity_type VARCHAR(40) NOT NULL DEFAULT 'customer',
    assign_mode VARCHAR(40) NOT NULL DEFAULT 'owner',
    team_id INT UNSIGNED NULL,
    territory_id INT UNSIGNED NULL,
    owner_user_id INT UNSIGNED NULL,
    is_enabled TINYINT(1) NOT NULL DEFAULT 1,
    config_json TEXT NULL,
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    UNIQUE KEY uq_crm_ownr_uuid (public_uuid),
    UNIQUE KEY uq_crm_ownr_key (company_id, rule_key),
    INDEX idx_crm_ownr_company (company_id, is_enabled),
    CONSTRAINT fk_crm_ownr_tenant FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Customer lifecycle events (history on existing customers — no new customer master)
CREATE TABLE IF NOT EXISTS rateb_crm_lifecycle_events (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_uuid CHAR(36) NOT NULL,
    company_id INT UNSIGNED NOT NULL,
    customer_id INT UNSIGNED NOT NULL,
    from_stage VARCHAR(40) NULL,
    to_stage VARCHAR(40) NOT NULL,
    event_type VARCHAR(60) NOT NULL DEFAULT 'lifecycle_transition',
    owner_user_id INT UNSIGNED NULL,
    team_id INT UNSIGNED NULL,
    reason VARCHAR(255) NULL,
    meta_json TEXT NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_crm_life_uuid (public_uuid),
    INDEX idx_crm_life_customer (company_id, customer_id, created_at),
    INDEX idx_crm_life_stage (company_id, to_stage),
    CONSTRAINT fk_crm_life_tenant FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pipeline stage duration / bottleneck transitions
CREATE TABLE IF NOT EXISTS rateb_crm_stage_transitions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_uuid CHAR(36) NOT NULL,
    company_id INT UNSIGNED NOT NULL,
    opportunity_id INT UNSIGNED NOT NULL,
    pipeline_id INT UNSIGNED NULL,
    from_stage_id INT UNSIGNED NULL,
    to_stage_id INT UNSIGNED NOT NULL,
    duration_seconds INT UNSIGNED NOT NULL DEFAULT 0,
    owner_user_id INT UNSIGNED NULL,
    team_id INT UNSIGNED NULL,
    meta_json TEXT NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_crm_sttr_uuid (public_uuid),
    INDEX idx_crm_sttr_opp (opportunity_id, created_at),
    INDEX idx_crm_sttr_stage (company_id, to_stage_id),
    INDEX idx_crm_sttr_from (company_id, from_stage_id),
    CONSTRAINT fk_crm_sttr_tenant FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Customer CRM lifecycle / ownership / retention columns on existing master
SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_customers' AND COLUMN_NAME = 'crm_lifecycle_stage'
);
SET @sql := IF(
    @col_exists = 0,
    'ALTER TABLE rateb_customers
        ADD COLUMN crm_lifecycle_stage VARCHAR(40) NOT NULL DEFAULT ''customer'' AFTER is_active,
        ADD COLUMN crm_owner_user_id INT UNSIGNED NULL AFTER crm_lifecycle_stage,
        ADD COLUMN crm_team_id INT UNSIGNED NULL AFTER crm_owner_user_id,
        ADD COLUMN crm_territory_id INT UNSIGNED NULL AFTER crm_team_id,
        ADD COLUMN crm_last_interaction_at DATETIME NULL AFTER crm_territory_id,
        ADD COLUMN crm_activity_score INT NOT NULL DEFAULT 0 AFTER crm_last_interaction_at,
        ADD COLUMN crm_renewal_due_at DATE NULL AFTER crm_activity_score,
        ADD COLUMN crm_at_risk TINYINT(1) NOT NULL DEFAULT 0 AFTER crm_renewal_due_at',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Stage expected duration (SLA days)
SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_crm_pipeline_stages' AND COLUMN_NAME = 'expected_duration_days'
);
SET @sql := IF(
    @col_exists = 0,
    'ALTER TABLE rateb_crm_pipeline_stages
        ADD COLUMN expected_duration_days INT UNSIGNED NULL AFTER probability_percent',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Opportunity stage entry + team ownership
SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_crm_opportunities' AND COLUMN_NAME = 'stage_entered_at'
);
SET @sql := IF(
    @col_exists = 0,
    'ALTER TABLE rateb_crm_opportunities
        ADD COLUMN stage_entered_at DATETIME NULL AFTER stage_id,
        ADD COLUMN team_id INT UNSIGNED NULL AFTER owner_user_id',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

INSERT INTO rateb_permissions (name, name_ar, slug, module, description, description_ar) VALUES
('CRM Teams View', 'عرض فرق المبيعات', 'crm.teams.view', 'crm', 'View CRM sales teams', 'عرض فرق مبيعات CRM'),
('CRM Teams Manage', 'إدارة فرق المبيعات', 'crm.teams.manage', 'crm', 'Manage CRM sales teams and territories', 'إدارة فرق ومناطق مبيعات CRM'),
('CRM Lifecycle Manage', 'إدارة دورة حياة العميل', 'crm.lifecycle.manage', 'crm', 'Manage customer lifecycle transitions', 'إدارة انتقالات دورة حياة العميل'),
('CRM Analytics View', 'عرض تحليلات CRM', 'crm.analytics.view', 'crm', 'View CRM sales analytics', 'عرض تحليلات مبيعات CRM'),
('CRM Retention View', 'عرض الاحتفاظ بالعملاء', 'crm.retention.view', 'crm', 'View customer retention indicators', 'عرض مؤشرات الاحتفاظ بالعملاء')
ON DUPLICATE KEY UPDATE name = VALUES(name), module = VALUES(module), description = VALUES(description);

INSERT IGNORE INTO rateb_role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM rateb_roles r
JOIN rateb_permissions p ON p.slug IN (
    'crm.teams.view', 'crm.teams.manage', 'crm.lifecycle.manage',
    'crm.analytics.view', 'crm.retention.view'
)
WHERE r.slug IN ('company-full-access', 'super-admin');

-- Phase 5 automation rules
INSERT IGNORE INTO rateb_crm_automation_rules (public_uuid, company_id, rule_key, name, is_enabled, config_json)
SELECT UUID(), c.id, 'no_activity', 'Customer / lead no activity', 1, '{"days":21}'
FROM rateb_companies c;
INSERT IGNORE INTO rateb_crm_automation_rules (public_uuid, company_id, rule_key, name, is_enabled, config_json)
SELECT UUID(), c.id, 'renewal_reminder', 'Renewal reminders', 1, '{"days_ahead":30}'
FROM rateb_companies c;
INSERT IGNORE INTO rateb_crm_automation_rules (public_uuid, company_id, rule_key, name, is_enabled, config_json)
SELECT UUID(), c.id, 'stale_opportunity', 'Stale opportunity (stage SLA)', 1, '{}'
FROM rateb_companies c;
INSERT IGNORE INTO rateb_crm_automation_rules (public_uuid, company_id, rule_key, name, is_enabled, config_json)
SELECT UUID(), c.id, 'customer_follow_up', 'Customer follow-up workflows', 1, '{"days":14}'
FROM rateb_companies c;
