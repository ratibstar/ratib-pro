-- Demo operational data for admin oversight screens (PR/PO/inventory/movements/evaluations/RFQ)
SET NAMES utf8mb4;

SET @cid = (SELECT id FROM rateb_companies WHERE slug = 'demo-company' LIMIT 1);
SET @cid = IFNULL(@cid, (SELECT id FROM rateb_companies WHERE status = 'active' ORDER BY id LIMIT 1));

-- Skip if company missing or demo data already present
SET @skip = IF(@cid IS NULL OR EXISTS (
    SELECT 1 FROM rateb_purchase_requests WHERE company_id = @cid LIMIT 1
), 1, 0);

-- Supplier
INSERT INTO rateb_suppliers (company_id, name, code, email, phone, status)
SELECT @cid, 'مورد تجريبي — المعدات الطبية', 'SUP-DEMO', 'supplier@demo.rateb.sa', '+966500000001', 'active'
FROM DUAL WHERE @skip = 0
  AND NOT EXISTS (SELECT 1 FROM rateb_suppliers WHERE company_id = @cid AND code = 'SUP-DEMO' LIMIT 1);

SET @sup = (SELECT id FROM rateb_suppliers WHERE company_id = @cid AND code = 'SUP-DEMO' LIMIT 1);

-- Warehouse
INSERT INTO rateb_warehouses (company_id, name, code, location, status)
SELECT @cid, 'المستودع الرئيسي', 'WH-MAIN', 'الرياض', 'active'
FROM DUAL WHERE @skip = 0
  AND NOT EXISTS (SELECT 1 FROM rateb_warehouses WHERE company_id = @cid AND code = 'WH-MAIN' LIMIT 1);

SET @wh = (SELECT id FROM rateb_warehouses WHERE company_id = @cid AND code = 'WH-MAIN' LIMIT 1);

-- Inventory items
INSERT INTO rateb_inventory (company_id, warehouse_id, item_name, sku, category, quantity, unit, unit_cost, reorder_level, expiry_date, status)
SELECT @cid, @wh, 'قفازات طبية — Large', 'GLV-L', 'مستهلكات', 120.000, 'box', 45.00, 50.000, DATE_ADD(CURDATE(), INTERVAL 180 DAY), 'active'
FROM DUAL WHERE @skip = 0 AND @wh IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM rateb_inventory WHERE company_id = @cid AND sku = 'GLV-L' LIMIT 1);

INSERT INTO rateb_inventory (company_id, warehouse_id, item_name, sku, category, quantity, unit, unit_cost, reorder_level, expiry_date, status)
SELECT @cid, @wh, 'محلول تعقيم', 'DSF-500', 'مستهلكات', 35.000, 'bottle', 28.50, 40.000, DATE_ADD(CURDATE(), INTERVAL 90 DAY), 'active'
FROM DUAL WHERE @skip = 0 AND @wh IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM rateb_inventory WHERE company_id = @cid AND sku = 'DSF-500' LIMIT 1);

SET @inv1 = (SELECT id FROM rateb_inventory WHERE company_id = @cid AND sku = 'GLV-L' LIMIT 1);
SET @inv2 = (SELECT id FROM rateb_inventory WHERE company_id = @cid AND sku = 'DSF-500' LIMIT 1);

-- Purchase request
INSERT INTO rateb_purchase_requests (company_id, request_no, title, department, priority, status, total_estimated, notes)
SELECT @cid, 'PR-00001', 'طلب شراء مستهلكات طبية', 'المشتريات', 'medium', 'submitted', 5250.00, 'بيانات تجريبية للعرض في لوحة الإدارة'
FROM DUAL WHERE @skip = 0
  AND NOT EXISTS (SELECT 1 FROM rateb_purchase_requests WHERE company_id = @cid AND request_no = 'PR-00001' LIMIT 1);

SET @pr = (SELECT id FROM rateb_purchase_requests WHERE company_id = @cid AND request_no = 'PR-00001' LIMIT 1);

-- Purchase order
INSERT INTO rateb_purchase_orders (company_id, order_no, supplier_id, purchase_request_id, status, order_date, expected_date, subtotal, total_amount, notes)
SELECT @cid, 'PO-00001', @sup, @pr, 'sent', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 14 DAY), 5250.00, 5250.00, 'أمر شراء تجريبي'
FROM DUAL WHERE @skip = 0 AND @sup IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM rateb_purchase_orders WHERE company_id = @cid AND order_no = 'PO-00001' LIMIT 1);

-- Stock movements
INSERT INTO rateb_stock_movements (company_id, inventory_id, warehouse_id, movement_type, quantity, reference_type, notes)
SELECT @cid, @inv1, @wh, 'in', 120.000, 'demo_seed', 'استلام أولي — تجريبي'
FROM DUAL WHERE @skip = 0 AND @inv1 IS NOT NULL AND @wh IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM rateb_stock_movements WHERE company_id = @cid AND reference_type = 'demo_seed' AND inventory_id = @inv1 LIMIT 1);

