<?php
/**
 * EN: Handles shared bootstrap/helpers/layout partial behavior in `includes/support_chat_db.php`.
 * AR: يدير سلوك الملفات المشتركة للإعدادات والمساعدات وأجزاء التخطيط في `includes/support_chat_db.php`.
 */
/**
 * Connection for control_support_chats / control_support_chat_messages.
 * Always prefers the control panel database by name so escalations work even when
 * $GLOBALS['conn'] is switched to a per-country DB (SINGLE_URL_MODE after login).
 */
if (!function_exists('ratib_support_chat_db')) {
    /**
     * @return mysqli|null
     */
    function ratib_support_chat_db() {
        static $resolved = false;
        static $db = null;
        if ($resolved) {
            return $db;
        }
        $resolved = true;

        $try = static function ($mysqli) {
            if (!$mysqli instanceof mysqli || $mysqli->connect_errno) {
                return null;
            }
            $chk = @$mysqli->query("SHOW TABLES LIKE 'control_support_chats'");
            if (!$chk || $chk->num_rows === 0) {
                return null;
            }
            $chk2 = @$mysqli->query("SHOW TABLES LIKE 'control_support_chat_messages'");
            return ($chk2 && $chk2->num_rows > 0) ? $mysqli : null;
        };

        if (function_exists('get_control_lookup_conn')) {
            $db = $try(get_control_lookup_conn());
            if ($db) {
                return $db;
            }
        }

        if (defined('CONTROL_PANEL_DB_NAME') && CONTROL_PANEL_DB_NAME !== '') {
            try {
                mysqli_report(MYSQLI_REPORT_OFF);
                $host = defined('DB_HOST') ? DB_HOST : 'localhost';
                $port = defined('DB_PORT') ? (int) DB_PORT : 3306;
                $user = defined('DB_USER') ? DB_USER : '';
                $pass = defined('DB_PASS') ? DB_PASS : '';
                if ($user !== '') {
                    $m = @new mysqli($host, $user, $pass, CONTROL_PANEL_DB_NAME, $port);
                    if ($m && !$m->connect_error) {
                        $m->set_charset('utf8mb4');
                        $db = $try($m);
                        if ($db) {
                            return $db;
                        }
                        $m->close();
                    }
                }
            } catch (Throwable $e) {
                error_log('ratib_support_chat_db: ' . $e->getMessage());
            }
        }

        $db = $try($GLOBALS['conn'] ?? null);
        return $db;
    }
}

if (!function_exists('ratib_support_chat_has_context_columns')) {
    function ratib_support_chat_has_context_columns(mysqli $conn) {
        static $cache = [];
        $key = spl_object_hash($conn);
        if (!array_key_exists($key, $cache)) {
            $cache[$key] = $conn->query("SHOW COLUMNS FROM control_support_chats LIKE 'country_id'")->num_rows > 0;
        }
        return $cache[$key];
    }
}
