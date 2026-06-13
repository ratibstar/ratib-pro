-- RATEB ERP — Fix corrupted Arabic CMS content (charset-safe re-apply)
SET NAMES utf8mb4;

-- Home sections
UPDATE rateb_cms_sections SET
    title_ar = 'رتب ERP — منصة العمليات الذكية',
    body_ar = 'منصة موحدة للمشتريات والمخزون والعقود وإدارة الأجهزة الطبية للقطاع الصحي والمؤسسات.'
WHERE page_slug = 'home' AND section_key = 'hero';

UPDATE rateb_cms_sections SET
    title_ar = 'نظرة شاملة على النظام',
    body_ar = 'رؤية شاملة للمشتريات والمستودعات والموردين والأصول وسير عمل الامتثال.'
WHERE page_slug = 'home' AND section_key = 'erp_overview';

UPDATE rateb_cms_sections SET
    title_ar = 'لماذا رتب ERP',
    body_ar = 'مصمم للقطاع الصحي السعودي والمؤسسات مع SaaS متعدد المستأجرين وواجهة عربية وأمان قوي.'
WHERE page_slug = 'home' AND section_key = 'why_rateb';

UPDATE rateb_cms_sections SET title_ar = 'موثوق من مؤسسات نامية' WHERE page_slug = 'home' AND section_key = 'stats';
UPDATE rateb_cms_sections SET title_ar = 'القطاعات التي نخدمها' WHERE page_slug = 'home' AND section_key = 'industries';
UPDATE rateb_cms_sections SET title_ar = 'ماذا يقول عملاؤنا' WHERE page_slug = 'home' AND section_key = 'testimonials';
UPDATE rateb_cms_sections SET title_ar = 'باقات مرنة' WHERE page_slug = 'home' AND section_key = 'pricing_preview';
UPDATE rateb_cms_sections SET title_ar = 'أحدث المقالات' WHERE page_slug = 'home' AND section_key = 'latest_articles';
UPDATE rateb_cms_sections SET title_ar = 'أسئلة شائعة' WHERE page_slug = 'home' AND section_key = 'faq_preview';
UPDATE rateb_cms_sections SET
    title_ar = 'جاهز لتحويل عملياتك؟',
    body_ar = 'اطلب عرضاً تجريبياً أو تحدث مع فريقنا اليوم.'
WHERE page_slug = 'home' AND section_key = 'contact_cta';

UPDATE rateb_cms_sections SET title_ar = 'مميزات المنصة' WHERE page_slug = 'features' AND section_key = 'list';
UPDATE rateb_cms_sections SET title_ar = 'حلول حسب القطاع' WHERE page_slug = 'solutions' AND section_key = 'list';

-- Home stat blocks
UPDATE rateb_cms_blocks b
INNER JOIN rateb_cms_sections s ON s.id = b.section_id
SET b.title_ar = 'شركات', b.content_ar = '50+'
WHERE s.page_slug = 'home' AND s.section_key = 'stats' AND b.title_en = 'Companies';

UPDATE rateb_cms_blocks b
INNER JOIN rateb_cms_sections s ON s.id = b.section_id
SET b.title_ar = 'مستودعات', b.content_ar = '120+'
WHERE s.page_slug = 'home' AND s.section_key = 'stats' AND b.title_en = 'Warehouses';

UPDATE rateb_cms_blocks b
INNER JOIN rateb_cms_sections s ON s.id = b.section_id
SET b.title_ar = 'معاملات', b.content_ar = '1M+'
WHERE s.page_slug = 'home' AND s.section_key = 'stats' AND b.title_en = 'Transactions';

UPDATE rateb_cms_blocks b
INNER JOIN rateb_cms_sections s ON s.id = b.section_id
SET b.title_ar = 'جاهزية', b.content_ar = '99.9%'
WHERE s.page_slug = 'home' AND s.section_key = 'stats' AND b.title_en = 'Uptime';

-- Home industry blocks
UPDATE rateb_cms_blocks b
INNER JOIN rateb_cms_sections s ON s.id = b.section_id
SET b.title_ar = 'الرعاية الصحية'
WHERE s.page_slug = 'home' AND s.section_key = 'industries' AND b.title_en = 'Healthcare';

UPDATE rateb_cms_blocks b
INNER JOIN rateb_cms_sections s ON s.id = b.section_id
SET b.title_ar = 'طبية'
WHERE s.page_slug = 'home' AND s.section_key = 'industries' AND b.title_en = 'Medical';

UPDATE rateb_cms_blocks b
INNER JOIN rateb_cms_sections s ON s.id = b.section_id
SET b.title_ar = 'مستودعات'
WHERE s.page_slug = 'home' AND s.section_key = 'industries' AND b.title_en = 'Warehousing';

UPDATE rateb_cms_blocks b
INNER JOIN rateb_cms_sections s ON s.id = b.section_id
SET b.title_ar = 'تجارة'
WHERE s.page_slug = 'home' AND s.section_key = 'industries' AND b.title_en = 'Trading';

UPDATE rateb_cms_blocks b
INNER JOIN rateb_cms_sections s ON s.id = b.section_id
SET b.title_ar = 'حكومي'
WHERE s.page_slug = 'home' AND s.section_key = 'industries' AND b.title_en = 'Government';

