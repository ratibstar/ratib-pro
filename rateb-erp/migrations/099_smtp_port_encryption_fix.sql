-- Fix swapped smtp_port / smtp_encryption values (common admin typo)
SET NAMES utf8mb4;

UPDATE rateb_system_settings SET setting_value = '587'
WHERE setting_key = 'smtp_port' AND setting_value NOT REGEXP '^[0-9]+$';

UPDATE rateb_system_settings SET setting_value = 'tls'
WHERE setting_key = 'smtp_encryption' AND setting_value REGEXP '^[0-9]+$';

UPDATE rateb_system_settings SET setting_value = 'localhost'
WHERE setting_key = 'smtp_host' AND setting_value IN ('mail.rateb.sa', 'rateb.sa');
