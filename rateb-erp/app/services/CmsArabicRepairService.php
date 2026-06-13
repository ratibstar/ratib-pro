<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use PDO;
use Rateb\App\Core\Database;

final class CmsArabicRepairService
{
    /** @return array{updated: int, hero_title: string} */
    public function repair(): array
    {
        $pdo = Database::connection();
        $pdo->exec('SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci');

        $updated = 0;

        /** @param array<string, string> $params */
        $patch = static function (string $sql, array $params) use ($pdo, &$updated): void {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $updated += $stmt->rowCount();
        };

        $homeSections = [
            'hero' => [
                'title_ar' => 'رتب ERP — منصة العمليات الذكية',
                'body_ar' => 'منصة موحدة للمشتريات والمخزون والعقود وإدارة الأجهزة الطبية للقطاع الصحي والمؤسسات.',
            ],
            'erp_overview' => [
                'title_ar' => 'نظرة شاملة على النظام',
                'body_ar' => 'رؤية شاملة للمشتريات والمستودعات والموردين والأصول وسير عمل الامتثال.',
            ],
            'why_rateb' => [
                'title_ar' => 'لماذا رتب ERP',
                'body_ar' => 'مصمم للقطاع الصحي السعودي والمؤسسات مع SaaS متعدد المستأجرين وواجهة عربية وأمان قوي.',
            ],
            'stats' => ['title_ar' => 'موثوق من مؤسسات نامية', 'body_ar' => ''],
            'industries' => ['title_ar' => 'القطاعات التي نخدمها', 'body_ar' => ''],
            'testimonials' => ['title_ar' => 'ماذا يقول عملاؤنا', 'body_ar' => ''],
            'pricing_preview' => ['title_ar' => 'باقات مرنة', 'body_ar' => ''],
            'latest_articles' => ['title_ar' => 'أحدث المقالات', 'body_ar' => ''],
            'faq_preview' => ['title_ar' => 'أسئلة شائعة', 'body_ar' => ''],
            'contact_cta' => [
                'title_ar' => 'جاهز لتحويل عملياتك؟',
                'body_ar' => 'اطلب عرضاً تجريبياً أو تحدث مع فريقنا اليوم.',
            ],
        ];

        foreach ($homeSections as $key => $fields) {
            $patch(
                'UPDATE rateb_cms_sections SET title_ar = :t, body_ar = :b WHERE page_slug = :p AND section_key = :k',
                ['t' => $fields['title_ar'], 'b' => $fields['body_ar'], 'p' => 'home', 'k' => $key]
            );
        }

        $patch('UPDATE rateb_cms_sections SET title_ar = :t WHERE page_slug = :p AND section_key = :k', [
            't' => 'مميزات المنصة', 'p' => 'features', 'k' => 'list',
        ]);
        $patch('UPDATE rateb_cms_sections SET title_ar = :t WHERE page_slug = :p AND section_key = :k', [
            't' => 'حلول حسب القطاع', 'p' => 'solutions', 'k' => 'list',
        ]);

        $menu = [
            'site' => 'الرئيسية',
            'site/features' => 'المميزات',
            'site/solutions' => 'الحلول',
            'site/pricing' => 'الأسعار',
            'site/blog' => 'المدونة',
            'site/contact' => 'اتصل بنا',
        ];
        foreach ($menu as $url => $label) {
            $patch('UPDATE rateb_cms_menu_items SET label_ar = :l WHERE url = :u', ['l' => $label, 'u' => $url]);
        }

        $stats = [
            'Companies' => ['شركات', '50+'],
            'Warehouses' => ['مستودعات', '120+'],
            'Transactions' => ['معاملات', '1M+'],
            'Uptime' => ['جاهزية', '99.9%'],
        ];
        foreach ($stats as $en => [$arTitle, $arVal]) {
            $patch(
                'UPDATE rateb_cms_blocks b
                 INNER JOIN rateb_cms_sections s ON s.id = b.section_id
                 SET b.title_ar = :t, b.content_ar = :c
                 WHERE s.page_slug = :p AND s.section_key = :k AND b.title_en = :e',
                ['t' => $arTitle, 'c' => $arVal, 'p' => 'home', 'k' => 'stats', 'e' => $en]
            );
        }

        $industries = [
            'Healthcare' => 'الرعاية الصحية',
            'Medical' => 'طبية',
            'Warehousing' => 'مستودعات',
            'Trading' => 'تجارة',
            'Government' => 'حكومي',
        ];
        foreach ($industries as $en => $ar) {
            $patch(
                'UPDATE rateb_cms_blocks b
                 INNER JOIN rateb_cms_sections s ON s.id = b.section_id
                 SET b.title_ar = :t
                 WHERE s.page_slug = :p AND s.section_key = :k AND b.title_en = :e',
                ['t' => $ar, 'p' => 'home', 'k' => 'industries', 'e' => $en]
            );
        }

        $features = [
            'Procurement' => ['المشتريات', 'طلبات الشراء وعروض الأسعار وأوامر الشراء.'],
            'Inventory' => ['المخزون', 'المستودعات والدفعات والتحويلات.'],
            'Suppliers' => ['الموردين', 'التصنيف ومؤشرات الأداء والتقييمات.'],
            'Contracts' => ['العقود', 'التجديدات والتنبيهات وسير الموافقات.'],
            'Assets' => ['الأصول', 'تتبع دورة الحياة والصيانة.'],
            'Medical Devices' => ['الأجهزة الطبية', 'المعايرة والضمان والامتثال.'],
            'Reports' => ['التقارير', 'لوحات تنفيذية وتصدير.'],
            'Notifications' => ['الإشعارات', 'بريد ورسائل وتنبيهات داخلية.'],
            'Workflows' => ['سير العمل', 'موافقات متعددة الخطوات.'],
            'Multi-Tenant SaaS' => ['SaaS متعدد المستأجرين', 'عزل المستأجرين وحدود الباقات.'],
            'Security' => ['الأمان', 'صلاحيات وسجلات ومصادقة ثنائية.'],
            'API' => ['واجهة API', 'REST API للتكامل.'],
        ];
        foreach ($features as $en => [$arTitle, $arBody]) {
            $patch(
                'UPDATE rateb_cms_blocks SET title_ar = :t, content_ar = :c WHERE title_en = :e AND block_type = :bt',
                ['t' => $arTitle, 'c' => $arBody, 'e' => $en, 'bt' => 'feature']
            );
        }

        $solutions = [
            'Healthcare' => ['الرعاية الصحية', 'مشتريات ومخزون للمستشفيات والعيادات.'],
            'Medical Companies' => ['شركات طبية', 'تتبع الأجهزة وحوكمة الموردين.'],
            'Warehouses' => ['المستودعات', 'تحكم مخزون متعدد المستودعات.'],
            'Trading Companies' => ['شركات تجارية', 'أتمتة من الشراء للدفع.'],
            'Contracting Companies' => ['شركات مقاولات', 'دورة حياة العقود والأصول.'],
            'Government Entities' => ['جهات حكومية', 'سير عمل وتقارير جاهزة للتدقيق.'],
        ];
        foreach ($solutions as $en => [$arTitle, $arBody]) {
            $patch(
                'UPDATE rateb_cms_blocks SET title_ar = :t, content_ar = :c WHERE title_en = :e AND block_type = :bt',
                ['t' => $arTitle, 'c' => $arBody, 'e' => $en, 'bt' => 'solution']
            );
        }

        $patch(
            'UPDATE rateb_cms_faqs SET question_ar = :q, answer_ar = :a WHERE question_en = :e',
            [
                'q' => 'ما هو رتب ERP؟',
                'a' => 'نظام ERP سحابي للمشتريات والمخزون والعقود وإدارة الأجهزة الطبية.',
                'e' => 'What is RATEB ERP?',
            ]
        );
        $patch(
            'UPDATE rateb_cms_faqs SET question_ar = :q, answer_ar = :a WHERE question_en = :e',
            [
                'q' => 'هل يدعم العربية؟',
                'a' => 'نعم — واجهة عربية كاملة مع دعم الإنجليزية.',
                'e' => 'Is Arabic supported?',
            ]
        );

        $extraFaqs = [
            'How do I get started with RATEB ERP?' => ['كيف أبدأ استخدام رتب ERP؟', 'سجّل من موقعنا، اختر باقة أو ابدأ التجربة المجانية 14 يوماً، ثم أعد ملف شركتك وادعُ فريقك.'],
            'Is there a free trial?' => ['هل يوجد تجربة مجانية؟', 'نعم — كل شركة جديدة تحصل على تجربة 14 يوماً مع الوصول للوحدات الأساسية قبل الاشتراك.'],
            'Can I manage multiple warehouses?' => ['هل يمكن إدارة أكثر من مستودع؟', 'نعم. يدعم رتب ERP مخزوناً متعدد المستودعات مع التحويلات ومستويات المخزون وتقارير حسب الموقع.'],
            'Does it support purchase orders and suppliers?' => ['هل يدعم أوامر الشراء والموردين؟', 'نعم — دورة مشتريات كاملة: سجل الموردين، طلبات الشراء، أوامر الشراء، الاستلام، وسلاسل الموافقات.'],
            'Is my data secure in the cloud?' => ['هل بياناتي آمنة في السحابة؟', 'البيانات مستضافة على بنية آمنة مع اتصالات مشفرة وصلاحيات حسب الدور ونسخ احتياطي دوري.'],
            'Can I export reports and data?' => ['هل يمكن تصدير التقارير والبيانات؟', 'نعم — صدّر التقارير بصيغ شائعة وحمّل البيانات التشغيلية للتدقيق والتحليل.'],
            'Who is RATEB ERP designed for?' => ['لمن صُمم رتب ERP؟', 'مقدمو الرعاية الصحية، الموردون الطبيون، شركات التجارة والمقاولات، المستودعات، والجهات الحكومية في السعودية والخليج.'],
            'Can I upgrade or change my plan later?' => ['هل يمكن ترقية الباقة أو تغييرها لاحقاً؟', 'نعم — رقِّ باقتك في أي وقت من منطقة العميل أو بالتواصل معنا؛ تتغير الحدود حسب اشتراكك الجديد.'],
            'Does RATEB ERP work on mobile devices?' => ['هل يعمل رتب ERP على الجوال؟', 'نعم — الواجهة متجاوبة وتعمل على الجوال والأجهزة اللوحية للموافقات والاستعلامات والمهام الأساسية.'],
            'How many users can I add to my account?' => ['كم عدد المستخدمين الذي يمكن إضافتهم؟', 'حدود المستخدمين تعتمد على باقتك. يمكنك رؤية الاستخدام والحد الحالي من منطقة العميل في أي وقت.'],
            'Is training and technical support included?' => ['هل التدريب والدعم الفني مشمولان؟', 'نعم — إرشادات البدء، التوثيق، وقنوات الدعم متاحة لجميع الاشتراكات النشطة.'],
            'Can I customize approval workflows?' => ['هل يمكن تخصيص مسارات الموافقات؟', 'نعم — اضبط موافقات متعددة المراحل حسب المبلغ أو القسم أو نوع المستند وفق سياساتك الداخلية.'],
            'Does it integrate with accounting systems?' => ['هل يتكامل مع أنظمة المحاسبة؟', 'يمكن تصدير البيانات التشغيلية والمالية لفريق المحاسبة؛ التكامل المباشر يعتمد على باقتك وإعداداتك.'],
            'What happens when my trial ends?' => ['ماذا يحدث عند انتهاء التجربة؟', 'يمكنك اختيار باقة مدفوعة للاستمرار بصلاحية كاملة، أو يتحول حسابك لوضع محدود حتى الاشتراك.'],
            'Can I manage medical device compliance?' => ['هل يمكن إدارة امتثال الأجهزة الطبية؟', 'نعم — تتبع سجلات الأجهزة والصيانة والشهادات ووثائق الموردين في مكان واحد.'],
            'Is Saudi VAT and e-invoicing supported?' => ['هل يدعم ضريبة القيمة المضافة والفوترة الإلكترونية السعودية؟', 'رتب ERP مبني للعمليات السعودية مع حقول جاهزة للضريبة وصيغ تصدير تتماشى مع متطلبات الامتثال المحلي.'],
            'Can departments work in parallel without conflicts?' => ['هل يمكن للأقسام العمل بالتوازي دون تعارض؟', 'نعم — الصلاحيات حسب الدور تضمن أن كل فريق يرى ما يحتاجه فقط مع مصدر بيانات واحد محدّث.'],
            'How fast can we go live after registration?' => ['ما سرعة التشغيل بعد التسجيل؟', 'أغلب الفرق تبدأ بالوحدات الأساسية في نفس اليوم: إعداد الشركة، المستخدمين، الموردين، وأول أوامر الشراء.'],
        ];
        foreach ($extraFaqs as $en => [$qAr, $aAr]) {
            $patch(
                'UPDATE rateb_cms_faqs SET question_ar = :q, answer_ar = :a WHERE question_en = :e',
                ['q' => $qAr, 'a' => $aAr, 'e' => $en]
            );
        }

        $faqCategories = [
            'general' => 'عام',
            'pricing' => 'الباقات والأسعار',
            'features' => 'المميزات',
            'security' => 'الأمان والبيانات',
        ];
        foreach ($faqCategories as $slug => $nameAr) {
            $patch(
                'UPDATE rateb_cms_faq_categories SET name_ar = :n WHERE slug = :s',
                ['n' => $nameAr, 's' => $slug]
            );
        }

        $extraTestimonials = [
            'Sara Al-Otaibi' => ['سارة العتيبي', 'مديرة المستودعات', 'شركة الإمداد الطبي', 'أخيراً أصبح لدينا رؤية فورية للمخزون في جميع الفروع.'],
            'Khalid Al-Harbi' => ['خالد الحربي', 'مدير العمليات', 'الخليج للتجارة', 'موافقات الشراء التي كانت تأخذ أياماً تُنجز الآن خلال ساعات.'],
            'Nora Al-Mutairi' => ['نورة المطيري', 'مديرة المالية', 'حلول الأدوية', 'حدود الاشتراك وتقارير الاستخدام الواضحة تساعدنا على تخطيط الميزانية بدقة.'],
            'Faisal Al-Ghamdi' => ['فيصل الغامدي', 'مدير تقنية المعلومات', 'المستشفى الوطني', 'الواجهة العربية والصلاحيات جعلت التطبيق سلساً لكل الأقسام.'],
            'Layla Al-Dosari' => ['ليلى الدوسري', 'مسؤولة العقود', 'بناء كورب', 'ربط العقود بالمشتريات أزال التكرار في العمل بالكامل.'],
            'Omar Al-Zahrani' => ['عمر الزهراني', 'قائد المشتريات', 'التقنية الطبية السعودية', 'تقييم الموردين وسجل أوامر الشراء في شاشة واحدة وفّر على فريقنا ساعات كل أسبوع.'],
            'Reem Al-Shammari' => ['ريم الشمري', 'مديرة الجودة', 'أجهزة الرعاية', 'شهادات الأجهزة وتنبيهات الصيانة أصبحت منظمة وقابلة للتدقيق أخيراً.'],
            'Youssef Al-Qahtani' => ['يوسف القحطاني', 'المدير المالي', 'اللوجستيات الموحدة', 'تحسّن ضبط الميزانية بعد ربط حدود الإنفاق باستخدام الاشتراك المباشر.'],
            'Hana Al-Shehri' => ['هناء الشهري', 'أمينة مخزن', 'الصيدلية المركزية', 'تتبع الصلاحية وأرقام الدفعات قلّل الهدر في مخازن الصيدلية.'],
            'Majed Al-Anazi' => ['ماجد العنزي', 'مدير مشاريع', 'مقاولو الخليج', 'ربط العقود بطلبات الشراء أعطانا رؤية كاملة لتكاليف المشاريع.'],
            'Dalal Al-Fahad' => ['دلال الفهد', 'مديرة الإدارة', 'وحدة التوريد الحكومية', 'تقارير جاهزة للتدقيق وصلاحيات واضحة سهّلت مراجعات الامتثال كثيراً.'],
            'Turki Al-Malki' => ['تركي المالكي', 'مدير سلسلة الإمداد', 'أفق التجارة', 'من طلب الشراء إلى استلام البضاعة، كل خطوة قابلة للتتبع — وهذا غيّر طريقة التدقيق لدينا.'],
            'Amal Al-Ruwaili' => ['أمل الرويلي', 'الموارد البشرية والإدارة', 'عيادات نما', 'إعداد الموظفين الجدد بالصلاحيات المناسبة يستغرق دقائق بدل مراسلات متكررة.'],
        ];
        foreach ($extraTestimonials as $en => [$n, $pos, $co, $q]) {
            $patch(
                'UPDATE rateb_cms_testimonials SET customer_name_ar = :n, position_ar = :pos, company_ar = :co, quote_ar = :q
                 WHERE customer_name_en = :e',
                ['n' => $n, 'pos' => $pos, 'co' => $co, 'q' => $q, 'e' => $en]
            );
        }

        $patch(
            'UPDATE rateb_cms_testimonials SET customer_name_ar = :n, position_ar = :pos, company_ar = :co, quote_ar = :q
             WHERE customer_name_en = :e',
            [
                'n' => 'أحمد الراشد',
                'pos' => 'مدير المشتريات',
                'co' => 'شركة الصحة',
                'q' => 'رتب ERP غيّر دورة المشتريات لدينا.',
                'e' => 'Ahmed Al-Rashid',
            ]
        );

        $heroTitle = '';
        $stmt = $pdo->query("SELECT title_ar FROM rateb_cms_sections WHERE page_slug='home' AND section_key='hero' LIMIT 1");
        if ($stmt !== false) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt->closeCursor();
            $heroTitle = is_array($row) ? (string) ($row['title_ar'] ?? '') : '';
        }

        return ['updated' => $updated, 'hero_title' => $heroTitle];
    }
}
