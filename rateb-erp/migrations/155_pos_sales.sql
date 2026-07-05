-- RATEB ERP — POS sales schema (Phase 2 — tables only, no posting logic)
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

CREATE TABLE IF NOT EXISTS rateb_pos_orders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    branch_id INT UNSIGNED NOT NULL,
    warehouse_id INT UNSIGNED NULL,
    terminal_id INT UNSIGNED NULL,
    shift_id INT UNSIGNED NULL,
    session_id INT UNSIGNED NULL,
    order_no VARCHAR(40) NOT NULL,
    order_type ENUM('sale','return','exchange','quote','suspended') NOT NULL DEFAULT 'sale',
    status ENUM('draft','completed','void','suspended') NOT NULL DEFAULT 'draft',
    customer_id INT UNSIGNED NULL,
    subtotal DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    discount_total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    tax DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_pos_order_no (company_id, order_no),
    INDEX idx_pos_order_branch (company_id, branch_id, status),
    CONSTRAINT fk_pos_order_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_pos_order_lines (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    company_id INT UNSIGNED NOT NULL,
    inventory_id INT UNSIGNED NULL,
    batch_id INT UNSIGNED NULL,
    line_no SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    description VARCHAR(255) NOT NULL DEFAULT '',
    quantity DECIMAL(12,3) NOT NULL DEFAULT 0.000,
    unit_price DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    discount_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    tax_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    line_total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    INDEX idx_pos_line_order (order_id),
    CONSTRAINT fk_pos_line_order FOREIGN KEY (order_id) REFERENCES rateb_pos_orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_pos_payments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    company_id INT UNSIGNED NOT NULL,
    payment_method ENUM('cash','card','bank','wallet','mixed') NOT NULL DEFAULT 'cash',
    amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    reference_no VARCHAR(80) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pos_payment_order (order_id),
    CONSTRAINT fk_pos_payment_order FOREIGN KEY (order_id) REFERENCES rateb_pos_orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
