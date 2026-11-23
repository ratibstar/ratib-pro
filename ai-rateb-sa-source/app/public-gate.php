<?php
declare(strict_types=1);

require_once __DIR__ . '/../migrations/platform_settings_schema.php';

/**
 * Public website gate: landing, registration, and marketing entry points.
 * Customer workspace (/dashboard, /hr, campaigns) stays available when logged in.
 * Admin console and sign-in are always available.
 */
function rateb_enforce_public_access(PDO $pdo): void
{
    static $enforced = false;
    if ($enforced) {
        return;
    }
    $enforced = true;

    $path = rateb_public_gate_request_path();
    if (rateb_public_gate_is_always_exempt($path) || rateb_public_gate_is_exempt_script()) {
        return;
    }

    ensure_platform_settings_schema($pdo);
    $publicOn = platform_setting_bool($pdo, 'public_site_enabled', true);
    $maintOn = platform_setting_bool($pdo, 'maintenance_mode', false);

    $marketing = rateb_public_gate_is_marketing_path($path);

    if ($maintOn && $marketing) {
        rateb_public_gate_render('maintenance', $pdo);
    }

    if (!$publicOn && $marketing) {
        rateb_public_gate_render('off', $pdo);
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

/**
 * Extra safety when rewrite/proxy leaves REQUEST_URI looking like marketing
 * while the actual PHP script is login/admin/workspace.
 * Uses URL SCRIPT_NAME only — never filesystem paths (server home may be /home/admin/).
 */
function rateb_public_gate_is_exempt_script(): bool
{
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
    return str_starts_with($script, '/admin/')
        || $script === '/admin'
        || str_starts_with($script, '/app/auth/')
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

/** Admin, auth, payments, customer app — never blocked by site toggles. */
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
        if ($path === $prefix || str_starts_with($path, $prefix . '/') || str_starts_with($path, $prefix . '.php') || str_starts_with($path, $prefix)) {
            return true;
        }
    }
    return false;
}

/** Paths treated as the public marketing website (affected by maintenance / public off). */
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

/** @deprecated use rateb_public_gate_is_always_exempt */
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
    $loginLabel = $ar ? 'تسجيل الدخول' : 'Sign in';
    $adminLabel = $ar ? 'لوحة الإدارة' : 'Admin console';
    $hint = $ar
        ? 'الموقع العام تحت الصيانة. الدخول ولوحة الإدارة متاحان.'
        : 'The public site is under maintenance. Sign-in and admin stay available.';
    echo '<!doctype html><html lang="' . ($ar ? 'ar' : 'en') . '" dir="' . ($ar ? 'rtl' : 'ltr') . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>RATEB AI — ' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title>';
    echo '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Manrope:wght@500;700;800&family=Tajawal:wght@400;500;700;800&display=swap">';
    echo '<style>';
    echo '*{box-sizing:border-box}body{margin:0;font-family:' . ($ar ? 'Tajawal,Manrope' : 'Manrope,Tajawal') . ',sans-serif;background:#070b14;color:#e8eef7;display:grid;place-items:center;min-height:100vh;padding:28px}';
    echo '.box{width:min(720px,100%);background:#10192b;border:1px solid #2b3b58;border-radius:28px;padding:42px 36px;box-shadow:0 28px 70px rgba(0,0,0,.45)}';
    echo 'h1{color:#67e8f9;font-size:clamp(2rem,4vw,2.75rem);line-height:1.2;margin:0 0 18px;font-weight:800}';
    echo 'p{color:#c5d0e0;line-height:1.7;font-size:clamp(1.1rem,2.2vw,1.35rem);margin:0 0 14px}';
    echo '.hint{color:#93a4bd;font-size:1rem;margin:0 0 28px}';
    echo '.actions{display:flex;flex-wrap:wrap;gap:14px;margin-top:8px}';
    echo 'a.btn{display:inline-flex;align-items:center;justify-content:center;min-height:56px;padding:0 24px;border-radius:999px;font-size:1.15rem;font-weight:800;text-decoration:none}';
    echo 'a.btn-primary{background:linear-gradient(90deg,#6d28d9,#0f766e);color:#fff}';
    echo 'a.btn-secondary{background:#18233a;border:1px solid #334663;color:#e8eef7}';
    echo '@media (max-width:560px){.box{padding:28px 20px}a.btn{width:100%}}';
    echo '</style></head><body>';
    echo '<div class="box"><h1>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1>';
    echo '<p>' . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . '</p>';
    echo '<p class="hint">' . htmlspecialchars($hint, ENT_QUOTES, 'UTF-8') . '</p>';
    echo '<div class="actions">';
    echo '<a class="btn btn-primary" href="/login">' . htmlspecialchars($loginLabel, ENT_QUOTES, 'UTF-8') . '</a>';
    if ($mode === 'maintenance') {
        echo '<a class="btn btn-secondary" href="/admin">' . htmlspecialchars($adminLabel, ENT_QUOTES, 'UTF-8') . '</a>';
    }
    echo '</div></div></body></html>';
    exit;
}
