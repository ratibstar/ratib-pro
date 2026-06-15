-- RATEB ERP — Supplier communications: SMS / WhatsApp channels
SET NAMES utf8mb4;

ALTER TABLE rateb_supplier_communications
    MODIFY COLUMN channel VARCHAR(32) NOT NULL DEFAULT 'email';
