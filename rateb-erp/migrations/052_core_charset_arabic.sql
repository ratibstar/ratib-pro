-- RATEB ERP — ensure core Arabic columns use utf8mb4 (fixes ? in stored Arabic)
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

ALTER TABLE rateb_permissions CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE rateb_warehouses CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE rateb_inventory CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE rateb_suppliers CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE rateb_purchase_requests CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE rateb_purchase_orders CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE rateb_stock_movements CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
