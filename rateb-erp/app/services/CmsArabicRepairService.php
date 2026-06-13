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
