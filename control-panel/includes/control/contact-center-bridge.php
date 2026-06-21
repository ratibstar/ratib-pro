<?php
declare(strict_types=1);

/**
 * RATIB Contact Center — Control Panel bridge (paths, DB, migrations, URLs).
 */
require_once dirname(__DIR__, 3) . '/config/env/directadmin_db.php';

function control_contact_center_root_candidates(): array
{
    $candidates = [];
    $candidates[] = dirname(__DIR__, 3) . '/ratib-contact-center';
    $candidates[] = dirname(__DIR__, 2) . '/ratib-contact-center';

    $docRoot = (string) ($_SERVER['DOCUMENT_ROOT'] ?? '');
    if ($docRoot !== '') {
        $candidates[] = rtrim($docRoot, '/\\') . '/ratib-contact-center';
        $candidates[] = dirname(rtrim($docRoot, '/\\')) . '/ratib-contact-center';
    }

    $unique = [];
    foreach ($candidates as $path) {
        $norm = str_replace('\\', '/', $path);
        if (!in_array($norm, $unique, true)) {
            $unique[] = $norm;
        }
    }
    return $unique;
}

function control_contact_center_root_path(): string
{
    foreach (control_contact_center_root_candidates() as $path) {
        if (is_file($path . '/bootstrap.php')) {
            return $path;
        }
    }
    return control_contact_center_root_candidates()[0] ?? (dirname(__DIR__, 3) . '/ratib-contact-center');
}

function control_contact_center_is_installed(): bool
{
    return is_file(control_contact_center_root_path() . '/bootstrap.php');
}

function control_contact_center_db_name(): string
{
    if (defined('RATIB_CC_DB_NAME') && (string) RATIB_CC_DB_NAME !== '') {
        return (string) RATIB_CC_DB_NAME;
    }
    if (function_exists('rateb_contact_center_database_name')) {
        return rateb_contact_center_database_name();
    }
    $env = getenv('RATIB_CC_DB_NAME');
    return ($env !== false && $env !== '') ? (string) $env : 'admin_call-center';
}

function control_contact_center_db_user(): string
{
    if (defined('RATIB_CC_DB_USER') && (string) RATIB_CC_DB_USER !== '') {
        return (string) RATIB_CC_DB_USER;
    }
    if (function_exists('rateb_contact_center_db_user')) {
        return rateb_contact_center_db_user();
    }
    return control_contact_center_db_name();
}

function control_contact_center_apply_db_env(): void
{
    $pairs = [
        'RATIB_CC_DB_NAME' => control_contact_center_db_name(),
        'RATIB_CC_DB_USER' => control_contact_center_db_user(),
        'RATIB_CC_DB_HOST' => defined('RATIB_CC_DB_HOST') ? (string) RATIB_CC_DB_HOST : (getenv('DB_HOST') ?: '127.0.0.1'),
        'RATIB_CC_DB_PORT' => (string) (defined('RATIB_CC_DB_PORT') ? RATIB_CC_DB_PORT : 3306),
    ];
    if (defined('RATIB_CC_DB_PASS')) {
        $pairs['RATIB_CC_DB_PASS'] = (string) RATIB_CC_DB_PASS;
    } elseif (getenv('RATIB_CC_DB_PASS') !== false) {
        $pairs['RATIB_CC_DB_PASS'] = (string) getenv('RATIB_CC_DB_PASS');
    } elseif (defined('DB_PASS')) {
        $pairs['RATIB_CC_DB_PASS'] = (string) DB_PASS;
    }
    foreach ($pairs as $key => $value) {
        if (!defined($key)) {
            define($key, $value);
        }
        if (getenv($key) === false) {
            putenv($key . '=' . $value);
        }
    }
}

function control_contact_center_site_base(): string
{
    $site = rtrim(defined('SITE_URL') ? (string) SITE_URL : '', '/');
    if ($site === '') {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $site = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    }
    return $site;
}

function control_contact_center_public_base_url(): string
{
    return control_contact_center_site_base() . '/ratib-contact-center/public';
}

function control_contact_center_assets_base_url(): string
{
    return control_contact_center_public_base_url() . '/assets';
}

function control_contact_center_inbox_api_url(): string
{
    return control_contact_center_public_base_url() . '/api/v1/inbox.php';
}

function control_contact_center_softphone_api_url(): string
{
    return control_contact_center_public_base_url() . '/api/v1/softphone.php';
}

function control_contact_center_ws_url(): string
{
    $host = defined('RATIB_CC_WS_HOST') ? (string) RATIB_CC_WS_HOST : (getenv('RCC_REALTIME_HUB_HOST') ?: '127.0.0.1');
    $port = (int) (getenv('RCC_WEBSOCKET_PORT') ?: 9702);
    $siteHost = parse_url(control_contact_center_site_base(), PHP_URL_HOST) ?: 'localhost';
    $useHost = ($host === '127.0.0.1' || $host === 'localhost') ? $siteHost : $host;
    $scheme = (strpos(control_contact_center_site_base(), 'https://') === 0) ? 'wss' : 'ws';
    return $scheme . '://' . $useHost . ':' . $port;
}

