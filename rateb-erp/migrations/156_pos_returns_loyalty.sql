-- RATEB ERP — POS returns / loyalty / promotions schema (Phase 2 — tables only)
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

CREATE TABLE IF NOT EXISTS rateb_pos_returns (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    branch_id INT UNSIGNED NOT NULL,
    original_order_id INT UNSIGNED NULL,
    return_no VARCHAR(40) NOT NULL,
    status ENUM('draft','completed','void') NOT NULL DEFAULT 'draft',
    total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_pos_return_no (company_id, return_no),
    INDEX idx_pos_return_branch (company_id, branch_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_pos_loyalty_accounts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    customer_id INT UNSIGNED NOT NULL,
    points_balance DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    status ENUM('active','suspended') NOT NULL DEFAULT 'active',
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_pos_loyalty_customer (company_id, customer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_pos_gift_cards (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    code VARCHAR(40) NOT NULL,
    balance DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    status ENUM('active','redeemed','expired','void') NOT NULL DEFAULT 'active',
    expires_at DATE NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_pos_gift_code (company_id, code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_pos_coupons (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    code VARCHAR(40) NOT NULL,
    discount_type ENUM('percent','fixed') NOT NULL DEFAULT 'percent',
    discount_value DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    max_uses INT UNSIGNED NULL,
    used_count INT UNSIGNED NOT NULL DEFAULT 0,
    status ENUM('active','inactive','expired') NOT NULL DEFAULT 'active',
    valid_from DATE NULL,
    valid_to DATE NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_pos_coupon_code (company_id, code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
