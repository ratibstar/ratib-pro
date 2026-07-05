-- RATEB ERP — inventory serial tracking (shared extension for POS)
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

CREATE TABLE IF NOT EXISTS rateb_inventory_serials (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    branch_id INT UNSIGNED NULL,
    inventory_id INT UNSIGNED NOT NULL,
    serial_no VARCHAR(100) NOT NULL,
    status ENUM('available','reserved','sold','returned','void') NOT NULL DEFAULT 'available',
    warehouse_id INT UNSIGNED NULL,
    notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_inv_serial (company_id, serial_no),
    INDEX idx_inv_serial_item (inventory_id, status),
    INDEX idx_inv_serial_branch (company_id, branch_id),
    CONSTRAINT fk_inv_serial_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_inv_serial_inv FOREIGN KEY (inventory_id) REFERENCES rateb_inventory(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