function control_contact_center_hub_page_url(): string
{
    return function_exists('control_panel_page_with_control')
        ? control_panel_page_with_control('control/contact-center.php')
        : '/control-panel/pages/control/contact-center.php?control=1';
}

function control_contact_center_migrate_page_url(): string
{
    return function_exists('control_panel_page_with_control')
        ? control_panel_page_with_control('control/contact-center-migrate.php')
        : '/control-panel/pages/control/contact-center-migrate.php?control=1';
}

function control_contact_center_app_url(string $route = 'agent-desktop'): string
{
    $base = function_exists('control_panel_page_with_control')
        ? control_panel_page_with_control('control/contact-center-app.php')
        : '/control-panel/pages/control/contact-center-app.php?control=1';
    return $base . '&route=' . rawurlencode($route);
}

/** @return array{ok:bool,schema:bool,db:string,user:string,error:string,tables:int} */
function control_contact_center_db_test(): array
{
    $result = [
        'ok' => false,
        'schema' => false,
        'db' => control_contact_center_db_name(),
        'user' => control_contact_center_db_user(),
        'error' => '',
        'tables' => 0,
    ];
    if (!control_contact_center_is_installed()) {
        $result['error'] = 'Contact Center files missing on server.';
        return $result;
    }
    try {
        control_contact_center_apply_db_env();
        require_once control_contact_center_root_path() . '/bootstrap.php';
        \Ratib\ContactCenter\App\Core\Database::disconnect();
        $pdo = \Ratib\ContactCenter\App\Core\Database::connection();
        $result['ok'] = true;
        $stmt = $pdo->query("SHOW TABLES LIKE 'rcc_%'");
        $result['tables'] = $stmt ? $stmt->rowCount() : 0;
        $check = $pdo->query("SHOW TABLES LIKE 'rcc_tenants'");
        $result['schema'] = $check !== false && $check->rowCount() > 0;
    } catch (Throwable $e) {
        $result['error'] = $e->getMessage();
        error_log('RCC DB test: ' . $e->getMessage());
    }
    return $result;
}

/** @return list<string> */
function control_contact_center_run_migrations(): array
{
    control_contact_center_apply_db_env();
    require_once control_contact_center_root_path() . '/bootstrap.php';
    \Ratib\ContactCenter\App\Core\Database::disconnect();
    $pdo = \Ratib\ContactCenter\App\Core\Database::connection();
    $dir = control_contact_center_root_path() . '/migrations';
    $files = glob($dir . '/*.sql') ?: [];
    sort($files);
    $log = [];

    foreach ($files as $file) {
        $name = basename($file);
        try {
            $chk = $pdo->prepare('SELECT 1 FROM rcc_migration_log WHERE migration = :m LIMIT 1');
            $chk->execute(['m' => $name]);
            if ($chk->fetchColumn() !== false) {
                $log[] = 'SKIP ' . $name . ' (already applied)';
                continue;
            }
        } catch (Throwable $e) {
            // rcc_migration_log may not exist yet — first migration creates it
        }

        $sql = (string) file_get_contents($file);
        control_contact_center_exec_sql_batch($pdo, $sql);
        try {
            $ins = $pdo->prepare('INSERT INTO rcc_migration_log (migration, batch) VALUES (:m, 1)');
            $ins->execute(['m' => $name]);
        } catch (Throwable $e) {
            // logged after 001 runs
        }
        $log[] = 'OK ' . $name;
    }

    return $log;
}

function control_contact_center_exec_sql_batch(\PDO $pdo, string $sql): void
{
    $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
    $parts = preg_split('/;\s*(?:\r?\n|$)/', $sql) ?: [];
    foreach ($parts as $stmt) {
        $stmt = trim($stmt);
        if ($stmt === '') {
            continue;
        }
        $pdo->exec($stmt);
    }
}

/** @return array<string, array{href:string,label:string,icon:string,key:string,description:string,route:string}> */
function control_contact_center_nav_links(): array
{
    return [
        'agent-desktop' => [
            'route' => 'agent-desktop',
            'href' => control_contact_center_app_url('agent-desktop'),
            'label' => 'Agent Desktop',
            'icon' => 'fa-headset',
            'key' => 'agent-desktop',
            'description' => 'Unified inbox + softphone — voice, WhatsApp, email, chat in one thread.',
        ],
        'hub' => [
            'route' => 'hub',
            'href' => control_contact_center_hub_page_url(),
            'label' => 'Contact Center Hub',
            'icon' => 'fa-phone-volume',
            'key' => 'hub',
            'description' => 'Overview, database status, and module links.',
        ],
    ];
}

function control_contact_center_diagnostic(): array
{
    $resolved = control_contact_center_root_path();
    return [
        'installed' => control_contact_center_is_installed(),
        'resolved' => $resolved,
        'bootstrap_exists' => is_file($resolved . '/bootstrap.php'),
        'candidates' => control_contact_center_root_candidates(),
        'db' => control_contact_center_db_name(),
        'db_user' => control_contact_center_db_user(),
    ];
}
