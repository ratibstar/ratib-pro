<?php
declare(strict_types=1);

require_once __DIR__ . '/../migrations/platform_settings_schema.php';

/**
 * Public website gate: landing, registration, and marketing entry points.
 * Customer workspace (/dashboard, /hr, campaigns) stays available when logged in.
 * Admin console is always available.
 */
function rateb_enforce_public_access(PDO $pdo): void
{
    static $enforced = false;
    if ($enforced) {
        return;
    }
    $enforced = true;

    $path = rateb_public_gate_normalize_path(parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/');
    if (rateb_public_gate_is_always_exempt($path)) {
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

function rateb_public_gate_normalize_path(string $path): string
{
    if ($path === '' || $path === '/index.php') {
        return '/';
    }
    return $path;
}

/** Admin, auth API, payments, customer app — never blocked by site toggles. */
function rateb_public_gate_is_always_exempt(string $path): bool
{
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
        '/login',
        '/logout',
        '/dashboard',
        '/billing.php',
        '/billing',
        '/credits.php',
        '/credits',
        '/app/payment',
        '/campaign',
        '/brand',
        '/hr',
        '/api',
        '/public',
        '/favicon.ico',
    ];
    foreach ($prefixes as $prefix) {
        if ($path === $prefix || str_starts_with($path, $prefix . '/') || str_starts_with($path, $prefix)) {
            return true;
        }
    }
    return false;
}

/** Paths treated as the public marketing website (affected by maintenance / public off). */
function rateb_public_gate_is_marketing_path(string $path): bool
{
    $marketing = [
        '/',
        '/register',
        '/register.php',
        '/forgot-password',
        '/forgot-password.php',
        '/reset-password',
        '/reset-password.php',
        '/contact',
        '/contact.php',
        '/privacy',
        '/privacy.php',
        '/terms',
        '/terms.php',
        '/pricing',
        '/pricing.php',
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
    header('Cache-Control: no-store');
    $pdo = $pdo ?? ($GLOBALS['pdo'] ?? null);
    $msgEn = 'This public site is currently unavailable.';
    $msgAr = 'الموقع العام غير متاح حاليًا.';
    if ($mode === 'maintenance' && $pdo instanceof PDO) {
        $msgEn = platform_setting($pdo, 'maintenance_message_en', $msgEn) ?? $msgEn;
        $msgAr = platform_setting($pdo, 'maintenance_message_ar', $msgAr) ?? $msgAr;
    }
    $msg = $ar ? $msgAr : $msgEn;
    $title = $mode === 'maintenance' ? ($ar ? 'صيانة' : 'Maintenance') : ($ar ? 'غير متاح' : 'Unavailable');
    echo '<!doctype html><html lang="' . ($ar ? 'ar' : 'en') . '" dir="' . ($ar ? 'rtl' : 'ltr') . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>RATEB AI — ' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title>';
    echo '<style>body{margin:0;font-family:Manrope,Tajawal,sans-serif;background:#070b14;color:#e8eef7;display:grid;place-items:center;min-height:100vh;padding:24px}';
    echo '.box{max-width:520px;background:#10192b;border:1px solid #243049;border-radius:16px;padding:24px}h1{color:#67e8f9;font-size:1.25rem;margin:0 0 12px}p{color:#93a4bd;line-height:1.6}a{color:#67e8f9}</style></head><body>';
    echo '<div class="box"><h1>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1><p>' . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . '</p>';
    echo '<p><a href="/login">' . ($ar ? 'تسجيل الدخول' : 'Sign in') . '</a>';
    if ($mode === 'maintenance') {
        echo ' · <a href="/admin">' . ($ar ? 'لوحة الإدارة' : 'Admin console') . '</a>';
    }
    echo '</p></div></body></html>';
    exit;
}