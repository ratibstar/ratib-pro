-- Phase 13 — Add updated_at to warehouses for reliable delta cursors (idempotent).
-- Mirror of offline/migrations/004_warehouses_updated_at_for_delta.sql
SET NAMES utf8mb4;

SET @col = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'rateb_warehouses'
    AND COLUMN_NAME = 'updated_at'
);
SET @sql = IF(
  @col = 0,
  'ALTER TABLE rateb_warehouses ADD COLUMN updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP AFTER created_at',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE rateb_warehouses
SET updated_at = COALESCE(updated_at, created_at, NOW())
WHERE updated_at IS NULL;
