-- Repair corrupted plan labels (e.g. name=label, description=". ERP") without touching prices/modules.
SET NAMES utf8mb4;

UPDATE rateb_plans
SET name = 'انطلاق',
    description = 'ابدأ بلوحة التحكم والإشعارات والتقارير الأساسية.'
WHERE slug = 'launch'
  AND (
      name IS NULL OR TRIM(name) = '' OR name IN ('Launch', 'label', 'ultimate', 'Ultimate', 'Starter')
      OR description IS NULL OR TRIM(description) = '' OR description LIKE '%. ERP%' OR description LIKE 'Start with%'
  );

UPDATE rateb_plans
SET name = 'أساسي',
    description = 'تشغيل المشتريات والمخزون والموردين للمنشآت الصغيرة.'
WHERE slug = 'starter'
  AND (
      name IS NULL OR TRIM(name) = '' OR name IN ('Starter', 'label')
      OR description IS NULL OR TRIM(description) = '' OR description LIKE '%. ERP%' OR description LIKE 'Core purchasing%'
  );

UPDATE rateb_plans
SET name = 'تجاري',
    description = 'البيع والتوزيع عبر نقطة البيع واللوجستيات وسوق الخدمات.'
WHERE slug = 'commerce'
  AND (
      name IS NULL OR TRIM(name) = '' OR name IN ('Commerce', 'label')
      OR description IS NULL OR TRIM(description) = '' OR description LIKE '%. ERP%' OR description LIKE 'Sell, stock%'
  );

UPDATE rateb_plans
SET name = 'احترافي',
    description = 'نمو إداري مع الموارد البشرية وCRM والمشاريع والحسابات.'
WHERE slug = 'professional'
  AND (
      name IS NULL OR TRIM(name) = '' OR name IN ('Professional', 'label')
      OR description IS NULL OR TRIM(description) = '' OR description LIKE '%. ERP%' OR description LIKE 'Grow with%'
  );

UPDATE rateb_plans
SET name = 'مؤسسات',
    description = 'عمق مؤسسي: التصنيع والرواتب والجودة وذكاء الأعمال.'
WHERE slug = 'enterprise'
  AND (
      name IS NULL OR TRIM(name) = '' OR name IN ('Enterprise', 'label')
      OR description IS NULL OR TRIM(description) = '' OR description LIKE '%. ERP%' OR description LIKE 'Industrial depth%'
  );

UPDATE rateb_plans
SET name = 'متكامل',
    description = 'منصة رتب ERP كاملة مع الحوكمة والتحكم بالوصول.'
WHERE slug = 'ultimate'
  AND (
      name IS NULL OR TRIM(name) = '' OR name IN ('Ultimate', 'label', 'Launch', 'Starter', 'ultimate')
      OR description IS NULL OR TRIM(description) = '' OR description LIKE '%. ERP%' OR description LIKE 'Full Rateb%'
      OR (description LIKE '%ERP%' AND CHAR_LENGTH(TRIM(description)) < 12)
  );
