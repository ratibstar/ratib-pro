-- Force branch_id on ops tables (MariaDB IF NOT EXISTS).
-- Run on the ERP database the app actually uses — check BOTH:
--   admin_rateb-erp  AND  admin_rateb_erp
-- Verify first: SELECT DATABASE(); SHOW COLUMNS FROM rateb_purchase_orders LIKE 'branch_id';
SET NAMES utf8mb4;

ALTER TABLE rateb_purchase_requests ADD COLUMN IF NOT EXISTS branch_id INT UNSIGNED NULL AFTER company_id;
ALTER TABLE rateb_purchase_orders ADD COLUMN IF NOT EXISTS branch_id INT UNSIGNED NULL AFTER company_id;
ALTER TABLE rateb_suppliers ADD COLUMN IF NOT EXISTS branch_id INT UNSIGNED NULL AFTER company_id;
ALTER TABLE rateb_inventory ADD COLUMN IF NOT EXISTS branch_id INT UNSIGNED NULL AFTER company_id;
ALTER TABLE rateb_rfq ADD COLUMN IF NOT EXISTS branch_id INT UNSIGNED NULL AFTER company_id;
ALTER TABLE rateb_contracts ADD COLUMN IF NOT EXISTS branch_id INT UNSIGNED NULL AFTER company_id;
ALTER TABLE rateb_assets ADD COLUMN IF NOT EXISTS branch_id INT UNSIGNED NULL AFTER company_id;
ALTER TABLE rateb_tenders ADD COLUMN IF NOT EXISTS branch_id INT UNSIGNED NULL AFTER company_id;
ALTER TABLE rateb_stock_movements ADD COLUMN IF NOT EXISTS branch_id INT UNSIGNED NULL AFTER company_id;
