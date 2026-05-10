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

/*
 * Project-root .env is NOT fully merged here — only bridge keys (DB_*, CONTROL_PANEL_*, RATIB_SITE_CONTENT_* …).
 * config/env.php merges NGENIUS_* from .env only; without this step those keys never reached getenv() before host profiles.
 */
if (!function_exists('ratib_env_load_bridge_dotenv')) {
    /**
     * @param string $path Absolute path to .env
     */
    function ratib_env_load_bridge_dotenv(string $path): void
    {
        if ($path === '' || !is_readable($path)) {
            return;
        }
        $allowed = [
            'DB_HOST',
            'DB_PORT',
            'DB_USER',
            'DB_PASS',
            'DB_NAME',
            // Same names as control-panel/config/env.php — lets pages/home.php open ratib_site_content with the same MySQL user as the CMS when .env sets these.
            'CONTROL_DB_HOST',
            'CONTROL_DB_PORT',
            'CONTROL_DB_USER',
            'CONTROL_DB_PASS',
            'CONTROL_PANEL_DB_NAME',
            'CONTROL_PANEL_DB_USER',
            'CONTROL_PANEL_DB_PASS',
            'RATIB_SITE_CONTENT_DB_HOST',
            'RATIB_SITE_CONTENT_DB_PORT',
            'RATIB_SITE_CONTENT_DB_USER',
            'RATIB_SITE_CONTENT_DB_PASS',
            'RATIB_SITE_CONTENT_DB_NAME',
            'RATIB_SITE_CONTENT_CACHE_FILE',
            'RATIB_SITE_CONTENT_DIAG_SECRET',
            'RATIB_SITE_CONTENT_PUBLIC_SOURCE',
            'RATIB_SITE_CONTENT_SKIP_DISK_JSON_CACHE',
        ];
        $lines = @file($path, FILE_IGNORE_NEW_LINES);
        if (!is_array($lines)) {
            return;
        }
        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            if (strncasecmp($line, 'export ', 7) === 0) {
                $line = trim(substr($line, 7));
            }
            if (strpos($line, '=') === false) {
                continue;
            }
            $parts = explode('=', $line, 2);
            $key = trim((string) ($parts[0] ?? ''));
            $val = trim((string) ($parts[1] ?? ''));
            if ($key === '' || !in_array($key, $allowed, true)) {
                continue;
            }
            $len = strlen($val);
            if ($len >= 2) {
                $q0 = $val[0];
                $q1 = $val[$len - 1];
                if (($q0 === '"' && $q1 === '"') || ($q0 === "'" && $q1 === "'")) {
                    $val = substr($val, 1, -1);
                }
            }
            putenv($key . '=' . $val);
            $_ENV[$key] = $val;
            $_SERVER[$key] = $val;
        }
    }
}
$ratibRoot = dirname(dirname($env_dir));
ratib_env_load_bridge_dotenv($ratibRoot . DIRECTORY_SEPARATOR . '.env');
if (!empty($_SERVER['DOCUMENT_ROOT'])) {
    $ratibDoc = rtrim((string) $_SERVER['DOCUMENT_ROOT'], "/\\");
    if ($ratibDoc !== '') {
        ratib_env_load_bridge_dotenv($ratibDoc . DIRECTORY_SEPARATOR . '.env');
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

