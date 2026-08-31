-- RATEB ERP — Module Add-on Commerce ledger (Phase 1)
-- Dedicated commercial table. Does NOT modify rateb_subscriptions / rateb_plans /
-- rateb_payments / rateb_invoices / rateb_companies.modules or any access-control schema.
--
-- Runtime access remains: company.modules → PlanLimitService::companyHasModule()
-- → CompanyModuleMiddleware.
--
-- History rows are allowed (no unique company+module). One invoice / one payment
-- transaction maps to at most one ledger row. Enforce one active row per
-- company+module in ModuleAddonService when inserting new purchases.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS rateb_company_module_addons (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    module_slug VARCHAR(80) NOT NULL,
    status ENUM('pending','active','expired','cancelled') NOT NULL DEFAULT 'pending',
    starts_at DATE NOT NULL,
    ends_at DATE NULL,
    billing_cycle ENUM('monthly','yearly') NOT NULL DEFAULT 'monthly',
    invoice_id INT UNSIGNED NULL,
    payment_transaction_id INT UNSIGNED NULL,
    preexisting_grant TINYINT(1) NOT NULL DEFAULT 0,
    source ENUM('self_serve','admin') NOT NULL DEFAULT 'self_serve',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_module_addons_company_slug (company_id, module_slug),
    INDEX idx_module_addons_slug (module_slug),
    INDEX idx_module_addons_status_ends (status, ends_at),
    UNIQUE KEY uq_module_addons_invoice (invoice_id),
    UNIQUE KEY uq_module_addons_payment_tx (payment_transaction_id),
    CONSTRAINT fk_module_addons_company
        FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_module_addons_invoice
        FOREIGN KEY (invoice_id) REFERENCES rateb_invoices(id) ON DELETE SET NULL,
    CONSTRAINT fk_module_addons_payment_tx
        FOREIGN KEY (payment_transaction_id) REFERENCES rateb_payment_transactions(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
