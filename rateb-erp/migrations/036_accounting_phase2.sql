-- RATEB ERP — Accounting phase 2: fiscal periods, cash vouchers, journal source types
SET NAMES utf8mb4;

ALTER TABLE rateb_journal_entries
    MODIFY source_type ENUM(
        'manual','invoice','payment','purchase_order','subscription',
        'cash_voucher','stock_movement'
    ) NOT NULL DEFAULT 'manual';

CREATE TABLE IF NOT EXISTS rateb_fiscal_periods (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    name VARCHAR(120) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    status ENUM('open','closed') NOT NULL DEFAULT 'open',
    closed_at DATETIME NULL,
    closed_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_fp_company_dates (company_id, start_date, end_date),
    INDEX idx_fp_company (company_id),
    CONSTRAINT fk_fp_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_cash_vouchers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    voucher_no VARCHAR(50) NOT NULL,
    voucher_type ENUM('receipt','payment') NOT NULL,
    voucher_date DATE NOT NULL,
    amount DECIMAL(14,2) NOT NULL,
    party_name VARCHAR(200) NULL,
    description VARCHAR(500) NOT NULL,
    description_ar VARCHAR(500) NULL,
    counter_account_id INT UNSIGNED NOT NULL,
    status ENUM('draft','posted','void') NOT NULL DEFAULT 'draft',
    journal_entry_id INT UNSIGNED NULL,
    created_by INT UNSIGNED NULL,
    posted_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_cv_company_no (company_id, voucher_no),
    INDEX idx_cv_company (company_id),
    INDEX idx_cv_status (status),
    CONSTRAINT fk_cv_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_cv_counter FOREIGN KEY (counter_account_id) REFERENCES rateb_chart_of_accounts(id) ON DELETE RESTRICT,
    CONSTRAINT fk_cv_journal FOREIGN KEY (journal_entry_id) REFERENCES rateb_journal_entries(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
