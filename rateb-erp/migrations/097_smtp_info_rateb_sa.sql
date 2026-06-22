-- RATEB ERP — default SMTP for info@rateb.sa (DirectAdmin / mail.rateb.sa)
-- Password: set RATEB_ERP_SMTP_PASS in server .env or config/mail.secrets.php (never in SQL).
SET NAMES utf8mb4;

INSERT INTO rateb_system_settings (setting_key, setting_value, setting_group) VALUES
('smtp_host', 'mail.rateb.sa', 'mail'),
('smtp_port', '587', 'mail'),
('smtp_encryption', 'tls', 'mail'),
('smtp_user', 'info@rateb.sa', 'mail'),
('smtp_from_email', 'info@rateb.sa', 'mail'),
('smtp_from_name', 'Rateb ERP', 'mail')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), setting_group = VALUES(setting_group);
