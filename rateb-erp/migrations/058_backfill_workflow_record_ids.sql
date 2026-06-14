-- RATEB ERP — backfill workflow record IDs for existing rows
SET NAMES utf8mb4;

UPDATE rateb_asset_maintenance m
JOIN (
    SELECT id,
           CONCAT('AM', LPAD(ROW_NUMBER() OVER (PARTITION BY company_id ORDER BY id), 4, '0')) AS new_no
    FROM rateb_asset_maintenance
    WHERE maintenance_no IS NULL OR TRIM(maintenance_no) = ''
) x ON x.id = m.id
SET m.maintenance_no = x.new_no;

UPDATE rateb_asset_assignments m
JOIN (
    SELECT id,
           CONCAT('AA', LPAD(ROW_NUMBER() OVER (PARTITION BY company_id ORDER BY id), 4, '0')) AS new_no
    FROM rateb_asset_assignments
    WHERE assignment_no IS NULL OR TRIM(assignment_no) = ''
) x ON x.id = m.id
SET m.assignment_no = x.new_no;

UPDATE rateb_asset_depreciation m
JOIN (
    SELECT id,
           CONCAT('AD', LPAD(ROW_NUMBER() OVER (PARTITION BY company_id ORDER BY id), 4, '0')) AS new_no
    FROM rateb_asset_depreciation
    WHERE depreciation_no IS NULL OR TRIM(depreciation_no) = ''
) x ON x.id = m.id
SET m.depreciation_no = x.new_no;

UPDATE rateb_device_service_history m
JOIN (
    SELECT id,
           CONCAT('DS', LPAD(ROW_NUMBER() OVER (PARTITION BY company_id ORDER BY id), 4, '0')) AS new_no
    FROM rateb_device_service_history
    WHERE service_no IS NULL OR TRIM(service_no) = ''
) x ON x.id = m.id
SET m.service_no = x.new_no;

UPDATE rateb_contract_renewals m
JOIN (
    SELECT id,
           CONCAT('CR', LPAD(ROW_NUMBER() OVER (PARTITION BY company_id ORDER BY id), 4, '0')) AS new_no
    FROM rateb_contract_renewals
    WHERE renewal_no IS NULL OR TRIM(renewal_no) = ''
) x ON x.id = m.id
SET m.renewal_no = x.new_no;

UPDATE rateb_device_spare_parts m
JOIN (
    SELECT id,
           CONCAT('SP', LPAD(ROW_NUMBER() OVER (PARTITION BY company_id ORDER BY id), 4, '0')) AS new_no
    FROM rateb_device_spare_parts
    WHERE part_no IS NULL OR TRIM(part_no) = ''
) x ON x.id = m.id
SET m.part_no = x.new_no;
