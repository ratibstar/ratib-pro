-- RATEB ERP — DirectAdmin on-server SMTP (localhost:587 TLS works best on same server)
SET NAMES utf8mb4;

UPDATE rateb_system_settings SET setting_value = 'localhost' WHERE setting_key = 'smtp_host';
UPDATE rateb_system_settings SET setting_value = '587' WHERE setting_key = 'smtp_port';
UPDATE rateb_system_settings SET setting_value = 'tls' WHERE setting_key = 'smtp_encryption';
