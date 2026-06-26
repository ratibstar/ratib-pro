-- RATEB ERP Phase 6 — inter-branch transfer execution (failed status + journal source)
-- RC1 deploy bundle trigger: 2026-06-26
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- Allow failed execution status on branch transfers
ALTER TABLE rateb_branch_transfers
    MODIFY status ENUM('draft','pending','approved','completed','failed','rejected','cancelled') NOT NULL DEFAULT 'draft';

-- Journal source for inter-branch GL postings
ALTER TABLE rateb_journal_entries
    MODIFY source_type ENUM(
        'manual','invoice','payment','purchase_order','subscription',
        'cash_voucher','stock_movement','purchase_invoice',
        'supplier_payment','year_end_close','branch_transfer'
    ) NOT NULL DEFAULT 'manual';
