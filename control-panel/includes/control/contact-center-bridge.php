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

function control_contact_center_assistant_api_url(): string
{
    return control_contact_center_public_base_url() . '/api/v1/assistant.php';
}

function control_contact_center_asset_manifest(): array
{
    static $manifest = null;
    if ($manifest !== null) {
        return $manifest;
    }
    $path = control_contact_center_root_path() . '/config/assets-manifest.php';
    if (!is_file($path)) {
        $manifest = [];
        return $manifest;
    }
    $loaded = require $path;
    $manifest = is_array($loaded) ? $loaded : [];

    return $manifest;
}

function control_contact_center_asset_url(string $assetKey): string
{
    $manifest = control_contact_center_asset_manifest();
    $relativePath = $manifest[$assetKey] ?? null;
    if ($relativePath === null) {
        // Legacy: treat as relative path under public/assets/
        $relativePath = ltrim(str_replace('\\', '/', $assetKey), '/');
        $relativePath = preg_replace('/[^\x2E\x2F\x30-\x39\x41-\x5A\x5F\x61-\x7A-]/', '', $relativePath) ?? $relativePath;
        $relativePath = str_replace(['sمنتphone', 'smtphone'], 'softphone', $relativePath);
    }
    if ($relativePath === '' || strpos($relativePath, '..') !== false) {
        return control_contact_center_assets_base_url();
    }

    $base = control_contact_center_assets_base_url();
    $disk = control_contact_center_root_path() . '/public/assets/' . $relativePath;
    $v = is_file($disk) ? (string) filemtime($disk) : (string) time();

    // Prefer direct static file (Apache/LiteSpeed MIME) — avoids asset.php 404/cache issues.
    if (is_file($disk)) {
        $segments = explode('/', $relativePath);
        $encoded = implode('/', array_map('rawurlencode', $segments));

        return $base . '/' . $encoded . '?v=' . $v;
    }

    if (isset($manifest[$assetKey])) {
        return control_contact_center_public_base_url() . '/asset.php?k=' . rawurlencode($assetKey) . '&v=' . $v;
    }

    return control_contact_center_public_base_url() . '/asset.php?f=' . rawurlencode($relativePath) . '&v=' . $v;
}

function control_contact_center_realtime_mode(): string
{
    if (defined('RCC_REALTIME_MODE') && (string) RCC_REALTIME_MODE !== '') {
        return strtolower((string) RCC_REALTIME_MODE) === 'websocket' ? 'websocket' : 'polling';
    }
    $mode = getenv('RCC_REALTIME_MODE');
    if ($mode !== false && trim((string) $mode) !== '') {
        return strtolower(trim((string) $mode)) === 'websocket' ? 'websocket' : 'polling';
    }
    // Default: WebSocket realtime (set RCC_REALTIME_MODE=polling only when hub unavailable).
    return 'websocket';
}

function control_contact_center_ws_url(): string
{
    if (control_contact_center_realtime_mode() === 'polling') {
        return '';
    }
    $public = getenv('RCC_WEBSOCKET_PUBLIC_URL');
    if ($public !== false && trim((string) $public) !== '') {
        return rtrim(trim((string) $public), '/');
    }
    if (defined('RATIB_CC_WS_URL') && (string) RATIB_CC_WS_URL !== '') {
        return rtrim((string) RATIB_CC_WS_URL, '/');
    }
    $host = defined('RATIB_CC_WS_HOST') ? (string) RATIB_CC_WS_HOST : (getenv('RCC_REALTIME_HUB_HOST') ?: '127.0.0.1');
    $port = (int) (getenv('RCC_WEBSOCKET_PORT') ?: 9702);
    $siteHost = parse_url(control_contact_center_site_base(), PHP_URL_HOST) ?: 'localhost';
    $useHost = ($host === '127.0.0.1' || $host === 'localhost') ? $siteHost : $host;
    $scheme = (strpos(control_contact_center_site_base(), 'https://') === 0) ? 'wss' : 'ws';
    return $scheme . '://' . $useHost . ':' . $port;
}

/** @return array{running:bool,port:int,ws_url:string,pid:int|null,log:string,error:string} */
function control_contact_center_realtime_hub_status(): array
{
    $port = (int) (getenv('RCC_WEBSOCKET_PORT') ?: 9702);
    $running = false;
    $fp = @fsockopen('127.0.0.1', $port, $errno, $errstr, 1);
    if (is_resource($fp)) {
        $running = true;
        fclose($fp);
    }

    $pid = null;
    $root = control_contact_center_root_path();
    $logPath = $root . '/storage/logs/rcc-realtime-hub.log';
    if (!is_file($logPath)) {
        $logPath = $root . '/storage/logs/rcc-realtime-hub.log';
    }

    $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
    if (function_exists('shell_exec') && !in_array('shell_exec', $disabled, true)) {
        $out = trim((string) @shell_exec('pgrep -f rcc-realtime-hub.php 2>/dev/null | head -1'));
        if ($out !== '' && ctype_digit($out)) {
            $pid = (int) $out;
            if (!$running) {
                $running = true;
            }
        }
    }

    return [
        'running' => $running,
        'port' => $port,
        'ws_url' => control_contact_center_ws_url(),
        'pid' => $pid,
        'log' => is_file($logPath) ? (string) file_get_contents($logPath) : '',
        'error' => $running ? '' : ($errstr ?? 'port closed'),
    ];
}

