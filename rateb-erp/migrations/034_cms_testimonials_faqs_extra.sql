-- RATEB ERP — More testimonials & FAQs + support categories
SET NAMES utf8mb4;

INSERT INTO rateb_cms_faqs (category_id, question_en, question_ar, answer_en, answer_ar, sort_order)
SELECT c.id, t.q_en, t.q_ar, t.a_en, t.a_ar, t.ord
FROM rateb_cms_faq_categories c
JOIN (
    SELECT 11 ord, 'Does RATEB ERP work on mobile devices?' q_en,
           'هل يعمل رتب ERP على الجوال؟' q_ar,
           'Yes — the interface is responsive and works on phones and tablets for approvals, lookups, and key workflows.' a_en,
           'نعم — الواجهة متجاوبة وتعمل على الجوال والأجهزة اللوحية للموافقات والاستعلامات والمهام الأساسية.' a_ar,
           'features' cat
    UNION ALL
    SELECT 12, 'How many users can I add to my account?',
           'كم عدد المستخدمين الذي يمكن إضافتهم؟',
           'User limits depend on your plan. You can see your current usage and limit in the customer portal at any time.',
           'حدود المستخدمين تعتمد على باقتك. يمكنك رؤية الاستخدام والحد الحالي من منطقة العميل في أي وقت.',
           'pricing'
    UNION ALL
    SELECT 13, 'Is training and technical support included?',
           'هل التدريب والدعم الفني مشمولان؟',
           'Yes — onboarding guidance, documentation, and support channels are available for all active subscriptions.',
           'نعم — إرشادات البدء، التوثيق، وقنوات الدعم متاحة لجميع الاشتراكات النشطة.',
           'general'
    UNION ALL
    SELECT 14, 'Can I customize approval workflows?',
           'هل يمكن تخصيص مسارات الموافقات؟',
           'Yes — configure multi-step approvals by amount, department, or document type to match your internal policies.',
           'نعم — اضبط موافقات متعددة المراحل حسب المبلغ أو القسم أو نوع المستند وفق سياساتك الداخلية.',
           'features'
    UNION ALL
    SELECT 15, 'Does it integrate with accounting systems?',
           'هل يتكامل مع أنظمة المحاسبة؟',
           'Operational and financial data can be exported for your accounting team; direct integrations depend on your plan and setup.',
           'يمكن تصدير البيانات التشغيلية والمالية لفريق المحاسبة؛ التكامل المباشر يعتمد على باقتك وإعداداتك.',
           'features'
    UNION ALL
    SELECT 16, 'What happens when my trial ends?',
           'ماذا يحدث عند انتهاء التجربة؟',
           'You can choose a paid plan to continue with full access, or your account moves to a limited state until you subscribe.',
           'يمكنك اختيار باقة مدفوعة للاستمرار بصلاحية كاملة، أو يتحول حسابك لوضع محدود حتى الاشتراك.',
           'pricing'
    UNION ALL
    SELECT 17, 'Can I manage medical device compliance?',
           'هل يمكن إدارة امتثال الأجهزة الطبية؟',
           'Yes — track device records, maintenance, certificates, and supplier documentation in one place.',
           'نعم — تتبع سجلات الأجهزة والصيانة والشهادات ووثائق الموردين في مكان واحد.',
           'features'
    UNION ALL
    SELECT 18, 'Is Saudi VAT and e-invoicing supported?',
           'هل يدعم ضريبة القيمة المضافة والفوترة الإلكترونية السعودية؟',
           'RATEB ERP is built for Saudi operations with VAT-ready fields and export formats aligned with local compliance needs.',
           'رتب ERP مبني للعمليات السعودية مع حقول جاهزة للضريبة وصيغ تصدير تتماشى مع متطلبات الامتثال المحلي.',
           'general'
    UNION ALL
    SELECT 19, 'Can departments work in parallel without conflicts?',
           'هل يمكن للأقسام العمل بالتوازي دون تعارض؟',
           'Yes — role-based permissions ensure each team sees only what they need while sharing one live data source.',
           'نعم — الصلاحيات حسب الدور تضمن أن كل فريق يرى ما يحتاجه فقط مع مصدر بيانات واحد محدّث.',
           'security'
    UNION ALL
    SELECT 20, 'How fast can we go live after registration?',
           'ما سرعة التشغيل بعد التسجيل؟',
           'Most teams start with core modules the same day: company setup, users, suppliers, and first purchase orders.',
           'أغلب الفرق تبدأ بالوحدات الأساسية في نفس اليوم: إعداد الشركة، المستخدمين، الموردين، وأول أوامر الشراء.',
           'general'
) t ON c.slug = t.cat
WHERE NOT EXISTS (SELECT 1 FROM rateb_cms_faqs f WHERE f.question_en = t.q_en);

