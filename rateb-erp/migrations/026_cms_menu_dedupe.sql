-- RATEB ERP — CMS menu dedupe (fix repeated nav links from multi-run migration 024)
SET NAMES utf8mb4;

DELETE i FROM rateb_cms_menu_items i
INNER JOIN (
    SELECT menu_id, url, MAX(id) AS keep_id
    FROM rateb_cms_menu_items
    GROUP BY menu_id, url
) k ON i.menu_id = k.menu_id AND i.url = k.url
WHERE i.id <> k.keep_id;

SET @idx_exists := (
    SELECT COUNT(*) FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'rateb_cms_menu_items'
      AND index_name = 'uq_cms_menu_item_url'
);
SET @sql := IF(@idx_exists = 0,
    'ALTER TABLE rateb_cms_menu_items ADD UNIQUE KEY uq_cms_menu_item_url (menu_id, url)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Re-seed main menu if empty
INSERT INTO rateb_cms_menu_items (menu_id, label_en, label_ar, url, sort_order)
SELECT m.id, t.en, t.ar, t.url, t.ord
FROM rateb_cms_menus m
JOIN (
    SELECT 1 ord, 'Home' en, 'الرئيسية' ar, 'site' url UNION ALL
    SELECT 2, 'Features', 'المميزات', 'site/features' UNION ALL
    SELECT 3, 'Solutions', 'الحلول', 'site/solutions' UNION ALL
    SELECT 4, 'Pricing', 'الأسعار', 'site/pricing' UNION ALL
    SELECT 5, 'Blog', 'المدونة', 'site/blog' UNION ALL
    SELECT 6, 'Contact', 'اتصل بنا', 'site/contact'
) t ON m.slug = 'main'
WHERE NOT EXISTS (SELECT 1 FROM rateb_cms_menu_items WHERE menu_id = m.id LIMIT 1)
ON DUPLICATE KEY UPDATE
    label_en = VALUES(label_en),
    label_ar = VALUES(label_ar),
    sort_order = VALUES(sort_order);
