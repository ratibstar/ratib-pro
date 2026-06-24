-- RATEB ERP — branch isolation phase 3: CRM, HR, procurement, inventory, support
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- rateb_customers
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_customers' AND COLUMN_NAME = 'branch_id');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_customers ADD COLUMN branch_id INT UNSIGNED NULL AFTER company_id, ADD INDEX idx_customer_branch (branch_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- rateb_attendance_records
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_attendance_records' AND COLUMN_NAME = 'branch_id');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_attendance_records ADD COLUMN branch_id INT UNSIGNED NULL AFTER company_id, ADD INDEX idx_att_branch (branch_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- rateb_payroll_periods
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_payroll_periods' AND COLUMN_NAME = 'branch_id');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_payroll_periods ADD COLUMN branch_id INT UNSIGNED NULL AFTER company_id, ADD INDEX idx_pp_branch (branch_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- rateb_payroll_lines
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_payroll_lines' AND COLUMN_NAME = 'branch_id');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_payroll_lines ADD COLUMN branch_id INT UNSIGNED NULL AFTER company_id, ADD INDEX idx_pl_branch (branch_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- rateb_leave_requests
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_leave_requests' AND COLUMN_NAME = 'branch_id');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_leave_requests ADD COLUMN branch_id INT UNSIGNED NULL AFTER company_id, ADD INDEX idx_lr_branch (branch_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- rateb_supplier_quotations
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_supplier_quotations' AND COLUMN_NAME = 'branch_id');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_supplier_quotations ADD COLUMN branch_id INT UNSIGNED NULL AFTER company_id, ADD INDEX idx_sq_branch (branch_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- rateb_purchase_invoices
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_purchase_invoices' AND COLUMN_NAME = 'branch_id');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_purchase_invoices ADD COLUMN branch_id INT UNSIGNED NULL AFTER company_id, ADD INDEX idx_pi_branch (branch_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- rateb_documents
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_documents' AND COLUMN_NAME = 'branch_id');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_documents ADD COLUMN branch_id INT UNSIGNED NULL AFTER company_id, ADD INDEX idx_doc_branch (branch_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- rateb_support_tickets
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_support_tickets' AND COLUMN_NAME = 'branch_id');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_support_tickets ADD COLUMN branch_id INT UNSIGNED NULL AFTER company_id, ADD INDEX idx_ticket_branch (branch_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- rateb_supplier_evaluations
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_supplier_evaluations' AND COLUMN_NAME = 'branch_id');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_supplier_evaluations ADD COLUMN branch_id INT UNSIGNED NULL AFTER company_id, ADD INDEX idx_se_branch (branch_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- rateb_supplier_communications
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_supplier_communications' AND COLUMN_NAME = 'branch_id');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_supplier_communications ADD COLUMN branch_id INT UNSIGNED NULL AFTER company_id, ADD INDEX idx_sc_branch (branch_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- rateb_inventory_batches
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_inventory_batches' AND COLUMN_NAME = 'branch_id');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_inventory_batches ADD COLUMN branch_id INT UNSIGNED NULL AFTER company_id, ADD INDEX idx_ib_branch (branch_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- rateb_contract_renewals
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_contract_renewals' AND COLUMN_NAME = 'branch_id');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_contract_renewals ADD COLUMN branch_id INT UNSIGNED NULL AFTER company_id, ADD INDEX idx_cr_branch (branch_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- rateb_cms_leads (CRM) — add company_id if missing, then branch_id
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_cms_leads' AND COLUMN_NAME = 'company_id');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_cms_leads ADD COLUMN company_id INT UNSIGNED NULL AFTER id, ADD INDEX idx_lead_company (company_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_cms_leads' AND COLUMN_NAME = 'branch_id');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_cms_leads ADD COLUMN branch_id INT UNSIGNED NULL, ADD INDEX idx_lead_branch (branch_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- rateb_warehouse_transfers (inter-warehouse / inter-branch)
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_warehouse_transfers' AND COLUMN_NAME = 'source_branch_id');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_warehouse_transfers ADD COLUMN source_branch_id INT UNSIGNED NULL AFTER company_id, ADD COLUMN dest_branch_id INT UNSIGNED NULL AFTER source_branch_id, ADD INDEX idx_wt_src_branch (source_branch_id), ADD INDEX idx_wt_dest_branch (dest_branch_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Backfill to main branch
UPDATE rateb_customers t JOIN rateb_branches b ON b.company_id = t.company_id AND b.is_main = 1 SET t.branch_id = b.id WHERE t.branch_id IS NULL;
UPDATE rateb_payroll_periods t JOIN rateb_branches b ON b.company_id = t.company_id AND b.is_main = 1 SET t.branch_id = b.id WHERE t.branch_id IS NULL;
UPDATE rateb_documents t JOIN rateb_branches b ON b.company_id = t.company_id AND b.is_main = 1 SET t.branch_id = b.id WHERE t.branch_id IS NULL;
UPDATE rateb_support_tickets t JOIN rateb_branches b ON b.company_id = t.company_id AND b.is_main = 1 SET t.branch_id = b.id WHERE t.branch_id IS NULL;
UPDATE rateb_supplier_evaluations t JOIN rateb_branches b ON b.company_id = t.company_id AND b.is_main = 1 SET t.branch_id = b.id WHERE t.branch_id IS NULL;
UPDATE rateb_supplier_communications t JOIN rateb_branches b ON b.company_id = t.company_id AND b.is_main = 1 SET t.branch_id = b.id WHERE t.branch_id IS NULL;
UPDATE rateb_cms_leads t
JOIN rateb_companies c ON c.id = (SELECT MIN(id) FROM rateb_companies)
SET t.company_id = c.id
WHERE t.company_id IS NULL;

UPDATE rateb_cms_leads t JOIN rateb_branches b ON b.company_id = t.company_id AND b.is_main = 1 SET t.branch_id = b.id WHERE t.branch_id IS NULL AND t.company_id IS NOT NULL;

UPDATE rateb_attendance_records t JOIN rateb_employees e ON e.id = t.employee_id SET t.branch_id = e.branch_id WHERE t.branch_id IS NULL AND e.branch_id IS NOT NULL;
UPDATE rateb_attendance_records t JOIN rateb_branches b ON b.company_id = t.company_id AND b.is_main = 1 SET t.branch_id = b.id WHERE t.branch_id IS NULL;

UPDATE rateb_leave_requests t JOIN rateb_employees e ON e.id = t.employee_id SET t.branch_id = e.branch_id WHERE t.branch_id IS NULL AND e.branch_id IS NOT NULL;
UPDATE rateb_leave_requests t JOIN rateb_branches b ON b.company_id = t.company_id AND b.is_main = 1 SET t.branch_id = b.id WHERE t.branch_id IS NULL;

UPDATE rateb_payroll_lines t JOIN rateb_employees e ON e.id = t.employee_id SET t.branch_id = e.branch_id WHERE t.branch_id IS NULL AND e.branch_id IS NOT NULL;
UPDATE rateb_payroll_lines t JOIN rateb_branches b ON b.company_id = t.company_id AND b.is_main = 1 SET t.branch_id = b.id WHERE t.branch_id IS NULL;

UPDATE rateb_supplier_quotations t JOIN rateb_rfq r ON r.id = t.rfq_id SET t.branch_id = r.branch_id WHERE t.branch_id IS NULL AND r.branch_id IS NOT NULL;
UPDATE rateb_supplier_quotations t JOIN rateb_branches b ON b.company_id = t.company_id AND b.is_main = 1 SET t.branch_id = b.id WHERE t.branch_id IS NULL;

UPDATE rateb_purchase_invoices t JOIN rateb_purchase_orders po ON po.id = t.purchase_order_id SET t.branch_id = po.branch_id WHERE t.branch_id IS NULL AND po.branch_id IS NOT NULL;
UPDATE rateb_purchase_invoices t JOIN rateb_branches b ON b.company_id = t.company_id AND b.is_main = 1 SET t.branch_id = b.id WHERE t.branch_id IS NULL;

UPDATE rateb_inventory_batches t JOIN rateb_inventory i ON i.id = t.inventory_id SET t.branch_id = i.branch_id WHERE t.branch_id IS NULL AND i.branch_id IS NOT NULL;
UPDATE rateb_inventory_batches t JOIN rateb_branches b ON b.company_id = t.company_id AND b.is_main = 1 SET t.branch_id = b.id WHERE t.branch_id IS NULL;

UPDATE rateb_contract_renewals t JOIN rateb_contracts c ON c.id = t.contract_id SET t.branch_id = c.branch_id WHERE t.branch_id IS NULL AND c.branch_id IS NOT NULL;
UPDATE rateb_contract_renewals t JOIN rateb_branches b ON b.company_id = t.company_id AND b.is_main = 1 SET t.branch_id = b.id WHERE t.branch_id IS NULL;

UPDATE rateb_warehouse_transfers t
JOIN rateb_warehouses sw ON sw.id = t.source_warehouse_id
JOIN rateb_warehouses dw ON dw.id = t.destination_warehouse_id
SET t.source_branch_id = sw.branch_id, t.dest_branch_id = dw.branch_id
WHERE t.source_branch_id IS NULL OR t.dest_branch_id IS NULL;

UPDATE rateb_warehouse_transfers t
JOIN rateb_branches b ON b.company_id = t.company_id AND b.is_main = 1
SET t.source_branch_id = b.id
WHERE t.source_branch_id IS NULL;

UPDATE rateb_warehouse_transfers t
JOIN rateb_branches b ON b.company_id = t.company_id AND b.is_main = 1
SET t.dest_branch_id = b.id
WHERE t.dest_branch_id IS NULL;
