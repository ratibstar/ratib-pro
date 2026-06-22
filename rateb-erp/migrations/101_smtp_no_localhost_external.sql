-- External mail must use mail.rateb.sa (localhost only delivers to @rateb.sa)
SET NAMES utf8mb4;

UPDATE rateb_system_settings SET setting_value = 'mail.rateb.sa' WHERE setting_key = 'smtp_host' AND setting_value IN ('localhost', '127.0.0.1');
UPDATE rateb_system_settings SET setting_value = '587' WHERE setting_key = 'smtp_port' AND setting_value NOT REGEXP '^[0-9]+$';
UPDATE rateb_system_settings SET setting_value = 'tls' WHERE setting_key = 'smtp_encryption' AND setting_value NOT IN ('tls', 'ssl', 'none');
