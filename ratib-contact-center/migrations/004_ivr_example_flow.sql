-- Example multi-tenant IVR flow: Press 1 Sales, 2 Support (ticket), 0 Operator
SET NAMES utf8mb4;

INSERT INTO rcc_tenants (id, erp_company_id, name, slug, default_locale, status)
VALUES (1, 1, 'Demo Company', 'demo-company', 'ar', 'active')
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO rcc_queues (tenant_id, name, code, status) VALUES
(1, 'Sales Queue', 'sales', 'active'),
(1, 'Support Queue', 'support', 'active')
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO rcc_ivr_flows (id, tenant_id, name, is_active, default_locale)
VALUES (1, 1, 'Main Menu AR/EN', 1, 'ar')
ON DUPLICATE KEY UPDATE is_active = 1;

-- Node 1: Welcome message
INSERT INTO rcc_ivr_nodes (id, flow_id, type, payload, next_node_id, fallback_node_id, max_retries, timeout_seconds, sort_order)
VALUES (
    1, 1, 'play_message',
    JSON_OBJECT(
        'message_en', 'Welcome to Ratib Contact Center.',
        'message_ar', 'مرحباً بكم في مركز اتصال رتب.',
        'message', 'Welcome to Ratib Contact Center.'
    ),
    2, NULL, 3, 10, 1
) ON DUPLICATE KEY UPDATE payload = VALUES(payload), next_node_id = VALUES(next_node_id);

-- Node 2: Main menu collect
INSERT INTO rcc_ivr_nodes (id, flow_id, type, payload, next_node_id, fallback_node_id, max_retries, timeout_seconds, sort_order)
VALUES (
    2, 1, 'collect_input',
    JSON_OBJECT(
        'message_en', 'Press 1 for sales, 2 for support, 0 for operator.',
        'message_ar', 'اضغط 1 للمبيعات، 2 للدعم، 0 للموظف.',
        'max_digits', 1
    ),
    3, 5, 3, 10, 2
) ON DUPLICATE KEY UPDATE payload = VALUES(payload), next_node_id = VALUES(next_node_id), fallback_node_id = VALUES(fallback_node_id);

-- Node 3: Route by DTMF
INSERT INTO rcc_ivr_nodes (id, flow_id, type, payload, next_node_id, fallback_node_id, max_retries, timeout_seconds, sort_order)
VALUES (
    3, 1, 'route_call',
    JSON_OBJECT(
        'routes', JSON_ARRAY(
            JSON_OBJECT(
                'dtmf', '1',
                'label', 'Sales',
                'action', 'queue',
                'queue_code', 'sales',
                'next_node_id', 4
            ),
            JSON_OBJECT(
                'dtmf', '2',
                'label', 'Support',
                'action', 'create_ticket',
                'ticket_subject', 'IVR Support Request',
                'ticket_description', 'Customer selected support from IVR menu.',
                'next_node_id', 4
            ),
            JSON_OBJECT(
                'dtmf', '0',
                'label', 'Operator',
                'action', 'extension',
                'extension', '100'
            )
        ),
        'default', JSON_OBJECT(
            'action', 'next_node',
            'next_node_id', 5
        )
    ),
    4, 5, 3, 10, 3
) ON DUPLICATE KEY UPDATE payload = VALUES(payload);

-- Node 4: Transfer confirmation
INSERT INTO rcc_ivr_nodes (id, flow_id, type, payload, next_node_id, fallback_node_id, max_retries, timeout_seconds, sort_order)
VALUES (
    4, 1, 'play_message',
    JSON_OBJECT(
        'message_en', 'Please hold while we connect you.',
        'message_ar', 'يرجى الانتظار بينما نقوم بتوصيلك.'
    ),
    6, NULL, 3, 10, 4
) ON DUPLICATE KEY UPDATE payload = VALUES(payload), next_node_id = VALUES(next_node_id);

-- Node 5: Invalid input fallback
INSERT INTO rcc_ivr_nodes (id, flow_id, type, payload, next_node_id, fallback_node_id, max_retries, timeout_seconds, sort_order)
VALUES (
    5, 1, 'play_message',
    JSON_OBJECT(
        'message_en', 'Invalid selection. Returning to menu.',
        'message_ar', 'اختيار غير صحيح. العودة للقائمة.'
    ),
    2, NULL, 3, 10, 5
) ON DUPLICATE KEY UPDATE payload = VALUES(payload), next_node_id = VALUES(next_node_id);

-- Node 6: Hangup after routing
INSERT INTO rcc_ivr_nodes (id, flow_id, type, payload, next_node_id, fallback_node_id, max_retries, timeout_seconds, sort_order)
VALUES (
    6, 1, 'hangup',
    JSON_OBJECT('reason', 'routed'),
    NULL, NULL, 3, 10, 6
) ON DUPLICATE KEY UPDATE payload = VALUES(payload);

UPDATE rcc_ivr_flows SET entry_node_id = 1 WHERE id = 1 AND tenant_id = 1;
