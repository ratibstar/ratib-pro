-- Password reset email template with reset link placeholder
SET NAMES utf8mb4;

UPDATE rateb_email_templates SET
    subject = 'RTAB ERP — Password Reset',
    body_html = '<p>Hello {name},</p><p>Click to reset your password:</p><p><a href="{reset_url}">{reset_url}</a></p>',
    body_text = 'Reset your password: {reset_url}'
WHERE slug = 'password_reset';
