-- RATEB ERP — Subscription Suspension Audit (Phase 7A Shadow Mode)
-- Optional decision log only. Does NOT enforce access or alter billing tables.
--
-- Run: mysql -u user -p database < migrations/213_subscription_suspension_audit.sql

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS rateb_subscription_suspension_audit (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    decision ENUM('eligible', 'not_eligible') NOT NULL,
    reason VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_sub_suspension_audit_company (company_id),
    INDEX idx_sub_suspension_audit_created (created_at),
    CONSTRAINT fk_sub_suspension_audit_company
        FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
