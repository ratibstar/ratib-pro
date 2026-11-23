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
    echo '*{box-sizing:border-box}html,body{margin:0;min-height:100%}';
    echo 'body{font-family:' . ($ar ? 'Tajawal,Manrope' : 'Manrope,Tajawal') . ',sans-serif;color:#e8eef7;display:grid;place-items:center;padding:28px;position:relative;overflow:hidden;background:#060914}';
    echo '.bg{position:fixed;inset:0;z-index:0;overflow:hidden;pointer-events:none;background:linear-gradient(125deg,#0b1020,#1e1b4b 42%,#0f3f3a 100%);background-size:200% 200%;animation:bgShift 14s ease-in-out infinite alternate}';
    echo '.bg-orb{position:absolute;border-radius:50%;filter:blur(40px);opacity:.7;mix-blend-mode:screen}';
    echo '.bg-orb.a{width:46vw;height:46vw;min-width:280px;min-height:280px;left:-12%;top:-18%;background:radial-gradient(circle,rgba(124,58,237,.7),rgba(124,58,237,0) 68%);animation:orbA 18s ease-in-out infinite alternate}';
    echo '.bg-orb.b{width:42vw;height:42vw;min-width:240px;min-height:240px;right:-14%;top:8%;background:radial-gradient(circle,rgba(20,184,166,.55),rgba(20,184,166,0) 70%);animation:orbB 16s ease-in-out infinite alternate}';
    echo '.bg-orb.c{width:38vw;height:38vw;min-width:220px;min-height:220px;left:28%;bottom:-22%;background:radial-gradient(circle,rgba(249,115,22,.4),rgba(249,115,22,0) 72%);animation:orbC 20s ease-in-out infinite alternate}';
    echo '.bg-wave{position:absolute;inset:-20% -10%;background:repeating-linear-gradient(115deg,transparent 0 46px,rgba(103,232,249,.045) 46px 48px);transform:rotate(-8deg);animation:waveDrift 22s linear infinite;opacity:.7}';
    echo '.bg-grid{position:absolute;inset:0;background-image:radial-gradient(rgba(255,255,255,.07) 1px,transparent 1px);background-size:42px 42px;animation:gridFloat 28s linear infinite;opacity:.45}';
    echo '.bg-glow{position:absolute;inset:10% 18%;border-radius:40%;background:radial-gradient(circle,rgba(167,139,250,.18),transparent 65%);animation:glowPulse 7s ease-in-out infinite alternate;filter:blur(20px)}';
    echo '.shell{position:relative;z-index:1;width:min(920px,100%);display:grid;gap:18px}';
    echo '.brand-mark{font-size:clamp(1.35rem,2.4vw,1.7rem);font-weight:800;letter-spacing:.02em}';
    echo '.brand-mark span{color:#67e8f9}';
    echo '.panel{border-radius:28px;padding:36px 32px;border:1px solid rgba(255,255,255,.14);background:linear-gradient(145deg,rgba(24,16,48,.88),rgba(15,23,42,.82));box-shadow:0 30px 80px rgba(0,0,0,.45);backdrop-filter:blur(12px);animation:panelIn .8s ease both}';
    echo 'h1{color:#67e8f9;font-size:clamp(2rem,4vw,2.8rem);line-height:1.15;margin:0 0 14px;font-weight:800}';
    echo '.msg{color:#d7e0ee;line-height:1.7;font-size:clamp(1.08rem,2vw,1.3rem);margin:0 0 10px}';
    echo '.lead{color:#a8b7cd;margin:0 0 22px;font-size:1.02rem;line-height:1.65}';
    echo '.how{margin:0;padding:18px 16px;border-radius:20px;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1)}';
    echo '.how h2{margin:0 0 14px;font-size:1.05rem;font-weight:800;color:#fff}';
    echo '.steps{list-style:none;margin:0;padding:0;display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:8px}';
    echo '.steps li{display:grid;justify-items:center;gap:8px;text-align:center;min-width:0;animation:stepRise .7s ease both}';
    echo '.steps li:nth-child(1){animation-delay:.05s}.steps li:nth-child(2){animation-delay:.12s}.steps li:nth-child(3){animation-delay:.19s}.steps li:nth-child(4){animation-delay:.26s}.steps li:nth-child(5){animation-delay:.33s}.steps li:nth-child(6){animation-delay:.4s}';
    echo '.steps .num{width:36px;height:36px;border-radius:999px;display:grid;place-items:center;font-weight:800;font-size:.95rem;color:#fff;background:linear-gradient(135deg,#6d28d9,#0f766e);box-shadow:0 0 0 3px rgba(109,40,217,.22);animation:numPulse 3.6s ease-in-out infinite}';
    echo '.steps li:nth-child(2) .num{animation-delay:.3s}.steps li:nth-child(3) .num{animation-delay:.6s}.steps li:nth-child(4) .num{animation-delay:.9s}.steps li:nth-child(5) .num{animation-delay:1.2s}.steps li:nth-child(6) .num{animation-delay:1.5s}';
    echo '.steps .label{font-size:.9rem;font-weight:700;color:#dbe4f2;line-height:1.25}';
    echo '@keyframes bgShift{0%{background-position:0% 40%}100%{background-position:100% 60%}}';
    echo '@keyframes orbA{0%{transform:translate(0,0) scale(1)}100%{transform:translate(18%,14%) scale(1.18)}}';
    echo '@keyframes orbB{0%{transform:translate(0,0) scale(1)}100%{transform:translate(-16%,22%) scale(1.14)}}';
    echo '@keyframes orbC{0%{transform:translate(0,0) scale(1)}100%{transform:translate(-12%,-18%) scale(1.2)}}';
    echo '@keyframes waveDrift{0%{transform:translateX(0) rotate(-8deg)}100%{transform:translateX(-8%) rotate(-8deg)}}';
    echo '@keyframes gridFloat{0%{transform:translateY(0)}100%{transform:translateY(-42px)}}';
    echo '@keyframes glowPulse{0%{opacity:.35;transform:scale(.92)}100%{opacity:.8;transform:scale(1.08)}}';
    echo '@keyframes panelIn{from{opacity:0;transform:translateY(18px) scale(.98)}to{opacity:1;transform:none}}';
    echo '@keyframes stepRise{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:none}}';
    echo '@keyframes numPulse{0%,100%{box-shadow:0 0 0 3px rgba(109,40,217,.2)}50%{box-shadow:0 0 0 6px rgba(20,184,166,.28)}}';
    echo '@media (max-width:720px){.panel{padding:28px 18px}.steps{grid-template-columns:repeat(3,minmax(0,1fr));gap:12px 8px}}';
    echo '@media (prefers-reduced-motion:reduce){.bg,.bg-orb,.bg-wave,.bg-grid,.bg-glow,.panel,.steps li,.steps .num{animation:none!important}}';
    echo '</style></head><body>';
    echo '<div class="bg" aria-hidden="true"><span class="bg-orb a"></span><span class="bg-orb b"></span><span class="bg-orb c"></span><span class="bg-wave"></span><span class="bg-grid"></span><span class="bg-glow"></span></div>';
    echo '<div class="shell">';
    echo '<div class="brand-mark">RATEB <span>AI</span></div>';
    echo '<div class="panel"><h1>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1>';
    echo '<p class="msg">' . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . '</p>';
    if ($mode === 'maintenance') {
        echo '<p class="lead">' . htmlspecialchars($lead, ENT_QUOTES, 'UTF-8') . '</p>';
        echo '<section class="how"><h2>' . htmlspecialchars($howTitle, ENT_QUOTES, 'UTF-8') . '</h2><ol class="steps">';
        foreach ($steps as $i => $label) {
            echo '<li><span class="num">' . ($i + 1) . '</span><span class="label">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span></li>';
        }
        echo '</ol></section>';
    }
    echo '</div></div></body></html>';
    exit;
}
