-- RATEB ERP — Subscription Grace Period Phase 6
-- Adds grace window columns + SUSPENSION_PENDING status.
-- Does NOT modify billing / payments / invoices tables.
--
-- Run: mysql -u user -p database < migrations/212_subscription_grace_period.sql

SET NAMES utf8mb4;

-- Expand status vocabulary (idempotent-ish: ignore duplicate value errors on re-run via procedure-less ALTER).
ALTER TABLE rateb_subscription_engine
    MODIFY COLUMN current_status ENUM(
        'ACTIVE',
        'WARNING',
        'CRITICAL',
        'GRACE',
        'SUSPENSION_PENDING',
        'SUSPENDED'
    ) NOT NULL DEFAULT 'ACTIVE';

ALTER TABLE rateb_subscription_engine
    ADD COLUMN grace_started_at DATE NULL AFTER grace_period_days,
    ADD COLUMN grace_end_at DATE NULL AFTER grace_started_at;

-- Prefer a 7-day default for new/zero configurations (does not rewrite existing non-zero values).
ALTER TABLE rateb_subscription_engine
    MODIFY COLUMN grace_period_days INT UNSIGNED NOT NULL DEFAULT 7;

UPDATE rateb_subscription_engine
SET grace_period_days = 7
WHERE grace_period_days = 0;

-- Backfill calculated grace window from subscription_end when missing.
UPDATE rateb_subscription_engine
SET
    grace_started_at = DATE_ADD(subscription_end, INTERVAL 1 DAY),
    grace_end_at = DATE_ADD(subscription_end, INTERVAL grace_period_days DAY)
WHERE grace_started_at IS NULL
   OR grace_end_at IS NULL;
