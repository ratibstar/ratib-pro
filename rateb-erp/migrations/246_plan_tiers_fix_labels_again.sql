-- Re-run label repair if 245 already applied with a narrower WHERE clause.
SET NAMES utf8mb4;

UPDATE rateb_plans
SET name = 'متكامل',
    description = 'منصة رتب ERP كاملة مع الحوكمة والتحكم بالوصول.'
WHERE slug = 'ultimate'
  AND (
      name IS NULL OR TRIM(name) = '' OR name IN ('Ultimate', 'label', 'ultimate')
      OR description IS NULL OR TRIM(description) = '' OR TRIM(description) = '. ERP'
      OR description LIKE '%. ERP%'
      OR (description LIKE '%ERP%' AND CHAR_LENGTH(TRIM(description)) < 20)
  );

UPDATE rateb_plans
SET name = 'انطلاق',
    description = 'ابدأ بلوحة التحكم والإشعارات والتقارير الأساسية.'
WHERE slug = 'launch'
  AND (
      name IS NULL OR TRIM(name) = '' OR name IN ('Launch', 'label')
      OR description IS NULL OR TRIM(description) = '' OR TRIM(description) = '. ERP'
      OR description LIKE '%. ERP%'
  );
