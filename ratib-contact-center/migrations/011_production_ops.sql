-- RATIB Contact Center — 011 production operations layer (Phase 8)

CREATE TABLE IF NOT EXISTS rcc_pbx_servers (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    name VARCHAR(128) NOT NULL,
    ami_host VARCHAR(255) NOT NULL,
    ami_port INT UNSIGNED NOT NULL DEFAULT 5038,
    ami_username VARCHAR(64) NOT NULL,
    ami_secret_ref VARCHAR(128) NOT NULL,
    sip_domain VARCHAR(255) NOT NULL,
    wss_uri VARCHAR(512) NULL,
    rtp_start INT UNSIGNED NULL,
    rtp_end INT UNSIGNED NULL,
    dialplan_package VARCHAR(64) NOT NULL DEFAULT 'deploy/asterisk',
    status ENUM('draft','active','disabled') NOT NULL DEFAULT 'draft',
    last_health_at TIMESTAMP NULL,
    last_health_status VARCHAR(32) NULL,
    config_json JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_rcc_pbx_tenant (tenant_id),
    KEY idx_rcc_pbx_tenant_status (tenant_id, status),
    CONSTRAINT fk_rcc_pbx_tenant FOREIGN KEY (tenant_id) REFERENCES rcc_tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rcc_ops_checklist_steps (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug VARCHAR(64) NOT NULL,
    category VARCHAR(64) NOT NULL,
    title VARCHAR(255) NOT NULL,
    title_ar VARCHAR(255) NULL,
    description TEXT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    verify_action VARCHAR(64) NULL,
    is_required TINYINT(1) NOT NULL DEFAULT 1,
    is_system TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_rcc_ops_step_slug (slug),
    KEY idx_rcc_ops_step_category (category, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rcc_ops_checklist_status (
    tenant_id INT UNSIGNED NOT NULL,
    step_slug VARCHAR(64) NOT NULL,
    status ENUM('pending','pass','fail','skipped') NOT NULL DEFAULT 'pending',
    evidence_json JSON NULL,
    verified_by_user_id INT UNSIGNED NULL,
    verified_at TIMESTAMP NULL,
    notes TEXT NULL,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (tenant_id, step_slug),
    KEY idx_rcc_ops_checklist_tenant (tenant_id, status),
    CONSTRAINT fk_rcc_ops_checklist_tenant FOREIGN KEY (tenant_id) REFERENCES rcc_tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO rcc_permissions (slug, name, module) VALUES
('rcc.ops.view', 'View Operations Center', 'ops'),
('rcc.ops.pbx', 'Manage PBX Deployment', 'ops'),
('rcc.ops.sip', 'Manage SIP Extensions', 'ops'),
('rcc.ops.queues', 'Manage Queues', 'ops'),
('rcc.ops.ivr', 'Manage IVR Flows', 'ops'),
('rcc.ops.agents', 'Provision Agents', 'ops'),
('rcc.ops.diagnostics', 'Run Diagnostics', 'ops'),
('rcc.ops.hub', 'Manage Realtime Hub', 'ops'),
('rcc.ops.golive', 'Manage Go-Live Checklist', 'ops'),
('rcc.tenants.manage', 'Manage Tenants', 'admin');

INSERT IGNORE INTO rcc_role_permissions (role_id, permission_id)
SELECT 1, id FROM rcc_permissions WHERE slug LIKE 'rcc.ops.%' OR slug = 'rcc.tenants.manage';

INSERT IGNORE INTO rcc_role_permissions (role_id, permission_id)
SELECT 2, id FROM rcc_permissions WHERE slug IN (
    'rcc.ops.view', 'rcc.ops.diagnostics', 'rcc.ops.hub', 'rcc.ops.golive',
    'rcc.ops.queues', 'rcc.ops.agents'
);

INSERT IGNORE INTO rcc_ops_checklist_steps (slug, category, title, title_ar, description, sort_order, verify_action, is_required) VALUES
('db_schema', 'foundation', 'Database schema migrated', 'ترحيل قاعدة البيانات', 'All rcc_* migrations applied and verified.', 10, 'health_center', 1),
('pbx_configured', 'telephony', 'PBX server configured and tested', 'إعداد خادم المقاسم', 'Active PBX record with successful AMI login.', 20, 'diag_ami', 1),
('sip_extensions', 'telephony', 'SIP extensions provisioned', 'توفير امتدادات SIP', 'At least one active WebRTC extension per tenant.', 30, 'sip_list', 1),
('queues_ready', 'telephony', 'Queues and members configured', 'إعداد الطوابير', 'Active queue with at least one member agent.', 40, 'queue_list', 1),
('ivr_published', 'telephony', 'IVR flow published', 'نشر مسار IVR', 'At least one active IVR flow with entry node.', 50, 'ivr_flows_list', 1),
('agents_provisioned', 'workforce', 'Agents provisioned', 'توفير الوكلاء', 'Active agents linked to users and queues.', 60, 'agent_list', 1),
('realtime_hub', 'realtime', 'Realtime hub running', 'تشغيل محور الوقت الفعلي', 'WebSocket hub listening when mode is websocket.', 70, 'hub_status', 1),
('voice_worker', 'telephony', 'Voice worker reachable', 'عامل الصوت', 'AMI voice worker script present and AMI reachable.', 80, 'diag_voice_worker', 1),
('webrtc_diag', 'telephony', 'WebRTC path verified', 'مسار WebRTC', 'WSS URI and SIP credentials resolvable.', 90, 'diag_webrtc', 1),
('omnichannel_secrets', 'channels', 'Omnichannel credentials set', 'قنوات الاتصال', 'SMTP/WhatsApp env or settings configured.', 100, NULL, 0),
('security_webhooks', 'security', 'Webhook signing configured', 'توقيع Webhooks', 'RCC_WEBHOOK_SECRET or WEBHOOK_SIGNING_SECRET set.', 110, NULL, 0),
('golive_signoff', 'golive', 'Operations sign-off', 'اعتماد التشغيل', 'All required checklist items passed.', 120, 'checklist_summary', 1);
