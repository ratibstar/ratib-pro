-- Product brand: راتب/RATIB → رتب/RATEB (display names only; salary wording untouched).
SET NAMES utf8mb4;

UPDATE rateb_mobile_app_configs
SET app_name = REPLACE(app_name, 'راتب — الموارد البشرية', 'رتب — الموارد البشرية')
WHERE app_name LIKE '%راتب — الموارد البشرية%';

UPDATE rateb_mobile_app_configs
SET app_name = REPLACE(app_name, 'راتب - الموارد البشرية', 'رتب — الموارد البشرية')
WHERE app_name LIKE '%راتب - الموارد البشرية%';

UPDATE rateb_mobile_app_configs
SET app_name = REPLACE(app_name, 'راتب – الموارد البشرية', 'رتب — الموارد البشرية')
WHERE app_name LIKE '%راتب – الموارد البشرية%';

UPDATE rateb_mobile_app_configs
SET app_name = 'رتب'
WHERE TRIM(app_name) = 'راتب';

UPDATE rateb_mobile_app_configs
SET app_name = REPLACE(app_name, 'راتب ERP', 'رتب ERP')
WHERE app_name LIKE '%راتب ERP%';

UPDATE rateb_mobile_app_configs
SET app_name = REPLACE(REPLACE(app_name, 'RATIB', 'RATEB'), 'Ratib', 'RATEB')
WHERE app_name LIKE '%RATIB%' OR app_name LIKE '%Ratib%';

UPDATE rateb_system_settings
SET setting_value = 'RATEB ERP'
WHERE setting_key = 'app_name'
  AND setting_value IN ('RATIB ERP', 'Ratib ERP', 'RTAB ERP', 'راتب ERP', 'نظام راتب');