INSERT INTO rateb_cms_testimonials (customer_name_en, customer_name_ar, position_en, position_ar, company_en, company_ar, quote_en, quote_ar, rating, status, sort_order)
SELECT t.n_en, t.n_ar, t.p_en, t.p_ar, t.co_en, t.co_ar, t.q_en, t.q_ar, 5, 'approved', t.ord
FROM (
    SELECT 7 ord,
           'Omar Al-Zahrani' n_en, 'عمر الزهراني' n_ar,
           'Procurement Lead' p_en, 'قائد المشتريات' p_ar,
           'Saudi MedTech' co_en, 'التقنية الطبية السعودية' co_ar,
           'Supplier evaluation and PO history in one screen saved our team hours every week.' q_en,
           'تقييم الموردين وسجل أوامر الشراء في شاشة واحدة وفّر على فريقنا ساعات كل أسبوع.' q_ar
    UNION ALL
    SELECT 8,
           'Reem Al-Shammari', 'ريم الشمري',
           'Quality Manager', 'مديرة الجودة',
           'Care Devices', 'أجهزة الرعاية',
           'Device certificates and maintenance alerts are finally organized and auditable.',
           'شهادات الأجهزة وتنبيهات الصيانة أصبحت منظمة وقابلة للتدقيق أخيراً.'
    UNION ALL
    SELECT 9,
           'Youssef Al-Qahtani', 'يوسف القحطاني',
           'CFO', 'المدير المالي',
           'Unified Logistics', 'اللوجستيات الموحدة',
           'Budget control improved once we linked spending limits to live subscription usage.',
           'تحسّن ضبط الميزانية بعد ربط حدود الإنفاق باستخدام الاشتراك المباشر.'
    UNION ALL
    SELECT 10,
           'Hana Al-Shehri', 'هناء الشهري',
           'Storekeeper', 'أمينة مخزن',
           'Central Pharmacy', 'الصيدلية المركزية',
           'Expiry tracking and batch numbers reduced waste in our pharmacy stores.',
           'تتبع الصلاحية وأرقام الدفعات قلّل الهدر في مخازن الصيدلية.'
    UNION ALL
    SELECT 11,
           'Majed Al-Anazi', 'ماجد العنزي',
           'Project Manager', 'مدير مشاريع',
           'Gulf Contractors', 'مقاولو الخليج',
           'Linking contracts to purchase requests gave us full visibility on project costs.',
           'ربط العقود بطلبات الشراء أعطانا رؤية كاملة لتكاليف المشاريع.'
    UNION ALL
    SELECT 12,
           'Dalal Al-Fahad', 'دلال الفهد',
           'Administration Manager', 'مديرة الإدارة',
           'Govt Supply Unit', 'وحدة التوريد الحكومية',
           'Audit-ready reports and clear permissions made compliance reviews much easier.',
           'تقارير جاهزة للتدقيق وصلاحيات واضحة سهّلت مراجعات الامتثال كثيراً.'
    UNION ALL
    SELECT 13,
           'Turki Al-Malki', 'تركي المالكي',
           'Supply Chain Director', 'مدير سلسلة الإمداد',
           'Horizon Trading', 'أفق التجارة',
           'From requisition to goods receipt, every step is traceable — that changed how we audit.',
           'من طلب الشراء إلى استلام البضاعة، كل خطوة قابلة للتتبع — وهذا غيّر طريقة التدقيق لدينا.'
    UNION ALL
    SELECT 14,
           'Amal Al-Ruwaili', 'أمل الرويلي',
           'HR & Admin', 'الموارد البشرية والإدارة',
           'Nama Clinics', 'عيادات نما',
           'Onboarding new staff with the right roles takes minutes instead of back-and-forth emails.',
           'إعداد الموظفين الجدد بالصلاحيات المناسبة يستغرق دقائق بدل مراسلات متكررة.'
) t
WHERE NOT EXISTS (SELECT 1 FROM rateb_cms_testimonials x WHERE x.customer_name_en = t.n_en);
