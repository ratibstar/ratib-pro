-- RATEB ERP automation hardening schema

ALTER TABLE rateb_users
    ADD COLUMN failed_attempts INT UNSIGNED NOT NULL DEFAULT 0,
    ADD COLUMN locked_until DATETIME NULL;

CREATE TABLE IF NOT EXISTS rateb_remember_tokens (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    device_name VARCHAR(255) NULL,
    expires_at DATETIME NOT NULL,
    last_used_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    revoked_at DATETIME NULL,
    UNIQUE KEY uq_remember_hash (token_hash),
    KEY idx_remember_user (user_id),
    KEY idx_remember_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_two_factor_backup_codes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    code_hash CHAR(64) NOT NULL,
    used_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_2fa_backup_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_warehouse_transfers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    transfer_no VARCHAR(50) NOT NULL,
    source_warehouse_id INT UNSIGNED NOT NULL,
    destination_warehouse_id INT UNSIGNED NOT NULL,
    inventory_id INT UNSIGNED NOT NULL,
    quantity DECIMAL(15,4) NOT NULL,
    status ENUM('draft','pending','approved','completed','rejected','cancelled') NOT NULL DEFAULT 'draft',
    notes TEXT NULL,
    created_by INT UNSIGNED NULL,
    approved_by INT UNSIGNED NULL,
    completed_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_wt_company (company_id),
    KEY idx_wt_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE rateb_approval_instances
    ADD COLUMN due_at DATETIME NULL,
    ADD COLUMN escalated_at DATETIME NULL,
    ADD COLUMN escalation_count INT UNSIGNED NOT NULL DEFAULT 0;

ALTER TABLE rateb_approval_workflow_steps
    ADD COLUMN sla_hours INT UNSIGNED NULL DEFAULT 48;

ALTER TABLE rateb_notification_queue
    ADD COLUMN next_retry_at DATETIME NULL,
    ADD COLUMN dead_letter_at DATETIME NULL;

ALTER TABLE rateb_contracts
    ADD COLUMN signature_status ENUM('none','pending','partial','signed') NOT NULL DEFAULT 'none',
    ADD COLUMN signature_trail JSON NULL;

CREATE TABLE IF NOT EXISTS rateb_cron_health (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    job_name VARCHAR(100) NOT NULL,
    last_run_at DATETIME NOT NULL,
    next_expected_at DATETIME NULL,
    status ENUM('ok','late','failed') NOT NULL DEFAULT 'ok',
    stats_json TEXT NULL,
    UNIQUE KEY uq_cron_job (job_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO rateb_system_settings (setting_key, setting_value, setting_group) VALUES
    ('lockout_max_attempts', '5', 'security'),
    ('lockout_duration_minutes', '30', 'security'),
    ('remember_me_days', '30', 'security'),
    ('smtp_encryption', 'tls', 'mail'),
    ('sms_provider', 'log', 'sms'),
    ('sms_api_url', '', 'sms'),
    ('sms_api_key', '', 'sms'),
    ('sms_sender_id', 'RTAB', 'sms'),
    ('backup_retention_days', '30', 'backup'),
    ('trial_reminder_days', '7', 'saas'),
    ('subscription_reminder_days', '14', 'saas'),
    ('supplier_kpi_poor_threshold', '50', 'suppliers'),
    ('supplier_inactive_days', '180', 'suppliers')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

INSERT INTO rateb_email_templates (slug, subject, body_html, is_active) VALUES
    ('subscription_expiring', 'Subscription expiring soon', '<p>Hello {name}, your subscription expires on {date}.</p>', 1),
    ('trial_expiring', 'Trial ending soon', '<p>Hello {name}, your trial ends on {date}.</p>', 1),
    ('approval_request', 'Approval required: {type}', '<p>You have a pending approval for {type} #{id}.</p>', 1),
    ('approval_completed', 'Approval completed', '<p>{type} #{id} was approved.</p>', 1),
    ('approval_rejected', 'Approval rejected', '<p>{type} #{id} was rejected.</p>', 1),
    ('low_stock_alert', 'Low stock: {item}', '<p>{item} is low ({qty} remaining).</p>', 1),
    ('expiry_alert', 'Expiry alert: {item}', '<p>{item} expires on {date}.</p>', 1),
    ('contract_expiry_alert', 'Contract expiring: {no}', '<p>Contract {no} ends on {date}.</p>', 1),
    ('maintenance_due_alert', 'Maintenance due: {device}', '<p>{device} maintenance due on {date}.</p>', 1),
    ('warranty_expiry_alert', 'Warranty expiring: {device}', '<p>{device} warranty expires on {date}.</p>', 1)
ON DUPLICATE KEY UPDATE subject = VALUES(subject);
