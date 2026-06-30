<?php
/**
 * EN: Handles configuration/runtime setup behavior in `config/env/load.php`.
 * AR: يدير سلوك إعدادات النظام وتهيئة التشغيل في `config/env/load.php`.
 */
/**   
 * Environment loader: each link/site gets its own database and settings.
 * No conflict — everyone has their own data.
 * RATEB Pro only — no control panel on this codebase.
 */
if (defined('ENV_LOADED')) {
    return;
}
$env_host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'default';
$env_host = strtolower(trim((string) $env_host));
if ($env_host !== '' && strpos($env_host, ':') !== false) {
    $env_host = explode(':', $env_host, 2)[0];
}
if ($env_host === '') {
    $env_host = 'default';
}
$env_dir = __DIR__;

$ratebErpWeb = defined('RATEB_ROOT')
    && is_file(rtrim(str_replace('\\', '/', (string) RATEB_ROOT), '/') . '/app/Core/SessionManager.php');
$ratebSkipSession = (defined('RATEB_ENV_NO_SESSION') && RATEB_ENV_NO_SESSION) || $ratebErpWeb;
if (!$ratebSkipSession) {
    // Session ini must be set BEFORE session_start()
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        ini_set('session.cookie_secure', 1);
    } else {
        ini_set('session.cookie_secure', 0);
    }

    // Control SSO uses session name rateb_control. Dashboard links often omit ?control=1, so continue
    // that session whenever the rateb_control cookie is present (otherwise logout reads the wrong session).
    $ratebControlCookie = isset($_COOKIE['rateb_control']) ? (string)$_COOKIE['rateb_control'] : '';
    if (isset($_GET['control']) && (string)$_GET['control'] === '1') {
        session_name('rateb_control');
    } elseif ($ratebControlCookie !== '') {
        session_name('rateb_control');
    }

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

$env_safe = str_replace('.', '_', trim($env_host));
if (empty($env_safe)) {
    $env_safe = 'default';
}
$env_candidates = [$env_safe];
if (strpos($env_host, 'www.') === 0) {
    $env_candidates[] = str_replace('.', '_', substr($env_host, 4));
}
$env_file = null;
foreach ($env_candidates as $safe) {
    if ($safe === '') {
        continue;
    }
    $candidate = $env_dir . DIRECTORY_SEPARATOR . $safe . '.php';
    if (file_exists($candidate)) {
        $env_file = $candidate;
        break;
    }
}

/*
 * Project-root .env is NOT fully merged here — only bridge keys (DB_*, CONTROL_PANEL_*, RATEB_SITE_CONTENT_* …).
 * config/env.php merges NGENIUS_* from .env only; without this step those keys never reached getenv() before host profiles.
 */
require_once $env_dir . DIRECTORY_SEPARATOR . 'dotenv_bridge.php';
$ratebRoot = dirname(dirname($env_dir));
rateb_env_load_bridge_dotenv($ratebRoot . DIRECTORY_SEPARATOR . '.env');
if (!empty($_SERVER['DOCUMENT_ROOT'])) {
    $ratebDoc = rtrim((string) $_SERVER['DOCUMENT_ROOT'], "/\\");
    if ($ratebDoc !== '') {
        rateb_env_load_bridge_dotenv($ratebDoc . DIRECTORY_SEPARATOR . '.env');
    }
}

if ($env_file !== null) {
    require $env_file;
} else {
    // No env file for this host - try to resolve agency from control_agencies by site_url
    require_once $env_dir . DIRECTORY_SEPARATOR . 'agency_resolver.php';
    if (resolve_agency_by_host($env_host)) {
        // Agency found: DB_* and SITE_URL already defined
    } else {
        require $env_dir . DIRECTORY_SEPARATOR . 'default.php';
    }
}
define('ENV_LOADED', true);

