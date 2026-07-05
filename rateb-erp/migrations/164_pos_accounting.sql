-- RATEB ERP — POS accounting integration (Phase 8)
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

ALTER TABLE rateb_journal_entries MODIFY source_type ENUM(
    'manual','invoice','payment','purchase_order','subscription',
    'cash_voucher','stock_movement','purchase_invoice',
    'supplier_payment','year_end_close','branch_transfer',
    'pos_sale_revenue','pos_sale_cogs',
    'pos_return_revenue','pos_return_cogs',
    'pos_exchange_revenue','pos_exchange_cogs'
) NOT NULL DEFAULT 'manual';

CREATE TABLE IF NOT EXISTS rateb_pos_gl_postings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    branch_id INT UNSIGNED NULL,
    order_id INT UNSIGNED NOT NULL,
    posting_kind VARCHAR(32) NOT NULL,
    journal_entry_id INT UNSIGNED NOT NULL,
    source_type VARCHAR(40) NOT NULL,
    source_id INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_pos_gl_source (company_id, source_type, source_id),
    INDEX idx_pos_gl_order (company_id, order_id),
    CONSTRAINT fk_pos_gl_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
