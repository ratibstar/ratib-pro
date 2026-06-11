-- RATEB ERP P0 hardening: migration tracking, indexes, SMTP settings
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS rateb_migrations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(255) NOT NULL,
    applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_migration_filename (filename)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO rateb_system_settings (setting_key, setting_value, setting_group) VALUES
('smtp_host', '', 'mail'),
('smtp_port', '587', 'mail'),
('smtp_user', '', 'mail'),
('smtp_pass', '', 'mail')
ON DUPLICATE KEY UPDATE setting_key = setting_key;

CREATE INDEX IF NOT EXISTS idx_notif_dedup ON rateb_notifications (company_id, trigger_type, entity_type, entity_id, created_at);
CREATE INDEX IF NOT EXISTS idx_nq_status_created ON rateb_notification_queue (status, created_at);
CREATE INDEX IF NOT EXISTS idx_inventory_expiry ON rateb_inventory (company_id, expiry_date);
CREATE INDEX IF NOT EXISTS idx_contracts_expiry ON rateb_contracts (company_id, end_date, status);
