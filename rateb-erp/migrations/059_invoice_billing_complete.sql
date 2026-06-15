-- RATEB ERP — Invoice lines, payment link, sent timestamp
-- Idempotent: duplicate column/index errors are ignored by MigrationService.
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

ALTER TABLE rateb_invoices ADD COLUMN sent_at DATETIME NULL AFTER payment_status;
ALTER TABLE rateb_payments ADD COLUMN invoice_id INT UNSIGNED NULL AFTER subscription_id;
ALTER TABLE rateb_payments ADD INDEX idx_payments_invoice (invoice_id);
