-- RATEB ERP — workflow record IDs (2 letters + 4 digits)

ALTER TABLE rateb_asset_maintenance
    ADD COLUMN maintenance_no VARCHAR(6) NULL AFTER company_id;

ALTER TABLE rateb_asset_assignments
    ADD COLUMN assignment_no VARCHAR(6) NULL AFTER company_id;

ALTER TABLE rateb_asset_depreciation
    ADD COLUMN depreciation_no VARCHAR(6) NULL AFTER company_id;

ALTER TABLE rateb_device_service_history
    ADD COLUMN service_no VARCHAR(6) NULL AFTER company_id;

ALTER TABLE rateb_contract_renewals
    ADD COLUMN renewal_no VARCHAR(6) NULL AFTER company_id;
