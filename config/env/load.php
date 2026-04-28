<?php
/**
 * EN: Handles configuration/runtime setup behavior in `config/env/load.php`.
 * AR: يدير سلوك إعدادات النظام وتهيئة التشغيل في `config/env/load.php`.
 */
/**
 * Environment loader: each link/site gets its own database and settings.
 * No conflict — everyone has their own data.
 * Ratib Pro only — no control panel on this codebase.
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

$ratibSkipSession = defined('RATIB_ENV_NO_SESSION') && RATIB_ENV_NO_SESSION;
if (!$ratibSkipSession) {
    // Session ini must be set BEFORE session_start()
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        ini_set('session.cookie_secure', 1);
    } else {
        ini_set('session.cookie_secure', 0);
    }

    // Control SSO uses session name ratib_control. Dashboard links often omit ?control=1, so continue
    // that session whenever the ratib_control cookie is present (otherwise logout reads the wrong session).
    $ratibControlCookie = isset($_COOKIE['ratib_control']) ? (string)$_COOKIE['ratib_control'] : '';
    if (isset($_GET['control']) && (string)$_GET['control'] === '1') {
        session_name('ratib_control');
    } elseif ($ratibControlCookie !== '') {
        session_name('ratib_control');
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
