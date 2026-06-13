-- RATEB ERP — CMS dedupe: fix triple-run section/block duplicates + charset-safe unique keys
SET NAMES utf8mb4;

-- 1) Remove duplicate sections (keep newest id per page + section_key)
DELETE s FROM rateb_cms_sections s
INNER JOIN (
    SELECT page_slug, section_key, MAX(id) AS keep_id
    FROM rateb_cms_sections
    GROUP BY page_slug, section_key
) k ON s.page_slug = k.page_slug AND s.section_key = k.section_key
WHERE s.id <> k.keep_id;

-- 2) Remove blocks tied to deleted sections
DELETE b FROM rateb_cms_blocks b
LEFT JOIN rateb_cms_sections s ON s.id = b.section_id
WHERE s.id IS NULL;

-- 3) Remove duplicate blocks on same section
DELETE b1 FROM rateb_cms_blocks b1
INNER JOIN rateb_cms_blocks b2
    ON b1.section_id = b2.section_id
    AND b1.block_type = b2.block_type
    AND b1.title_en = b2.title_en
    AND b1.sort_order = b2.sort_order
    AND b1.id < b2.id;

-- 4) Prevent future duplicate sections (ignored if index already exists)
ALTER TABLE rateb_cms_sections ADD UNIQUE KEY uq_cms_section_page_key (page_slug, section_key);

-- 5) Re-apply correct Arabic for home (safe after dedupe)
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

UPDATE rateb_cms_sections SET
    title_ar = 'موثوق من مؤسسات نامية'
WHERE page_slug = 'home' AND section_key = 'stats';

UPDATE rateb_cms_sections SET
    title_ar = 'القطاعات التي نخدمها'
WHERE page_slug = 'home' AND section_key = 'industries';

UPDATE rateb_cms_sections SET
    title_ar = 'ماذا يقول عملاؤنا'
WHERE page_slug = 'home' AND section_key = 'testimonials';

UPDATE rateb_cms_sections SET
    title_ar = 'باقات مرنة'
WHERE page_slug = 'home' AND section_key = 'pricing_preview';

UPDATE rateb_cms_sections SET
    title_ar = 'أحدث المقالات'
WHERE page_slug = 'home' AND section_key = 'latest_articles';

UPDATE rateb_cms_sections SET
    title_ar = 'أسئلة شائعة'
WHERE page_slug = 'home' AND section_key = 'faq_preview';

UPDATE rateb_cms_sections SET
    title_ar = 'جاهز لتحويل عملياتك؟',
    body_ar = 'اطلب عرضاً تجريبياً أو تحدث مع فريقنا اليوم.'
WHERE page_slug = 'home' AND section_key = 'contact_cta';
