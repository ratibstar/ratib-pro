-- RATEB ERP — POS rewards / promotions (Phase 9)
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_inventory_batches' AND COLUMN_NAME = 'unit_cost');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_inventory_batches ADD COLUMN unit_cost DECIMAL(14,4) NULL AFTER quantity',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_pos_orders' AND COLUMN_NAME = 'coupon_code');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_pos_orders
        ADD COLUMN coupon_code VARCHAR(40) NULL AFTER discount_total,
        ADD COLUMN promotion_id INT UNSIGNED NULL AFTER coupon_code,
        ADD COLUMN loyalty_points_redeemed DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER promotion_id,
        ADD COLUMN loyalty_points_earned DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER loyalty_points_redeemed,
        ADD COLUMN gift_card_code VARCHAR(40) NULL AFTER loyalty_points_earned',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

ALTER TABLE rateb_pos_payments MODIFY payment_method
    ENUM('cash','card','bank','wallet','mixed','gift_card') NOT NULL DEFAULT 'cash';

CREATE TABLE IF NOT EXISTS rateb_pos_loyalty_ledger (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    customer_id INT UNSIGNED NOT NULL,
    order_id INT UNSIGNED NULL,
    entry_type ENUM('earn','redeem','adjust') NOT NULL,
    points DECIMAL(14,2) NOT NULL,
    balance_after DECIMAL(14,2) NOT NULL,
    notes VARCHAR(255) NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pos_loyalty_ledger_cust (company_id, customer_id),
    CONSTRAINT fk_pos_loyalty_ledger_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_pos_gift_card_ledger (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    gift_card_id INT UNSIGNED NOT NULL,
    order_id INT UNSIGNED NULL,
    entry_type ENUM('issue','redeem','refund','void') NOT NULL,
    amount DECIMAL(14,2) NOT NULL,
    balance_after DECIMAL(14,2) NOT NULL,
    notes VARCHAR(255) NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pos_gc_ledger_card (company_id, gift_card_id),
    CONSTRAINT fk_pos_gc_ledger_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_pos_coupon_redemptions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    branch_id INT UNSIGNED NULL,
    coupon_id INT UNSIGNED NOT NULL,
    order_id INT UNSIGNED NOT NULL,
    discount_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_pos_coupon_order (company_id, coupon_id, order_id),
    INDEX idx_pos_coupon_red_order (order_id),
    CONSTRAINT fk_pos_coupon_red_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_pos_promotion_applications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    promotion_id INT UNSIGNED NOT NULL,
    order_id INT UNSIGNED NOT NULL,
    inventory_id INT UNSIGNED NULL,
    discount_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pos_promo_app_order (company_id, order_id),
    CONSTRAINT fk_pos_promo_app_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
