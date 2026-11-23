<?php
declare(strict_types=1);

require_once __DIR__ . '/../migrations/platform_settings_schema.php';

/**
 * Site access gate.
 * - Maintenance: locks the whole customer site. Only /admin (+ static assets) stay up.
 * - Public site off: locks marketing pages only. Logged-in customer workspace stays up.
 */
function rateb_enforce_public_access(PDO $pdo): void
{
    static $enforced = false;
    if ($enforced) {
        return;
    }
    $enforced = true;

    $path = rateb_public_gate_request_path();

    ensure_platform_settings_schema($pdo);
    $publicOn = platform_setting_bool($pdo, 'public_site_enabled', true);
    $maintOn = platform_setting_bool($pdo, 'maintenance_mode', false);

    if ($maintOn) {
        if (rateb_public_gate_is_maintenance_exempt($path) || rateb_public_gate_is_admin_script()) {
            return;
        }
        rateb_public_gate_render('maintenance', $pdo);
    }

    if (!$publicOn) {
        if (rateb_public_gate_is_always_exempt($path) || rateb_public_gate_is_exempt_script()) {
            return;
        }
        if (rateb_public_gate_is_marketing_path($path)) {
            rateb_public_gate_render('off', $pdo);
        }
    }
}

function rateb_public_gate_request_path(): string
{
    $uriPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
    return rateb_public_gate_normalize_path(is_string($uriPath) && $uriPath !== '' ? $uriPath : '/');
}

function rateb_public_gate_normalize_path(string $path): string
{
    if ($path === '' || $path === '/index.php') {
        return '/';
    }
    if (strlen($path) > 1 && str_ends_with($path, '/')) {
        $path = rtrim($path, '/');
    }
    return $path === '' ? '/' : $path;
}

function rateb_public_gate_path_matches(string $path, string $prefix): bool
{
    $path = rateb_public_gate_normalize_path($path);
    return $path === $prefix
        || str_starts_with($path, $prefix . '/')
        || str_starts_with($path, $prefix . '.php');
}

/** During maintenance: only admin console and static assets. */
function rateb_public_gate_is_maintenance_exempt(string $path): bool
{
    $path = rateb_public_gate_normalize_path($path);
    foreach (['/admin', '/public', '/favicon.ico'] as $prefix) {
        if (rateb_public_gate_path_matches($path, $prefix) || ($prefix !== '/favicon.ico' && str_starts_with($path, $prefix))) {
            return true;
        }
    }
    return $path === '/favicon.ico';
}

/** URL SCRIPT_NAME under /admin only (never filesystem paths — server home may be /home/admin/). */
function rateb_public_gate_is_admin_script(): bool
{
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    return $script === '/admin'
        || $script === '/admin/index.php'
        || str_starts_with($script, '/admin/');
}

/**
 * Soft public-off: customer workspace scripts stay available.
 * Uses URL SCRIPT_NAME only.
 */
function rateb_public_gate_is_exempt_script(): bool
{
    if (rateb_public_gate_is_admin_script()) {
        return true;
    }
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    if ($script === '') {
        return false;
    }
    $base = strtolower(basename($script));
    $exemptFiles = [
        'login.php',
        'dashboard.php',
        'credits.php',
        'billing.php',
        'account.php',
        'campaign.php',
        'campaign-new.php',
        'brand.php',
        'logout.php',
    ];
    if (in_array($base, $exemptFiles, true)) {
        return true;
    }
    return str_starts_with($script, '/app/auth/')
        || str_starts_with($script, '/app/payment/')
        || str_starts_with($script, '/app/account/')
        || str_starts_with($script, '/app/ai/')
        || str_starts_with($script, '/app/campaign/')
        || str_starts_with($script, '/app/content/')
        || str_starts_with($script, '/app/media/')
        || str_starts_with($script, '/app/brand/')
        || str_starts_with($script, '/app/hr/')
        || str_starts_with($script, '/hr/');
}

