-- RATEB ERP — Permissions audit: Arabic labels repair + billing module normalize
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

UPDATE rateb_permissions SET module = 'subscriptions' WHERE slug = 'billing.manage' AND module <> 'subscriptions';

UPDATE rateb_permissions SET
    name_ar = 'عرض الموارد البشرية',
    description_ar = 'عرض لوحة الموارد البشرية وقوائم الموظفين والتقارير'
WHERE slug = 'hr.view';

UPDATE rateb_permissions SET
    name_ar = 'إدارة الموارد البشرية',
    description_ar = 'إدارة الموظفين والحضور والإجازات والرواتب'
WHERE slug = 'hr.manage';

UPDATE rateb_permissions SET name_ar = 'لوحة الفرع', description_ar = 'عرض مؤشرات أداء الفرع' WHERE slug = 'branch.dashboard.view';
UPDATE rateb_permissions SET name_ar = 'مقارنة الفروع', description_ar = 'مقارنة مؤشرات الأداء بين الفروع' WHERE slug = 'branch.dashboard.compare';
UPDATE rateb_permissions SET name_ar = 'تقارير الفروع', description_ar = 'عرض تقارير مستوى الفرع' WHERE slug = 'branch.reports.view';
UPDATE rateb_permissions SET name_ar = 'عرض التحويلات بين الفروع', description_ar = 'عرض طلبات التحويل بين الفروع' WHERE slug = 'branch.transfers.view';
UPDATE rateb_permissions SET name_ar = 'إدارة التحويلات بين الفروع', description_ar = 'إنشاء واعتماد التحويلات بين الفروع' WHERE slug = 'branch.transfers.manage';

UPDATE rateb_permissions SET name_ar = 'تقرير الأرباح والخسائر للفرع', description_ar = 'قائمة الدخل حسب الفرع' WHERE slug = 'branch.financial.pl';
UPDATE rateb_permissions SET name_ar = 'الميزانية العمومية للفرع', description_ar = 'الميزانية العمومية حسب الفرع' WHERE slug = 'branch.financial.bs';
UPDATE rateb_permissions SET name_ar = 'التدفقات النقدية للفرع', description_ar = 'قائمة التدفقات النقدية حسب الفرع' WHERE slug = 'branch.financial.cf';
UPDATE rateb_permissions SET name_ar = 'التقارير المالية الموحدة', description_ar = 'القوائم المالية الموحدة للمقر الرئيسي' WHERE slug = 'branch.financial.consolidated';
UPDATE rateb_permissions SET name_ar = 'حسابات بين الفروع', description_ar = 'أرصدة مستحقة من/إلى الفروع في الدفتر العام' WHERE slug = 'branch.financial.interbranch';
UPDATE rateb_permissions SET name_ar = 'ميزان المراجعة الموحد', description_ar = 'ميزان مراجعة موحد لجميع الفروع' WHERE slug = 'branch.financial.consolidated.tb';
UPDATE rateb_permissions SET name_ar = 'دفتر الأستاذ الموحد', description_ar = 'دفتر الأستاذ العام الموحد لجميع الفروع' WHERE slug = 'branch.financial.consolidated.gl';
UPDATE rateb_permissions SET name_ar = 'أعمار الذمم المدينة للفرع', description_ar = 'تحليل أعمار الذمم المدينة حسب الفرع' WHERE slug = 'branch.financial.araging';
UPDATE rateb_permissions SET name_ar = 'أعمار الذمم الدائنة للفرع', description_ar = 'تحليل أعمار الذمم الدائنة حسب الفرع' WHERE slug = 'branch.financial.apaging';
UPDATE rateb_permissions SET name_ar = 'ملخص المدينين للفرع', description_ar = 'ملخص الذمم المدينة حسب الفرع' WHERE slug = 'branch.financial.receivables';
UPDATE rateb_permissions SET name_ar = 'ملخص الدائنين للفرع', description_ar = 'ملخص الذمم الدائنة حسب الفرع' WHERE slug = 'branch.financial.payables';

UPDATE rateb_permissions SET
    name_ar = 'إدارة التحكم بالوصول',
    description_ar = 'يشمل إدارة المستخدمين والأدوار والصلاحيات ومصفوفة الصلاحيات'
WHERE slug = 'access.manage';