INSERT INTO rateb_stock_movements (company_id, inventory_id, warehouse_id, movement_type, quantity, reference_type, notes)
SELECT @cid, @inv2, @wh, 'out', 5.000, 'demo_seed', 'صرف تجريبي'
FROM DUAL WHERE @skip = 0 AND @inv2 IS NOT NULL AND @wh IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM rateb_stock_movements WHERE company_id = @cid AND reference_type = 'demo_seed' AND inventory_id = @inv2 AND movement_type = 'out' LIMIT 1);

-- Supplier evaluation
INSERT INTO rateb_supplier_evaluations (company_id, supplier_id, evaluation_date, quality_score, delivery_score, price_score, service_score, overall_score, comments, status)
SELECT @cid, @sup, CURDATE(), 85, 90, 78, 82, 83.75, 'تقييم تجريبي', 'published'
FROM DUAL WHERE @skip = 0 AND @sup IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM rateb_supplier_evaluations WHERE company_id = @cid AND supplier_id = @sup LIMIT 1);

-- RFQ + quotations for comparison demo
INSERT INTO rateb_rfq (company_id, rfq_no, title, status, deadline, description)
SELECT @cid, 'RFQ-00001', 'طلب عروض — مستهلكات Q2', 'published', DATE_ADD(CURDATE(), INTERVAL 30 DAY), 'مقارنة عروض تجريبية'
FROM DUAL WHERE @skip = 0
  AND NOT EXISTS (SELECT 1 FROM rateb_rfq WHERE company_id = @cid AND rfq_no = 'RFQ-00001' LIMIT 1);

SET @rfq = (SELECT id FROM rateb_rfq WHERE company_id = @cid AND rfq_no = 'RFQ-00001' LIMIT 1);

INSERT INTO rateb_supplier_quotations (company_id, rfq_id, supplier_id, quotation_no, amount, status, valid_until)
SELECT @cid, @rfq, @sup, 'QT-00001', 5100.00, 'submitted', DATE_ADD(CURDATE(), INTERVAL 45 DAY)
FROM DUAL WHERE @skip = 0 AND @rfq IS NOT NULL AND @sup IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM rateb_supplier_quotations WHERE company_id = @cid AND quotation_no = 'QT-00001' LIMIT 1);

INSERT INTO rateb_supplier_quotations (company_id, rfq_id, supplier_id, quotation_no, amount, status, valid_until, notes)
SELECT @cid, @rfq, @sup, 'QT-00002', 4980.00, 'under_review', DATE_ADD(CURDATE(), INTERVAL 45 DAY), 'عرض بديل (نفس المورد — سيناريو تجريبي)'
FROM DUAL WHERE @skip = 0 AND @rfq IS NOT NULL AND @sup IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM rateb_supplier_quotations WHERE company_id = @cid AND quotation_no = 'QT-00002' LIMIT 1);

-- Default approval workflows (platform-wide templates)
INSERT INTO rateb_approval_workflows (company_id, name, entity_type, is_active)
SELECT NULL, 'اعتماد طلبات الشراء', 'purchase_request', 1
FROM DUAL WHERE @skip = 0
  AND NOT EXISTS (SELECT 1 FROM rateb_approval_workflows WHERE entity_type = 'purchase_request' AND company_id IS NULL LIMIT 1);

INSERT INTO rateb_approval_workflows (company_id, name, entity_type, is_active)
SELECT NULL, 'اعتماد أوامر الشراء', 'purchase_order', 1
FROM DUAL WHERE @skip = 0
  AND NOT EXISTS (SELECT 1 FROM rateb_approval_workflows WHERE entity_type = 'purchase_order' AND company_id IS NULL LIMIT 1);

SET @wf_pr = (SELECT id FROM rateb_approval_workflows WHERE entity_type = 'purchase_request' AND company_id IS NULL ORDER BY id LIMIT 1);
SET @wf_po = (SELECT id FROM rateb_approval_workflows WHERE entity_type = 'purchase_order' AND company_id IS NULL ORDER BY id LIMIT 1);
SET @role = (SELECT id FROM rateb_roles WHERE slug = 'super-admin' LIMIT 1);

INSERT INTO rateb_approval_workflow_steps (workflow_id, step_order, role_id, label)
SELECT @wf_pr, 1, @role, 'مراجعة المشتريات'
FROM DUAL WHERE @skip = 0 AND @wf_pr IS NOT NULL AND @role IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM rateb_approval_workflow_steps WHERE workflow_id = @wf_pr LIMIT 1);

INSERT INTO rateb_approval_workflow_steps (workflow_id, step_order, role_id, label)
SELECT @wf_po, 1, @role, 'اعتماد أمر الشراء'
FROM DUAL WHERE @skip = 0 AND @wf_po IS NOT NULL AND @role IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM rateb_approval_workflow_steps WHERE workflow_id = @wf_po LIMIT 1);

-- Demo subscription for SaaS gate (if missing)
INSERT INTO rateb_subscriptions (company_id, plan_id, status, starts_at, ends_at, billing_cycle)
SELECT @cid, c.plan_id, 'active', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 YEAR), 'yearly'
FROM rateb_companies c
WHERE @skip = 0 AND @cid IS NOT NULL AND c.id = @cid AND c.plan_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM rateb_subscriptions WHERE company_id = @cid LIMIT 1);
