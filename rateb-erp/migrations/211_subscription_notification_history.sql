-- RATEB ERP — Subscription Notification Engine Phase 3
-- Dedicated history table for eligibility / generation records.
-- Does NOT modify rateb_subscription_engine or any billing tables.
--
-- Scope: schema only. No cron, no senders, no UI.
-- Run: mysql -u user -p database < migrations/211_subscription_notification_history.sql

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS rateb_subscription_notification_history (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    subscription_id INT UNSIGNED NULL COMMENT 'rateb_subscription_engine.id (not billing)',
    notification_type ENUM(
        'REMINDER',
        'FINAL_WARNING',
        'GRACE',
        'SUSPENSION'
    ) NOT NULL,
    trigger_day INT NOT NULL COMMENT 'Signed days vs subscription_end (14..-7)',
    scheduled_date DATE NOT NULL,
    generated_at DATETIME NULL,
    delivered_at DATETIME NULL,
    dismissed_at DATETIME NULL,
    status ENUM(
        'generated',
        'delivered',
        'dismissed',
        'cancelled'
    ) NOT NULL DEFAULT 'generated',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_sub_notif_dedupe (company_id, notification_type, trigger_day),
    INDEX idx_sub_notif_company (company_id),
    INDEX idx_sub_notif_scheduled (scheduled_date),
    INDEX idx_sub_notif_status (status),
    CONSTRAINT fk_sub_notif_company
        FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_sub_notif_subscription
        FOREIGN KEY (subscription_id) REFERENCES rateb_subscription_engine(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
