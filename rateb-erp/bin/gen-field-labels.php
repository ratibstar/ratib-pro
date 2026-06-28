<?php
declare(strict_types=1);
/** One-off generator for config/field-labels-{en,ar}.php — run from rateb-erp/: php bin/gen-field-labels.php */
$root = dirname(__DIR__);
$enMain = require $root . '/config/lang/en.php';
$labels = [];
foreach (['app', 'views'] as $dir) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $dir));
    foreach ($it as $f) {
        if ($f->getExtension() !== 'php') {
            continue;
        }
        $c = file_get_contents($f->getPathname());
        preg_match_all("/'label'\s*=>\s*'([^']+)'/", $c, $m);
        foreach ($m[1] as $k) {
            $labels[$k] = true;
        }
        preg_match_all("/\['name'\s*=>\s*'([^']+)'/", $c, $m2);
        foreach ($m2[1] as $k) {
            $labels[$k] = true;
        }
    }
}

$arParts = [
    'holiday' => 'العطلة', 'date' => 'التاريخ', 'title' => 'العنوان', 'name' => 'الاسم', 'template' => 'القالب',
    'meta' => 'SEO', 'description' => 'الوصف', 'section' => 'القسم', 'page' => 'الصفحة', 'slug' => 'المعرّف',
    'block' => 'الكتلة', 'type' => 'النوع', 'sort' => 'ترتيب', 'order' => 'الترتيب', 'active' => 'نشط',
    'recurring' => 'متكرر', 'visible' => 'ظاهر', 'published' => 'النشر', 'employee' => 'الموظف',
    'department' => 'القسم', 'branch' => 'الفرع', 'company' => 'الشركة', 'supplier' => 'المورد',
    'contract' => 'العقد', 'asset' => 'الأصل', 'device' => 'الجهاز', 'invoice' => 'الفاتورة',
    'amount' => 'المبلغ', 'status' => 'الحالة', 'code' => 'الرمز', 'no' => 'الرقم', 'number' => 'الرقم',
    'start' => 'البداية', 'end' => 'النهاية', 'from' => 'من', 'to' => 'إلى', 'total' => 'الإجمالي',
    'cost' => 'التكلفة', 'value' => 'القيمة', 'image' => 'الصورة', 'path' => 'المسار', 'url' => 'الرابط',
    'icon' => 'الأيقونة', 'body' => 'المحتوى', 'content' => 'المحتوى', 'excerpt' => 'المقتطف',
    'subtitle' => 'العنوان الفرعي', 'summary' => 'الملخص', 'message' => 'الرسالة', 'notes' => 'ملاحظات',
    'location' => 'الموقع', 'address' => 'العنوان', 'phone' => 'الهاتف', 'email' => 'البريد',
    'category' => 'التصنيف', 'parent' => 'الأب', 'menu' => 'القائمة', 'segment' => 'الشريحة',
    'channel' => 'القناة', 'method' => 'الطريقة', 'default' => 'افتراضي', 'issue' => 'الإصدار',
    'renewal' => 'التجديد', 'transfer' => 'التحويل', 'warehouse' => 'المستودع', 'inventory' => 'المخزون',
    'maintenance' => 'الصيانة', 'manufacturer' => 'الشركة المصنعة', 'model' => 'الموديل', 'serial' => 'التسلسل',
    'ticket' => 'التذكرة', 'voucher' => 'السند', 'period' => 'الفترة', 'year' => 'السنة', 'month' => 'الشهر',
    'day' => 'اليوم', 'time' => 'الوقت', 'score' => 'الدرجة', 'rating' => 'التقييم', 'percent' => 'النسبة',
    'tax' => 'الضريبة', 'discount' => 'الخصم', 'shipping' => 'الشحن', 'line' => 'البند', 'unit' => 'الوحدة',
    'quantity' => 'الكمية', 'source' => 'المصدر', 'destination' => 'الوجهة', 'dest' => 'الوجهة',
    'assigned' => 'المُسند', 'approval' => 'الاعتماد', 'manager' => 'المدير', 'responsible' => 'المسؤول',
    'response' => 'الرد', 'question' => 'السؤال', 'answer' => 'الجواب', 'quote' => 'الاقتباس',
    'bio' => 'النبذة', 'position' => 'المنصب', 'photo' => 'الصورة', 'logo' => 'الشعار', 'website' => 'الموقع',
    'video' => 'الفيديو', 'canonical' => 'Canonical', 'og' => 'Open Graph', 'twitter' => 'Twitter',
    'cta' => 'CTA', 'label' => 'التسمية', 'links' => 'الروابط', 'lines' => 'الأسطر', 'component' => 'المكوّن',
    'plan' => 'الباقة', 'price' => 'السعر', 'monthly' => 'شهري', 'yearly' => 'سنوي', 'storage' => 'التخزين',
    'users' => 'المستخدمين', 'max' => 'الحد', 'permission' => 'الصلاحية', 'count' => 'العدد', 'locale' => 'اللغة',
    'roles' => 'الأدوار', 'list' => 'القائمة', 'display' => 'العرض', 'subject' => 'الموضوع', 'html' => 'HTML',
    'closing' => 'الإغلاق', 'opening' => 'الافتتاح', 'publish' => 'النشر', 'service' => 'الخدمة',
    'estimated' => 'المقدّر', 'approved' => 'المعتمد', 'accumulated' => 'المتراكم', 'avg' => 'المتوسط',
    'eval' => 'التقييم', 'tier' => 'المستوى', 'customs' => 'الجمرك', 'broker' => 'الوسيط', 'clearance' => 'التخليص',
    'declaration' => 'البيان', 'customer' => 'العميل', 'loan' => 'القرض', 'leave' => 'الإجازة', 'job' => 'الوظيفة',
    'hire' => 'التعيين', 'check' => 'التحقق', 'in' => 'الدخول', 'out' => 'الخروج', 'attendance' => 'الحضور',
    'payroll' => 'الرواتب', 'fleet' => 'الأسطول', 'vehicle' => 'المركبة', 'assignment' => 'التسليم',
    'depreciation' => 'الإهلاك', 'calibration' => 'المعايرة', 'due' => 'الاستحقاق', 'deadline' => 'الموعد',
    'completed' => 'الإكمال', 'follow' => 'المتابعة', 'up' => '', 'priority' => 'الأولوية', 'comm' => 'التواصل',
    'rfq' => 'طلب عرض', 'po' => 'أمر شراء', 'purchase' => 'الشراء', 'item' => 'الصنف', 'sku' => 'SKU',
    'barcode' => 'الباركود', 'batch' => 'الدفعة', 'expiry' => 'الانتهاء', 'audit' => 'الجرد', 'movement' => 'الحركة',
    'journal' => 'اليومية', 'entry' => 'القيد', 'voucher' => 'السند', 'fiscal' => 'المالي', 'bank' => 'البنك',
    'account' => 'الحساب', 'counter' => 'المقابل', 'debit' => 'مدين', 'credit' => 'دائن', 'balance' => 'الرصيد',
    'en' => 'إنجليزي', 'ar' => 'عربي', 'id' => '', 'key' => 'المفتاح', 'details' => 'التفاصيل', 'device' => 'الجهاز',
    'manufacturer' => 'الشركة المصنعة', 'item' => 'الصنف', 'closing' => 'الإغلاق', 'start' => 'البداية',
    'publish' => 'النشر', 'serial' => 'التسلسل', 'storage' => 'التخزين', 'mb' => 'MB', 'ticket' => 'التذكرة',
    'message' => 'الرسالة', 'html' => 'HTML', 'body' => 'المحتوى',
];