-- Feature blocks
UPDATE rateb_cms_blocks SET title_ar = 'المشتريات', content_ar = 'طلبات الشراء وعروض الأسعار وأوامر الشراء.' WHERE title_en = 'Procurement' AND block_type = 'feature';
UPDATE rateb_cms_blocks SET title_ar = 'المخزون', content_ar = 'المستودعات والدفعات والتحويلات.' WHERE title_en = 'Inventory' AND block_type = 'feature';
UPDATE rateb_cms_blocks SET title_ar = 'الموردين', content_ar = 'التصنيف ومؤشرات الأداء والتقييمات.' WHERE title_en = 'Suppliers' AND block_type = 'feature';
UPDATE rateb_cms_blocks SET title_ar = 'العقود', content_ar = 'التجديدات والتنبيهات وسير الموافقات.' WHERE title_en = 'Contracts' AND block_type = 'feature';
UPDATE rateb_cms_blocks SET title_ar = 'الأصول', content_ar = 'تتبع دورة الحياة والصيانة.' WHERE title_en = 'Assets' AND block_type = 'feature';
UPDATE rateb_cms_blocks SET title_ar = 'الأجهزة الطبية', content_ar = 'المعايرة والضمان والامتثال.' WHERE title_en = 'Medical Devices' AND block_type = 'feature';
UPDATE rateb_cms_blocks SET title_ar = 'التقارير', content_ar = 'لوحات تنفيذية وتصدير.' WHERE title_en = 'Reports' AND block_type = 'feature';
UPDATE rateb_cms_blocks SET title_ar = 'الإشعارات', content_ar = 'بريد ورسائل وتنبيهات داخلية.' WHERE title_en = 'Notifications' AND block_type = 'feature';
UPDATE rateb_cms_blocks SET title_ar = 'سير العمل', content_ar = 'موافقات متعددة الخطوات.' WHERE title_en = 'Workflows' AND block_type = 'feature';
UPDATE rateb_cms_blocks SET title_ar = 'SaaS متعدد المستأجرين', content_ar = 'عزل المستأجرين وحدود الباقات.' WHERE title_en = 'Multi-Tenant SaaS' AND block_type = 'feature';
UPDATE rateb_cms_blocks SET title_ar = 'الأمان', content_ar = 'صلاحيات وسجلات ومصادقة ثنائية.' WHERE title_en = 'Security' AND block_type = 'feature';
UPDATE rateb_cms_blocks SET title_ar = 'واجهة API', content_ar = 'REST API للتكامل.' WHERE title_en = 'API' AND block_type = 'feature';

-- Solution blocks
UPDATE rateb_cms_blocks SET title_ar = 'الرعاية الصحية', content_ar = 'مشتريات ومخزون للمستشفيات والعيادات.' WHERE title_en = 'Healthcare' AND block_type = 'solution';
UPDATE rateb_cms_blocks SET title_ar = 'شركات طبية', content_ar = 'تتبع الأجهزة وحوكمة الموردين.' WHERE title_en = 'Medical Companies' AND block_type = 'solution';
UPDATE rateb_cms_blocks SET title_ar = 'المستودعات', content_ar = 'تحكم مخزون متعدد المستودعات.' WHERE title_en = 'Warehouses' AND block_type = 'solution';
UPDATE rateb_cms_blocks SET title_ar = 'شركات تجارية', content_ar = 'أتمتة من الشراء للدفع.' WHERE title_en = 'Trading Companies' AND block_type = 'solution';
UPDATE rateb_cms_blocks SET title_ar = 'شركات مقاولات', content_ar = 'دورة حياة العقود والأصول.' WHERE title_en = 'Contracting Companies' AND block_type = 'solution';
UPDATE rateb_cms_blocks SET title_ar = 'جهات حكومية', content_ar = 'سير عمل وتقارير جاهزة للتدقيق.' WHERE title_en = 'Government Entities' AND block_type = 'solution';

-- FAQs
UPDATE rateb_cms_faqs SET
    question_ar = 'ما هو رتب ERP؟',
    answer_ar = 'نظام ERP سحابي للمشتريات والمخزون والعقود وإدارة الأجهزة الطبية.'
WHERE question_en = 'What is RATEB ERP?';

UPDATE rateb_cms_faqs SET
    question_ar = 'هل يدعم العربية؟',
    answer_ar = 'نعم — واجهة عربية كاملة مع دعم الإنجليزية.'
WHERE question_en = 'Is Arabic supported?';

-- Testimonials
UPDATE rateb_cms_testimonials SET
    customer_name_ar = 'أحمد الراشد',
    position_ar = 'مدير المشتريات',
    company_ar = 'شركة الصحة',
    quote_ar = 'رتب ERP غيّر دورة المشتريات لدينا.'
WHERE customer_name_en = 'Ahmed Al-Rashid';

-- Menu labels
UPDATE rateb_cms_menu_items SET label_ar = 'الرئيسية' WHERE url = 'site';
UPDATE rateb_cms_menu_items SET label_ar = 'المميزات' WHERE url = 'site/features';
UPDATE rateb_cms_menu_items SET label_ar = 'الحلول' WHERE url = 'site/solutions';
UPDATE rateb_cms_menu_items SET label_ar = 'الأسعار' WHERE url = 'site/pricing';
UPDATE rateb_cms_menu_items SET label_ar = 'المدونة' WHERE url = 'site/blog';
UPDATE rateb_cms_menu_items SET label_ar = 'اتصل بنا' WHERE url = 'site/contact';
