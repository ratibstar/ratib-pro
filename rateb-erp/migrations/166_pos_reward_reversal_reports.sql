-- RATEB ERP — POS reward reversal idempotency (Phase 9.1) + report snapshots (Phase 10)
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

CREATE TABLE IF NOT EXISTS rateb_pos_reward_reversals (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    branch_id INT UNSIGNED NULL,
    return_order_id INT UNSIGNED NOT NULL,
    original_order_id INT UNSIGNED NOT NULL,
    reversal_kind VARCHAR(32) NOT NULL,
    reference_id INT UNSIGNED NULL,
    points DECIMAL(14,2) NULL,
    amount DECIMAL(14,2) NULL,
    metadata_json JSON NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_pos_reward_rev (return_order_id, reversal_kind, reference_id),
    INDEX idx_pos_reward_rev_orig (company_id, original_order_id),
    CONSTRAINT fk_pos_reward_rev_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE rateb_pos_refunds MODIFY refund_method
    ENUM('cash','card','bank','wallet','store_credit','gift_card') NOT NULL DEFAULT 'cash';

CREATE TABLE IF NOT EXISTS rateb_pos_report_snapshots (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    branch_id INT UNSIGNED NULL,
    terminal_id INT UNSIGNED NULL,
    shift_id INT UNSIGNED NULL,
    report_type ENUM('x','z') NOT NULL,
    report_no VARCHAR(40) NOT NULL,
    snapshot_json JSON NOT NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_pos_report_no (company_id, report_no),
    INDEX idx_pos_report_shift (company_id, shift_id, report_type),
    CONSTRAINT fk_pos_report_snap_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_pos_shifts' AND COLUMN_NAME = 'last_x_report_at');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_pos_shifts
        ADD COLUMN last_x_report_at DATETIME NULL AFTER variance,
        ADD COLUMN last_z_report_id INT UNSIGNED NULL AFTER last_x_report_at',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_pos_coupons' AND COLUMN_NAME = 'reversible');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_pos_coupons ADD COLUMN reversible TINYINT(1) NOT NULL DEFAULT 1 AFTER status',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_pos_coupon_redemptions' AND COLUMN_NAME = 'reversed_at');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_pos_coupon_redemptions ADD COLUMN reversed_at DATETIME NULL AFTER discount_amount',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

ALTER TABLE rateb_pos_loyalty_ledger MODIFY entry_type
    ENUM('earn','redeem','adjust','earn_reverse','redeem_restore') NOT NULL;
