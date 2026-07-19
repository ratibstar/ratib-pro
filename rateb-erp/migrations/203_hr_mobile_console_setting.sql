-- HR Mobile Console feature flag (Admin Settings → Features).
-- Default OFF. Toggle via rateb_system_settings; not env/dotenv.
INSERT INTO rateb_system_settings (setting_key, setting_value, setting_group) VALUES
('hr_mobile_console_enabled', '0', 'features')
ON DUPLICATE KEY UPDATE setting_key = setting_key;
