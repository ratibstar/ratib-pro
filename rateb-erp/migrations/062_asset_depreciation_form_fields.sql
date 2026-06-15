-- RATEB ERP — Asset depreciation form fields (mockup alignment)
SET NAMES utf8mb4;

ALTER TABLE rateb_asset_depreciation ADD COLUMN depreciation_type VARCHAR(32) NOT NULL DEFAULT 'monthly' AFTER period_date;
ALTER TABLE rateb_asset_depreciation ADD COLUMN depreciation_rate DECIMAL(8,2) NULL DEFAULT NULL AFTER depreciation_type;
ALTER TABLE rateb_asset_depreciation ADD COLUMN useful_life_months INT UNSIGNED NULL DEFAULT NULL AFTER depreciation_rate;
ALTER TABLE rateb_asset_depreciation ADD COLUMN residual_value DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER useful_life_months;
ALTER TABLE rateb_asset_depreciation ADD COLUMN cost_center_id INT UNSIGNED NULL DEFAULT NULL AFTER residual_value;
ALTER TABLE rateb_asset_depreciation ADD COLUMN notes TEXT NULL AFTER cost_center_id;