function humanizeEn(string $key): string
{
    if (preg_match('/^(.+)_(en|ar)$/', $key, $m)) {
        $lang = strtoupper($m[2]);
        return humanizeEn($m[1]) . ' (' . $lang . ')';
    }
    return ucwords(str_replace('_', ' ', $key));
}

function humanizeAr(string $key, array $parts): string
{
    if (preg_match('/^(.+)_(en|ar)$/', $key, $m)) {
        $lang = $m[2] === 'en' ? 'إنجليزي' : 'عربي';
        return humanizeAr($m[1], $parts) . ' (' . $lang . ')';
    }
    $segs = explode('_', $key);
    $out = [];
    foreach ($segs as $seg) {
        if ($seg === 'id' || $seg === '') {
            continue;
        }
        $out[] = $parts[$seg] ?? $seg;
    }
    return trim(implode(' ', array_filter($out))) ?: $key;
}

$enOut = [];
$arOut = [];
foreach (array_keys($labels) as $raw) {
    $key = strtolower(str_replace([' ', '-'], '_', trim($raw)));
    if ($key === '' || $key === '—' || isset($enMain[$key])) {
        continue;
    }
    $enOut[$key] = humanizeEn($key);
    $arOut[$key] = humanizeAr($key, $arParts);
}

ksort($enOut);
ksort($arOut);

$write = static function (string $path, array $data): void {
    $lines = ["<?php", "declare(strict_types=1);", "", "/** Auto-generated field labels — used by rateb_label() */", "return ["];
    foreach ($data as $k => $v) {
        $lines[] = "    " . var_export($k, true) . ' => ' . var_export($v, true) . ',';
    }
    $lines[] = '];';
    $lines[] = '';
    file_put_contents($path, implode("\n", $lines));
};

$write($root . '/config/field-labels-en.php', $enOut);
$write($root . '/config/field-labels-ar.php', $arOut);
echo 'Generated ' . count($enOut) . " field labels\n";
