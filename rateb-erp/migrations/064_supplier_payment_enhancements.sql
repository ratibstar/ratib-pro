-- Supplier payments: invoice link, due date
SET NAMES utf8mb4;

ALTER TABLE rateb_supplier_payments
    ADD COLUMN invoice_id INT UNSIGNED NULL AFTER purchase_order_id,
    ADD COLUMN due_date DATE NULL AFTER payment_date;

ALTER TABLE rateb_supplier_payments
    ADD INDEX idx_sp_invoice (invoice_id);
