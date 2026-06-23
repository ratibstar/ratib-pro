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

function control_contact_center_migrate_token_expected(): string
{
    if (defined('RATEB_ERP_MIGRATE_TOKEN') && (string) RATEB_ERP_MIGRATE_TOKEN !== '') {
        return (string) RATEB_ERP_MIGRATE_TOKEN;
    }
    $fromEnv = getenv('CPANEL_API_TOKEN');
    if ($fromEnv !== false && $fromEnv !== '') {
        return (string) $fromEnv;
    }
    $rccRoot = control_contact_center_root_path();
    $tokenPaths = [
        $rccRoot . '/storage/deploy-migrate-token',
        dirname($rccRoot) . '/rateb-erp/storage/deploy-migrate-token',
    ];
    foreach ($tokenPaths as $tokenFile) {
        if (is_file($tokenFile)) {
            return trim((string) file_get_contents($tokenFile));
        }
    }

    return '';
}

function control_contact_center_verify_migrate_token(?string $provided = null): bool
{
    $provided = trim($provided ?? (string) ($_SERVER['HTTP_X_RATEB_MIGRATE_TOKEN'] ?? ''));
    if ($provided === '') {
        return false;
    }
    $expected = control_contact_center_migrate_token_expected();
    if ($expected === '') {
        return false;
    }

    return hash_equals($expected, $provided);
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

function control_contact_center_ops_page_url(string $route = 'health'): string
{
    $base = function_exists('control_panel_page_with_control')
        ? control_panel_page_with_control('control/contact-center-ops.php')
        : '/control-panel/pages/control/contact-center-ops.php?control=1';
    return $base . '&route=' . rawurlencode($route);
}

function control_contact_center_ops_api_url(): string
{
    return control_contact_center_public_base_url() . '/api/v1/ops.php';
}

function control_contact_center_supervisor_page_url(string $route = 'dashboard'): string
{
    $base = function_exists('control_panel_page_with_control')
        ? control_panel_page_with_control('control/contact-center-supervisor.php')
        : '/control-panel/pages/control/contact-center-supervisor.php?control=1';
    return $base . '&route=' . rawurlencode($route);
}

function control_contact_center_supervisor_api_url(): string
{
    return control_contact_center_public_base_url() . '/api/v1/supervisor.php';
}

function control_contact_center_crm_page_url(string $route = 'accounts'): string
{
    $base = function_exists('control_panel_page_with_control')
        ? control_panel_page_with_control('control/contact-center-crm.php')
        : '/control-panel/pages/control/contact-center-crm.php?control=1';
    return $base . '&route=' . rawurlencode($route);
}

function control_contact_center_crm_api_url(): string
{
    return control_contact_center_public_base_url() . '/api/v1/crm.php';
}

function control_contact_center_tickets_api_url(): string
{
    return control_contact_center_public_base_url() . '/api/v1/tickets.php';
}

function control_contact_center_analytics_api_url(): string
{
    return control_contact_center_public_base_url() . '/api/v1/analytics.php';
}

function control_contact_center_command_page_url(): string
{
    return function_exists('control_panel_page_with_control')
        ? control_panel_page_with_control('control/contact-center-command-center.php')
        : '/control-panel/pages/control/contact-center-command-center.php?control=1';
}

/** Resolve tenant for RCC UI/API — never hardcode; session first, else first active tenant. */
function control_contact_center_resolve_tenant_id(): int
{
    $fromSession = (int) ($_SESSION['rcc_tenant_id'] ?? 0);
    if ($fromSession > 0) {
        return $fromSession;
    }
    try {
        control_contact_center_apply_db_env();
        if (!defined('RCC_SKIP_ORCHESTRATOR_BOOT')) {
            define('RCC_SKIP_ORCHESTRATOR_BOOT', true);
        }
        require_once control_contact_center_root_path() . '/bootstrap.php';
        $stmt = \Ratib\ContactCenter\App\Core\Database::connection()->query(
            "SELECT id FROM rcc_tenants WHERE status = 'active' ORDER BY id ASC LIMIT 1"
        );
        $id = $stmt ? $stmt->fetchColumn() : false;
        return $id !== false ? (int) $id : 0;
    } catch (Throwable $e) {
        return 0;
    }
}

/** Resolve agent for RCC UI — session first, else match CP user email to rcc_agents. */
function control_contact_center_resolve_agent_id(int $tenantId): int
{
    if ($tenantId < 1) {
        return 0;
    }
    $fromSession = (int) ($_SESSION['rcc_agent_id'] ?? 0);
    if ($fromSession > 0) {
        return $fromSession;
    }
    $email = (string) ($_SESSION['control_user_email'] ?? $_SESSION['control_email'] ?? '');
    if ($email === '') {
        return 0;
    }
    try {
        control_contact_center_apply_db_env();
        if (!defined('RCC_SKIP_ORCHESTRATOR_BOOT')) {
            define('RCC_SKIP_ORCHESTRATOR_BOOT', true);
        }
        require_once control_contact_center_root_path() . '/bootstrap.php';
        $stmt = \Ratib\ContactCenter\App\Core\Database::connection()->prepare(
            'SELECT a.id FROM rcc_agents a
             INNER JOIN rcc_users u ON u.id = a.user_id AND u.tenant_id = a.tenant_id
             WHERE a.tenant_id = :tid AND u.email = :email AND a.status = \'active\'
             LIMIT 1'
        );
        $stmt->execute(['tid' => $tenantId, 'email' => $email]);
        $id = $stmt->fetchColumn();
        return $id !== false ? (int) $id : 0;
    } catch (Throwable $e) {
        return 0;
    }
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
    $files = array_values(array_filter(
        glob($dir . '/*.sql') ?: [],
        static fn (string $path): bool => is_file($path) && !str_contains($path, DIRECTORY_SEPARATOR . 'archive' . DIRECTORY_SEPARATOR)
    ));
    sort($files);
    $log = [];

    // Legacy filenames removed during 001–012 renumber (Jun 2026). Mark applied so orphan
    // copies left on the server by fast-deploy are never re-executed.
    $retiredMigrations = [
        '001_core_tenancy.sql',
        '002_ivr_runtime_engine.sql',
        '003_queue_ticket_stub.sql',
        '004_ivr_example_flow.sql',
        '005_realtime_core.sql',
        '006_softphone.sql',
        '010_rcc_tickets_ai_columns.sql',
    ];
    foreach ($retiredMigrations as $retired) {
        try {
            $ins = $pdo->prepare('INSERT IGNORE INTO rcc_migration_log (migration, batch) VALUES (:m, 0)');
            $ins->execute(['m' => $retired]);
        } catch (Throwable $e) {
            // rcc_migration_log may not exist until 001 runs
        }
    }

    foreach ($files as $file) {
        $name = basename($file);
        if (in_array($name, $retiredMigrations, true)) {
            continue;
        }
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
        control_contact_center_exec_sql_batch($pdo, $sql, $log, $name);
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

function control_contact_center_exec_sql_batch(\PDO $pdo, string $sql, ?array &$log = null, ?string $migration = null): void
{
    $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
    $parts = preg_split('/;\s*(?:\r?\n|$)/', $sql) ?: [];
    foreach ($parts as $stmt) {
        $stmt = trim($stmt);
        if ($stmt === '') {
            continue;
        }
        try {
            $pdo->exec($stmt);
        } catch (\PDOException $e) {
            $mysqlCode = (int) ($e->errorInfo[1] ?? 0);
            // Idempotent re-runs: duplicate column/key, existing table/index (production drift).
            $ignorable = [1050, 1060, 1061, 1062, 1091, 1826];
            if (in_array($mysqlCode, $ignorable, true)) {
                if ($log !== null) {
                    $log[] = 'WARN ' . ($migration ?? 'sql') . ': ignored MySQL ' . $mysqlCode . ' — ' . $e->getMessage();
                }
                continue;
            }
            throw $e;
        }
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
        'operations' => [
            'route' => 'health',
            'href' => control_contact_center_ops_page_url('health'),
            'label' => 'Operations Center',
            'icon' => 'fa-screwdriver-wrench',
            'key' => 'operations',
            'description' => 'PBX wizard, SIP, queues, IVR, agents, diagnostics, go-live checklist.',
        ],
        'supervisor' => [
            'route' => 'dashboard',
            'href' => control_contact_center_supervisor_page_url('dashboard'),
            'label' => 'Supervisor Suite',
            'icon' => 'fa-chart-line',
            'key' => 'supervisor',
            'description' => 'Live wallboard, queue/agent monitors, SLA, workforce, shifts, alerts.',
        ],
        'crm' => [
            'route' => 'accounts',
            'href' => control_contact_center_crm_page_url('accounts'),
            'label' => 'Enterprise CRM',
            'icon' => 'fa-address-book',
            'key' => 'crm',
            'description' => 'Accounts, contacts, timeline, tags, documents, ERP sync.',
        ],
        'command' => [
            'route' => 'command',
            'href' => control_contact_center_command_page_url(),
            'label' => 'Command Center',
            'icon' => 'fa-satellite-dish',
            'key' => 'command',
            'description' => 'Executive KPIs, live wallboard, ticket backlog, AI alerts.',
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
        'rcc_pbx_servers', 'rcc_ops_checklist_steps', 'rcc_ops_checklist_status',
        'rcc_wfm_shifts', 'rcc_wfm_shift_assignments', 'rcc_wfm_attendance', 'rcc_wfm_breaks',
        'rcc_supervisor_alerts', 'rcc_supervisor_alert_rules', 'rcc_audit_logs',
        'rcc_accounts', 'rcc_contact_notes', 'rcc_ticket_comments', 'rcc_qa_forms',
        'rcc_recordings', 'rcc_metrics_daily', 'rcc_kpis', 'rcc_kb_articles',
        'rcc_api_rate_limits',
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
