-- RATEB ERP — Subscription Renewal History + Lifecycle Audit (Phase 8)
-- Manual renewal / reactivation only. No payment / billing table changes.
--
-- Run: mysql -u user -p database < migrations/214_subscription_renewal_history.sql

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS rateb_subscription_renewal_history (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    previous_expiry_date DATE NULL,
    new_expiry_date DATE NOT NULL,
    period VARCHAR(64) NOT NULL,
    actor_id INT UNSIGNED NULL,
    reference VARCHAR(190) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_sub_renewal_company (company_id),
    INDEX idx_sub_renewal_created (created_at),
    CONSTRAINT fk_sub_renewal_company
        FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_subscription_lifecycle_audit (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    action VARCHAR(64) NOT NULL,
    old_status VARCHAR(64) NOT NULL,
    new_status VARCHAR(64) NOT NULL,
    actor_id INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_sub_lifecycle_company (company_id),
    INDEX idx_sub_lifecycle_action (action),
    INDEX idx_sub_lifecycle_created (created_at),
    CONSTRAINT fk_sub_lifecycle_company
        FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
