-- RATEB ERP — Phase 20A Enterprise Approval Workflow Platform (ONLINE)
-- Additive EAP layer. Does NOT alter legacy rateb_approval_* / WorkflowService / Offline Foundation.
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

CREATE TABLE IF NOT EXISTS rateb_eap_templates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_uuid CHAR(36) NOT NULL,
    company_id INT UNSIGNED NOT NULL,
    branch_id INT UNSIGNED NULL,
    code VARCHAR(40) NOT NULL,
    name VARCHAR(190) NOT NULL,
    name_ar VARCHAR(190) NULL,
    module_key VARCHAR(60) NULL,
    description TEXT NULL,
    status ENUM('active','inactive','archived') NOT NULL DEFAULT 'active',
    version INT UNSIGNED NOT NULL DEFAULT 1,
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    UNIQUE KEY uq_eap_tpl_uuid (public_uuid),
    UNIQUE KEY uq_eap_tpl_code (company_id, code),
    INDEX idx_eap_tpl_company (company_id, deleted_at),
    CONSTRAINT fk_eap_tpl_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_eap_stages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_uuid CHAR(36) NOT NULL,
    company_id INT UNSIGNED NOT NULL,
    branch_id INT UNSIGNED NULL,
    template_id INT UNSIGNED NOT NULL,
    code VARCHAR(40) NOT NULL,
    name VARCHAR(160) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    approver_role VARCHAR(80) NULL,
    min_approvals INT UNSIGNED NOT NULL DEFAULT 1,
    sla_hours INT UNSIGNED NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    version INT UNSIGNED NOT NULL DEFAULT 1,
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    UNIQUE KEY uq_eap_stg_uuid (public_uuid),
    UNIQUE KEY uq_eap_stg_code (company_id, template_id, code),
    INDEX idx_eap_stg_template (company_id, template_id),
    CONSTRAINT fk_eap_stg_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_eap_stg_template FOREIGN KEY (template_id) REFERENCES rateb_eap_templates(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_eap_rules (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_uuid CHAR(36) NOT NULL,
    company_id INT UNSIGNED NOT NULL,
    branch_id INT UNSIGNED NULL,
    template_id INT UNSIGNED NULL,
    code VARCHAR(40) NOT NULL,
    name VARCHAR(160) NOT NULL,
    rule_type VARCHAR(40) NOT NULL DEFAULT 'amount',
    condition_json JSON NULL,
    priority INT NOT NULL DEFAULT 0,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    version INT UNSIGNED NOT NULL DEFAULT 1,
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    UNIQUE KEY uq_eap_rule_uuid (public_uuid),
    UNIQUE KEY uq_eap_rule_code (company_id, code),
    INDEX idx_eap_rule_template (company_id, template_id),
    CONSTRAINT fk_eap_rule_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_eap_rule_template FOREIGN KEY (template_id) REFERENCES rateb_eap_templates(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_eap_chains (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_uuid CHAR(36) NOT NULL,
    company_id INT UNSIGNED NOT NULL,
    branch_id INT UNSIGNED NULL,
    template_id INT UNSIGNED NOT NULL,
    code VARCHAR(40) NOT NULL,
    name VARCHAR(160) NOT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    version INT UNSIGNED NOT NULL DEFAULT 1,
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    UNIQUE KEY uq_eap_chn_uuid (public_uuid),
    UNIQUE KEY uq_eap_chn_code (company_id, code),
    INDEX idx_eap_chn_template (company_id, template_id),
    CONSTRAINT fk_eap_chn_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_eap_chn_template FOREIGN KEY (template_id) REFERENCES rateb_eap_templates(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_eap_chain_stages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_uuid CHAR(36) NOT NULL,
    company_id INT UNSIGNED NOT NULL,
    chain_id INT UNSIGNED NOT NULL,
    stage_id INT UNSIGNED NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    UNIQUE KEY uq_eap_cs_uuid (public_uuid),
    UNIQUE KEY uq_eap_cs_link (chain_id, stage_id),
    INDEX idx_eap_cs_chain (company_id, chain_id, sort_order),
    CONSTRAINT fk_eap_cs_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_eap_cs_chain FOREIGN KEY (chain_id) REFERENCES rateb_eap_chains(id) ON DELETE CASCADE,
    CONSTRAINT fk_eap_cs_stage FOREIGN KEY (stage_id) REFERENCES rateb_eap_stages(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_eap_requests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_uuid CHAR(36) NOT NULL,
    company_id INT UNSIGNED NOT NULL,
    branch_id INT UNSIGNED NULL,
    request_no VARCHAR(40) NOT NULL,
    title VARCHAR(190) NOT NULL,
    template_id INT UNSIGNED NULL,
    chain_id INT UNSIGNED NULL,
    current_stage_id INT UNSIGNED NULL,
    related_module VARCHAR(60) NULL,
    related_type VARCHAR(60) NULL,
    related_id INT UNSIGNED NULL,
    workflow_status VARCHAR(40) NOT NULL DEFAULT 'draft',
    priority ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
    amount DECIMAL(14,2) NULL,
    currency_code CHAR(3) NULL,
    submitted_at DATETIME NULL,
    decided_at DATETIME NULL,
    requester_user_id INT UNSIGNED NULL,
    status ENUM('active','inactive','archived') NOT NULL DEFAULT 'active',
    notes TEXT NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    UNIQUE KEY uq_eap_req_uuid (public_uuid),
    UNIQUE KEY uq_eap_req_no (company_id, request_no),
    INDEX idx_eap_req_company (company_id, deleted_at),
    INDEX idx_eap_req_workflow (company_id, workflow_status),
    INDEX idx_eap_req_related (company_id, related_type, related_id),
    CONSTRAINT fk_eap_req_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_eap_req_template FOREIGN KEY (template_id) REFERENCES rateb_eap_templates(id) ON DELETE SET NULL,
    CONSTRAINT fk_eap_req_chain FOREIGN KEY (chain_id) REFERENCES rateb_eap_chains(id) ON DELETE SET NULL,
    CONSTRAINT fk_eap_req_stage FOREIGN KEY (current_stage_id) REFERENCES rateb_eap_stages(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_eap_actions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_uuid CHAR(36) NOT NULL,
    company_id INT UNSIGNED NOT NULL,
    branch_id INT UNSIGNED NULL,
    request_id INT UNSIGNED NOT NULL,
    stage_id INT UNSIGNED NULL,
    action_type ENUM('approve','reject','delegate','escalate','comment','submit','cancel') NOT NULL,
    actor_user_id INT UNSIGNED NULL,
    comment TEXT NULL,
    acted_at DATETIME NOT NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    UNIQUE KEY uq_eap_act_uuid (public_uuid),
    INDEX idx_eap_act_request (company_id, request_id),
    CONSTRAINT fk_eap_act_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_eap_act_request FOREIGN KEY (request_id) REFERENCES rateb_eap_requests(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_eap_delegations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_uuid CHAR(36) NOT NULL,
    company_id INT UNSIGNED NOT NULL,
    branch_id INT UNSIGNED NULL,
    request_id INT UNSIGNED NULL,
    from_user_id INT UNSIGNED NOT NULL,
    to_user_id INT UNSIGNED NOT NULL,
    starts_at DATETIME NULL,
    ends_at DATETIME NULL,
    status ENUM('active','revoked','expired') NOT NULL DEFAULT 'active',
    notes TEXT NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    UNIQUE KEY uq_eap_dlg_uuid (public_uuid),
    INDEX idx_eap_dlg_request (company_id, request_id),
    CONSTRAINT fk_eap_dlg_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_eap_dlg_request FOREIGN KEY (request_id) REFERENCES rateb_eap_requests(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_eap_escalations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_uuid CHAR(36) NOT NULL,
    company_id INT UNSIGNED NOT NULL,
    branch_id INT UNSIGNED NULL,
    request_id INT UNSIGNED NOT NULL,
    stage_id INT UNSIGNED NULL,
    escalate_to_user_id INT UNSIGNED NULL,
    reason VARCHAR(255) NULL,
    escalated_at DATETIME NOT NULL,
    status ENUM('open','resolved','cancelled') NOT NULL DEFAULT 'open',
    version INT UNSIGNED NOT NULL DEFAULT 1,
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    UNIQUE KEY uq_eap_esc_uuid (public_uuid),
    INDEX idx_eap_esc_request (company_id, request_id),
    CONSTRAINT fk_eap_esc_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_eap_esc_request FOREIGN KEY (request_id) REFERENCES rateb_eap_requests(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_eap_sla (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_uuid CHAR(36) NOT NULL,
    company_id INT UNSIGNED NOT NULL,
    branch_id INT UNSIGNED NULL,
    request_id INT UNSIGNED NOT NULL,
    stage_id INT UNSIGNED NULL,
    due_at DATETIME NULL,
    breached_at DATETIME NULL,
    status ENUM('ok','warning','breached','closed') NOT NULL DEFAULT 'ok',
    version INT UNSIGNED NOT NULL DEFAULT 1,
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    UNIQUE KEY uq_eap_sla_uuid (public_uuid),
    INDEX idx_eap_sla_request (company_id, request_id),
    CONSTRAINT fk_eap_sla_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_eap_sla_request FOREIGN KEY (request_id) REFERENCES rateb_eap_requests(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_eap_reminders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_uuid CHAR(36) NOT NULL,
    company_id INT UNSIGNED NOT NULL,
    branch_id INT UNSIGNED NULL,
    request_id INT UNSIGNED NOT NULL,
    remind_at DATETIME NOT NULL,
    channel VARCHAR(40) NULL,
    status ENUM('pending','sent','cancelled') NOT NULL DEFAULT 'pending',
    version INT UNSIGNED NOT NULL DEFAULT 1,
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    UNIQUE KEY uq_eap_rmd_uuid (public_uuid),
    INDEX idx_eap_rmd_request (company_id, request_id),
    CONSTRAINT fk_eap_rmd_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_eap_rmd_request FOREIGN KEY (request_id) REFERENCES rateb_eap_requests(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_eap_timeline (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_uuid CHAR(36) NOT NULL,
    company_id INT UNSIGNED NOT NULL,
    branch_id INT UNSIGNED NULL,
    request_id INT UNSIGNED NULL,
    event_type VARCHAR(60) NOT NULL,
    title VARCHAR(190) NOT NULL,
    body TEXT NULL,
    related_type VARCHAR(40) NULL,
    related_id INT UNSIGNED NULL,
    meta_json JSON NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_eap_tl_uuid (public_uuid),
    INDEX idx_eap_tl_request (company_id, request_id, created_at),
    CONSTRAINT fk_eap_tl_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_eap_comments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_uuid CHAR(36) NOT NULL,
    company_id INT UNSIGNED NOT NULL,
    branch_id INT UNSIGNED NULL,
    request_id INT UNSIGNED NOT NULL,
    body TEXT NOT NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    UNIQUE KEY uq_eap_cmt_uuid (public_uuid),
    INDEX idx_eap_cmt_request (company_id, request_id),
    CONSTRAINT fk_eap_cmt_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_eap_cmt_request FOREIGN KEY (request_id) REFERENCES rateb_eap_requests(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_eap_audit (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_uuid CHAR(36) NOT NULL,
    company_id INT UNSIGNED NOT NULL,
    branch_id INT UNSIGNED NULL,
    request_id INT UNSIGNED NULL,
    event_code VARCHAR(60) NOT NULL,
    message VARCHAR(255) NOT NULL,
    meta_json JSON NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_eap_aud_uuid (public_uuid),
    INDEX idx_eap_aud_request (company_id, request_id),
    CONSTRAINT fk_eap_aud_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_eap_notification_meta (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_uuid CHAR(36) NOT NULL,
    company_id INT UNSIGNED NOT NULL,
    branch_id INT UNSIGNED NULL,
    request_id INT UNSIGNED NOT NULL,
    channel VARCHAR(40) NOT NULL DEFAULT 'in_app',
    recipient_user_id INT UNSIGNED NULL,
    subject VARCHAR(190) NULL,
    status ENUM('queued','sent','failed','cancelled') NOT NULL DEFAULT 'queued',
    notification_id INT UNSIGNED NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    UNIQUE KEY uq_eap_nm_uuid (public_uuid),
    INDEX idx_eap_nm_request (company_id, request_id),
    CONSTRAINT fk_eap_nm_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_eap_nm_request FOREIGN KEY (request_id) REFERENCES rateb_eap_requests(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_eap_attachment_meta (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_uuid CHAR(36) NOT NULL,
    company_id INT UNSIGNED NOT NULL,
    branch_id INT UNSIGNED NULL,
    request_id INT UNSIGNED NOT NULL,
    document_id INT UNSIGNED NULL,
    title VARCHAR(190) NOT NULL,
    doc_type VARCHAR(40) NULL,
    status ENUM('active','archived') NOT NULL DEFAULT 'active',
    version INT UNSIGNED NOT NULL DEFAULT 1,
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    UNIQUE KEY uq_eap_att_uuid (public_uuid),
    INDEX idx_eap_att_request (company_id, request_id),
    CONSTRAINT fk_eap_att_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_eap_att_request FOREIGN KEY (request_id) REFERENCES rateb_eap_requests(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_eap_status_history (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    request_id INT UNSIGNED NOT NULL,
    from_status VARCHAR(40) NOT NULL,
    to_status VARCHAR(40) NOT NULL,
    reason VARCHAR(255) NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_eap_sh_request (company_id, request_id),
    CONSTRAINT fk_eap_sh_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_eap_sh_request FOREIGN KEY (request_id) REFERENCES rateb_eap_requests(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO rateb_permissions (name, name_ar, slug, module, description, description_ar) VALUES
('View Approvals', 'عرض الموافقات', 'approval.view', 'approval', 'View enterprise approval platform', 'عرض منصة الموافقات المؤسسية'),
('Create Approvals', 'إنشاء الموافقات', 'approval.create', 'approval', 'Create approval templates and requests', 'إنشاء قوالب وطلبات الموافقة'),
('Submit Approvals', 'تقديم الموافقات', 'approval.submit', 'approval', 'Submit approval requests', 'تقديم طلبات الموافقة'),
('Approve Requests', 'اعتماد الطلبات', 'approval.approve', 'approval', 'Approve pending requests', 'اعتماد الطلبات المعلقة'),
('Reject Requests', 'رفض الطلبات', 'approval.reject', 'approval', 'Reject pending requests', 'رفض الطلبات المعلقة'),
('Delegate Approvals', 'تفويض الموافقات', 'approval.delegate', 'approval', 'Delegate approval authority', 'تفويض صلاحية الموافقة'),
('Approvals Admin', 'إدارة كاملة للموافقات', 'approval.admin', 'approval', 'Full enterprise approvals administration', 'إدارة كاملة لمنصة الموافقات'),
('Manage Approvals', 'إدارة الموافقات', 'approval.manage', 'approval', 'All enterprise approval operations', 'جميع عمليات منصة الموافقات')
ON DUPLICATE KEY UPDATE name = VALUES(name), module = VALUES(module), description = VALUES(description);

INSERT IGNORE INTO rateb_role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM rateb_roles r
JOIN rateb_permissions p ON p.slug IN (
    'approval.view', 'approval.create', 'approval.submit', 'approval.approve',
    'approval.reject', 'approval.delegate', 'approval.admin', 'approval.manage'
)
WHERE r.slug IN ('company-full-access', 'super-admin');
