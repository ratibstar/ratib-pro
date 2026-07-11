-- RATEB ERP — Phase 18A Enterprise Projects Platform (ONLINE)
-- Additive only. Does not alter Offline / CRM / Recruitment / Accounting / Inventory / HR / Procurement / POS.
-- Distinct from CRM tasks (rateb_crm_tasks) and HR attendance.
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- ---------------------------------------------------------------------------
-- Project roles (member role catalog)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS rateb_project_roles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_uuid CHAR(36) NOT NULL,
    company_id INT UNSIGNED NOT NULL,
    branch_id INT UNSIGNED NULL,
    code VARCHAR(40) NOT NULL,
    name VARCHAR(120) NOT NULL,
    name_ar VARCHAR(120) NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    UNIQUE KEY uq_prj_role_uuid (public_uuid),
    UNIQUE KEY uq_prj_role_code (company_id, code),
    INDEX idx_prj_role_company (company_id, deleted_at),
    CONSTRAINT fk_prj_role_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Tags
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS rateb_project_tags (
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
    UNIQUE KEY uq_prj_tag_uuid (public_uuid),
    UNIQUE KEY uq_prj_tag_code (company_id, code),
    INDEX idx_prj_tag_company (company_id, deleted_at),
    CONSTRAINT fk_prj_tag_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Projects
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS rateb_projects (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_uuid CHAR(36) NOT NULL,
    company_id INT UNSIGNED NOT NULL,
    branch_id INT UNSIGNED NULL,
    project_no VARCHAR(40) NOT NULL,
    name VARCHAR(190) NOT NULL,
    name_ar VARCHAR(190) NULL,
    description TEXT NULL,
    customer_id INT UNSIGNED NULL,
    owner_user_id INT UNSIGNED NULL,
    workflow_status VARCHAR(40) NOT NULL DEFAULT 'draft',
    priority ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
    start_date DATE NULL,
    end_date DATE NULL,
    planned_start DATE NULL,
    planned_end DATE NULL,
    percent_complete DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    currency_code CHAR(3) NULL,
    budget_amount DECIMAL(14,2) NULL,
    cost_center_id INT UNSIGNED NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    status ENUM('active','inactive','archived') NOT NULL DEFAULT 'active',
    notes TEXT NULL,
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    UNIQUE KEY uq_prj_uuid (public_uuid),
    UNIQUE KEY uq_prj_no (company_id, project_no),
    INDEX idx_prj_company (company_id, deleted_at),
    INDEX idx_prj_branch (company_id, branch_id),
    INDEX idx_prj_workflow (company_id, workflow_status),
    INDEX idx_prj_owner (owner_user_id),
    CONSTRAINT fk_prj_tenant FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_project_members (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_uuid CHAR(36) NOT NULL,
    company_id INT UNSIGNED NOT NULL,
    branch_id INT UNSIGNED NULL,
    project_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    role_id INT UNSIGNED NULL,
    role_label VARCHAR(80) NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    UNIQUE KEY uq_prj_mem_uuid (public_uuid),
    UNIQUE KEY uq_prj_mem_user (project_id, user_id),
    INDEX idx_prj_mem_company (company_id, project_id),
    CONSTRAINT fk_prj_mem_tenant FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_prj_mem_project FOREIGN KEY (project_id) REFERENCES rateb_projects(id) ON DELETE CASCADE,
    CONSTRAINT fk_prj_mem_role FOREIGN KEY (role_id) REFERENCES rateb_project_roles(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_project_phases (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_uuid CHAR(36) NOT NULL,
    company_id INT UNSIGNED NOT NULL,
    branch_id INT UNSIGNED NULL,
    project_id INT UNSIGNED NOT NULL,
    code VARCHAR(40) NOT NULL,
    name VARCHAR(160) NOT NULL,
    name_ar VARCHAR(160) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    start_date DATE NULL,
    end_date DATE NULL,
    status ENUM('planned','active','completed','cancelled') NOT NULL DEFAULT 'planned',
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    UNIQUE KEY uq_prj_phase_uuid (public_uuid),
    UNIQUE KEY uq_prj_phase_code (project_id, code),
    INDEX idx_prj_phase_project (company_id, project_id, sort_order),
    CONSTRAINT fk_prj_phase_tenant FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_prj_phase_project FOREIGN KEY (project_id) REFERENCES rateb_projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_project_milestones (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_uuid CHAR(36) NOT NULL,
    company_id INT UNSIGNED NOT NULL,
    branch_id INT UNSIGNED NULL,
    project_id INT UNSIGNED NOT NULL,
    phase_id INT UNSIGNED NULL,
    name VARCHAR(190) NOT NULL,
    name_ar VARCHAR(190) NULL,
    due_date DATE NULL,
    completed_at DATETIME NULL,
    status ENUM('pending','achieved','missed','cancelled') NOT NULL DEFAULT 'pending',
    sort_order INT NOT NULL DEFAULT 0,
    notes TEXT NULL,
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    UNIQUE KEY uq_prj_ms_uuid (public_uuid),
    INDEX idx_prj_ms_project (company_id, project_id, due_date),
    CONSTRAINT fk_prj_ms_tenant FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_prj_ms_project FOREIGN KEY (project_id) REFERENCES rateb_projects(id) ON DELETE CASCADE,
    CONSTRAINT fk_prj_ms_phase FOREIGN KEY (phase_id) REFERENCES rateb_project_phases(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_project_tasks (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_uuid CHAR(36) NOT NULL,
    company_id INT UNSIGNED NOT NULL,
    branch_id INT UNSIGNED NULL,
    project_id INT UNSIGNED NOT NULL,
    phase_id INT UNSIGNED NULL,
    milestone_id INT UNSIGNED NULL,
    parent_task_id INT UNSIGNED NULL,
    task_no VARCHAR(40) NOT NULL,
    title VARCHAR(190) NOT NULL,
    description TEXT NULL,
    workflow_status VARCHAR(40) NOT NULL DEFAULT 'new',
    priority ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
    assignee_user_id INT UNSIGNED NULL,
    start_date DATE NULL,
    due_date DATE NULL,
    estimated_hours DECIMAL(10,2) NULL,
    actual_hours DECIMAL(10,2) NULL,
    percent_complete DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    sort_order INT NOT NULL DEFAULT 0,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    status ENUM('active','inactive','archived') NOT NULL DEFAULT 'active',
    notes TEXT NULL,
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    UNIQUE KEY uq_prj_task_uuid (public_uuid),
    UNIQUE KEY uq_prj_task_no (project_id, task_no),
    INDEX idx_prj_task_project (company_id, project_id, deleted_at),
    INDEX idx_prj_task_workflow (company_id, workflow_status),
    INDEX idx_prj_task_parent (parent_task_id),
    INDEX idx_prj_task_assignee (assignee_user_id),
    CONSTRAINT fk_prj_task_tenant FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_prj_task_project FOREIGN KEY (project_id) REFERENCES rateb_projects(id) ON DELETE CASCADE,
    CONSTRAINT fk_prj_task_phase FOREIGN KEY (phase_id) REFERENCES rateb_project_phases(id) ON DELETE SET NULL,
    CONSTRAINT fk_prj_task_ms FOREIGN KEY (milestone_id) REFERENCES rateb_project_milestones(id) ON DELETE SET NULL,
    CONSTRAINT fk_prj_task_parent FOREIGN KEY (parent_task_id) REFERENCES rateb_project_tasks(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_project_activities (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_uuid CHAR(36) NOT NULL,
    company_id INT UNSIGNED NOT NULL,
    branch_id INT UNSIGNED NULL,
    project_id INT UNSIGNED NOT NULL,
    task_id INT UNSIGNED NULL,
    activity_type VARCHAR(40) NOT NULL DEFAULT 'note',
    subject VARCHAR(190) NOT NULL,
    body TEXT NULL,
    activity_at DATETIME NULL,
    owner_user_id INT UNSIGNED NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    UNIQUE KEY uq_prj_act_uuid (public_uuid),
    INDEX idx_prj_act_project (company_id, project_id),
    INDEX idx_prj_act_task (task_id),
    CONSTRAINT fk_prj_act_tenant FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_prj_act_project FOREIGN KEY (project_id) REFERENCES rateb_projects(id) ON DELETE CASCADE,
    CONSTRAINT fk_prj_act_task FOREIGN KEY (task_id) REFERENCES rateb_project_tasks(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_project_timeline (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_uuid CHAR(36) NOT NULL,
    company_id INT UNSIGNED NOT NULL,
    branch_id INT UNSIGNED NULL,
    project_id INT UNSIGNED NULL,
    task_id INT UNSIGNED NULL,
    event_type VARCHAR(60) NOT NULL,
    title VARCHAR(190) NOT NULL,
    body TEXT NULL,
    related_type VARCHAR(40) NULL,
    related_id INT UNSIGNED NULL,
    meta_json JSON NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_prj_tl_uuid (public_uuid),
    INDEX idx_prj_tl_project (company_id, project_id, created_at),
    INDEX idx_prj_tl_task (task_id),
    CONSTRAINT fk_prj_tl_tenant FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_project_issues (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_uuid CHAR(36) NOT NULL,
    company_id INT UNSIGNED NOT NULL,
    branch_id INT UNSIGNED NULL,
    project_id INT UNSIGNED NOT NULL,
    task_id INT UNSIGNED NULL,
    issue_no VARCHAR(40) NOT NULL,
    title VARCHAR(190) NOT NULL,
    description TEXT NULL,
    severity ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium',
    status ENUM('open','in_progress','resolved','closed','cancelled') NOT NULL DEFAULT 'open',
    assignee_user_id INT UNSIGNED NULL,
    due_date DATE NULL,
    resolved_at DATETIME NULL,
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    UNIQUE KEY uq_prj_iss_uuid (public_uuid),
    UNIQUE KEY uq_prj_iss_no (project_id, issue_no),
    INDEX idx_prj_iss_project (company_id, project_id, status),
    CONSTRAINT fk_prj_iss_tenant FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_prj_iss_project FOREIGN KEY (project_id) REFERENCES rateb_projects(id) ON DELETE CASCADE,
    CONSTRAINT fk_prj_iss_task FOREIGN KEY (task_id) REFERENCES rateb_project_tasks(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_project_risks (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_uuid CHAR(36) NOT NULL,
    company_id INT UNSIGNED NOT NULL,
    branch_id INT UNSIGNED NULL,
    project_id INT UNSIGNED NOT NULL,
    risk_no VARCHAR(40) NOT NULL,
    title VARCHAR(190) NOT NULL,
    description TEXT NULL,
    probability ENUM('low','medium','high') NOT NULL DEFAULT 'medium',
    impact ENUM('low','medium','high') NOT NULL DEFAULT 'medium',
    status ENUM('identified','mitigating','accepted','closed') NOT NULL DEFAULT 'identified',
    owner_user_id INT UNSIGNED NULL,
    mitigation_plan TEXT NULL,
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    UNIQUE KEY uq_prj_risk_uuid (public_uuid),
    UNIQUE KEY uq_prj_risk_no (project_id, risk_no),
    INDEX idx_prj_risk_project (company_id, project_id, status),
    CONSTRAINT fk_prj_risk_tenant FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_prj_risk_project FOREIGN KEY (project_id) REFERENCES rateb_projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_project_timesheets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_uuid CHAR(36) NOT NULL,
    company_id INT UNSIGNED NOT NULL,
    branch_id INT UNSIGNED NULL,
    project_id INT UNSIGNED NOT NULL,
    task_id INT UNSIGNED NULL,
    user_id INT UNSIGNED NOT NULL,
    work_date DATE NOT NULL,
    hours DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    description TEXT NULL,
    status ENUM('draft','submitted','approved','rejected') NOT NULL DEFAULT 'draft',
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    UNIQUE KEY uq_prj_ts_uuid (public_uuid),
    INDEX idx_prj_ts_project (company_id, project_id, work_date),
    INDEX idx_prj_ts_user (user_id, work_date),
    CONSTRAINT fk_prj_ts_tenant FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_prj_ts_project FOREIGN KEY (project_id) REFERENCES rateb_projects(id) ON DELETE CASCADE,
    CONSTRAINT fk_prj_ts_task FOREIGN KEY (task_id) REFERENCES rateb_project_tasks(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_project_resources (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_uuid CHAR(36) NOT NULL,
    company_id INT UNSIGNED NOT NULL,
    branch_id INT UNSIGNED NULL,
    project_id INT UNSIGNED NOT NULL,
    resource_type ENUM('user','equipment','material','other') NOT NULL DEFAULT 'user',
    name VARCHAR(190) NOT NULL,
    user_id INT UNSIGNED NULL,
    allocation_percent DECIMAL(5,2) NULL,
    start_date DATE NULL,
    end_date DATE NULL,
    cost_rate DECIMAL(14,2) NULL,
    currency_code CHAR(3) NULL,
    status ENUM('planned','active','released') NOT NULL DEFAULT 'planned',
    notes TEXT NULL,
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    UNIQUE KEY uq_prj_res_uuid (public_uuid),
    INDEX idx_prj_res_project (company_id, project_id),
    CONSTRAINT fk_prj_res_tenant FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_prj_res_project FOREIGN KEY (project_id) REFERENCES rateb_projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_project_budgets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_uuid CHAR(36) NOT NULL,
    company_id INT UNSIGNED NOT NULL,
    branch_id INT UNSIGNED NULL,
    project_id INT UNSIGNED NOT NULL,
    category VARCHAR(80) NOT NULL DEFAULT 'general',
    planned_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    currency_code CHAR(3) NULL,
    notes TEXT NULL,
    status ENUM('draft','approved','locked') NOT NULL DEFAULT 'draft',
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    UNIQUE KEY uq_prj_bud_uuid (public_uuid),
    INDEX idx_prj_bud_project (company_id, project_id),
    CONSTRAINT fk_prj_bud_tenant FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_prj_bud_project FOREIGN KEY (project_id) REFERENCES rateb_projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_project_costs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_uuid CHAR(36) NOT NULL,
    company_id INT UNSIGNED NOT NULL,
    branch_id INT UNSIGNED NULL,
    project_id INT UNSIGNED NOT NULL,
    budget_id INT UNSIGNED NULL,
    cost_date DATE NOT NULL,
    amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    currency_code CHAR(3) NULL,
    category VARCHAR(80) NULL,
    description VARCHAR(255) NULL,
    status ENUM('recorded','void') NOT NULL DEFAULT 'recorded',
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    UNIQUE KEY uq_prj_cost_uuid (public_uuid),
    INDEX idx_prj_cost_project (company_id, project_id, cost_date),
    CONSTRAINT fk_prj_cost_tenant FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_prj_cost_project FOREIGN KEY (project_id) REFERENCES rateb_projects(id) ON DELETE CASCADE,
    CONSTRAINT fk_prj_cost_budget FOREIGN KEY (budget_id) REFERENCES rateb_project_budgets(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_project_comments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_uuid CHAR(36) NOT NULL,
    company_id INT UNSIGNED NOT NULL,
    branch_id INT UNSIGNED NULL,
    project_id INT UNSIGNED NOT NULL,
    task_id INT UNSIGNED NULL,
    body TEXT NOT NULL,
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    UNIQUE KEY uq_prj_cmt_uuid (public_uuid),
    INDEX idx_prj_cmt_project (company_id, project_id),
    INDEX idx_prj_cmt_task (task_id),
    CONSTRAINT fk_prj_cmt_tenant FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_prj_cmt_project FOREIGN KEY (project_id) REFERENCES rateb_projects(id) ON DELETE CASCADE,
    CONSTRAINT fk_prj_cmt_task FOREIGN KEY (task_id) REFERENCES rateb_project_tasks(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_project_assignments (
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
    UNIQUE KEY uq_prj_asg_uuid (public_uuid),
    INDEX idx_prj_asg_related (company_id, related_type, related_id),
    INDEX idx_prj_asg_user (assignee_user_id),
    CONSTRAINT fk_prj_asg_tenant FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_project_entity_tags (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    tag_id INT UNSIGNED NOT NULL,
    related_type VARCHAR(40) NOT NULL,
    related_id INT UNSIGNED NOT NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_prj_etag (company_id, tag_id, related_type, related_id),
    INDEX idx_prj_etag_related (related_type, related_id),
    CONSTRAINT fk_prj_etag_tenant FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_prj_etag_tag FOREIGN KEY (tag_id) REFERENCES rateb_project_tags(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_project_status_history (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    project_id INT UNSIGNED NULL,
    task_id INT UNSIGNED NULL,
    entity_type ENUM('project','task') NOT NULL DEFAULT 'project',
    from_status VARCHAR(40) NOT NULL,
    to_status VARCHAR(40) NOT NULL,
    reason VARCHAR(255) NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_prj_sh_project (company_id, project_id),
    INDEX idx_prj_sh_task (task_id),
    CONSTRAINT fk_prj_sh_tenant FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Permissions
-- ---------------------------------------------------------------------------
INSERT INTO rateb_permissions (name, name_ar, slug, module, description, description_ar) VALUES
('View Projects', 'عرض المشاريع', 'projects.view', 'projects', 'View projects and related records', 'عرض المشاريع والسجلات المرتبطة'),
('Create Projects', 'إنشاء المشاريع', 'projects.create', 'projects', 'Create projects', 'إنشاء المشاريع'),
('Update Projects', 'تحديث المشاريع', 'projects.update', 'projects', 'Update projects', 'تحديث المشاريع'),
('Delete Projects', 'حذف المشاريع', 'projects.delete', 'projects', 'Soft-delete projects', 'حذف ناعم للمشاريع'),
('Assign Projects', 'تعيين المشاريع', 'projects.assign', 'projects', 'Assign project members and owners', 'تعيين أعضاء ومالكي المشاريع'),
('Project Tasks', 'مهام المشاريع', 'projects.tasks', 'projects', 'Manage project tasks and subtasks', 'إدارة مهام المشاريع والمهام الفرعية'),
('Project Timesheets', 'سجلات وقت المشاريع', 'projects.timesheets', 'projects', 'Manage project timesheets', 'إدارة سجلات وقت المشاريع'),
('Project Budget', 'ميزانية المشاريع', 'projects.budget', 'projects', 'Manage project budgets and costs', 'إدارة ميزانيات وتكاليف المشاريع'),
('Project Reports', 'تقارير المشاريع', 'projects.reports', 'projects', 'View project reports', 'عرض تقارير المشاريع'),
('Projects Admin', 'إدارة كاملة للمشاريع', 'projects.admin', 'projects', 'Full projects administration', 'إدارة كاملة للمشاريع'),
('Manage Projects', 'إدارة المشاريع', 'projects.manage', 'projects', 'All project operations', 'جميع عمليات المشاريع')
ON DUPLICATE KEY UPDATE name = VALUES(name), module = VALUES(module), description = VALUES(description);

INSERT IGNORE INTO rateb_role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM rateb_roles r
JOIN rateb_permissions p ON p.slug IN (
    'projects.view', 'projects.create', 'projects.update', 'projects.delete', 'projects.assign',
    'projects.tasks', 'projects.timesheets', 'projects.budget', 'projects.reports',
    'projects.admin', 'projects.manage'
)
WHERE r.slug IN ('company-full-access', 'super-admin');
