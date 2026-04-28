<?php
/**
 * EN: Handles configuration/runtime setup behavior in `config/env/agency_resolver.php`.
 * AR: يدير سلوك إعدادات النظام وتهيئة التشغيل في `config/env/agency_resolver.php`.
 */
/**
 * Resolve agency config from control_agencies by HTTP_HOST.
 * When a new agency visits their site_url (e.g. https://newagency.out.ratib.sa),
 * we look up their DB credentials and use them - no manual env file needed.
 *
 * @param string $host HTTP_HOST (e.g. newagency.out.ratib.sa)
 * @return bool true if agency found and DB_* defined, false otherwise
 */
function resolve_agency_by_host($host) {
    if (empty($host) || defined('DB_NAME')) {
        return false;
    }
    $env_dir = __DIR__;
    $lookupFile = $env_dir . DIRECTORY_SEPARATOR . 'control_db_for_lookup.php';
    if (!is_readable($lookupFile)) {
        error_log('Agency resolver: control_db_for_lookup.php missing or unreadable; skipping lookup.');
        return false;
    }
    require_once $lookupFile;
    try {
        $conn = new mysqli(
            CONTROL_DB_HOST,
            CONTROL_DB_USER,
            CONTROL_DB_PASS,
            CONTROL_DB_NAME,
            CONTROL_DB_PORT
        );
        $conn->set_charset('utf8mb4');
    } catch (Throwable $e) {
        error_log('Agency resolver: Control DB connection failed: ' . $e->getMessage());
        return false;
    }
    $chk = @$conn->query("SHOW TABLES LIKE 'control_agencies'");
    if (!$chk || $chk->num_rows === 0) {
        $conn->close();
        return false;
    }
    $stmt = $conn->prepare("SELECT db_host, db_port, db_user, db_pass, db_name, site_url FROM control_agencies WHERE (site_url = ? OR site_url = ? OR site_url = ? OR site_url = ? OR site_url LIKE ? OR site_url LIKE ?) AND is_active = 1 LIMIT 1");
    if (!$stmt) {
        $conn->close();
        return false;
    }
    $https = 'https://' . $host;
    $httpsSlash = 'https://' . $host . '/';
    $http = 'http://' . $host;
    $httpSlash = 'http://' . $host . '/';
    $httpsLike = 'https://' . $host . '/%';
    $httpLike = 'http://' . $host . '/%';
    $stmt->bind_param('ssssss', $https, $httpsSlash, $http, $httpSlash, $httpsLike, $httpLike);
    $stmt->execute();
    $res = $stmt->get_result();
    $stmt->close();
    $conn->close();
    if (!$res || $res->num_rows === 0) {
        return false;
    }
    $row = $res->fetch_assoc();
    define('DB_HOST', $row['db_host']);
    define('DB_PORT', (int)($row['db_port'] ?? 3306));
    define('DB_USER', $row['db_user']);
    define('DB_PASS', $row['db_pass']);
    define('DB_NAME', $row['db_name']);
    if (!defined('CONTROL_PANEL_DB_NAME')) {
        $_cp = getenv('CONTROL_PANEL_DB_NAME');
        define('CONTROL_PANEL_DB_NAME', ($_cp !== false && $_cp !== '') ? $_cp : 'outratib_control_panel_db');
    }
    define('SITE_URL', rtrim(rtrim($row['site_url'] ?? '', '/')) ?: ('https://' . $host));
    define('APP_NAME', 'Ratib Program');
    define('APP_VERSION', '1.0.0');
    define('BASE_URL', '');
    return true;
}
