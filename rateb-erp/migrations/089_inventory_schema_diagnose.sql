-- RATEB ERP — inventory schema diagnostic (run on admin_rateb-erp in phpMyAdmin)
-- Shows missing columns/tables required for inventory create/save.
SET NAMES utf8mb4;

SELECT expected.table_name AS missing_table, expected.column_name AS missing_column
FROM (
    SELECT 'rateb_inventory' AS table_name, 'item_code' AS column_name
    UNION ALL SELECT 'rateb_inventory', 'category_id'
    UNION ALL SELECT 'rateb_inventory', 'barcode'
    UNION ALL SELECT 'rateb_inventory', 'qr_code'
    UNION ALL SELECT 'rateb_inventory', 'min_stock'
    UNION ALL SELECT 'rateb_inventory', 'max_stock'
    UNION ALL SELECT 'rateb_inventory', 'production_date'
    UNION ALL SELECT 'rateb_inventory', 'document_path'
    UNION ALL SELECT 'rateb_inventory', 'notes'
    UNION ALL SELECT 'rateb_stock_movements', 'movement_no'
) expected
LEFT JOIN information_schema.tables t
    ON t.table_schema = DATABASE() AND t.table_name = expected.table_name
LEFT JOIN information_schema.columns c
    ON c.table_schema = DATABASE()
   AND c.table_name = expected.table_name
   AND c.column_name = expected.column_name
WHERE t.table_name IS NULL OR c.column_name IS NULL
ORDER BY expected.table_name, expected.column_name;

-- If the query above returns zero rows, inventory schema is OK.
-- Then register migration (if not already):
-- INSERT IGNORE INTO rateb_migrations (filename) VALUES ('088_inventory_production_catchup.sql');
