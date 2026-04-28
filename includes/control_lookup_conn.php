<?php
/**
 * EN: Handles shared bootstrap/helpers/layout partial behavior in `includes/control_lookup_conn.php`.
 * AR: يدير سلوك الملفات المشتركة للإعدادات والمساعدات وأجزاء التخطيط في `includes/control_lookup_conn.php`.
 */
/**
 * Control panel DB lookup for Ratib Pro (main app).
 * When SINGLE_URL_MODE is on, countries and agency DB credentials are read from
 * the control panel DB so one source of truth is used and each country uses its own DB.
 */
if (!function_exists('get_control_lookup_conn')) {
    /**
     * @return mysqli|null Connection to control panel DB for reading control_countries / control_agencies, or null
     */
    function get_control_lookup_conn() {
        static $controlConn = null;
        if ($controlConn !== null) {
            return $controlConn;
        }
        if (!defined('SINGLE_URL_MODE') || !SINGLE_URL_MODE) {
            return null;
        }
        if (!defined('CONTROL_PANEL_DB_NAME') || CONTROL_PANEL_DB_NAME === '') {
            return null;
        }
        $host = defined('DB_HOST') ? DB_HOST : 'localhost';
        $port = defined('DB_PORT') ? (int)DB_PORT : 3306;
        $user = defined('DB_USER') ? DB_USER : '';
        $pass = defined('DB_PASS') ? DB_PASS : '';
        if ($user === '') {
            return null;
        }
        try {
            $controlConn = @new mysqli($host, $user, $pass, CONTROL_PANEL_DB_NAME, $port);
            if ($controlConn && !$controlConn->connect_error) {
                $controlConn->set_charset('utf8mb4');
                return $controlConn;
            }
            if ($controlConn) {
                $controlConn->close();
            }
            $controlConn = null;
        } catch (Throwable $e) {
            error_log('Control lookup conn: ' . $e->getMessage());
            $controlConn = null;
        }
        return null;
    }
}

if (!function_exists('ratib_control_agencies_has_is_suspended')) {
    /**
     * Older installs may not have control_agencies.is_suspended — avoid "Unknown column" in WHERE.
     */
    function ratib_control_agencies_has_is_suspended(?mysqli $conn): bool
    {
        static $cache = [];
        if (!$conn instanceof mysqli) {
            return false;
        }
        $k = spl_object_hash($conn);
        if (!array_key_exists($k, $cache)) {
            $cache[$k] = false;
            try {
                $t = @$conn->query("SHOW TABLES LIKE 'control_agencies'");
                if ($t && $t->num_rows > 0) {
                    $c = @$conn->query("SHOW COLUMNS FROM control_agencies LIKE 'is_suspended'");
                    $cache[$k] = ($c && $c->num_rows > 0);
                }
            } catch (Throwable $e) {
                $cache[$k] = false;
            }
        }
        return $cache[$k];
    }
}

if (!function_exists('ratib_control_agency_active_fragment')) {
    /**
     * SQL fragment for "agency not suspended" (1=1 if column missing).
     *
     * @param mysqli|null $conn Same connection used for the query (correct DB for SHOW COLUMNS).
     * @param string|null $alias Table alias without dot, e.g. 'a' for COALESCE(a.is_suspended,0)=0
     */
    function ratib_control_agency_active_fragment(?mysqli $conn, ?string $alias = null): string
    {
        if (!ratib_control_agencies_has_is_suspended($conn)) {
            return '1=1';
        }
        $p = ($alias !== null && $alias !== '') ? $alias . '.' : '';
        return 'COALESCE(' . $p . 'is_suspended, 0) = 0';
    }
}
