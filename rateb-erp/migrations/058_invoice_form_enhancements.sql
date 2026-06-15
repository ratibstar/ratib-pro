-- RATEB ERP — Invoice form enhancements (discount, terms, payment metadata)
-- Idempotent: duplicate column errors (1060) are ignored by MigrationService.
SET NAMES utf8mb4;

ALTER TABLE rateb_invoices ADD COLUMN currency VARCHAR(3) NOT NULL DEFAULT 'SAR' AFTER total_amount;
ALTER TABLE rateb_invoices ADD COLUMN discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER currency;
ALTER TABLE rateb_invoices ADD COLUMN discount_type ENUM('value','percent') NOT NULL DEFAULT 'value' AFTER discount_amount;
ALTER TABLE rateb_invoices ADD COLUMN tax_rate DECIMAL(5,2) NOT NULL DEFAULT 15.00 AFTER discount_type;
ALTER TABLE rateb_invoices ADD COLUMN payment_terms_days INT UNSIGNED NOT NULL DEFAULT 30 AFTER tax_rate;
ALTER TABLE rateb_invoices ADD COLUMN payment_method VARCHAR(50) NULL AFTER payment_terms_days;
ALTER TABLE rateb_invoices ADD COLUMN invoice_type VARCHAR(50) NOT NULL DEFAULT 'tax' AFTER invoice_no;
ALTER TABLE rateb_invoices ADD COLUMN po_number VARCHAR(80) NULL AFTER subscription_id;
ALTER TABLE rateb_invoices ADD COLUMN notes TEXT NULL AFTER status;
ALTER TABLE rateb_invoices ADD COLUMN payment_status ENUM('unpaid','partial','paid') NOT NULL DEFAULT 'unpaid' AFTER notes;
