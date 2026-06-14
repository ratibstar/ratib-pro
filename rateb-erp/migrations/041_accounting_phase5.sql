-- RATEB ERP accounting phase 5: supplier payments, bank statements, year-end close
SET NAMES utf8mb4;

ALTER TABLE rateb_journal_entries
    MODIFY source_type ENUM(
        'manual','invoice','payment','purchase_order','subscription',
        'cash_voucher','stock_movement','supplier_payment','year_end_close'
    ) NOT NULL DEFAULT 'manual';

CREATE TABLE IF NOT EXISTS rateb_supplier_payments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    supplier_id INT UNSIGNED NULL,
    purchase_order_id INT UNSIGNED NULL,
    payment_no VARCHAR(50) NOT NULL,
    payment_date DATE NOT NULL,
    amount DECIMAL(14,2) NOT NULL,
    bank_account_id INT UNSIGNED NULL,
    payment_method VARCHAR(50) NULL DEFAULT 'bank',
    reference_no VARCHAR(120) NULL,
    journal_entry_id INT UNSIGNED NULL,
    status ENUM('posted','void') NOT NULL DEFAULT 'posted',
    notes VARCHAR(500) NULL,
    created_by INT UNSIGNED NULL,
    posted_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_sp_company_no (company_id, payment_no),
    INDEX idx_sp_company (company_id),
    INDEX idx_sp_po (purchase_order_id),
    CONSTRAINT fk_sp_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_bank_statement_lines (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    bank_account_id INT UNSIGNED NOT NULL,
    line_date DATE NOT NULL,
    description VARCHAR(255) NOT NULL DEFAULT '',
    amount DECIMAL(14,2) NOT NULL,
    reference_no VARCHAR(120) NULL,
    import_batch VARCHAR(50) NULL,
    is_reconciled TINYINT(1) NOT NULL DEFAULT 0,
    journal_entry_id INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_bsl_bank (bank_account_id),
    INDEX idx_bsl_company (company_id),
    CONSTRAINT fk_bsl_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_bsl_bank FOREIGN KEY (bank_account_id) REFERENCES rateb_bank_accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE rateb_fiscal_periods
    ADD COLUMN closing_entry_id INT UNSIGNED NULL AFTER closed_by;
