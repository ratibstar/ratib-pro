-- RATEB ERP — supplier communications phase 2 (timeline, email status, response eval)
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_supplier_communications' AND COLUMN_NAME = 'send_status');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_supplier_communications ADD COLUMN send_status VARCHAR(20) NOT NULL DEFAULT ''not_sent'' AFTER comm_status',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_supplier_communications' AND COLUMN_NAME = 'sent_at');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_supplier_communications ADD COLUMN sent_at DATETIME NULL AFTER send_status',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_supplier_communications' AND COLUMN_NAME = 'response_rating');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_supplier_communications ADD COLUMN response_rating VARCHAR(20) NULL AFTER sent_at',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_supplier_communications' AND COLUMN_NAME = 'response_notes');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_supplier_communications ADD COLUMN response_notes TEXT NULL AFTER response_rating',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_supplier_communications' AND COLUMN_NAME = 'follow_up_reminded_at');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_supplier_communications ADD COLUMN follow_up_reminded_at DATE NULL AFTER follow_up_priority',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_supplier_communications' AND COLUMN_NAME = 'no_response_notified_at');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_supplier_communications ADD COLUMN no_response_notified_at DATETIME NULL AFTER follow_up_reminded_at',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS rateb_supplier_comm_timeline (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    comm_id INT UNSIGNED NOT NULL,
    event_type VARCHAR(40) NOT NULL,
    summary VARCHAR(255) NOT NULL,
    details TEXT NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_sct_comm (comm_id, id),
    INDEX idx_sct_company (company_id),
    CONSTRAINT fk_sct_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
