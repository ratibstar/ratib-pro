-- RATEB ERP — purchase invoices (landed costs) + partner capital sub-accounts (idempotent)
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS rateb_purchase_invoices (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id INT UNSIGNED NOT NULL,
    purchase_order_id INT UNSIGNED NOT NULL,
    supplier_id INT UNSIGNED NULL,
    invoice_no VARCHAR(40) NOT NULL,
    invoice_date DATE NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'draft',
    currency VARCHAR(3) NOT NULL DEFAULT 'SAR',
    line_subtotal DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    discount_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    tax_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    shipping_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    customs_clearance_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    total_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    customs_declaration_no VARCHAR(80) NULL,
    customs_clearance_date DATE NULL,
    customs_broker_id INT UNSIGNED NULL,
    customs_clearance_status VARCHAR(30) NOT NULL DEFAULT '',
    notes TEXT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_pi_company_no (company_id, invoice_no),
    KEY idx_pi_po (purchase_order_id),
    KEY idx_pi_supplier (supplier_id),
    KEY idx_pi_customs_broker (customs_broker_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Backfill: one invoice per PO that had landed-cost or customs metadata on the PO
INSERT INTO rateb_purchase_invoices (
    company_id, purchase_order_id, supplier_id, invoice_no, invoice_date, status, currency,
    line_subtotal, discount_amount, tax_amount, shipping_amount, customs_clearance_amount, total_amount,
    customs_declaration_no, customs_clearance_date, customs_broker_id, customs_clearance_status, notes
)
SELECT
    po.company_id,
    po.id,
    po.supplier_id,
    CONCAT('PI-', po.order_no),
    COALESCE(po.order_date, CURDATE()),
    IF(po.status IN ('received', 'partial', 'confirmed'), 'posted', 'draft'),
    COALESCE(po.currency, 'SAR'),
    COALESCE(po.subtotal, 0),
    COALESCE(po.discount_amount, 0),
    COALESCE(po.tax_amount, 0),
    COALESCE(po.shipping_amount, 0),
    COALESCE(po.customs_clearance_amount, 0),
    ROUND(COALESCE(po.subtotal, 0) - COALESCE(po.discount_amount, 0) + COALESCE(po.tax_amount, 0)
        + COALESCE(po.shipping_amount, 0) + COALESCE(po.customs_clearance_amount, 0), 2),
    po.customs_declaration_no,
    po.customs_clearance_date,
    po.customs_broker_id,
    COALESCE(po.customs_clearance_status, ''),
    po.notes
FROM rateb_purchase_orders po
WHERE (
    COALESCE(po.shipping_amount, 0) > 0
    OR COALESCE(po.customs_clearance_amount, 0) > 0
    OR (po.customs_declaration_no IS NOT NULL AND po.customs_declaration_no <> '')
    OR (po.customs_clearance_status IS NOT NULL AND po.customs_clearance_status <> '')
)
AND NOT EXISTS (
    SELECT 1 FROM rateb_purchase_invoices pi WHERE pi.purchase_order_id = po.id
);

-- PO totals: lines + tax only (landed costs live on purchase invoice)
UPDATE rateb_purchase_orders po
SET
    shipping_amount = 0,
    customs_clearance_amount = 0,
    customs_declaration_no = NULL,
    customs_clearance_date = NULL,
    customs_broker_id = NULL,
    customs_clearance_status = '',
    total_amount = ROUND(GREATEST(COALESCE(po.subtotal, 0) - COALESCE(po.discount_amount, 0) + COALESCE(po.tax_amount, 0), 0), 2)
WHERE EXISTS (SELECT 1 FROM rateb_purchase_invoices pi WHERE pi.purchase_order_id = po.id);

-- Partner capital sub-accounts under 3200 (for subsidiary ledger)
INSERT INTO rateb_chart_of_accounts (company_id, code, name, name_ar, account_type, is_active)
SELECT NULL, v.code, v.name, v.name_ar, 'equity', 1
FROM (
    SELECT '3210' AS code, 'Partner Capital — Account 1' AS name, 'رأس مال الشريك — حساب 1' AS name_ar UNION ALL
    SELECT '3220', 'Partner Capital — Account 2', 'رأس مال الشريك — حساب 2'
) v
WHERE NOT EXISTS (
    SELECT 1 FROM rateb_chart_of_accounts a WHERE a.company_id IS NULL AND a.code = v.code
);

INSERT INTO rateb_chart_of_accounts (company_id, code, name, name_ar, account_type, is_active)
SELECT c.id, v.code, v.name, v.name_ar, 'equity', 1
FROM rateb_companies c
CROSS JOIN (
    SELECT '3210' AS code, 'Partner Capital — Account 1' AS name, 'رأس مال الشريك — حساب 1' AS name_ar UNION ALL
    SELECT '3220', 'Partner Capital — Account 2', 'رأس مال الشريك — حساب 2'
) v
WHERE NOT EXISTS (
    SELECT 1 FROM rateb_chart_of_accounts a WHERE a.company_id = c.id AND a.code = v.code
);

UPDATE rateb_chart_of_accounts child
JOIN rateb_chart_of_accounts parent ON parent.company_id <=> child.company_id AND parent.code = '3200'
SET child.parent_id = parent.id
WHERE child.code IN ('3210', '3220');
