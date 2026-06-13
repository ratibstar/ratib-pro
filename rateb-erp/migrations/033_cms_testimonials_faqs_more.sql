-- RATEB ERP — Additional testimonials & FAQs for marketing home
SET NAMES utf8mb4;

INSERT INTO rateb_cms_faq_categories (slug, name_en, name_ar, sort_order) VALUES
('pricing', 'Pricing & Plans', 'الباقات والأسعار', 2),
('features', 'Features', 'المميزات', 3),
('security', 'Security & Data', 'الأمان والبيانات', 4)
ON DUPLICATE KEY UPDATE name_en = VALUES(name_en), name_ar = VALUES(name_ar);

INSERT INTO rateb_cms_faqs (category_id, question_en, question_ar, answer_en, answer_ar, sort_order)
SELECT c.id, t.q_en, t.q_ar, t.a_en, t.a_ar, t.ord
FROM rateb_cms_faq_categories c
JOIN (
    SELECT 3 ord, 'How do I get started with RATEB ERP?' q_en,
           'كيف أبدأ استخدام رتب ERP؟' q_ar,
           'Register on our website, choose a plan or start the 14-day trial, then set up your company profile and invite your team.' a_en,
           'سجّل من موقعنا، اختر باقة أو ابدأ التجربة المجانية 14 يوماً، ثم أعد ملف شركتك وادعُ فريقك.' a_ar,
           'general' cat
    UNION ALL
    SELECT 4, 'Is there a free trial?',
           'هل يوجد تجربة مجانية؟',
           'Yes — every new company gets a 14-day trial with access to core modules so you can evaluate the system before subscribing.',
           'نعم — كل شركة جديدة تحصل على تجربة 14 يوماً مع الوصول للوحدات الأساسية قبل الاشتراك.',
           'pricing'
    UNION ALL
    SELECT 5, 'Can I manage multiple warehouses?',
           'هل يمكن إدارة أكثر من مستودع؟',
           'Yes. RATEB ERP supports multi-warehouse inventory, transfers, stock levels, and location-based reporting.',
           'نعم. يدعم رتب ERP مخزوناً متعدد المستودعات مع التحويلات ومستويات المخزون وتقارير حسب الموقع.',
           'features'
    UNION ALL
    SELECT 6, 'Does it support purchase orders and suppliers?',
           'هل يدعم أوامر الشراء والموردين؟',
           'Yes — full procurement workflow: supplier records, purchase requests, purchase orders, receiving, and approval chains.',
           'نعم — دورة مشتريات كاملة: سجل الموردين، طلبات الشراء، أوامر الشراء، الاستلام، وسلاسل الموافقات.',
           'features'
    UNION ALL
    SELECT 7, 'Is my data secure in the cloud?',
           'هل بياناتي آمنة في السحابة؟',
           'Data is hosted on secure infrastructure with encrypted connections, role-based access, and regular backups.',
           'البيانات مستضافة على بنية آمنة مع اتصالات مشفرة وصلاحيات حسب الدور ونسخ احتياطي دوري.',
           'security'
    UNION ALL
    SELECT 8, 'Can I export reports and data?',
           'هل يمكن تصدير التقارير والبيانات؟',
           'Yes — export reports to common formats and download operational data for audit and analysis.',
           'نعم — صدّر التقارير بصيغ شائعة وحمّل البيانات التشغيلية للتدقيق والتحليل.',
           'features'
    UNION ALL
    SELECT 9, 'Who is RATEB ERP designed for?',
           'لمن صُمم رتب ERP؟',
           'Healthcare providers, medical suppliers, trading and contracting companies, warehouses, and government entities in Saudi Arabia and the GCC.',
           'مقدمو الرعاية الصحية، الموردون الطبيون، شركات التجارة والمقاولات، المستودعات، والجهات الحكومية في السعودية والخليج.',
           'general'
    UNION ALL
    SELECT 10, 'Can I upgrade or change my plan later?',
           'هل يمكن ترقية الباقة أو تغييرها لاحقاً؟',
           'Yes — upgrade your plan anytime from the customer portal or by contacting our team; limits adjust to your new subscription.',
           'نعم — رقِّ باقتك في أي وقت من منطقة العميل أو بالتواصل معنا؛ تتغير الحدود حسب اشتراكك الجديد.',
           'pricing'
) t ON c.slug = t.cat
WHERE NOT EXISTS (SELECT 1 FROM rateb_cms_faqs f WHERE f.question_en = t.q_en);

INSERT INTO rateb_cms_testimonials (customer_name_en, customer_name_ar, position_en, position_ar, company_en, company_ar, quote_en, quote_ar, rating, status, sort_order)
SELECT t.n_en, t.n_ar, t.p_en, t.p_ar, t.co_en, t.co_ar, t.q_en, t.q_ar, 5, 'approved', t.ord
FROM (
    SELECT 2 ord,
           'Sara Al-Otaibi' n_en, 'سارة العتيبي' n_ar,
           'Warehouse Manager' p_en, 'مديرة المستودعات' p_ar,
           'MedSupply Co' co_en, 'شركة الإمداد الطبي' co_ar,
           'We finally have real-time stock visibility across all branches.' q_en,
           'أخيراً أصبح لدينا رؤية فورية للمخزون في جميع الفروع.' q_ar
    UNION ALL
    SELECT 3,
           'Khalid Al-Harbi', 'خالد الحربي',
           'Operations Manager', 'مدير العمليات',
           'Gulf Trading', 'الخليج للتجارة',
           'Purchase approvals that used to take days now finish in hours.',
           'موافقات الشراء التي كانت تأخذ أياماً تُنجز الآن خلال ساعات.'
    UNION ALL
    SELECT 4,
           'Nora Al-Mutairi', 'نورة المطيري',
           'Finance Director', 'مديرة المالية',
           'Pharma Solutions', 'حلول الأدوية',
           'Clear subscription limits and usage reports help us plan budgets accurately.',
           'حدود الاشتراك وتقارير الاستخدام الواضحة تساعدنا على تخطيط الميزانية بدقة.'
    UNION ALL
    SELECT 5,
           'Faisal Al-Ghamdi', 'فيصل الغامدي',
           'IT Manager', 'مدير تقنية المعلومات',
           'National Hospital', 'المستشفى الوطني',
           'Arabic interface and role permissions made rollout smooth for every department.',
           'الواجهة العربية والصلاحيات جعلت التطبيق سلساً لكل الأقسام.'
    UNION ALL
    SELECT 6,
           'Layla Al-Dosari', 'ليلى الدوسري',
           'Contracts Officer', 'مسؤولة العقود',
           'BuildCorp', 'بناء كورب',
           'Contract tracking linked to procurement removed duplicate work completely.',
           'ربط العقود بالمشتريات أزال التكرار في العمل بالكامل.'
) t
WHERE NOT EXISTS (SELECT 1 FROM rateb_cms_testimonials x WHERE x.customer_name_en = t.n_en);
