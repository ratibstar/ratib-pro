-- RATEB ERP — Enterprise offline device registry (Phase 2A)
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

CREATE TABLE IF NOT EXISTS rateb_offline_devices (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    branch_id INT UNSIGNED NULL,
    device_id VARCHAR(64) NOT NULL,
    user_id INT UNSIGNED NULL,
    label VARCHAR(150) NULL,
    meta_json JSON NULL,
    last_seen_at DATETIME NULL,
    status ENUM('active','inactive','revoked') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_offline_device (company_id, device_id),
    INDEX idx_offline_device_branch (company_id, branch_id),
    CONSTRAINT fk_offline_device_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
