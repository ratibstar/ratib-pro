-- RATEB ERP — Accounting phase 4: bank accounts, budget, reopen period, PO cost center
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS rateb_bank_accounts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    name VARCHAR(120) NOT NULL,
    bank_name VARCHAR(120) NULL,
    account_number VARCHAR(50) NULL,
    chart_account_id INT UNSIGNED NOT NULL,
    opening_balance DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ba_company (company_id),
    CONSTRAINT fk_ba_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_ba_coa FOREIGN KEY (chart_account_id) REFERENCES rateb_chart_of_accounts(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_budget_lines (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    fiscal_year SMALLINT UNSIGNED NOT NULL,
    account_id INT UNSIGNED NOT NULL,
    amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_budget_company_year_acct (company_id, fiscal_year, account_id),
    INDEX idx_budget_company_year (company_id, fiscal_year),
    CONSTRAINT fk_budget_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_budget_account FOREIGN KEY (account_id) REFERENCES rateb_chart_of_accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE rateb_cash_vouchers
    ADD COLUMN bank_account_id INT UNSIGNED NULL AFTER counter_account_id,
    ADD INDEX idx_cv_bank (bank_account_id);

ALTER TABLE rateb_purchase_orders
    ADD COLUMN cost_center_id INT UNSIGNED NULL AFTER supplier_id,
    ADD INDEX idx_po_cost_center (cost_center_id);
