-- RATEB ERP — Subscription Engine Phase 1 Foundation
-- Dedicated lifecycle tables. Does NOT modify rateb_subscriptions / rateb_plans /
-- rateb_payments / rateb_invoices or any billing schema.
--
-- Scope: schema only. No seed data, no triggers, no cron, no billing sync.
-- Run: mysql -u user -p database < migrations/210_subscription_engine_foundation.sql

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS rateb_subscription_engine (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    subscription_start DATE NOT NULL,
    subscription_end DATE NOT NULL,
    grace_period_days INT UNSIGNED NOT NULL DEFAULT 0,
    current_status ENUM(
        'ACTIVE',
        'WARNING',
        'CRITICAL',
        'GRACE',
        'SUSPENDED'
    ) NOT NULL DEFAULT 'ACTIVE',
    suspended_at DATETIME NULL,
    renewed_at DATETIME NULL,
    next_notification_date DATE NULL,
    last_notification_date DATE NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_subscription_engine_company (company_id),
    INDEX idx_subscription_engine_status (current_status),
    INDEX idx_subscription_engine_end (subscription_end),
    INDEX idx_subscription_engine_next_notif (next_notification_date),
    CONSTRAINT fk_subscription_engine_company
        FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
