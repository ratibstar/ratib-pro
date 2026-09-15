-- RATEB ERP — Marketing > Bulk Newsletter (test mode, pause/resume, granular permissions)
--
-- Adds campaign test mode + a 'paused' status to the existing campaign engine and
-- registers marketing.newsletter.* permissions for a dedicated Marketing admin area.
-- Delivery stays unchanged (rateb_notification_queue + bin/erp-cron.php).

SET NAMES utf8mb4;

ALTER TABLE rateb_cms_newsletter_campaigns ADD COLUMN test_mode TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER bounced_count;

ALTER TABLE rateb_cms_newsletter_campaigns MODIFY COLUMN status ENUM('draft','scheduled','sending','paused','sent','failed') NOT NULL DEFAULT 'draft';

-- Test-mode recipients (comma-separated emails, editable from Settings -> Mail).
INSERT INTO rateb_system_settings (setting_key, setting_value, setting_group) VALUES
    ('campaign_test_emails', '', 'mail')
ON DUPLICATE KEY UPDATE setting_key = setting_key;

INSERT INTO rateb_permissions (name, name_ar, slug, module, description, description_ar) VALUES
('View Bulk Newsletter', '', 'marketing.newsletter.view', 'marketing', 'View bulk newsletter campaigns and subscribers', ''),
('Manage Bulk Newsletter', '', 'marketing.newsletter.manage', 'marketing', 'Create and edit bulk newsletter campaigns', ''),
('Send Bulk Newsletter', '', 'marketing.newsletter.send', 'marketing', 'Start, pause and resume bulk newsletter campaigns', ''),
('Import Newsletter Subscribers', '', 'marketing.newsletter.import', 'marketing', 'Import newsletter subscriber lists', '')
ON DUPLICATE KEY UPDATE name = VALUES(name), module = VALUES(module);

INSERT INTO rateb_role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM rateb_roles r
JOIN rateb_permissions p ON p.slug IN ('marketing.newsletter.view', 'marketing.newsletter.manage', 'marketing.newsletter.send', 'marketing.newsletter.import')
WHERE r.slug = 'super-admin'
ON DUPLICATE KEY UPDATE role_id = role_id;