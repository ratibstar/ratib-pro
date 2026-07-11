-- RATEB ERP — Phase 17A Enterprise CRM Platform (ONLINE)
-- Additive only. Does not alter Offline / Recruitment / Accounting / Inventory / HR / Procurement / POS.
-- Distinct from CMS marketing leads (rateb_cms_leads) and CMS newsletter campaigns.
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- ---------------------------------------------------------------------------
-- Lead sources
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS rateb_crm_lead_sources (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_uuid CHAR(36) NOT NULL,
    company_id INT UNSIGNED NOT NULL,
    branch_id INT UNSIGNED NULL,
    code VARCHAR(40) NOT NULL,
    name VARCHAR(160) NOT NULL,
    name_ar VARCHAR(160) NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    UNIQUE KEY uq_crm_src_uuid (public_uuid),
    UNIQUE KEY uq_crm_src_code (company_id, code),
    INDEX idx_crm_src_company (company_id, deleted_at),
    CONSTRAINT fk_crm_src_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Tags
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS rateb_crm_tags (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_uuid CHAR(36) NOT NULL,
    company_id INT UNSIGNED NOT NULL,
    branch_id INT UNSIGNED NULL,
    code VARCHAR(40) NOT NULL,
    name VARCHAR(120) NOT NULL,
    name_ar VARCHAR(120) NULL,
    color VARCHAR(20) NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    UNIQUE KEY uq_crm_tag_uuid (public_uuid),
    UNIQUE KEY uq_crm_tag_code (company_id, code),
    INDEX idx_crm_tag_company (company_id, deleted_at),
    CONSTRAINT fk_crm_tag_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Pipelines + stages
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS rateb_crm_pipelines (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_uuid CHAR(36) NOT NULL,
    company_id INT UNSIGNED NOT NULL,
    branch_id INT UNSIGNED NULL,
    code VARCHAR(40) NOT NULL,
    name VARCHAR(160) NOT NULL,
    name_ar VARCHAR(160) NULL,
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    UNIQUE KEY uq_crm_pipe_uuid (public_uuid),
    UNIQUE KEY uq_crm_pipe_code (company_id, code),
    INDEX idx_crm_pipe_company (company_id, deleted_at),
    CONSTRAINT fk_crm_pipe_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_crm_pipeline_stages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_uuid CHAR(36) NOT NULL,
    company_id INT UNSIGNED NOT NULL,
    pipeline_id INT UNSIGNED NOT NULL,
    code VARCHAR(40) NOT NULL,
    name VARCHAR(160) NOT NULL,
    name_ar VARCHAR(160) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    probability_percent DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    is_won TINYINT(1) NOT NULL DEFAULT 0,
    is_lost TINYINT(1) NOT NULL DEFAULT 0,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    UNIQUE KEY uq_crm_stage_uuid (public_uuid),
    UNIQUE KEY uq_crm_stage_code (pipeline_id, code),
    INDEX idx_crm_stage_pipe (company_id, pipeline_id, sort_order),
    CONSTRAINT fk_crm_stage_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_crm_stage_pipe FOREIGN KEY (pipeline_id) REFERENCES rateb_crm_pipelines(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- CRM companies (accounts) — optional link to rateb_customers
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS rateb_crm_companies (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_uuid CHAR(36) NOT NULL,
    company_id INT UNSIGNED NOT NULL,
    branch_id INT UNSIGNED NULL,
    customer_id INT UNSIGNED NULL,
    code VARCHAR(40) NOT NULL,
    name VARCHAR(190) NOT NULL,
    name_ar VARCHAR(190) NULL,
    industry VARCHAR(120) NULL,
    website VARCHAR(255) NULL,
    phone VARCHAR(40) NULL,
    email VARCHAR(190) NULL,
    city VARCHAR(120) NULL,
    country_code CHAR(2) NULL,
    status ENUM('active','inactive','archived') NOT NULL DEFAULT 'active',
    notes TEXT NULL,
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    UNIQUE KEY uq_crm_co_uuid (public_uuid),
    UNIQUE KEY uq_crm_co_code (company_id, code),
    INDEX idx_crm_co_company (company_id, deleted_at),
    INDEX idx_crm_co_customer (customer_id),
    INDEX idx_crm_co_branch (company_id, branch_id),
    CONSTRAINT fk_crm_co_tenant FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Contacts
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS rateb_crm_contacts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_uuid CHAR(36) NOT NULL,
    company_id INT UNSIGNED NOT NULL,
    branch_id INT UNSIGNED NULL,
    crm_company_id INT UNSIGNED NULL,
    customer_id INT UNSIGNED NULL,
    full_name VARCHAR(190) NOT NULL,
    full_name_ar VARCHAR(190) NULL,
    job_title VARCHAR(120) NULL,
    email VARCHAR(190) NULL,
    phone VARCHAR(40) NULL,
    mobile VARCHAR(40) NULL,
    is_primary TINYINT(1) NOT NULL DEFAULT 0,
    status ENUM('active','inactive','archived') NOT NULL DEFAULT 'active',
    notes TEXT NULL,
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    UNIQUE KEY uq_crm_ct_uuid (public_uuid),
    INDEX idx_crm_ct_company (company_id, deleted_at),
    INDEX idx_crm_ct_crmco (crm_company_id),
    INDEX idx_crm_ct_customer (customer_id),
    CONSTRAINT fk_crm_ct_tenant FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_crm_ct_crmco FOREIGN KEY (crm_company_id) REFERENCES rateb_crm_companies(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Leads
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS rateb_crm_leads (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_uuid CHAR(36) NOT NULL,
    company_id INT UNSIGNED NOT NULL,
    branch_id INT UNSIGNED NULL,
    lead_no VARCHAR(40) NOT NULL,
    title VARCHAR(190) NOT NULL,
    contact_name VARCHAR(190) NULL,
    email VARCHAR(190) NULL,
    phone VARCHAR(40) NULL,
    crm_company_id INT UNSIGNED NULL,
    contact_id INT UNSIGNED NULL,
    customer_id INT UNSIGNED NULL,
    source_id INT UNSIGNED NULL,
    owner_user_id INT UNSIGNED NULL,
    workflow_status VARCHAR(40) NOT NULL DEFAULT 'new',
    estimated_value DECIMAL(14,2) NULL,
    currency_code CHAR(3) NULL,
    expected_close_date DATE NULL,
    priority ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
    status ENUM('active','inactive','archived') NOT NULL DEFAULT 'active',
    notes TEXT NULL,
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    UNIQUE KEY uq_crm_lead_uuid (public_uuid),
    UNIQUE KEY uq_crm_lead_no (company_id, lead_no),
    INDEX idx_crm_lead_company (company_id, deleted_at),
    INDEX idx_crm_lead_status (company_id, workflow_status),
    INDEX idx_crm_lead_owner (owner_user_id),
    INDEX idx_crm_lead_branch (company_id, branch_id),
    CONSTRAINT fk_crm_lead_tenant FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_crm_lead_src FOREIGN KEY (source_id) REFERENCES rateb_crm_lead_sources(id) ON DELETE SET NULL,
    CONSTRAINT fk_crm_lead_crmco FOREIGN KEY (crm_company_id) REFERENCES rateb_crm_companies(id) ON DELETE SET NULL,
    CONSTRAINT fk_crm_lead_contact FOREIGN KEY (contact_id) REFERENCES rateb_crm_contacts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Opportunities
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS rateb_crm_opportunities (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_uuid CHAR(36) NOT NULL,
    company_id INT UNSIGNED NOT NULL,
    branch_id INT UNSIGNED NULL,
    opportunity_no VARCHAR(40) NOT NULL,
    name VARCHAR(190) NOT NULL,
    name_ar VARCHAR(190) NULL,
    lead_id INT UNSIGNED NULL,
    crm_company_id INT UNSIGNED NULL,
    contact_id INT UNSIGNED NULL,
    customer_id INT UNSIGNED NULL,
    pipeline_id INT UNSIGNED NULL,
    stage_id INT UNSIGNED NULL,
    owner_user_id INT UNSIGNED NULL,
    amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    currency_code CHAR(3) NULL,
    probability_percent DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    expected_close_date DATE NULL,
    workflow_status VARCHAR(40) NOT NULL DEFAULT 'open',
    status ENUM('active','inactive','archived') NOT NULL DEFAULT 'active',
    notes TEXT NULL,
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    UNIQUE KEY uq_crm_opp_uuid (public_uuid),
    UNIQUE KEY uq_crm_opp_no (company_id, opportunity_no),
    INDEX idx_crm_opp_company (company_id, deleted_at),
    INDEX idx_crm_opp_pipe (pipeline_id, stage_id),
    INDEX idx_crm_opp_lead (lead_id),
    INDEX idx_crm_opp_owner (owner_user_id),
    CONSTRAINT fk_crm_opp_tenant FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_crm_opp_lead FOREIGN KEY (lead_id) REFERENCES rateb_crm_leads(id) ON DELETE SET NULL,
    CONSTRAINT fk_crm_opp_pipe FOREIGN KEY (pipeline_id) REFERENCES rateb_crm_pipelines(id) ON DELETE SET NULL,
    CONSTRAINT fk_crm_opp_stage FOREIGN KEY (stage_id) REFERENCES rateb_crm_pipeline_stages(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Campaigns (sales CRM — distinct from CMS newsletter)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS rateb_crm_campaigns (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_uuid CHAR(36) NOT NULL,
    company_id INT UNSIGNED NOT NULL,
    branch_id INT UNSIGNED NULL,
    code VARCHAR(40) NOT NULL,
    name VARCHAR(190) NOT NULL,
    name_ar VARCHAR(190) NULL,
    campaign_type ENUM('email','call','event','social','other') NOT NULL DEFAULT 'other',
    start_date DATE NULL,
    end_date DATE NULL,
    budget DECIMAL(14,2) NULL,
    status ENUM('draft','active','paused','completed','cancelled') NOT NULL DEFAULT 'draft',
    notes TEXT NULL,
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    UNIQUE KEY uq_crm_camp_uuid (public_uuid),
    UNIQUE KEY uq_crm_camp_code (company_id, code),
    INDEX idx_crm_camp_company (company_id, deleted_at),
    CONSTRAINT fk_crm_camp_tenant FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Activities / Meetings / Calls / Tasks
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS rateb_crm_activities (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_uuid CHAR(36) NOT NULL,
    company_id INT UNSIGNED NOT NULL,
    branch_id INT UNSIGNED NULL,
    activity_type ENUM('note','follow_up','other') NOT NULL DEFAULT 'other',
    subject VARCHAR(190) NOT NULL,
    body TEXT NULL,
    related_type VARCHAR(40) NULL,
    related_id INT UNSIGNED NULL,
    lead_id INT UNSIGNED NULL,
    opportunity_id INT UNSIGNED NULL,
    contact_id INT UNSIGNED NULL,
    crm_company_id INT UNSIGNED NULL,
    customer_id INT UNSIGNED NULL,
    owner_user_id INT UNSIGNED NULL,
    activity_at DATETIME NULL,
    status ENUM('open','done','cancelled') NOT NULL DEFAULT 'open',
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    UNIQUE KEY uq_crm_act_uuid (public_uuid),
    INDEX idx_crm_act_company (company_id, deleted_at),
    INDEX idx_crm_act_related (related_type, related_id),
    INDEX idx_crm_act_lead (lead_id),
    CONSTRAINT fk_crm_act_tenant FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_crm_meetings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_uuid CHAR(36) NOT NULL,
    company_id INT UNSIGNED NOT NULL,
    branch_id INT UNSIGNED NULL,
    subject VARCHAR(190) NOT NULL,
    location VARCHAR(190) NULL,
    starts_at DATETIME NOT NULL,
    ends_at DATETIME NULL,
    lead_id INT UNSIGNED NULL,
    opportunity_id INT UNSIGNED NULL,
    contact_id INT UNSIGNED NULL,
    crm_company_id INT UNSIGNED NULL,
    customer_id INT UNSIGNED NULL,
    owner_user_id INT UNSIGNED NULL,
    status ENUM('scheduled','completed','cancelled') NOT NULL DEFAULT 'scheduled',
    notes TEXT NULL,
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    UNIQUE KEY uq_crm_mtg_uuid (public_uuid),
    INDEX idx_crm_mtg_company (company_id, starts_at),
    INDEX idx_crm_mtg_lead (lead_id),
    CONSTRAINT fk_crm_mtg_tenant FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_crm_calls (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_uuid CHAR(36) NOT NULL,
    company_id INT UNSIGNED NOT NULL,
    branch_id INT UNSIGNED NULL,
    subject VARCHAR(190) NOT NULL,
    direction ENUM('inbound','outbound') NOT NULL DEFAULT 'outbound',
    called_at DATETIME NOT NULL,
    duration_sec INT UNSIGNED NULL,
    phone VARCHAR(40) NULL,
    lead_id INT UNSIGNED NULL,
    opportunity_id INT UNSIGNED NULL,
    contact_id INT UNSIGNED NULL,
    crm_company_id INT UNSIGNED NULL,
    customer_id INT UNSIGNED NULL,
    owner_user_id INT UNSIGNED NULL,
    outcome VARCHAR(80) NULL,
    status ENUM('logged','missed','cancelled') NOT NULL DEFAULT 'logged',
    notes TEXT NULL,
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    UNIQUE KEY uq_crm_call_uuid (public_uuid),
    INDEX idx_crm_call_company (company_id, called_at),
    INDEX idx_crm_call_lead (lead_id),
    CONSTRAINT fk_crm_call_tenant FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_crm_tasks (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_uuid CHAR(36) NOT NULL,
    company_id INT UNSIGNED NOT NULL,
    branch_id INT UNSIGNED NULL,
    subject VARCHAR(190) NOT NULL,
    due_at DATETIME NULL,
    priority ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
    lead_id INT UNSIGNED NULL,
    opportunity_id INT UNSIGNED NULL,
    contact_id INT UNSIGNED NULL,
    crm_company_id INT UNSIGNED NULL,
    customer_id INT UNSIGNED NULL,
    owner_user_id INT UNSIGNED NULL,
    reminder_at DATETIME NULL,
    status ENUM('open','done','cancelled') NOT NULL DEFAULT 'open',
    completed_at DATETIME NULL,
    notes TEXT NULL,
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    UNIQUE KEY uq_crm_task_uuid (public_uuid),
    INDEX idx_crm_task_company (company_id, status, due_at),
    INDEX idx_crm_task_owner (owner_user_id),
    CONSTRAINT fk_crm_task_tenant FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Notes / Timeline / Assignments / Tags / Status history
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS rateb_crm_notes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_uuid CHAR(36) NOT NULL,
    company_id INT UNSIGNED NOT NULL,
    branch_id INT UNSIGNED NULL,
    related_type VARCHAR(40) NOT NULL,
    related_id INT UNSIGNED NOT NULL,
    lead_id INT UNSIGNED NULL,
    opportunity_id INT UNSIGNED NULL,
    contact_id INT UNSIGNED NULL,
    crm_company_id INT UNSIGNED NULL,
    customer_id INT UNSIGNED NULL,
    body TEXT NOT NULL,
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    UNIQUE KEY uq_crm_note_uuid (public_uuid),
    INDEX idx_crm_note_related (company_id, related_type, related_id),
    CONSTRAINT fk_crm_note_tenant FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_crm_timeline (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    branch_id INT UNSIGNED NULL,
    event_type VARCHAR(40) NOT NULL,
    title VARCHAR(190) NOT NULL,
    body TEXT NULL,
    related_type VARCHAR(40) NULL,
    related_id INT UNSIGNED NULL,
    lead_id INT UNSIGNED NULL,
    opportunity_id INT UNSIGNED NULL,
    contact_id INT UNSIGNED NULL,
    crm_company_id INT UNSIGNED NULL,
    customer_id INT UNSIGNED NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_crm_tl_company (company_id, created_at),
    INDEX idx_crm_tl_lead (lead_id),
    INDEX idx_crm_tl_related (related_type, related_id),
    CONSTRAINT fk_crm_tl_tenant FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_crm_assignments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_uuid CHAR(36) NOT NULL,
    company_id INT UNSIGNED NOT NULL,
    branch_id INT UNSIGNED NULL,
    related_type VARCHAR(40) NOT NULL,
    related_id INT UNSIGNED NOT NULL,
    assignee_user_id INT UNSIGNED NOT NULL,
    role_label VARCHAR(80) NULL,
    status ENUM('active','released') NOT NULL DEFAULT 'active',
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    UNIQUE KEY uq_crm_asg_uuid (public_uuid),
    INDEX idx_crm_asg_related (company_id, related_type, related_id),
    INDEX idx_crm_asg_user (assignee_user_id),
    CONSTRAINT fk_crm_asg_tenant FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_crm_entity_tags (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    tag_id INT UNSIGNED NOT NULL,
    related_type VARCHAR(40) NOT NULL,
    related_id INT UNSIGNED NOT NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_crm_etag (company_id, tag_id, related_type, related_id),
    INDEX idx_crm_etag_related (related_type, related_id),
    CONSTRAINT fk_crm_etag_tenant FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_crm_etag_tag FOREIGN KEY (tag_id) REFERENCES rateb_crm_tags(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_crm_status_history (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    lead_id INT UNSIGNED NOT NULL,
    from_status VARCHAR(40) NOT NULL,
    to_status VARCHAR(40) NOT NULL,
    reason VARCHAR(255) NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_crm_sh_lead (company_id, lead_id),
    CONSTRAINT fk_crm_sh_tenant FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_crm_sh_lead FOREIGN KEY (lead_id) REFERENCES rateb_crm_leads(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Permissions
-- ---------------------------------------------------------------------------
INSERT INTO rateb_permissions (name, name_ar, slug, module, description, description_ar) VALUES
('View CRM', 'عرض إدارة علاقات العملاء', 'crm.view', 'crm', 'View CRM records', 'عرض سجلات إدارة علاقات العملاء'),
('Create CRM', 'إنشاء إدارة علاقات العملاء', 'crm.create', 'crm', 'Create CRM records', 'إنشاء سجلات إدارة علاقات العملاء'),
('Update CRM', 'تحديث إدارة علاقات العملاء', 'crm.update', 'crm', 'Update CRM records', 'تحديث سجلات إدارة علاقات العملاء'),
('Delete CRM', 'حذف إدارة علاقات العملاء', 'crm.delete', 'crm', 'Soft-delete CRM records', 'حذف ناعم لسجلات إدارة علاقات العملاء'),
('Assign CRM', 'تعيين إدارة علاقات العملاء', 'crm.assign', 'crm', 'Assign CRM owners', 'تعيين مالكي سجلات إدارة علاقات العملاء'),
('CRM Pipeline', 'مسار المبيعات', 'crm.pipeline', 'crm', 'Manage CRM pipelines and stages', 'إدارة مسارات ومراحل المبيعات'),
('CRM Activities', 'أنشطة إدارة علاقات العملاء', 'crm.activities', 'crm', 'Manage meetings, calls, and tasks', 'إدارة الاجتماعات والمكالمات والمهام'),
('CRM Campaigns', 'حملات إدارة علاقات العملاء', 'crm.campaign', 'crm', 'Manage CRM campaigns', 'إدارة حملات إدارة علاقات العملاء'),
('CRM Admin', 'إدارة كاملة لعلاقات العملاء', 'crm.admin', 'crm', 'Full CRM administration', 'إدارة كاملة لعلاقات العملاء'),
('Manage CRM', 'إدارة علاقات العملاء', 'crm.manage', 'crm', 'All CRM operations', 'جميع عمليات إدارة علاقات العملاء')
ON DUPLICATE KEY UPDATE name = VALUES(name), module = VALUES(module), description = VALUES(description);

INSERT IGNORE INTO rateb_role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM rateb_roles r
JOIN rateb_permissions p ON p.slug IN (
    'crm.view', 'crm.create', 'crm.update', 'crm.delete', 'crm.assign',
    'crm.pipeline', 'crm.activities', 'crm.campaign', 'crm.admin', 'crm.manage'
)
WHERE r.slug IN ('company-full-access', 'super-admin');
