-- RATEB ERP — Bulk email campaigns (audience + per-recipient log + unsubscribe + throttle)
--
-- Reuses the existing mail stack: rateb_cms_newsletter_campaigns (campaign),
-- rateb_notification_queue (delivery), MailService (SMTP). No new mail system,
-- no external provider. Adds only audience selection, a per-recipient log and
-- the unsubscribe suppression list.

SET NAMES utf8mb4;

ALTER TABLE rateb_cms_newsletter_campaigns ADD COLUMN audience VARCHAR(40) NOT NULL DEFAULT 'subscribers';
ALTER TABLE rateb_cms_newsletter_campaigns ADD COLUMN recipient_count INT UNSIGNED NOT NULL DEFAULT 0;
ALTER TABLE rateb_cms_newsletter_campaigns ADD COLUMN failed_count INT UNSIGNED NOT NULL DEFAULT 0;
ALTER TABLE rateb_cms_newsletter_campaigns ADD COLUMN bounced_count INT UNSIGNED NOT NULL DEFAULT 0;

-- Delivery queue carries the per-message unsubscribe URL (List-Unsubscribe header)
-- and the SMTP failure code used to separate hard bounces from transient failures.
ALTER TABLE rateb_notification_queue ADD COLUMN unsubscribe_url VARCHAR(255) NULL;
ALTER TABLE rateb_notification_queue ADD COLUMN error_code VARCHAR(40) NULL;

CREATE TABLE IF NOT EXISTS rateb_cms_campaign_recipients (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    campaign_id INT UNSIGNED NOT NULL,
    email VARCHAR(190) NOT NULL,
    name VARCHAR(190) NULL,
    source VARCHAR(30) NOT NULL DEFAULT 'subscriber',
    source_id INT UNSIGNED NULL,
    queue_id BIGINT UNSIGNED NULL,
    status ENUM('pending','queued','sent','failed','bounced','unsubscribed') NOT NULL DEFAULT 'pending',
    unsubscribe_token CHAR(40) NOT NULL,
    error_message VARCHAR(255) NULL,
    queued_at DATETIME NULL,
    sent_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_campaign_recipient_email (campaign_id, email),
    UNIQUE KEY uq_campaign_recipient_token (unsubscribe_token),
    KEY idx_campaign_recipient_status (campaign_id, status),
    KEY idx_campaign_recipient_queue (queue_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_email_unsubscribes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(190) NOT NULL,
    campaign_id INT UNSIGNED NULL,
    source VARCHAR(30) NOT NULL DEFAULT 'link',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_email_unsubscribe (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Throttle defaults. Editable in Admin → Settings → Mail. Deliberately conservative:
-- shared cPanel SMTP suspends accounts well before the theoretical queue throughput.
INSERT INTO rateb_system_settings (setting_key, setting_value, setting_group) VALUES
    ('mail_queue_batch_size', '100', 'mail'),
    ('mail_queue_delay_ms', '300', 'mail'),
    ('mail_queue_hourly_limit', '400', 'mail'),
    ('campaign_batch_size', '200', 'mail')
ON DUPLICATE KEY UPDATE setting_key = setting_key;
