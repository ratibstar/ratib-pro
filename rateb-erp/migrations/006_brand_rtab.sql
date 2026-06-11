-- RTAB ERP — rebrand display name (راتب → رتب)
SET NAMES utf8mb4;

UPDATE rateb_system_settings SET setting_value = 'RTAB ERP' WHERE setting_key = 'app_name';

UPDATE rateb_email_templates SET
    subject = REPLACE(subject, 'RATEB', 'RTAB'),
    body_html = REPLACE(body_html, 'RATEB', 'RTAB'),
    body_text = REPLACE(body_text, 'RATEB', 'RTAB')
WHERE slug IN ('welcome', 'password_reset');

UPDATE rateb_sms_templates SET body = REPLACE(body, 'RATEB', 'RTAB');
