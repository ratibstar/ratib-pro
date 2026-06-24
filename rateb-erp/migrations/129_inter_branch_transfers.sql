-- RATEB ERP — inter-branch transfers (inventory, assets, employees, accounting)
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

CREATE TABLE IF NOT EXISTS rateb_branch_transfers (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id INT UNSIGNED NOT NULL,
    transfer_no VARCHAR(50) NOT NULL,
    transfer_type ENUM('inventory','asset','employee','accounting') NOT NULL,
    source_branch_id INT UNSIGNED NOT NULL,
    dest_branch_id INT UNSIGNED NOT NULL,
    source_entity_type VARCHAR(60) NULL,
    source_entity_id INT UNSIGNED NULL,
    quantity DECIMAL(15,4) NULL,
    amount DECIMAL(14,2) NULL,
    status ENUM('draft','pending','approved','completed','rejected','cancelled') NOT NULL DEFAULT 'draft',
    notes TEXT NULL,
    payload_json JSON NULL,
    created_by INT UNSIGNED NULL,
    approved_by INT UNSIGNED NULL,
    completed_at DATETIME NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_bt_company_no (company_id, transfer_no),
    KEY idx_bt_company (company_id),
    KEY idx_bt_source (source_branch_id),
    KEY idx_bt_dest (dest_branch_id),
    KEY idx_bt_type (transfer_type),
    KEY idx_bt_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
