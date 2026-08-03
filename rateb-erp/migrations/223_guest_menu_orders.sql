-- Guest menu guest orders (QR table orders)
CREATE TABLE IF NOT EXISTS rateb_guest_menu_orders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    branch_id INT UNSIGNED NULL,
    public_slug VARCHAR(64) NOT NULL,
    order_no VARCHAR(32) NOT NULL,
    table_label VARCHAR(64) NULL,
    guest_name VARCHAR(120) NULL,
    items_json JSON NOT NULL,
    total_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    currency VARCHAR(8) NOT NULL DEFAULT 'SAR',
    status ENUM('pending', 'accepted', 'cancelled') NOT NULL DEFAULT 'pending',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_gm_orders_company (company_id, status, created_at),
    KEY idx_gm_orders_slug (public_slug, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