/** Soft public-off: customer app paths stay available. */
function rateb_public_gate_is_always_exempt(string $path): bool
{
    $path = rateb_public_gate_normalize_path($path);
    $prefixes = [
        '/admin',
        '/app/auth',
        '/app/payment',
        '/app/ai',
        '/app/campaign',
        '/app/content',
        '/app/media',
        '/app/brand',
        '/app/hr',
        '/app/account',
        '/login',
        '/logout',
        '/forgot-password',
        '/reset-password',
        '/dashboard',
        '/billing.php',
        '/billing',
        '/credits.php',
        '/credits',
        '/account',
        '/campaign',
        '/brand',
        '/hr',
        '/api',
        '/public',
        '/favicon.ico',
    ];
    foreach ($prefixes as $prefix) {
        if (rateb_public_gate_path_matches($path, $prefix) || str_starts_with($path, $prefix)) {
            return true;
        }
    }
    return false;
}

/** Paths treated as the public marketing website (affected by public site off). */
function rateb_public_gate_is_marketing_path(string $path): bool
{
    $path = rateb_public_gate_normalize_path($path);
    $marketing = [
        '/',
        '/register',
        '/register.php',
        '/contact',
        '/contact.php',
        '/privacy',
        '/privacy.php',
        '/terms',
        '/terms.php',
        '/pricing',
        '/pricing.php',
        '/index.php',
    ];
    return in_array($path, $marketing, true);
}

/** @deprecated */
function rateb_public_gate_is_exempt_path(string $path): bool
{
    $path = rateb_public_gate_normalize_path($path);
    return rateb_public_gate_is_always_exempt($path) || !rateb_public_gate_is_marketing_path($path);
}

