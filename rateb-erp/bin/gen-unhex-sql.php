<?php
declare(strict_types=1);
/** Generate 029_cms_arabic_unhex_fix.sql — safe for phpMyAdmin paste (ASCII-only). */

function hx(string $s): string
{
    return strtoupper(bin2hex($s));
}

function u(string $s): string
{
    if ($s === '') {
        return "''";
    }
    return "CONVERT(UNHEX('" . hx($s) . "') USING utf8mb4)";
}

$out = [];
$out[] = '-- RATEB ERP — Arabic repair via UNHEX (phpMyAdmin-safe; no UTF-8 paste needed)';
$out[] = 'SET NAMES utf8mb4;';
$out[] = '';

$homeSections = [
    'hero' => ['title_ar' => 'رتب ERP — منصة العمليات الذكية', 'body_ar' => 'منصة موحدة للمشتريات والمخزون والعقود وإدارة الأجهزة الطبية للقطاع الصحي والمؤسسات.'],
    'erp_overview' => ['title_ar' => 'نظرة شاملة على النظام', 'body_ar' => 'رؤية شاملة للمشتريات والمستودعات والموردين والأصول وسير عمل الامتثال.'],
    'why_rateb' => ['title_ar' => 'لماذا رتب ERP', 'body_ar' => 'مصمم للقطاع الصحي السعودي والمؤسسات مع SaaS متعدد المستأجرين وواجهة عربية وأمان قوي.'],
    'stats' => ['title_ar' => 'موثوق من مؤسسات نامية', 'body_ar' => ''],
    'industries' => ['title_ar' => 'القطاعات التي نخدمها', 'body_ar' => ''],
    'testimonials' => ['title_ar' => 'ماذا يقول عملاؤنا', 'body_ar' => ''],
    'pricing_preview' => ['title_ar' => 'باقات مرنة', 'body_ar' => ''],
    'latest_articles' => ['title_ar' => 'أحدث المقالات', 'body_ar' => ''],
    'faq_preview' => ['title_ar' => 'أسئلة شائعة', 'body_ar' => ''],
    'contact_cta' => ['title_ar' => 'جاهز لتحويل عملياتك؟', 'body_ar' => 'اطلب عرضاً تجريبياً أو تحدث مع فريقنا اليوم.'],
];

foreach ($homeSections as $key => $fields) {
    $out[] = "UPDATE rateb_cms_sections SET title_ar = " . u($fields['title_ar']) . ", body_ar = " . u($fields['body_ar'])
        . " WHERE page_slug = 'home' AND section_key = '{$key}';";
}

$out[] = "UPDATE rateb_cms_sections SET title_ar = " . u('مميزات المنصة') . " WHERE page_slug = 'features' AND section_key = 'list';";
$out[] = "UPDATE rateb_cms_sections SET title_ar = " . u('حلول حسب القطاع') . " WHERE page_slug = 'solutions' AND section_key = 'list';";
$out[] = '';

$menu = [
    'site' => 'الرئيسية',
    'site/features' => 'المميزات',
    'site/solutions' => 'الحلول',
    'site/pricing' => 'الأسعار',
    'site/blog' => 'المدونة',
    'site/contact' => 'اتصل بنا',
];
foreach ($menu as $url => $label) {
    $out[] = "UPDATE rateb_cms_menu_items SET label_ar = " . u($label) . " WHERE url = '{$url}';";
}
$out[] = '';

$stats = [
    'Companies' => ['شركات', '50+'],
    'Warehouses' => ['مستودعات', '120+'],
    'Transactions' => ['معاملات', '1M+'],
    'Uptime' => ['جاهزية', '99.9%'],
];
foreach ($stats as $en => [$t, $c]) {
    $out[] = "UPDATE rateb_cms_blocks b INNER JOIN rateb_cms_sections s ON s.id = b.section_id"
        . " SET b.title_ar = " . u($t) . ", b.content_ar = " . u($c)
        . " WHERE s.page_slug = 'home' AND s.section_key = 'stats' AND b.title_en = '{$en}';";
}
$out[] = '';

$industries = [
    'Healthcare' => 'الرعاية الصحية',
    'Medical' => 'طبية',
    'Warehousing' => 'مستودعات',
    'Trading' => 'تجارة',
    'Government' => 'حكومي',
];
foreach ($industries as $en => $t) {
    $out[] = "UPDATE rateb_cms_blocks b INNER JOIN rateb_cms_sections s ON s.id = b.section_id"
        . " SET b.title_ar = " . u($t)
        . " WHERE s.page_slug = 'home' AND s.section_key = 'industries' AND b.title_en = '{$en}';";
}
$out[] = '';

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
foreach ($features as $en => [$t, $c]) {
    $out[] = "UPDATE rateb_cms_blocks SET title_ar = " . u($t) . ", content_ar = " . u($c)
        . " WHERE title_en = '{$en}' AND block_type = 'feature';";
}
$out[] = '';

$solutions = [
    'Healthcare' => ['الرعاية الصحية', 'مشتريات ومخزون للمستشفيات والعيادات.'],
    'Medical Companies' => ['شركات طبية', 'تتبع الأجهزة وحوكمة الموردين.'],
    'Warehouses' => ['المستودعات', 'تحكم مخزون متعدد المستودعات.'],
    'Trading Companies' => ['شركات تجارية', 'أتمتة من الشراء للدفع.'],
    'Contracting Companies' => ['شركات مقاولات', 'دورة حياة العقود والأصول.'],
    'Government Entities' => ['جهات حكومية', 'سير عمل وتقارير جاهزة للتدقيق.'],
];
foreach ($solutions as $en => [$t, $c]) {
    $out[] = "UPDATE rateb_cms_blocks SET title_ar = " . u($t) . ", content_ar = " . u($c)
        . " WHERE title_en = '{$en}' AND block_type = 'solution';";
}
$out[] = '';

$out[] = "UPDATE rateb_cms_faqs SET question_ar = " . u('ما هو رتب ERP؟') . ", answer_ar = " . u('نظام ERP سحابي للمشتريات والمخزون والعقود وإدارة الأجهزة الطبية.')
    . " WHERE question_en = 'What is RATEB ERP?';";
$out[] = "UPDATE rateb_cms_faqs SET question_ar = " . u('هل يدعم العربية؟') . ", answer_ar = " . u('نعم — واجهة عربية كاملة مع دعم الإنجليزية.')
    . " WHERE question_en = 'Is Arabic supported?';";
$out[] = '';

$out[] = "UPDATE rateb_cms_testimonials SET customer_name_ar = " . u('أحمد الراشد')
    . ", position_ar = " . u('مدير المشتريات')
    . ", company_ar = " . u('شركة الصحة')
    . ", quote_ar = " . u('رتب ERP غيّر دورة المشتريات لدينا.')
    . " WHERE customer_name_en = 'Ahmed Al-Rashid';";

$dest = dirname(__DIR__) . '/migrations/029_cms_arabic_unhex_fix.sql';
file_put_contents($dest, implode("\n", $out) . "\n");
echo "Wrote {$dest} (" . count($out) . " lines)\n";
