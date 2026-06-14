-- RATEB ERP — production dates for inventory + batches

ALTER TABLE rateb_inventory
    ADD COLUMN production_date DATE NULL AFTER reorder_level;

ALTER TABLE rateb_inventory_batches
    ADD COLUMN production_date DATE NULL AFTER quantity;