function rateb_public_gate_render(string $mode, ?PDO $pdo = null): void
{
    $ar = (($_COOKIE['rateb_ui_lang'] ?? '') === 'ar')
        || (($_COOKIE['rateb_admin_lang'] ?? '') !== 'en' && ($_COOKIE['rateb_ui_lang'] ?? '') !== 'en');
    http_response_code($mode === 'maintenance' ? 503 : 403);
    header('Content-Type: text/html; charset=utf-8');
    header('Retry-After: 3600');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
    $pdo = $pdo ?? ($GLOBALS['pdo'] ?? null);
    $msgEn = 'This public site is currently unavailable.';
    $msgAr = 'الموقع العام غير متاح حاليًا.';
    if ($mode === 'maintenance' && $pdo instanceof PDO) {
        $msgEn = platform_setting($pdo, 'maintenance_message_en', $msgEn) ?? $msgEn;
        $msgAr = platform_setting($pdo, 'maintenance_message_ar', $msgAr) ?? $msgAr;
    }
    $msg = $ar ? $msgAr : $msgEn;
    $title = $mode === 'maintenance' ? ($ar ? 'صيانة' : 'Maintenance') : ($ar ? 'غير متاح' : 'Unavailable');
    $adminLabel = $ar ? 'لوحة الإدارة' : 'Admin console';
    $howTitle = $ar ? 'كيف يعمل RATEB AI' : 'How RATEB AI works';
    $lead = $ar
        ? 'حوّل فكرتك إلى حملة تسويقية كاملة: استراتيجية، نص، صور، صوت، وفيديو.'
        : 'Turn your idea into a full campaign: strategy, copy, images, voice, and video.';
    $steps = $ar
        ? ['الفكرة', 'الاستراتيجية', 'النص', 'الصور', 'الصوت', 'الفيديو']
        : ['Idea', 'Strategy', 'Copy', 'Images', 'Voice', 'Video'];
    echo '<!doctype html><html lang="' . ($ar ? 'ar' : 'en') . '" dir="' . ($ar ? 'rtl' : 'ltr') . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>RATEB AI — ' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title>';
    echo '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Manrope:wght@500;700;800&family=Tajawal:wght@400;500;700;800&display=swap">';
    echo '<style>';
    echo '*{box-sizing:border-box}body{margin:0;min-height:100vh;font-family:' . ($ar ? 'Tajawal,Manrope' : 'Manrope,Tajawal') . ',sans-serif;color:#e8eef7;display:grid;place-items:center;padding:28px;position:relative;overflow:hidden;';
    echo 'background:#070b14;background-image:radial-gradient(900px 420px at 12% -10%,rgba(109,40,217,.45),transparent 58%),radial-gradient(780px 420px at 100% 0%,rgba(20,184,166,.28),transparent 55%),radial-gradient(700px 380px at 50% 110%,rgba(249,115,22,.18),transparent 50%),linear-gradient(160deg,#0b1020 0%,#1e1b4b 42%,#0f766e22 100%)}';
    echo 'body::before{content:"";position:absolute;inset:0;background:radial-gradient(circle at 20% 30%,rgba(255,255,255,.05) 0 1px,transparent 1.5px),radial-gradient(circle at 80% 70%,rgba(255,255,255,.04) 0 1px,transparent 1.5px);background-size:48px 48px;opacity:.55;pointer-events:none}';
    echo '.shell{position:relative;z-index:1;width:min(920px,100%);display:grid;gap:18px}';
    echo '.brand{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}';
    echo '.brand-mark{font-size:clamp(1.35rem,2.4vw,1.7rem);font-weight:800;letter-spacing:.02em}';
    echo '.brand-mark span{color:#67e8f9}';
    echo '.panel{border-radius:28px;padding:36px 32px;border:1px solid rgba(255,255,255,.14);background:linear-gradient(145deg,rgba(24,16,48,.88),rgba(15,23,42,.82));box-shadow:0 30px 80px rgba(0,0,0,.4);backdrop-filter:blur(10px)}';
    echo 'h1{color:#67e8f9;font-size:clamp(2rem,4vw,2.8rem);line-height:1.15;margin:0 0 14px;font-weight:800}';
    echo '.msg{color:#d7e0ee;line-height:1.7;font-size:clamp(1.08rem,2vw,1.3rem);margin:0 0 10px}';
    echo '.lead{color:#a8b7cd;margin:0 0 22px;font-size:1.02rem;line-height:1.65}';
    echo '.how{margin:0 0 24px;padding:18px 16px;border-radius:20px;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1)}';
    echo '.how h2{margin:0 0 14px;font-size:1.05rem;font-weight:800;color:#fff}';
    echo '.steps{list-style:none;margin:0;padding:0;display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:8px}';
    echo '.steps li{display:grid;justify-items:center;gap:8px;text-align:center;min-width:0}';
    echo '.steps .num{width:36px;height:36px;border-radius:999px;display:grid;place-items:center;font-weight:800;font-size:.95rem;color:#fff;background:linear-gradient(135deg,#6d28d9,#0f766e);box-shadow:0 0 0 3px rgba(109,40,217,.22)}';
    echo '.steps .label{font-size:.9rem;font-weight:700;color:#dbe4f2;line-height:1.25}';
    echo '.actions{display:flex;flex-wrap:wrap;gap:12px}';
    echo 'a.btn{display:inline-flex;align-items:center;justify-content:center;min-height:56px;padding:0 26px;border-radius:999px;font-size:1.12rem;font-weight:800;text-decoration:none;color:#fff;background:linear-gradient(90deg,#6d28d9,#0f766e);box-shadow:0 12px 30px rgba(109,40,217,.35)}';
    echo '@media (max-width:720px){.panel{padding:28px 18px}.steps{grid-template-columns:repeat(3,minmax(0,1fr));gap:12px 8px}a.btn{width:100%}}';
    echo '</style></head><body><div class="shell">';
    echo '<div class="brand"><div class="brand-mark">RATEB <span>AI</span></div></div>';
    echo '<div class="panel"><h1>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1>';
    echo '<p class="msg">' . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . '</p>';
    if ($mode === 'maintenance') {
        echo '<p class="lead">' . htmlspecialchars($lead, ENT_QUOTES, 'UTF-8') . '</p>';
        echo '<section class="how"><h2>' . htmlspecialchars($howTitle, ENT_QUOTES, 'UTF-8') . '</h2><ol class="steps">';
        foreach ($steps as $i => $label) {
            echo '<li><span class="num">' . ($i + 1) . '</span><span class="label">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span></li>';
        }
        echo '</ol></section>';
        echo '<div class="actions"><a class="btn" href="/admin">' . htmlspecialchars($adminLabel, ENT_QUOTES, 'UTF-8') . '</a></div>';
    }
    echo '</div></div></body></html>';
    exit;
}
