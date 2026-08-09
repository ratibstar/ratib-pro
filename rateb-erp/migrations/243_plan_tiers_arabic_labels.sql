-- Arabize canonical plan display names/descriptions (keep prices & limits as-is).
SET NAMES utf8mb4;

UPDATE rateb_plans
SET name = 'انطلاق',
    description = 'ابدأ بلوحة التحكم والإشعارات والتقارير الأساسية.'
WHERE slug = 'launch';

UPDATE rateb_plans
SET name = 'أساسي',
    description = 'تشغيل المشتريات والمخزون والموردين للمنشآت الصغيرة.'
WHERE slug = 'starter';

UPDATE rateb_plans
SET name = 'تجاري',
    description = 'البيع والتوزيع عبر نقطة البيع واللوجستيات وسوق الخدمات.'
WHERE slug = 'commerce';

UPDATE rateb_plans
SET name = 'احترافي',
    description = 'نمو إداري مع الموارد البشرية وCRM والمشاريع والحسابات.'
WHERE slug = 'professional';

UPDATE rateb_plans
SET name = 'مؤسسات',
    description = 'عمق مؤسسي: التصنيع والرواتب والجودة وذكاء الأعمال.'
WHERE slug = 'enterprise';

UPDATE rateb_plans
SET name = 'متكامل',
    description = 'منصة رتب ERP كاملة مع الحوكمة والتحكم بالوصول.'
WHERE slug = 'ultimate';
