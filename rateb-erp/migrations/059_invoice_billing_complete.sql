-- RATEB ERP — Invoice lines, payment link, sent timestamp
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS rateb_invoice_lines (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT UNSIGNED NOT NULL,
    line_no INT UNSIGNED NOT NULL DEFAULT 1,
    item_name VARCHAR(255) NOT NULL,
    description VARCHAR(500) NULL,
    quantity DECIMAL(12,3) NOT NULL DEFAULT 1.000,
    unit VARCHAR(30) NOT NULL DEFAULT 'unit',
    unit_price DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    tax_rate DECIMAL(5,2) NOT NULL DEFAULT 15.00,
    excluding_tax TINYINT(1) NOT NULL DEFAULT 1,
    line_subtotal DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    tax_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    line_total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_invoice_lines_invoice (invoice_id),
    CONSTRAINT fk_invoice_lines_invoice FOREIGN KEY (invoice_id) REFERENCES rateb_invoices(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_invoices' AND COLUMN_NAME = 'sent_at');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_invoices ADD COLUMN sent_at DATETIME NULL AFTER payment_status',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_payments' AND COLUMN_NAME = 'invoice_id');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_payments ADD COLUMN invoice_id INT UNSIGNED NULL AFTER subscription_id, ADD INDEX idx_payments_invoice (invoice_id)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
