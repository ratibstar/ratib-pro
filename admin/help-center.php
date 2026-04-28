<?php
/**
 * EN: Handles system administration/observability module behavior in `admin/help-center.php`.
 * AR: يدير سلوك وحدة إدارة النظام والمراقبة في `admin/help-center.php`.
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!defined('ADMIN_CONTROL_MODE')) {
    define('ADMIN_CONTROL_MODE', true);
}

function hcEnabled(): bool
{
    if (defined('DEBUG_MODE') && DEBUG_MODE === true) {
        return true;
    }
    if (defined('ADMIN_CONTROL_CENTER_ENABLED') && ADMIN_CONTROL_CENTER_ENABLED === true) {
        return true;
    }
    $env = getenv('ADMIN_CONTROL_CENTER_ENABLED');
    if ($env !== false) {
        $env = strtolower(trim((string) $env));
        return in_array($env, ['1', 'true', 'on', 'yes'], true);
    }
    return true;
}

function hcIsAdmin(): bool
{
    if (class_exists('Auth') && Auth::isSuperAdmin()) {
        return true;
    }
    if (!empty($_SESSION['control_logged_in'])) {
        $username = strtolower(trim((string) ($_SESSION['control_username'] ?? '')));
        if ($username === 'admin') {
            return true;
        }
    }
    $hasProgramSession = function_exists('ratib_program_session_is_valid_user')
        ? ratib_program_session_is_valid_user()
        : (!empty($_SESSION['logged_in']) && (int) ($_SESSION['user_id'] ?? 0) > 0);
    if ($hasProgramSession) {
        $roleId = (int) ($_SESSION['role_id'] ?? 0);
        $roleName = strtolower(trim((string) ($_SESSION['role'] ?? '')));
        return $roleId === 1 || $roleId === 2 || strpos($roleName, 'admin') !== false;
    }
    return false;
}

if (!hcEnabled() || !hcIsAdmin()) {
    http_response_code(403);
    exit('403 Forbidden');
}

$lang = strtolower(trim((string) ($_GET['lang'] ?? 'en')));
if (!in_array($lang, ['en', 'ar'], true)) {
    $lang = 'en';
}
function hcGuideCandidates(string $lang): array
{
    if ($lang === 'ar') {
        return [
            __DIR__ . '/CONTROL_CENTER_PRO_CHECKLIST.ar.md',
            __DIR__ . '/control_center_pro_checklist.ar.md',
            __DIR__ . '/CONTROL_CENTER_PRO_CHECKLIST.md',
            __DIR__ . '/control_center_pro_checklist.md',
        ];
    }
    return [
        __DIR__ . '/CONTROL_CENTER_PRO_CHECKLIST.md',
        __DIR__ . '/control_center_pro_checklist.md',
        __DIR__ . '/CONTROL_CENTER_PRO_CHECKLIST.ar.md',
        __DIR__ . '/control_center_pro_checklist.ar.md',
    ];
}

function hcLoadGuideFromDirectory(string $lang): ?string
{
    $entries = @scandir(__DIR__);
    if (!is_array($entries)) {
        return null;
    }
    foreach ($entries as $entry) {
        $name = strtolower((string) $entry);
        if ($name === '' || substr($name, -3) !== '.md') {
            continue;
        }
        if (strpos($name, 'control_center_pro_checklist') === false) {
            continue;
        }
        if ($lang === 'ar' && strpos($name, '.ar.md') === false) {
            continue;
        }
        if ($lang === 'en' && strpos($name, '.ar.md') !== false) {
            continue;
        }
        $path = __DIR__ . DIRECTORY_SEPARATOR . $entry;
        if (is_file($path) && is_readable($path)) {
            return $path;
        }
    }
    return null;
}

function hcLoadGuide(string $lang): string
{
    $candidates = hcGuideCandidates($lang);
    $dirFound = hcLoadGuideFromDirectory($lang);
    if (is_string($dirFound) && $dirFound !== '') {
        array_unshift($candidates, $dirFound);
    }
    foreach (array_unique($candidates) as $path) {
        if (is_file($path) && is_readable($path)) {
            $content = (string) file_get_contents($path);
            if (trim($content) !== '') {
                return $content;
            }
        }
    }

    // Built-in fallback so Help Center never appears empty.
    if ($lang === 'ar') {
        return "# مركز المساعدة - دليل احترافي\n\n## قائمة التنقل\n\n- [ ] [1) الوصول وقواعد الأمان](#1-الوصول-وقواعد-الأمان)\n- [ ] [2) لوحة الملخص](#2-لوحة-الملخص)\n- [ ] [3) إدارة المستأجرين](#3-إدارة-المستأجرين)\n- [ ] [4) لوحة قاعدة البيانات](#4-لوحة-قاعدة-البيانات)\n- [ ] [5) وحدة تنفيذ الاستعلامات](#5-وحدة-تنفيذ-الاستعلامات)\n- [ ] [6) سياسات البوابة](#6-سياسات-البوابة)\n- [ ] [7) أحداث الأمان](#7-أحداث-الأمان)\n- [ ] [8) مستكشف الأحداث](#8-مستكشف-الأحداث)\n- [ ] [9) أدوات الطوارئ](#9-أدوات-الطوارئ)\n- [ ] [10) قائمة تشغيل يومية](#10-قائمة-تشغيل-يومية)\n\n## 1) الوصول وقواعد الأمان\n\n- افتح `admin/control-center.php`.\n- تأكد من حساب إداري.\n- اعتبر كل إجراء كتابي إجراء حساس.\n\n## 2) لوحة الملخص\n\n- راقب عدد المستأجرين.\n- راقب وضع النظام (`SAFE` / `STRICT`).\n- راقب مؤشرات الأمان.\n\n## 3) إدارة المستأجرين\n\n- إنشاء مستأجر جديد.\n- تعديل بيانات المستأجر.\n- إيقاف/تفعيل.\n- حذف بعد التأكد.\n\n## 4) لوحة قاعدة البيانات\n\n- `Test Connection` لفحص الاتصال.\n- `Run Migration` لتنفيذ/تسجيل الترحيل.\n- `Rebuild Schema` إجراء خطير يتطلب تأكيد كتابة.\n\n## 5) وحدة تنفيذ الاستعلامات\n\n- استخدم `SAFE` للاستعلامات القرائية.\n- استخدم `SYSTEM` للاستعلامات الكتابية فقط.\n- راجع الاستعلام قبل التنفيذ.\n\n## 6) سياسات البوابة\n\n- راقب السماح/المنع/التحذير.\n- استخدم الفلاتر حسب المستأجر والحالة.\n\n## 7) أحداث الأمان\n\n- راقب التحذيرات المتكررة.\n- اربط الأحداث بتيار الأحداث وسياسات البوابة.\n\n## 8) مستكشف الأحداث\n\n- صفِّ حسب المستوى والكلمة المفتاحية ورقم المستأجر.\n- راجع الأحداث بعد كل عملية عالية التأثير.\n\n## 9) أدوات الطوارئ\n\n- استخدمها للحوادث فقط.\n- نفذ التأكيد المزدوج قبل التنفيذ.\n\n## 10) قائمة تشغيل يومية\n\n- [ ] مراجعة الملخص.\n- [ ] مراجعة أحداث الأمان.\n- [ ] فحص اتصالات قواعد البيانات المهمة.\n- [ ] تنفيذ الاستعلامات الضرورية فقط.\n- [ ] مراجعة الأحداث قبل إنهاء الجلسة.\n";
    }
    return "# Help Center - Pro Guide\n\n## Navigation Checklist\n\n- [ ] [1) Access and Safety](#1-access-and-safety)\n- [ ] [2) Overview Panel](#2-overview-panel)\n- [ ] [3) Tenant Control](#3-tenant-control)\n- [ ] [4) Database Control Panel](#4-database-control-panel)\n- [ ] [5) Query Console](#5-query-console)\n- [ ] [6) Gateway Policies](#6-gateway-policies)\n- [ ] [7) Safety Events](#7-safety-events)\n- [ ] [8) Events Explorer](#8-events-explorer)\n- [ ] [9) Emergency Controls](#9-emergency-controls)\n- [ ] [10) Daily Checklist](#10-daily-checklist)\n\n## 1) Access and Safety\n\n- Open `admin/control-center.php`.\n- Work with admin-level account only.\n- Treat write operations as high-risk.\n\n## 2) Overview Panel\n\n- Check tenant count.\n- Check system mode (`SAFE` / `STRICT`).\n- Check security counters.\n\n## 3) Tenant Control\n\n- Create tenant.\n- Edit tenant.\n- Suspend/activate.\n- Delete with confirmation.\n\n## 4) Database Control Panel\n\n- `Test Connection` for DB health.\n- `Run Migration` for schema flow.\n- `Rebuild Schema` only when required.\n\n## 5) Query Console\n\n- Use `SAFE` for read queries.\n- Use `SYSTEM` for write queries only.\n- Validate before executing.\n\n## 6) Gateway Policies\n\n- Review allow/block/warn decisions.\n- Filter by tenant and decision state.\n\n## 7) Safety Events\n\n- Investigate repeated warnings.\n- Correlate with event stream and gateway entries.\n\n## 8) Events Explorer\n\n- Filter by keyword/level/tenant.\n- Review outcomes after sensitive actions.\n\n## 9) Emergency Controls\n\n- Incident-use only.\n- Complete double confirmation.\n\n## 10) Daily Checklist\n\n- [ ] Review overview and safety indicators.\n- [ ] Validate key tenant DB connectivity.\n- [ ] Run only required queries.\n- [ ] Review events before session end.\n";
}

$raw = hcLoadGuide($lang);

function hcInline(string $text): string
{
    $tokens = [];
    $i = 0;

    // Extract links first from raw markdown, then escape other content safely.
    $text = preg_replace_callback('/\[(.*?)\]\((.*?)\)/', static function (array $m) use (&$tokens, &$i): string {
        $key = '__HC_LINK_' . $i++ . '__';
        $label = htmlspecialchars((string) ($m[1] ?? ''), ENT_QUOTES, 'UTF-8');
        $href = htmlspecialchars((string) ($m[2] ?? ''), ENT_QUOTES, 'UTF-8');
        $tokens[$key] = '<a href="' . $href . '">' . $label . '</a>';
        return $key;
    }, $text) ?? $text;

    // Extract inline code markers.
    $text = preg_replace_callback('/`([^`]+)`/', static function (array $m) use (&$tokens, &$i): string {
        $key = '__HC_CODE_' . $i++ . '__';
        $code = htmlspecialchars((string) ($m[1] ?? ''), ENT_QUOTES, 'UTF-8');
        $tokens[$key] = '<code>' . $code . '</code>';
        return $key;
    }, $text) ?? $text;

    $safe = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    if (!empty($tokens)) {
        $safe = strtr($safe, $tokens);
    }
    return $safe;
}

function hcSlug(string $text): string
{
    $slug = mb_strtolower(trim($text), 'UTF-8');
    // Keep unicode letters/numbers, spaces and dashes.
    $slug = preg_replace('/[^\p{L}\p{N}\s\-]/u', '', $slug) ?? $slug;
    $slug = preg_replace('/\s+/u', '-', $slug) ?? $slug;
    $slug = trim((string) $slug, '-');
    return $slug !== '' ? $slug : 'section';
}

function hcRenderMarkdown(string $md): string
{
    $lines = preg_split("/\r\n|\n|\r/", $md) ?: [];
    $out = [];
    $inList = false;
    $inCode = false;

    foreach ($lines as $line) {
        $trim = trim($line);

        if ($trim === '```') {
            if ($inList) {
                $out[] = '</ul>';
                $inList = false;
            }
            $out[] = $inCode ? '</code></pre>' : '<pre><code>';
            $inCode = !$inCode;
            continue;
        }

        if ($inCode) {
            $out[] = htmlspecialchars($line, ENT_QUOTES, 'UTF-8');
            continue;
        }

        if ($trim === '') {
            if ($inList) {
                $out[] = '</ul>';
                $inList = false;
            }
            continue;
        }

        if (strpos($trim, '# ') === 0) {
            if ($inList) {
                $out[] = '</ul>';
                $inList = false;
            }
            $h = substr($trim, 2);
            $out[] = '<h1 id="' . htmlspecialchars(hcSlug($h), ENT_QUOTES, 'UTF-8') . '">' . hcInline($h) . '</h1>';
            continue;
        }

        if (strpos($trim, '## ') === 0) {
            if ($inList) {
                $out[] = '</ul>';
                $inList = false;
            }
            $h = substr($trim, 3);
            $out[] = '<h2 id="' . htmlspecialchars(hcSlug($h), ENT_QUOTES, 'UTF-8') . '">' . hcInline($h) . '</h2>';
            continue;
        }

        if (strpos($trim, '### ') === 0) {
            if ($inList) {
                $out[] = '</ul>';
                $inList = false;
            }
            $h = substr($trim, 4);
            $out[] = '<h3 id="' . htmlspecialchars(hcSlug($h), ENT_QUOTES, 'UTF-8') . '">' . hcInline($h) . '</h3>';
            continue;
        }

        if (preg_match('/^- \[( |x)\] (.+)$/i', $trim, $m)) {
            if (!$inList) {
                $out[] = '<ul class="checklist">';
                $inList = true;
            }
            $checked = strtolower($m[1]) === 'x' ? ' checked' : '';
            $out[] = '<li><input type="checkbox" disabled' . $checked . '> <span>' . hcInline($m[2]) . '</span></li>';
            continue;
        }

        if (strpos($trim, '- ') === 0) {
            if (!$inList) {
                $out[] = '<ul>';
                $inList = true;
            }
            $out[] = '<li>' . hcInline(substr($trim, 2)) . '</li>';
            continue;
        }

        if ($inList) {
            $out[] = '</ul>';
            $inList = false;
        }
        $out[] = '<p>' . hcInline($trim) . '</p>';
    }

    if ($inList) {
        $out[] = '</ul>';
    }
    if ($inCode) {
        $out[] = '</code></pre>';
    }
    return implode("\n", $out);
}

$html = hcRenderMarkdown($raw);
$isArabic = $lang === 'ar';
$pageTitle = $isArabic ? 'مركز المساعدة - مركز التحكم' : 'Control Center Help Center';
$heading = $isArabic ? 'مركز المساعدة' : 'Help Center';
$backLabel = $isArabic ? 'العودة إلى مركز التحكم' : 'Back to Control Center';
$switchLabel = $isArabic ? 'English' : 'العربية';
$switchLang = $isArabic ? 'en' : 'ar';
$siteUrl = rtrim((defined('SITE_URL') ? (string) SITE_URL : ''), '/');
$cssPath = __DIR__ . '/assets/css/help-center.css';
$cssVersion = file_exists($cssPath) ? (string) filemtime($cssPath) : '1';
$helpCssUrl = ($siteUrl !== '' ? $siteUrl : '') . '/admin/assets/css/help-center.css?v=' . rawurlencode($cssVersion);
?>
<!doctype html>
<html lang="<?php echo $isArabic ? 'ar' : 'en'; ?>" dir="<?php echo $isArabic ? 'rtl' : 'ltr'; ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($helpCssUrl, ENT_QUOTES, 'UTF-8'); ?>">
</head>
<body>
    <div class="wrap">
        <div class="top">
            <h1><?php echo htmlspecialchars($heading, ENT_QUOTES, 'UTF-8'); ?></h1>
            <div class="top-actions">
                <a class="btn" href="?lang=<?php echo htmlspecialchars($switchLang, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($switchLabel, ENT_QUOTES, 'UTF-8'); ?></a>
                <a class="btn" href="control-center.php"><?php echo htmlspecialchars($backLabel, ENT_QUOTES, 'UTF-8'); ?></a>
            </div>
        </div>
        <div class="card">
            <?php echo $html; ?>
        </div>
    </div>
</body>
</html>
