-- RATIB Contact Center — 010 production bootstrap (idempotent)
-- Seeds first tenant, agent, queue, and SIP extension placeholders.
-- Change agent email/password via admin after first login.

INSERT IGNORE INTO rcc_tenants (id, code, name, name_ar, erp_company_id, locale, timezone, status)
VALUES (1, 'rateb', 'RATEB Contact Center', 'مركز اتصال راتب', 1, 'ar', 'Asia/Riyadh', 'active');

INSERT IGNORE INTO rcc_users (id, tenant_id, email, password_hash, full_name, locale, status)
VALUES (
    1,
    1,
    'agent@rateb.sa',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'Default Agent',
    'ar',
    'active'
);

INSERT IGNORE INTO rcc_user_roles (user_id, role_id, tenant_id)
VALUES (1, 3, 1);

INSERT IGNORE INTO rcc_agents (id, tenant_id, user_id, extension, display_name, email, status)
VALUES (1, 1, 1, '1001', 'Default Agent', 'agent@rateb.sa', 'active');

INSERT IGNORE INTO rcc_queues (id, tenant_id, code, name, name_ar, sla_target_seconds, status, strategy)
VALUES (1, 1, 'support', 'Support', 'الدعم', 300, 'active', 'rrmemory');

INSERT IGNORE INTO rcc_queue_members (tenant_id, queue_id, agent_id, penalty, is_paused)
VALUES (1, 1, 1, 0, 0);

INSERT IGNORE INTO rcc_sip_extensions (
    tenant_id, agent_id, extension, sip_username, sip_password_ref, sip_domain, wss_uri, webrtc_enabled, status
)
VALUES (
    1,
    1,
    '1001',
    '1001',
    'RCC_SIP_EXT_1001',
    'pbx.rateb.sa',
    'wss://pbx.rateb.sa:8089/ws',
    1,
    'active'
);

INSERT IGNORE INTO rcc_settings (tenant_id, group_key, setting_key, setting_value)
VALUES
    (1, 'softphone', 'auto_answer_queue_calls', '0'),
    (1, 'realtime', 'mode', 'websocket');
