<?php
/**
 * EN: Handles control-panel module behavior and admin-country operations in `control-panel/includes/config.php`.
 * AR: يدير سلوك وحدة لوحة التحكم وعمليات إدارة الدول في `control-panel/includes/config.php`.
 */
/**
 * Control Panel - Standalone Configuration
 * Load this for the separated control panel. Uses only control DB and control admins.
 */
if (defined('CONTROL_CONFIG_LOADED')) {
    return;
}

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../core/bootstrap.php';
require_once __DIR__ . '/control/request-url.php';

// Session: use separate session name for control panel; path / so cookies reach /api/control/* (not only /pages/...)
if (session_status() === PHP_SESSION_NONE) {
    session_name('ratib_control');
    $sessSecure = control_request_is_https();
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $sessSecure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    } else {
        session_set_cookie_params(0, '/', '', $sessSecure, true);
    }
    session_start();
}

// Helper functions
if (!function_exists('getBaseUrl')) {
    function getBaseUrl() {
        return defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
    }
}
if (!function_exists('asset')) {
    function asset($path) {
        $base = getBaseUrl();
        $path = ltrim($path, '/');
        return ($base ? $base . '/' : '/') . $path;
    }
}
if (!function_exists('pageUrl')) {
    function pageUrl($page) {
        $base = getBaseUrl();
        $page = ltrim($page, '/');
        return ($base ? rtrim($base, '/') . '/' : '/') . 'pages/' . $page;
    }
}
if (!function_exists('apiUrl')) {
    function apiUrl($endpoint) {
        $base = getBaseUrl();
        $endpoint = ltrim($endpoint, '/');
        return ($base ? $base . '/' : '') . 'api/' . $endpoint;
    }
}
if (!function_exists('control_panel_page_with_control')) {
    /** Canonical control-panel URL with ?control=1 for routing and embed flags. */
    function control_panel_page_with_control(string $pagePath): string {
        $u = pageUrl($pagePath);
        return $u . (strpos($u, '?') !== false ? '&' : '?') . 'control=1';
    }
}

date_default_timezone_set('Asia/Riyadh');
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php-errors.log');
define('PRODUCTION_MODE', true);
define('DEBUG_MODE', false);

// Control panel: connect to control DB
$controlDb = defined('CONTROL_PANEL_DB_NAME') ? CONTROL_PANEL_DB_NAME : DB_NAME;
try {
    if (function_exists('mysqli_report') && defined('MYSQLI_REPORT_ERROR') && defined('MYSQLI_REPORT_STRICT')) {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    }
    $GLOBALS['control_conn'] = new mysqli(DB_HOST, DB_USER, DB_PASS, $controlDb, DB_PORT);
    $GLOBALS['control_conn']->set_charset("utf8mb4");
    $GLOBALS['conn'] = $GLOBALS['control_conn'];
} catch (Throwable $e) {
    error_log("Control panel DB failed: " . $e->getMessage());
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        die("Control panel database unavailable: " . $e->getMessage());
    }
    $GLOBALS['control_conn'] = null;
    $GLOBALS['conn'] = null;
}

// Refresh control permissions from DB on every load
if (!empty($_SESSION['control_logged_in']) && isset($GLOBALS['control_conn']) && $GLOBALS['control_conn'] instanceof mysqli) {
    $cid = isset($_SESSION['control_user_id']) ? (int)$_SESSION['control_user_id'] : 0;
    if ($cid > 0) {
        try {
            $ctrl = $GLOBALS['control_conn'];
            $chk = $ctrl->query("SHOW TABLES LIKE 'control_admin_permissions'");
            if ($chk && $chk->num_rows > 0) {
                $pStmt = $ctrl->prepare("SELECT permissions FROM control_admin_permissions WHERE user_id = ? LIMIT 1");
                if ($pStmt) {
                    $pStmt->bind_param("i", $cid);
                    $pStmt->execute();
                    $res = $pStmt->get_result();
                    $row = $res ? $res->fetch_assoc() : null;
                    $pStmt->close();
                    if ($row !== null) {
                        $decoded = json_decode($row['permissions'] ?? '', true);
                        $_SESSION['control_permissions'] = is_array($decoded) ? $decoded : [];
                        if (empty($_SESSION['control_permissions'])) {
                            $_SESSION['control_permissions'] = ['*'];
                        }
                    } else {
                        $_SESSION['control_permissions'] = ['*'];
                    }
                }
            }
            $un = strtolower(trim($_SESSION['control_username'] ?? ''));
            if ($un === 'admin' && (empty($_SESSION['control_permissions']) || !in_array('*', $_SESSION['control_permissions'] ?? [], true))) {
                $_SESSION['control_permissions'] = ['*'];
            }
        } catch (Throwable $e) { /* keep existing */ }
    }
}

require_once __DIR__ . '/control-permissions.php';

