-- RATEB ERP — Asset depreciation: before/after book value + approval status
SET NAMES utf8mb4;

ALTER TABLE rateb_asset_depreciation ADD COLUMN book_value_before DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER amount;
ALTER TABLE rateb_asset_depreciation ADD COLUMN status ENUM('draft','approved') NOT NULL DEFAULT 'draft' AFTER book_value;

UPDATE rateb_asset_depreciation
SET book_value_before = ROUND(amount + book_value, 2)
WHERE book_value_before = 0.00 AND (amount <> 0 OR book_value <> 0);

UPDATE rateb_asset_depreciation SET status = 'approved' WHERE status = 'draft';
