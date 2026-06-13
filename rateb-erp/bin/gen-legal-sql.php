<?php
declare(strict_types=1);
/** Generate 031_cms_legal_content.sql */

function hx(string $s): string
{
    return strtoupper(bin2hex($s));
}

function u(string $s): string
{
    return "CONVERT(UNHEX('" . hx($s) . "') USING utf8mb4)";
}

$pages = [
    'privacy' => [
        'en' => "RATEB ERP respects your privacy. We collect contact details you submit through demo, contact, and newsletter forms solely to respond to your requests and improve our services.\n\nWe do not sell personal data. Data is stored securely on our servers in accordance with applicable regulations. You may request deletion of your data by contacting us.\n\nThis policy may be updated; continued use of the site constitutes acceptance of the latest version.",
        'ar' => "نظام رتب ERP يحترم خصوصيتك. نجمع بيانات التواصل التي تقدمها عبر نماذج العرض التجريبي والاتصال والنشرة البريدية للرد على طلباتك وتحسين خدماتنا فقط.\n\nلا نبيع البيانات الشخصية. تُخزَّن البيانات بشكل آمن وفق الأنظمة المعمول بها. يمكنك طلب حذف بياناتك عبر التواصل معنا.\n\nقد تُحدَّث هذه السياسة؛ استمرارك في استخدام الموقع يعني موافقتك على النسخة الأحدث.",
    ],
    'terms' => [
        'en' => "By using the RATEB ERP marketing website and requesting a demo, you agree to these terms. The site provides information about our ERP platform; subscription terms are defined in your service agreement.\n\nContent is provided as-is. We may modify features, pricing, and availability. Unauthorized access, scraping, or misuse of the site is prohibited.\n\nSaudi law governs these terms unless otherwise agreed in writing.",
        'ar' => "باستخدامك موقع رتب ERP التسويقي أو طلب عرض تجريبي، فإنك توافق على هذه الشروط. يقدّم الموقع معلومات عن منصة ERP؛ شروط الاشتراك تُحدَّد في اتفاقية الخدمة.\n\nيُقدَّم المحتوى كما هو. قد نعدّل الميزات والأسعار والتوفر. يُحظر الوصول غير المصرح به أو إساءة استخدام الموقع.\n\nتخضع هذه الشروط للأنظمة المعمول بها في المملكة العربية السعودية ما لم يُتفق كتابياً على غير ذلك.",
    ],
    'cookies' => [
        'en' => "We use essential cookies for language preference, theme (light/dark), and session security. Analytics cookies (Google Analytics / GTM) may be used when configured in CMS settings.\n\nYou can control cookies through your browser settings. Disabling essential cookies may affect site functionality.",
        'ar' => "نستخدم ملفات تعريف ارتباط أساسية لتفضيل اللغة والمظهر (فاتح/داكن) وأمان الجلسة. قد تُستخدم ملفات التحليلات (Google Analytics / GTM) عند تفعيلها من إعدادات CMS.\n\nيمكنك التحكم بالملفات من إعدادات المتصفح. تعطيل الملفات الأساسية قد يؤثر على عمل الموقع.",
    ],
];

$out = ["-- RATEB ERP — Default legal page content (UNHEX, phpMyAdmin-safe)", 'SET NAMES utf8mb4;', ''];
foreach ($pages as $slug => $texts) {
    $out[] = "UPDATE rateb_cms_pages SET content_en = " . u($texts['en']) . ", content_ar = " . u($texts['ar'])
        . " WHERE slug = '{$slug}' AND (content_en IS NULL OR content_en = '' OR content_ar IS NULL OR content_ar = '');";
}

$dest = dirname(__DIR__) . '/migrations/031_cms_legal_content.sql';
file_put_contents($dest, implode("\n", $out) . "\n");
echo "Wrote {$dest}\n";
