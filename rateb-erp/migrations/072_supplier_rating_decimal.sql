-- Widen supplier rating to hold evaluation average up to 10.00
SET NAMES utf8mb4;

ALTER TABLE rateb_suppliers
    MODIFY COLUMN rating DECIMAL(4,2) NULL DEFAULT 0.00;
