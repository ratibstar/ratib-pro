-- RATEB ERP — POS sell pricing (Phase 5.1)
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_inventory' AND COLUMN_NAME = 'sell_price');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_inventory ADD COLUMN sell_price DECIMAL(14,2) NULL AFTER unit_cost',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS rateb_pos_price_groups (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    code VARCHAR(30) NOT NULL,
    name VARCHAR(150) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_pos_price_group_code (company_id, code),
    CONSTRAINT fk_pos_price_group_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_customers' AND COLUMN_NAME = 'price_group_id');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_customers ADD COLUMN price_group_id INT UNSIGNED NULL AFTER cost_center_id, ADD INDEX idx_customer_price_group (price_group_id)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS rateb_pos_branch_prices (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    branch_id INT UNSIGNED NOT NULL,
    inventory_id INT UNSIGNED NOT NULL,
    price DECIMAL(14,2) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_pos_branch_price (company_id, branch_id, inventory_id),
    INDEX idx_pos_branch_price_inv (inventory_id),
    CONSTRAINT fk_pos_branch_price_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_pos_branch_price_inv FOREIGN KEY (inventory_id) REFERENCES rateb_inventory(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_pos_group_prices (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    price_group_id INT UNSIGNED NOT NULL,
    inventory_id INT UNSIGNED NOT NULL,
    price DECIMAL(14,2) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_pos_group_price (company_id, price_group_id, inventory_id),
    INDEX idx_pos_group_price_inv (inventory_id),
    CONSTRAINT fk_pos_group_price_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_pos_group_price_group FOREIGN KEY (price_group_id) REFERENCES rateb_pos_price_groups(id) ON DELETE CASCADE,
    CONSTRAINT fk_pos_group_price_inv FOREIGN KEY (inventory_id) REFERENCES rateb_inventory(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_pos_promotions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    code VARCHAR(40) NOT NULL,
    name VARCHAR(150) NOT NULL,
    rules_json JSON NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 0,
    valid_from DATETIME NULL,
    valid_to DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_pos_promotion_code (company_id, code),
    CONSTRAINT fk_pos_promotion_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
