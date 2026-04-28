<?php
/**
 * EN: Handles API endpoint/business logic in `api/hr/hr-connection.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/hr/hr-connection.php`.
 */
/**
 * HR API connection: main Ratib Pro DB by default; isolated CONTROL_PANEL_DB when ?control=1.
 */
function hr_api_is_control_request(): bool
{
    return isset($_GET['control']) && (string)$_GET['control'] === '1';
}

/** Accounting / global history only apply to Ratib Pro HR, not control-panel HR. */
function hr_api_writes_ratib_artifacts(): bool
{
    return !hr_api_is_control_request();
}

/**
 * Control HR (?control=1): only ratib_control + control_logged_in.
 * Avoids api-permission-helper / permissions.php / mysqli bootstrap that can fatally break API JSON.
 */
function hr_api_require_control_panel_auth(): void
{
    if (!hr_api_is_control_request()) {
        return;
    }
    if (session_status() === PHP_SESSION_NONE) {
        require_once __DIR__ . '/../core/ratib_api_session.inc.php';
        ratib_api_pick_session_name();
        session_start();
    }
    if (empty($_SESSION['control_logged_in'])) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Control panel login required. Sign in to the control panel in this browser, then retry.',
        ]);
        exit;
    }
}

/** Ratib Pro HR only — control requests must call hr_api_require_control_panel_auth() instead. */
function hr_api_enforce_employees_permission(string $action): void
{
    if (hr_api_is_control_request()) {
        return;
    }
    $helper = __DIR__ . '/../core/api-permission-helper.php';
    if (!function_exists('enforceApiPermission') && is_readable($helper)) {
        require_once $helper;
    }
    if (function_exists('enforceApiPermission')) {
        enforceApiPermission('employees', $action);
    }
}

function hr_api_get_connection(): PDO
{
    static $cached = null;
    static $cacheKey = null;
    $want = hr_api_is_control_request() ? 'control' : 'main';
    if ($cached !== null && $cacheKey === $want) {
        return $cached;
    }
    if ($want === 'control') {
        require_once __DIR__ . '/../../includes/config.php';
        if (!defined('CONTROL_PANEL_DB_NAME') || CONTROL_PANEL_DB_NAME === '') {
            throw new Exception('CONTROL_PANEL_DB_NAME is not configured; cannot isolate control HR data.');
        }
        if (defined('DB_NAME') && CONTROL_PANEL_DB_NAME === DB_NAME) {
            throw new Exception('Control HR isolation requires CONTROL_PANEL_DB_NAME to differ from DB_NAME.');
        }
        $host = defined('DB_HOST') ? DB_HOST : 'localhost';
        $port = defined('DB_PORT') ? (int)DB_PORT : 3306;
        $db = CONTROL_PANEL_DB_NAME;
        // Optional: dedicated MySQL user for the control DB when DB_USER only has the country schema.
        if (defined('CONTROL_PANEL_DB_USER') && CONTROL_PANEL_DB_USER !== '') {
            $user = CONTROL_PANEL_DB_USER;
            $pass = defined('CONTROL_PANEL_DB_PASS') ? CONTROL_PANEL_DB_PASS : '';
        } else {
            $user = defined('DB_USER') ? DB_USER : '';
            $pass = defined('DB_PASS') ? DB_PASS : '';
        }
        $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";
        try {
            $cached = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            error_log('HR control DB PDO failed [' . $db . ']: ' . $e->getMessage());
            throw new Exception(
                'Cannot connect to control HR database "' . $db . '". Create the database, run config/migrations/control_panel_hr_tables.sql, grant this MySQL user access (or set CONTROL_PANEL_DB_USER / CONTROL_PANEL_DB_PASS). Details: ' . $e->getMessage()
            );
        }
        $cacheKey = $want;
        return $cached;
    }
    // Ratib Pro PDO: Database::__construct loads includes/config.php, which require_once's config/env/load.php
    // first (session ini + session_start + host env), then mysqli + SINGLE_URL_MODE → $GLOBALS['agency_db'].
    require_once __DIR__ . '/../core/Database.php';
    $cached = Database::getInstance()->getConnection();
    $cacheKey = $want;
    return $cached;
}