/** @return array{ok:bool,running:bool,message:string,error?:string} */
function control_contact_center_start_realtime_hub(): array
{
    if (!control_contact_center_is_installed()) {
        return ['ok' => false, 'running' => false, 'message' => 'module missing', 'error' => 'ratib-contact-center not found'];
    }

    $status = control_contact_center_realtime_hub_status();
    if ($status['running']) {
        return ['ok' => true, 'running' => true, 'message' => 'already running'];
    }

    $root = control_contact_center_root_path();
    $script = $root . '/bin/start-realtime-hub.sh';
    if (!is_file($script)) {
        return ['ok' => false, 'running' => false, 'message' => 'start script missing', 'error' => $script];
    }

    @mkdir($root . '/storage/logs', 0755, true);
    $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
    $launched = false;

    if (function_exists('proc_open') && !in_array('proc_open', $disabled, true)) {
        $cmd = 'bash ' . escapeshellarg($script);
        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $proc = @proc_open($cmd, $descriptors, $pipes, $root);
        if (is_resource($proc)) {
            if (isset($pipes[1])) {
                fclose($pipes[1]);
            }
            if (isset($pipes[2])) {
                fclose($pipes[2]);
            }
            proc_close($proc);
            $launched = true;
        }
    } elseif (function_exists('shell_exec') && !in_array('shell_exec', $disabled, true)) {
        @shell_exec('cd ' . escapeshellarg($root) . ' && bash bin/start-realtime-hub.sh >/dev/null 2>&1 &');
        $launched = true;
    } else {
        @file_put_contents($root . '/storage/realtime-hub-start.requested', (string) time());
        return [
            'ok' => true,
            'running' => false,
            'message' => 'shell disabled — add cPanel cron: */5 * * * * pgrep -f rcc-realtime-hub.php || bash ' . $script,
        ];
    }

    if (!$launched) {
        return ['ok' => false, 'running' => false, 'message' => 'could not launch', 'error' => 'proc_open/shell_exec failed'];
    }

    usleep(800000);
    $status = control_contact_center_realtime_hub_status();
    return [
        'ok' => $status['running'],
        'running' => $status['running'],
        'message' => $status['running'] ? 'started on port ' . $status['port'] : 'launch sent — port ' . $status['port'] . ' not open yet (check log or cron)',
        'error' => $status['running'] ? '' : ($status['error'] ?? ''),
    ];
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
        if (!defined('RCC_SKIP_ORCHESTRATOR_BOOT')) {
            define('RCC_SKIP_ORCHESTRATOR_BOOT', true);
        }
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
    if (!defined('RCC_SKIP_ORCHESTRATOR_BOOT')) {
        define('RCC_SKIP_ORCHESTRATOR_BOOT', true);
    }
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

function control_contact_center_verify_schema(): array
{
    $required = [
        'rcc_tenants', 'rcc_users', 'rcc_realtime_events', 'rcc_ivr_flows', 'rcc_ivr_sessions',
        'rcc_agents', 'rcc_queues', 'rcc_sip_extensions', 'rcc_softphone_calls', 'rcc_routing_logs',
        'rcc_conversations', 'rcc_conversation_messages', 'rcc_ai_context', 'rcc_tickets',
    ];
    $missing = [];
    try {
        control_contact_center_apply_db_env();
        if (!defined('RCC_SKIP_ORCHESTRATOR_BOOT')) {
            define('RCC_SKIP_ORCHESTRATOR_BOOT', true);
        }
        require_once control_contact_center_root_path() . '/bootstrap.php';
        $pdo = \Ratib\ContactCenter\App\Core\Database::connection();
        foreach ($required as $table) {
            $stmt = $pdo->query("SHOW TABLES LIKE '" . str_replace("'", "''", $table) . "'");
            if ($stmt === false || $stmt->rowCount() === 0) {
                $missing[] = $table;
            }
        }
    } catch (Throwable $e) {
        return ['ok' => false, 'missing' => $required, 'error' => $e->getMessage()];
    }
    return ['ok' => $missing === [], 'missing' => $missing, 'error' => ''];
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
