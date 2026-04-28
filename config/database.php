<?php
/**
 * EN: Handles configuration/runtime setup behavior in `config/database.php`.
 * AR: يدير سلوك إعدادات النظام وتهيئة التشغيل في `config/database.php`.
 */
/**
 * Database config — uses same env as includes/config.php (each link = own data).
 */
if (!defined('ENV_LOADED')) {
    require_once __DIR__ . '/env/load.php';
}
if (!defined('DB_HOST')) {
    define('DB_HOST', 'localhost');
    define('DB_PORT', 3306);
    define('DB_USER', 'outratib_out');
    define('DB_PASS', '9s%BpMr1]dfb');
    define('DB_NAME', 'outratib_out');
}
if (!defined('SITE_URL')) {
    define('SITE_URL', 'https://bangladesh.out.ratib.sa');
}
if (!defined('APP_NAME')) {
    define('APP_NAME', 'Ratib Program');
}
if (!defined('APP_VERSION')) {
    define('APP_VERSION', '1.0.0');
}
if (!defined('BASE_URL')) {
    define('BASE_URL', '');
}

// Helper functions (wrap in function_exists so safe if includes/config.php was already loaded)
if (!function_exists('getBaseUrl')) {
    function getBaseUrl() {
        return defined('BASE_URL') ? BASE_URL : '';
    }
}
if (!function_exists('asset')) {
    function asset($path) {
        $base = getBaseUrl();
        $path = ltrim($path, '/');
        return $base . '/' . $path;
    }
}
if (!function_exists('apiUrl')) {
    function apiUrl($endpoint) {
        $base = getBaseUrl();
        $endpoint = ltrim($endpoint, '/');
        return $base . '/api/' . $endpoint;
    }
}
if (!function_exists('pageUrl')) {
    function pageUrl($page) {
        $base = getBaseUrl();
        $page = ltrim($page, '/');
        return $base . '/pages/' . $page;
    }
}

// Session Configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
// Only enable secure cookies if HTTPS is being used
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    ini_set('session.cookie_secure', 1); // HTTPS enabled
} else {
    ini_set('session.cookie_secure', 0); // Allow HTTP for localhost
}

// Timezone Configuration
date_default_timezone_set('Asia/Riyadh');

// Error Reporting (Production Settings)
// Log all errors but don't display them to users
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't show errors to users
ini_set('log_errors', 1); // Log errors to file
ini_set('error_log', __DIR__ . '/../logs/php-errors.log');

// Production Mode Flag
define('PRODUCTION_MODE', true);
define('DEBUG_MODE', false);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database Connection (mysqli)
if (!isset($GLOBALS['conn']) || $GLOBALS['conn'] === null) {
    try {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        $GLOBALS['conn'] = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
        $GLOBALS['conn']->set_charset("utf8mb4");
        // Make connection available globally
        $conn = $GLOBALS['conn'];
    } catch (Exception $e) {
        error_log("Failed to create database connection in config.php: " . $e->getMessage());
        // Don't set to null, let individual files handle connection errors
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            die("Database connection failed: " . $e->getMessage());
        }
    }
}

// Make $conn available globally if it was created
if (isset($GLOBALS['conn']) && $GLOBALS['conn'] !== null) {
    $conn = $GLOBALS['conn'];
}
