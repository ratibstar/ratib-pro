-- Product category cover image (stored path under uploads/)
ALTER TABLE rateb_product_categories
    ADD COLUMN image_path VARCHAR(500) NULL AFTER icon;