// Handle agency switching and "own program"
$ctrl = $GLOBALS['control_conn'] ?? null;
if (!empty($_SESSION['control_logged_in']) && isset($_GET['own']) && $_GET['own'] === '1') {
    $_SESSION['control_use_own_program'] = true;
    $_SESSION['control_agency_id'] = null;
    $_SESSION['control_agency_name'] = null;
    $_SESSION['control_country_id'] = null;
    $_SESSION['control_country_name'] = null;
}
if (!empty($_SESSION['control_logged_in']) && isset($_GET['agency_id']) && ctype_digit($_GET['agency_id']) && $ctrl instanceof mysqli) {
    if (hasControlPermission('control_agencies') || hasControlPermission('view_control_agencies') || hasControlPermission('open_control_agency')) {
        $aid = (int)$_GET['agency_id'];
        $stmt = $ctrl->prepare("SELECT id, name, site_url, country_id FROM control_agencies WHERE id = ? AND is_active = 1 AND COALESCE(is_suspended, 0) = 0 LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("i", $aid);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res && $res->num_rows > 0) {
                $row = $res->fetch_assoc();
                $stmt->close();
                $allowedIds = getAllowedCountryIds($ctrl);
                if ($allowedIds === null || in_array((int)$row['country_id'], $allowedIds, true)) {
                    unset($_SESSION['control_use_own_program']);
                    $_SESSION['control_agency_id'] = (int)$row['id'];
                    $_SESSION['control_agency_name'] = $row['name'];
                    $_SESSION['control_country_id'] = null;
                    $_SESSION['control_country_name'] = null;
                    if (isset($row['country_id']) && (int)$row['country_id'] > 0) {
                        $cStmt = $ctrl->prepare("SELECT id, name FROM control_countries WHERE id = ? AND is_active = 1 LIMIT 1");
                        if ($cStmt) {
                            $cStmt->bind_param("i", $row['country_id']);
                            $cStmt->execute();
                            $cRes = $cStmt->get_result();
                            if ($cRes && $cRes->num_rows > 0) {
                                $cRow = $cRes->fetch_assoc();
                                $_SESSION['control_country_id'] = (int)$cRow['id'];
                                $_SESSION['control_country_name'] = $cRow['name'];
                            }
                            $cStmt->close();
                        }
                    }
                    header('Location: ' . pageUrl('control/dashboard.php'));
                    exit;
                }
            }
        }
    }
}

// Set conn based on agency selection
$agencyId = isset($_SESSION['control_agency_id']) ? (int)$_SESSION['control_agency_id'] : 0;
$useOwnProgram = !empty($_SESSION['control_use_own_program']);
if ($useOwnProgram && isset($GLOBALS['control_conn'])) {
    $GLOBALS['conn'] = $GLOBALS['control_conn'];
} elseif ($agencyId > 0 && $ctrl instanceof mysqli) {
    $stmt = $ctrl->prepare("SELECT db_host, db_port, db_user, db_pass, db_name, name FROM control_agencies WHERE id = ? AND is_active = 1 AND COALESCE(is_suspended, 0) = 0 LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("i", $agencyId);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $res->num_rows > 0) {
            $agency = $res->fetch_assoc();
            $stmt->close();
            try {
                $port = (int)($agency['db_port'] ?? 3306);
                $GLOBALS['conn'] = new mysqli($agency['db_host'], $agency['db_user'], $agency['db_pass'], $agency['db_name'], $port);
                $GLOBALS['conn']->set_charset("utf8mb4");
                $GLOBALS['agency_db'] = ['host' => $agency['db_host'], 'port' => $port, 'db' => $agency['db_name'], 'user' => $agency['db_user'], 'pass' => $agency['db_pass']];
            } catch (Exception $e) {
                error_log("Control panel - agency DB failed: " . $e->getMessage());
                $GLOBALS['conn'] = $GLOBALS['control_conn'];
            }
        } else {
            $GLOBALS['conn'] = $GLOBALS['control_conn'];
        }
    } else {
        $GLOBALS['conn'] = $GLOBALS['control_conn'];
    }
} else {
    $GLOBALS['conn'] = $GLOBALS['control_conn'] ?? null;
    if (!empty($_SESSION['control_logged_in']) && empty($_SESSION['control_agency_id']) && empty($_SESSION['control_use_own_program'])) {
        $req = $_SERVER['REQUEST_URI'] ?? '';
        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        $isLogin = (strpos($req . ' ' . $script, 'login.php') !== false);
        // Allow control UI paths without literal "control/" (hyphenated legacy filenames under /pages/).
        // Otherwise embedded pages like pages/control-support-chats.php redirect → iframe breakout sends whole app to Select Country.
        $isSelect = (strpos($req, 'select-') !== false) || (strpos($req, 'control/') !== false) || (strpos($req, 'dashboard') !== false) || (strpos($req, '/api/') !== false)
            || (stripos($req, 'support-chats') !== false);
        $isLogout = (strpos($req, 'logout.php') !== false);
        $isSystemSettings = (strpos($req, 'system-settings') !== false) || (strpos($req, 'control-panel-settings.php') !== false) || (strpos($req, 'panel-settings.php') !== false) || (strpos($req, 'panel-users.php') !== false) || (strpos($req, 'control-panel-users.php') !== false);
        if (!$isLogin && !$isSelect && !$isLogout && !$isSystemSettings) {
            header('Location: ' . pageUrl('select-country.php'));
            exit;
        }
    }
}

define('CONTROL_CONFIG_LOADED', true);
