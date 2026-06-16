<?php
/**
 * Public marketing copy stored in the control-panel database (key/value).
 * Safe no-op if DB unavailable — callers use defaults.
 */
if (!defined('RATEB_SITE_CONTENT_HOME_SNAPSHOT_KEY')) {
    /** Reserved row in rateb_site_content: full homepage JSON when disk cache cannot be written. */
    define('RATEB_SITE_CONTENT_HOME_SNAPSHOT_KEY', '__rateb_home_json_snapshot.v1__');
}

if (!function_exists('rateb_site_content_sql_table')) {
    /** Resolved once per request — supports legacy DB table until RENAME is applied on server. */
    function rateb_site_content_sql_table(?mysqli $conn = null): string
    {
        static $resolved = null;
        if ($resolved !== null) {
            return $resolved;
        }
        $resolved = 'rateb_site_content';
        if ($conn instanceof mysqli) {
            foreach (['rateb_site_content', 'ratib_site_content'] as $candidate) {
                $esc = $conn->real_escape_string($candidate);
                $probe = @$conn->query("SHOW TABLES LIKE '{$esc}'");
                if ($probe && $probe->num_rows > 0) {
                    $resolved = $candidate;
                    break;
                }
            }
        }
        return $resolved;
    }
}

if (!function_exists('rateb_site_content_db_credentials')) {
    /**
     * Resolve connection params for reading rateb_site_content (control DB).
     * Public site often uses DB_NAME for the tenant DB; the CMS lives in CONTROL_PANEL_DB_NAME.
     *
     * Optional env (recommended when the app DB user has no access to the control DB):
     *   RATEB_SITE_CONTENT_DB_HOST, RATEB_SITE_CONTENT_DB_PORT, RATEB_SITE_CONTENT_DB_USER,
     *   RATEB_SITE_CONTENT_DB_PASS, RATEB_SITE_CONTENT_DB_NAME
     * Or getenv CONTROL_DB_USER / CONTROL_DB_PASS (same as control-panel/config/env.php), CONTROL_PANEL_DB_USER / CONTROL_PANEL_DB_PASS, or DB_*.
     *
     * Homepage JSON snapshot path (optional — overrides automatic candidates):
     *   RATEB_SITE_CONTENT_CACHE_FILE=/absolute/or/project-relative/path/rateb_site_content_home.json
     *
     * @return array{0:string,1:int,2:string,3:string,4:string}|null
     */
    function rateb_site_content_db_credentials(): ?array
    {
        if (!defined('CONTROL_PANEL_DB_NAME')) {
            return null;
        }
        $host = getenv('RATEB_SITE_CONTENT_DB_HOST');
        if ($host === false || $host === '') {
            $hCp = getenv('CONTROL_DB_HOST');
            $host = ($hCp !== false && $hCp !== '') ? (string) $hCp : (defined('DB_HOST') ? DB_HOST : '');
        } else {
            $host = (string) $host;
        }
        $portRaw = getenv('RATEB_SITE_CONTENT_DB_PORT');
        if ($portRaw !== false && $portRaw !== '') {
            $port = (int) $portRaw;
        } else {
            $pCp = getenv('CONTROL_DB_PORT');
            $port = ($pCp !== false && $pCp !== '') ? (int) $pCp : (defined('DB_PORT') ? (int) DB_PORT : 3306);
        }
        $dbName = getenv('RATEB_SITE_CONTENT_DB_NAME');
        $dbName = ($dbName !== false && $dbName !== '') ? (string) $dbName : CONTROL_PANEL_DB_NAME;

        $user = getenv('RATEB_SITE_CONTENT_DB_USER');
        if ($user === false || $user === '') {
            $uCp = getenv('CONTROL_DB_USER');
            if ($uCp !== false && $uCp !== '') {
                $user = (string) $uCp;
            } elseif (defined('CONTROL_PANEL_DB_USER') && (string) CONTROL_PANEL_DB_USER !== '') {
                $user = (string) CONTROL_PANEL_DB_USER;
            } else {
                $user = defined('DB_USER') ? (string) DB_USER : '';
            }
        } else {
            $user = (string) $user;
        }
        $pass = getenv('RATEB_SITE_CONTENT_DB_PASS');
        if ($pass === false) {
            $pEnv = getenv('CONTROL_DB_PASS');
            if ($pEnv !== false) {
                $pass = (string) $pEnv;
            } elseif (defined('CONTROL_PANEL_DB_PASS')) {
                $pass = (string) CONTROL_PANEL_DB_PASS;
            } else {
                $pass = defined('DB_PASS') ? (string) DB_PASS : '';
            }
        } else {
            $pass = (string) $pass;
        }

        if ($host === '' || $user === '') {
            return null;
        }

        return [$host, $port, $user, $pass, $dbName];
    }
}

if (!function_exists('rateb_site_content_db_credentials_app_to_control')) {
    /**
     * Same pattern as get_control_lookup_conn(): app DB_USER + DB_PASS opening CONTROL_PANEL_DB_NAME.
     * Used when CONTROL_PANEL_DB_USER / CONTROL_PANEL_DB_PASS are wrong but the tenant user has SELECT on rateb_site_content.
     *
     * @return array{0:string,1:int,2:string,3:string,4:string}|null
     */
    function rateb_site_content_db_credentials_app_to_control(): ?array
    {
        if (!defined('CONTROL_PANEL_DB_NAME') || CONTROL_PANEL_DB_NAME === '') {
            return null;
        }
        if (!defined('DB_HOST') || !defined('DB_USER') || (string) DB_USER === '') {
            return null;
        }
        $host = (string) DB_HOST;
        $port = defined('DB_PORT') ? (int) DB_PORT : 3306;
        $pass = defined('DB_PASS') ? (string) DB_PASS : '';

        return [$host, $port, (string) DB_USER, $pass, CONTROL_PANEL_DB_NAME];
    }
}

if (!function_exists('rateb_site_content_db_try_mysqli_once')) {
    /**
     * Single mysqli attempt (no localhost fallback).
     *
     * @param array{0:string,1:int,2:string,3:string,4:string} $cred
     */
    function rateb_site_content_db_try_mysqli_once(array $cred): ?mysqli
    {
        [$host, $port, $user, $pass, $dbName] = $cred;
        if ($host === '' || $user === '' || $dbName === '') {
            return null;
        }
        try {
            $c = new mysqli($host, $user, $pass, $dbName, $port);
            if ($c->connect_errno) {
                error_log('rateb_site_content_db: mysqli failed for user "' . $user . '" @ "' . $host . '" (' . $c->connect_errno . ') ' . $c->connect_error);
                $c->close();

                return null;
            }
            $c->set_charset('utf8mb4');

            return $c;
        } catch (Throwable $e) {
            error_log('rateb_site_content_db: ' . $e->getMessage());

            return null;
        }
    }
}

if (!function_exists('rateb_site_content_db_try_mysqli')) {
    /**
     * Try mysqli; if host is "localhost" and connection fails, retry 127.0.0.1 (TCP vs socket mismatch on some hosts).
     *
     * @param array{0:string,1:int,2:string,3:string,4:string} $cred
     */
    function rateb_site_content_db_try_mysqli(array $cred): ?mysqli
    {
        $c = rateb_site_content_db_try_mysqli_once($cred);
        if ($c instanceof mysqli) {
            return $c;
        }
        [$host] = $cred;
        if (strtolower((string) $host) === 'localhost') {
            $cred2 = $cred;
            $cred2[0] = '127.0.0.1';

            return rateb_site_content_db_try_mysqli_once($cred2);
        }

        return null;
    }
}

if (!function_exists('rateb_site_content_db_can_read_table')) {
    /**
     * True when this mysqli can SELECT from rateb_site_content (GRANT / wrong schema / missing table).
     */
    function rateb_site_content_db_can_read_table(mysqli $c): bool
    {
        $table = rateb_site_content_sql_table($c);
        $res = @$c->query('SELECT 1 FROM `' . $table . '` LIMIT 1');

        return $res !== false;
    }
}

if (!function_exists('rateb_site_content_db')) {
    /**
     * Connection order (important):
     * 0) Control panel: always use $GLOBALS['control_conn'] — same mysqli as INSERT/UPDATE on site-content.php.
     *    Otherwise the editor reads via rateb_site_content_get() from a different connection than Save and looks "disconnected".
     * 1) Dedicated reader when RATEB_SITE_CONTENT_DB_HOST is set (same as merged credentials — attempted first when env points away from DB_HOST).
     * 2) Merged credentials (RATEB_SITE_CONTENT_DB_* / CONTROL_PANEL_DB_USER / DB_* → CONTROL_PANEL_DB_NAME).
     * 3) App DB_USER → CONTROL_PANEL_DB_NAME (explicit corridor user).
     * 4) get_control_lookup_conn() on SINGLE_URL_MODE — must still pass SELECT on rateb_site_content (shared mysqli is not closed).
     *
     * Each candidate is accepted only if SELECT on rateb_site_content succeeds. Otherwise PHP would "connect" to the
     * control DB but return empty reads — leaving stale JSON/cache visible forever.
     *
     * @param bool $resetCachedPool When true, drop the public worker's cached mysqli (e.g. after "server has gone away").
     */
    function rateb_site_content_db(bool $resetCachedPool = false): ?mysqli
    {
        static $conn = null;

        if ($resetCachedPool) {
            $conn = null;
        }

        if (defined('IS_CONTROL_PANEL') && IS_CONTROL_PANEL) {
            $cp = $GLOBALS['control_conn'] ?? null;
            if ($cp instanceof mysqli) {
                $conn = $cp;

                return $cp;
            }
            // Panel without control_conn: do not reuse a cached public-site connection from another request.
            $conn = null;
        } elseif ($conn instanceof mysqli) {
            // Public / CLI: reuse only if the server link is still alive (avoids "works once" then silent failures after wait_timeout).
            if (@$conn->ping()) {
                return $conn;
            }
            $conn = null;
        }

        $acceptOwned = static function (?mysqli $c): ?mysqli {
            if (!$c instanceof mysqli) {
                return null;
            }
            if (rateb_site_content_db_can_read_table($c)) {
                return $c;
            }
            error_log(
                'rateb_site_content_db: mysqli connected but rateb_site_content not readable ('
                . $c->errno . ') ' . $c->error
            );
            $c->close();

            return null;
        };

        $dedicatedHost = getenv('RATEB_SITE_CONTENT_DB_HOST');
        $hasDedicatedHost = ($dedicatedHost !== false && trim((string) $dedicatedHost) !== '');

        if ($hasDedicatedHost) {
            $cred = rateb_site_content_db_credentials();
            if ($cred !== null) {
                $c = rateb_site_content_db_try_mysqli($cred);
                $c = $acceptOwned($c);
                if ($c instanceof mysqli) {
                    $conn = $c;

                    return $conn;
                }
            }
        }

        $credMerged = rateb_site_content_db_credentials();
        if ($credMerged !== null) {
            $c = rateb_site_content_db_try_mysqli($credMerged);
            $c = $acceptOwned($c);
            if ($c instanceof mysqli) {
                $conn = $c;

                return $conn;
            }
        }

        $credApp = rateb_site_content_db_credentials_app_to_control();
        if ($credApp !== null) {
            $c = rateb_site_content_db_try_mysqli($credApp);
            $c = $acceptOwned($c);
            if ($c instanceof mysqli) {
                $conn = $c;

                return $conn;
            }
        }

        if (defined('SINGLE_URL_MODE') && SINGLE_URL_MODE && function_exists('get_control_lookup_conn')) {
            $lk = get_control_lookup_conn();
            if ($lk instanceof mysqli && rateb_site_content_db_can_read_table($lk)) {
                $conn = $lk;

                return $conn;
            }
            if ($lk instanceof mysqli) {
                error_log(
                    'rateb_site_content_db: get_control_lookup_conn() mysqli cannot read rateb_site_content ('
                    . $lk->errno . ') ' . $lk->error
                );
            }
        }

        return null;
    }
}

if (!function_exists('rateb_site_content_key_allowed')) {
    /**
     * Keys are internal dotted identifiers — never pass user-controlled strings here without validation.
     */
    function rateb_site_content_key_allowed(string $key): bool
    {
        return $key !== '' && (bool) preg_match('/^[a-zA-Z0-9._-]{1,190}$/', $key);
    }
}

if (!function_exists('rateb_site_content_mysqli_lost_connection')) {
    function rateb_site_content_mysqli_lost_connection(int $errno): bool
    {
        // 2006 = MySQL server has gone away, 2013 = Lost connection during query
        return $errno === 2006 || $errno === 2013;
    }
}

if (!function_exists('rateb_site_content_fetch_value_by_key')) {
    /**
     * Read one cell via mysqli::query() (max compatibility). Prepared statements break on some PHP builds
     * without mysqlnd / with buggy libmysqlclient + mysqli_stmt combinations — this path matches phpMyAdmin-style reads.
     *
     * @return ?string null when missing row or query error
     */
    function rateb_site_content_fetch_value_by_key(mysqli $conn, string $key, bool $allowReconnect = true): ?string
    {
        if (!rateb_site_content_key_allowed($key)) {
            return null;
        }
        $esc = $conn->real_escape_string($key);
        $table = rateb_site_content_sql_table($conn);
        $sql = "SELECT content_value FROM `{$table}` WHERE content_key = '" . $esc . "' LIMIT 1";
        $res = $conn->query($sql);
        if ($res === false) {
            $errno = (int) $conn->errno;
            error_log('rateb_site_content_fetch_value_by_key: query failed: ' . $conn->error);
            if ($allowReconnect && rateb_site_content_mysqli_lost_connection($errno) && function_exists('rateb_site_content_db')) {
                rateb_site_content_db(true);
                $c2 = rateb_site_content_db();
                if ($c2 instanceof mysqli) {
                    return rateb_site_content_fetch_value_by_key($c2, $key, false);
                }
            }

            return null;
        }
        $row = $res->fetch_assoc();
        $res->free();
        if ($row === null || !array_key_exists('content_value', $row)) {
            return null;
        }

        return (string) $row['content_value'];
    }
}

if (!function_exists('rateb_site_content_fetch_key_values')) {
    /**
     * Batch load key => value in one SELECT so top-of-page fields (phone, WhatsApp, etc.) cannot disagree
     * with each other due to separate queries or timing.
     *
     * @param list<string> $keys
     *
     * @return array<string, string> Only keys that exist in the table (missing keys omitted).
     */
    function rateb_site_content_fetch_key_values(array $keys, bool $allowReconnect = true): array
    {
        $clean = [];
        foreach ($keys as $k) {
            $k = (string) $k;
            if (rateb_site_content_key_allowed($k)) {
                $clean[$k] = true;
            }
        }
        $uniq = array_keys($clean);
        if ($uniq === []) {
            return [];
        }
        $conn = rateb_site_content_db();
        if (!$conn) {
            return [];
        }
        $out = [];
        // Keep chunks moderate: very large IN (...) lists occasionally fail on strict hosts (packet/size/sql_mode).
        $chunkSize = 60;
        $chunks = array_chunk($uniq, $chunkSize);
        foreach ($chunks as $chunk) {
            $parts = [];
            foreach ($chunk as $k) {
                $parts[] = "'" . $conn->real_escape_string($k) . "'";
            }
            $sql = 'SELECT content_key, content_value FROM `' . rateb_site_content_sql_table($conn) . '` WHERE content_key IN (' . implode(',', $parts) . ')';
            $res = $conn->query($sql);
            if ($res === false) {
                $errno = (int) $conn->errno;
                error_log('rateb_site_content_fetch_key_values: chunk query failed: ' . $conn->error);
                if ($allowReconnect && rateb_site_content_mysqli_lost_connection($errno) && function_exists('rateb_site_content_db')) {
                    rateb_site_content_db(true);

                    return rateb_site_content_fetch_key_values($keys, false);
                }
                // Do not drop keys for this chunk — fetch row-by-row (same connection) so the homepage cannot mix
                // fresh rows with defaults/cache for only some fields (e.g. phone vs WhatsApp).
                foreach ($chunk as $k) {
                    if (array_key_exists($k, $out)) {
                        continue;
                    }
                    $one = rateb_site_content_fetch_value_by_key($conn, $k, false);
                    if ($one !== null) {
                        $out[$k] = $one;
                    }
                }

                continue;
            }
            while ($row = $res->fetch_assoc()) {
                if (isset($row['content_key'], $row['content_value'])) {
                    $out[(string) $row['content_key']] = (string) $row['content_value'];
                }
            }
            $res->free();
        }

        return $out;
    }
}

if (!function_exists('rateb_site_content_get')) {
    function rateb_site_content_get(string $key, string $default = ''): string
    {
        $conn = rateb_site_content_db();
        if (!$conn) {
            return $default;
        }
        // Prefer mysqli::query — mysqli_stmt prepared SELECT fails on many hosts without mysqlnd.
        $val = rateb_site_content_fetch_value_by_key($conn, $key);
        if ($val !== null) {
            return $val;
        }

        return $default;
    }
}

if (!function_exists('rateb_site_content_home_snapshot_db_read')) {
    /**
     * Full homepage JSON blob stored in DB when filesystem export is impossible (reserved content_key).
     */
    function rateb_site_content_home_snapshot_db_read(): ?string
    {
        $conn = rateb_site_content_db();
        if (!$conn) {
            return null;
        }
        $key = RATEB_SITE_CONTENT_HOME_SNAPSHOT_KEY;
        $val = rateb_site_content_fetch_value_by_key($conn, $key);
        if ($val === null || $val === '') {
            return null;
        }

        return $val;
    }
}

if (!function_exists('rateb_site_content_home_snapshot_db_save')) {
    function rateb_site_content_home_snapshot_db_save(string $json): bool
    {
        $conn = rateb_site_content_db();
        if (!$conn) {
            return false;
        }
        $key = RATEB_SITE_CONTENT_HOME_SNAPSHOT_KEY;
        $table = rateb_site_content_sql_table($conn);
        $stmt = $conn->prepare(
            'INSERT INTO `' . $table . '` (content_key, content_value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE content_value = VALUES(content_value), updated_at = CURRENT_TIMESTAMP'
        );
        if (!$stmt) {
            error_log('rateb_site_content_home_snapshot_db_save: prepare failed: ' . $conn->error);

            return false;
        }
        $stmt->bind_param('ss', $key, $json);
        $ok = $stmt->execute();
        $stmt->close();
        if (!$ok) {
            error_log('rateb_site_content_home_snapshot_db_save: execute failed');
        }

        return $ok;
    }
}

if (!function_exists('rateb_site_content_home_snapshot_db_delete')) {
    function rateb_site_content_home_snapshot_db_delete(): void
    {
        $conn = rateb_site_content_db();
        if (!$conn) {
            return;
        }
        $key = RATEB_SITE_CONTENT_HOME_SNAPSHOT_KEY;
        $table = rateb_site_content_sql_table($conn);
        $stmt = $conn->prepare('DELETE FROM `' . $table . '` WHERE content_key = ? LIMIT 1');
        if (!$stmt) {
            return;
        }
        $stmt->bind_param('s', $key);
        $stmt->execute();
        $stmt->close();
    }
}

if (!function_exists('rateb_site_content_cache_unlink_json_candidates')) {
    /** Remove snapshot files so an older JSON does not override DB snapshot reads. */
    function rateb_site_content_cache_unlink_json_candidates(): void
    {
        foreach (rateb_site_content_cache_file_candidates() as $path) {
            if ($path !== '' && is_file($path)) {
                @unlink($path);
            }
        }
    }
}

if (!function_exists('rateb_site_content_cache_abs_project_root')) {
    function rateb_site_content_cache_abs_project_root(): string
    {
        return dirname(__DIR__);
    }
}

if (!function_exists('rateb_site_content_cache_path_is_absolute')) {
    function rateb_site_content_cache_path_is_absolute(string $p): bool
    {
        if ($p === '') {
            return false;
        }
        if ($p[0] === '/' || $p[0] === '\\') {
            return true;
        }

        return strlen($p) > 2 && ctype_alpha($p[0]) && $p[1] === ':' && ($p[2] === '\\' || $p[2] === '/');
    }
}

if (!function_exists('rateb_site_content_cache_resolve_optional_path')) {
    /**
     * Relative paths are resolved from the project root (parent of includes/).
     */
    function rateb_site_content_cache_resolve_optional_path(string $raw): string
    {
        $raw = trim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $raw));
        if ($raw === '') {
            return '';
        }
        if (rateb_site_content_cache_path_is_absolute($raw)) {
            return $raw;
        }

        return rateb_site_content_cache_abs_project_root() . DIRECTORY_SEPARATOR . $raw;
    }
}

if (!function_exists('rateb_site_content_cache_file_candidates')) {
    /**
     * Ordered list of JSON snapshot paths (first preferred).
     * Tries: env RATEB_SITE_CONTENT_CACHE_FILE, constant RATEB_SITE_CONTENT_CACHE_FILE,
     * storage/, cache/, then uploads/rateb_cms_cache/ under each candidate from rateb_uploads_base.php
     * (same writable roots as worker document uploads — often works when storage/ is root-owned).
     *
     * @return list<string>
     */
    function rateb_site_content_cache_file_candidates(): array
    {
        $root = rateb_site_content_cache_abs_project_root();
        $out = [];

        $envFile = getenv('RATEB_SITE_CONTENT_CACHE_FILE');
        if ($envFile !== false && trim((string) $envFile) !== '') {
            $out[] = rateb_site_content_cache_resolve_optional_path((string) $envFile);
        }
        if (defined('RATEB_SITE_CONTENT_CACHE_FILE') && (string) RATEB_SITE_CONTENT_CACHE_FILE !== '') {
            $out[] = rateb_site_content_cache_resolve_optional_path((string) RATEB_SITE_CONTENT_CACHE_FILE);
        }

        $out[] = $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'rateb_site_content_home.json';
        $out[] = $root . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'rateb_site_content_home.json';

        $relUnderUpload = DIRECTORY_SEPARATOR . 'rateb_cms_cache' . DIRECTORY_SEPARATOR . 'rateb_site_content_home.json';
        $uplPhp = $root . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'rateb_uploads_base.php';
        if (is_readable($uplPhp)) {
            require_once $uplPhp;
            if (function_exists('rateb_uploads_read_valid_marker')) {
                $marker = rateb_uploads_read_valid_marker();
                if (is_string($marker) && $marker !== '') {
                    $out[] = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $marker), DIRECTORY_SEPARATOR) . $relUnderUpload;
                }
            }
            if (function_exists('rateb_uploads_candidate_base_dirs')) {
                foreach (rateb_uploads_candidate_base_dirs(false) as $base) {
                    $base = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $base), DIRECTORY_SEPARATOR);
                    if ($base !== '') {
                        $out[] = $base . $relUnderUpload;
                    }
                }
            }
        }

        $seen = [];
        $uniq = [];
        foreach ($out as $p) {
            if (!is_string($p) || $p === '') {
                continue;
            }
            $norm = strtolower(str_replace('\\', '/', $p));
            if (isset($seen[$norm])) {
                continue;
            }
            $seen[$norm] = true;
            $uniq[] = $p;
        }

        return $uniq;
    }
}

if (!function_exists('rateb_site_content_public_cache_path')) {
    /**
     * First candidate path (legacy / primary file name). See rateb_site_content_cache_file_candidates().
     */
    function rateb_site_content_public_cache_path(): string
    {
        $paths = rateb_site_content_cache_file_candidates();

        if ($paths !== []) {
            return $paths[0];
        }

        return rateb_site_content_cache_abs_project_root() . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'rateb_site_content_home.json';
    }
}

if (!function_exists('rateb_site_content_public_cache_path_for_read')) {
    /**
     * Best readable cache file: newest by mtime among all candidates.
     * Previously the first path in the list won even when an older file existed — public could stay on stale
     * phone/copy while the CMS had saved to another writable directory later in the list.
     */
    function rateb_site_content_public_cache_path_for_read(): ?string
    {
        $bestPath = null;
        $bestMtime = -1;
        foreach (rateb_site_content_cache_file_candidates() as $path) {
            if ($path === '' || !is_file($path) || !is_readable($path)) {
                continue;
            }
            $mt = @filemtime($path);
            if ($mt === false) {
                continue;
            }
            if ($mt > $bestMtime) {
                $bestMtime = $mt;
                $bestPath = $path;
            }
        }

        return $bestPath;
    }
}

if (!function_exists('rateb_site_content_normalize_sa_mobile_digits')) {
    /**
     * Saudi mobile: collapse accidental mega-pastes to 966 + 9 digits (12 total).
     * Used only for unusually long digit strings — not for normal CMS edits.
     */
    function rateb_site_content_normalize_sa_mobile_digits(string $digitsOnly): string
    {
        $d = preg_replace('/\D+/', '', $digitsOnly);
        if ($d === '') {
            return '';
        }
        if (strlen($d) >= 3 && substr($d, 0, 3) === '966') {
            $tail = substr($d, 3);
            if (strlen($tail) > 9) {
                $tail = substr($tail, 0, 9);
            }

            return '966' . $tail;
        }

        return $d;
    }
}

if (!function_exists('rateb_site_content_phone_digits_for_links')) {
    /**
     * Digits for tel:/wa.me — uses what you saved (digits only). Extra formatting is stripped;
     * we only collapse absurdly long pasted strings so tel:/wa.me stay bounded.
     */
    function rateb_site_content_phone_digits_for_links(string $display, string $fallbackDigits = '966599863868'): string
    {
        $d = preg_replace('/\D+/', '', $display);
        if (strlen($d) < 8) {
            return $fallbackDigits;
        }
        if (strlen($d) > 18) {
            $d = rateb_site_content_normalize_sa_mobile_digits($d);
        }

        return $d;
    }
}

if (!function_exists('rateb_site_content_db_fingerprint')) {
    /**
     * Safe DB identity string for troubleshooting (no secrets).
     * Example: db=admin_control_panel_db;host=mysql01;port=3306;user=admin_out@localhost
     */
    function rateb_site_content_db_fingerprint(?mysqli $conn = null): string
    {
        $c = $conn instanceof mysqli ? $conn : rateb_site_content_db();
        if (!$c instanceof mysqli) {
            return '';
        }
        $res = @$c->query("SELECT DATABASE() AS dbn, @@hostname AS hst, @@port AS prt, CURRENT_USER() AS curu");
        if ($res === false) {
            return '';
        }
        $row = $res->fetch_assoc();
        $res->free();
        if (!is_array($row)) {
            return '';
        }
        $dbn = isset($row['dbn']) ? (string) $row['dbn'] : '';
        $hst = isset($row['hst']) ? (string) $row['hst'] : '';
        $prt = isset($row['prt']) ? (string) $row['prt'] : '';
        $cur = isset($row['curu']) ? (string) $row['curu'] : '';

        return 'db=' . $dbn . ';host=' . $hst . ';port=' . $prt . ';user=' . $cur;
    }
}

if (!function_exists('rateb_site_content_revision_token')) {
    /**
     * Monotonic content revision token based on rateb_site_content.updated_at (unix timestamp as string).
     * Empty string when DB is unavailable.
     */
    function rateb_site_content_revision_token(): string
    {
        $c = rateb_site_content_db();
        if (!$c instanceof mysqli) {
            return '';
        }
        $table = rateb_site_content_sql_table($c);
        $res = @$c->query("SELECT COALESCE(UNIX_TIMESTAMP(MAX(updated_at)), 0) AS rev FROM `{$table}`");
        if ($res === false) {
            return '';
        }
        $row = $res->fetch_assoc();
        $res->free();
        $rev = is_array($row) ? (string) ($row['rev'] ?? '0') : '0';
        if ($rev === '' || $rev === '0') {
            return '';
        }

        return $rev;
    }
}

require_once __DIR__ . '/site-content-home-data.php';

if (!function_exists('rateb_site_content_home_flat_from_db')) {
    /**
     * Live rateb_site_content rows for homepage keys — single SELECT … IN (…) via rateb_site_content_fetch_key_values.
     * Returns null only when the marketing DB connection is unavailable (then caller uses caches).
     *
     * @param array<string, string> $defaults
     *
     * @return array<string, string>|null
     */
    function rateb_site_content_home_flat_from_db(array $defaults): ?array
    {
        if (!rateb_site_content_db()) {
            return null;
        }
        if ($defaults === []) {
            return [];
        }
        if (!function_exists('rateb_site_content_fetch_key_values')) {
            $out = [];
            foreach ($defaults as $key => $def) {
                $out[$key] = rateb_site_content_get($key, $def);
            }

            return $out;
        }
        $keys = array_keys($defaults);
        $rows = rateb_site_content_fetch_key_values($keys);
        $out = [];
        foreach ($defaults as $key => $def) {
            // Never use PHP $def when a row may exist: batch IN (...) can omit keys; per-key read matches CMS.
            $out[$key] = array_key_exists($key, $rows)
                ? $rows[$key]
                : rateb_site_content_get($key, $def);
        }

        return $out;
    }
}

if (!function_exists('rateb_site_content_export_public_cache')) {
    /**
     * After CMS save: write JSON snapshot to disk for fast public reads; if disk is not writable,
     * store the same JSON in rateb_site_content under RATEB_SITE_CONTENT_HOME_SNAPSHOT_KEY.
     */
    function rateb_site_content_export_public_cache(): bool
    {
        if (!function_exists('rateb_site_content_defaults_home')) {
            return false;
        }
        $defaults = rateb_site_content_defaults_home();
        $keys = array_keys($defaults);
        $rows = function_exists('rateb_site_content_fetch_key_values')
            ? rateb_site_content_fetch_key_values($keys)
            : [];
        $out = [];
        foreach ($defaults as $key => $def) {
            $out[$key] = array_key_exists($key, $rows) ? $rows[$key] : rateb_site_content_get($key, $def);
        }
        $json = json_encode($out, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            error_log('rateb_site_content_export_public_cache: json_encode failed');

            return false;
        }
        foreach (rateb_site_content_cache_file_candidates() as $path) {
            $dir = dirname($path);
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            if (@file_put_contents($path, $json, LOCK_EX) !== false) {
                foreach (rateb_site_content_cache_file_candidates() as $other) {
                    if ($other !== $path && is_file($other)) {
                        @unlink($other);
                    }
                }
                if (function_exists('rateb_site_content_home_snapshot_db_delete')) {
                    rateb_site_content_home_snapshot_db_delete();
                }

                return true;
            }
            error_log('rateb_site_content_export_public_cache: cannot write ' . $path);
        }

        if (function_exists('rateb_site_content_home_snapshot_db_save') && rateb_site_content_home_snapshot_db_save($json)) {
            rateb_site_content_cache_unlink_json_candidates();

            return true;
        }

        return false;
    }
}

if (!function_exists('rateb_site_content_defaults_public_home')) {
    /** @return array<string, string> */
    function rateb_site_content_defaults_public_home(): array
    {
        return rateb_site_content_defaults_home();
    }
}

if (!function_exists('rateb_site_content_public_home')) {
    /**
     * Resolved copy for pages/home.php (subset for legacy callers).
     *
     * @return array{eyebrow:string,lead:string,platform_title:string,platform_sub:string,program_img:array{0:string,1:string,2:string}}
     */
    function rateb_site_content_public_home(): array
    {
        $f = rateb_site_content_home_flat();

        $p1 = $p2 = $p3 = '';
        if (function_exists('rateb_site_content_home_program_slots_from_flat')) {
            $items = rateb_site_content_home_program_slots_from_flat($f);
            $p1 = trim((string) ($items[0]['src'] ?? ''));
            $p2 = trim((string) ($items[1]['src'] ?? ''));
            $p3 = trim((string) ($items[2]['src'] ?? ''));
        }

        return [
            'eyebrow' => $f['home.hero.eyebrow'] ?? '',
            'lead' => $f['home.hero.lead'] ?? '',
            'platform_title' => $f['home.platform.title'] ?? '',
            'platform_sub' => $f['home.platform.sub'] ?? '',
            'program_img' => [$p1, $p2, $p3],
        ];
    }
}

if (!function_exists('rateb_site_content_asset_url')) {
    if (!function_exists('rateb_site_content_media_storage_dir')) {
        function rateb_site_content_media_storage_dir(): string
        {
            $root = dirname(__DIR__);
            if (!function_exists('rateb_uploads_base_dir')) {
                $uploadsBaseFile = __DIR__ . '/rateb_uploads_base.php';
                if (is_file($uploadsBaseFile)) {
                    require_once $uploadsBaseFile;
                }
            }
            if (function_exists('rateb_uploads_base_dir')) {
                $base = (string) rateb_uploads_base_dir();
                if ($base !== '') {
                    return rtrim($base, '/\\') . DIRECTORY_SEPARATOR . 'rateb_cms_media';
                }
            }

            return $root . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'rateb_cms_media';
        }
    }
    if (!function_exists('rateb_site_content_media_token_from_filename')) {
        function rateb_site_content_media_token_from_filename(string $fileName): string
        {
            return 'scmedia:' . $fileName;
        }
    }
    if (!function_exists('rateb_site_content_media_filename_from_token')) {
        function rateb_site_content_media_filename_from_token(string $stored): string
        {
            $stored = trim($stored);
            if (strpos($stored, 'scmedia:') !== 0) {
                return '';
            }
            $name = substr($stored, 8);
            $name = basename(str_replace('\\', '/', $name));
            if (!preg_match('/^[a-zA-Z0-9._-]+$/', $name)) {
                return '';
            }

            return $name;
        }
    }
    if (!function_exists('rateb_site_content_media_endpoint_script')) {
        /** Public script that serves CMS uploads + bundled fallbacks (pages/ deploys reliably). */
        function rateb_site_content_media_endpoint_script(): string
        {
            return '/public/cms-media.php';
        }
    }
    if (!function_exists('rateb_site_content_media_filename_for_key')) {
        function rateb_site_content_media_filename_for_key(string $contentKey, string $ext): string
        {
            $slug = strtolower((string) preg_replace('/[^a-z0-9]+/i', '-', $contentKey));
            $slug = trim($slug, '-');
            if ($slug === '') {
                $slug = 'asset';
            }

            return 'cms-' . $slug . '.' . strtolower($ext);
        }
    }
    if (!function_exists('rateb_site_content_media_bundled_map')) {
        /**
         * @return array<string, list<string>> scmedia filename => site-relative paths (first existing wins)
         */
        function rateb_site_content_media_bundled_map(): array
        {
            $map = [
                'gov-government-control.png' => ['public/cms-bundle-gov-control-v2.png', 'public/cms-bundle-gov-control.png'],
                'gov-government-inspections.png' => ['public/cms-bundle-gov-inspections.png'],
                'gov-tracking-map.png' => ['public/cms-bundle-gov-tracking.png'],
                'gov-worker-mobile-onboarding.png' => ['public/cms-bundle-gov-onboarding.png'],
            ];
            $profileFallbacks = [
                'profile.image.hero' => 'public/cms-bundle-about.png',
                'profile.image.ops' => 'public/cms-bundle-about.png',
                'profile.image.workers' => 'public/cms-bundle-workers.svg',
                'profile.image.telemetry' => 'public/cms-bundle-gov-tracking.png',
                'profile.image.accounting' => 'public/cms-bundle-finance.svg',
                'profile.image.control' => 'public/cms-bundle-gov-control-v2.png',
                'profile.image.partners' => 'public/cms-bundle-about.png',
                'profile.image.pipeline' => 'public/cms-bundle-pipeline.svg',
                'profile.gov.image.control' => 'public/cms-bundle-gov-control-v2.png',
                'profile.gov.image.inspections' => 'public/cms-bundle-gov-inspections.png',
                'profile.gov.image.tracking' => 'public/cms-bundle-gov-tracking.png',
                'profile.gov.image.onboarding' => 'public/cms-bundle-gov-onboarding.png',
                'profile.diagram.workflow' => 'public/cms-bundle-diagram-workflow.svg',
                'profile.diagram.onboarding' => 'public/cms-bundle-diagram-onboarding.svg',
                'profile.diagram.deployment' => 'public/cms-bundle-diagram-deployment.svg',
                'profile.diagram.tenant' => 'public/cms-bundle-diagram-tenant.svg',
                'profile.diagram.events' => 'public/cms-bundle-diagram-events.svg',
            ];
            foreach ($profileFallbacks as $key => $rel) {
                $ext = strtolower((string) pathinfo($rel, PATHINFO_EXTENSION));
                if ($ext === '') {
                    continue;
                }
                $fname = rateb_site_content_media_filename_for_key($key, $ext);
                if (!isset($map[$fname])) {
                    $map[$fname] = [$rel];
                }
            }

            return $map;
        }
    }
    if (!function_exists('rateb_site_content_media_is_video_file')) {
        function rateb_site_content_media_is_video_file(string $fs): bool
        {
            $ext = strtolower((string) pathinfo($fs, PATHINFO_EXTENSION));

            return in_array($ext, ['mp4', 'webm', 'mov'], true)
                && is_file($fs)
                && filesize($fs) > 0;
        }
    }
    if (!function_exists('rateb_site_content_media_file_is_valid')) {
        /** Reject corrupt CMS uploads (UTF-8-mangled deploy) so bundled defaults can serve. */
        function rateb_site_content_media_file_is_valid(string $fs): bool
        {
            if (!is_file($fs) || !is_readable($fs)) {
                return false;
            }
            $fh = @fopen($fs, 'rb');
            if ($fh === false) {
                return false;
            }
            $sig = @fread($fh, 12);
            @fclose($fh);
            if (!is_string($sig) || strlen($sig) < 3) {
                return false;
            }
            if (strncmp($sig, "\x89PNG\r\n\x1a\n", 8) === 0) {
                return true;
            }
            if (strncmp($sig, "\xFF\xD8\xFF", 3) === 0) {
                return true;
            }
            if (strncmp($sig, 'GIF87a', 6) === 0 || strncmp($sig, 'GIF89a', 6) === 0) {
                return true;
            }
            if (strlen($sig) >= 12 && strncmp($sig, 'RIFF', 4) === 0 && substr($sig, 8, 4) === 'WEBP') {
                return true;
            }
            if (str_starts_with(ltrim($sig), '<svg') || str_starts_with(ltrim($sig), '<?xml')) {
                return true;
            }

            return false;
        }
    }
    if (!function_exists('rateb_site_content_media_detect_mime')) {
        function rateb_site_content_media_detect_mime(string $fs, string $ext): string
        {
            $allowed = [
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'webp' => 'image/webp',
                'gif' => 'image/gif',
                'svg' => 'image/svg+xml',
                'mp4' => 'video/mp4',
                'webm' => 'video/webm',
                'mov' => 'video/quicktime',
            ];
            $fh = @fopen($fs, 'rb');
            if ($fh !== false) {
                $sig = @fread($fh, 12);
                @fclose($fh);
                if (is_string($sig)) {
                    if (strncmp($sig, "\x89PNG\r\n\x1a\n", 8) === 0) {
                        return 'image/png';
                    }
                    if (strncmp($sig, "\xFF\xD8\xFF", 3) === 0) {
                        return 'image/jpeg';
                    }
                    if (strncmp($sig, 'GIF87a', 6) === 0 || strncmp($sig, 'GIF89a', 6) === 0) {
                        return 'image/gif';
                    }
                    if (strlen($sig) >= 12 && strncmp($sig, 'RIFF', 4) === 0 && substr($sig, 8, 4) === 'WEBP') {
                        return 'image/webp';
                    }
                    if (str_starts_with(ltrim($sig), '<svg') || str_starts_with(ltrim($sig), '<?xml')) {
                        return 'image/svg+xml';
                    }
                }
            }

            return $allowed[$ext] ?? 'application/octet-stream';
        }
    }
    if (!function_exists('rateb_site_content_media_resolve_fs')) {
        function rateb_site_content_media_resolve_fs(string $fileName): ?string
        {
            $fileName = basename(str_replace('\\', '/', $fileName));
            if ($fileName === '' || !preg_match('/^[a-zA-Z0-9._-]+$/', $fileName)) {
                return null;
            }
            $uploaded = rateb_site_content_media_storage_dir() . DIRECTORY_SEPARATOR . $fileName;
            if (is_file($uploaded)) {
                if (rateb_site_content_media_is_video_file($uploaded) || rateb_site_content_media_file_is_valid($uploaded)) {
                    return $uploaded;
                }
            }
            $root = dirname(__DIR__);
            $map = rateb_site_content_media_bundled_map();
            foreach ($map[$fileName] ?? [] as $rel) {
                $fs = $root . '/' . str_replace('/', DIRECTORY_SEPARATOR, ltrim($rel, '/'));
                if (is_file($fs)) {
                    return $fs;
                }
            }
            if (function_exists('rateb_site_content_scmedia_bundled_rel')) {
                $legacyRel = rateb_site_content_scmedia_bundled_rel($fileName);
                if ($legacyRel !== '') {
                    $fs = $root . '/' . str_replace('/', DIRECTORY_SEPARATOR, ltrim($legacyRel, '/'));
                    if (is_file($fs)) {
                        return $fs;
                    }
                }
            }

            return null;
        }
    }
    if (!function_exists('rateb_site_content_media_stored_is_image')) {
        function rateb_site_content_media_stored_is_image(string $stored): bool
        {
            $stored = trim($stored);
            if ($stored === '') {
                return false;
            }
            $name = rateb_site_content_media_filename_from_token($stored);
            if ($name === '') {
                $path = (string) (parse_url($stored, PHP_URL_PATH) ?? $stored);
                $name = basename(str_replace('\\', '/', $path));
            }
            $ext = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));

            return in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'], true);
        }
    }
    if (!function_exists('rateb_site_content_media_upload_direct_url')) {
        /** Serve CMS uploads as static /uploads/rateb_cms_media/… when on disk under project root (avoids PHP stream HTTP/2 issues). */
        function rateb_site_content_media_upload_direct_url(string $baseUrl, string $fs): string
        {
            $rootReal = realpath(dirname(__DIR__));
            $fsReal = realpath($fs);
            if ($rootReal === false || $fsReal === false) {
                return '';
            }
            $prefix = rtrim(str_replace('\\', '/', $rootReal), '/') . '/';
            $fsNorm = str_replace('\\', '/', $fsReal);
            if (!str_starts_with($fsNorm, $prefix)) {
                return '';
            }
            $rel = ltrim(substr($fsNorm, strlen($prefix)), '/');
            if (!str_starts_with($rel, 'uploads/rateb_cms_media/')) {
                return '';
            }
            $v = (int) filemtime($fsReal);

            return rtrim($baseUrl, '/') . '/' . $rel . '?v=' . $v;
        }
    }
    if (!function_exists('rateb_site_content_media_serve')) {
        function rateb_site_content_media_serve(string $fileName): void
        {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }

            $fileName = basename(str_replace('\\', '/', $fileName));
            $ext = strtolower((string) pathinfo($fileName, PATHINFO_EXTENSION));
            $allowed = [
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'webp' => 'image/webp',
                'gif' => 'image/gif',
                'svg' => 'image/svg+xml',
                'mp4' => 'video/mp4',
                'webm' => 'video/webm',
                'mov' => 'video/quicktime',
            ];
            if ($fileName === '' || !isset($allowed[$ext])) {
                http_response_code(404);
                exit;
            }
            $fs = rateb_site_content_media_resolve_fs($fileName);
            if ($fs === null) {
                http_response_code(404);
                exit;
            }
            $size = (int) filesize($fs);
            header('Content-Type: ' . rateb_site_content_media_detect_mime($fs, $ext));
            header('Content-Length: ' . (string) $size);
            header('Cache-Control: public, max-age=604800, immutable');
            header('X-Content-Type-Options: nosniff');
            if (in_array($ext, ['mp4', 'webm', 'mov'], true)) {
                header('Accept-Ranges: bytes');
            }
            if ($ext === 'svg') {
                $svg = (string) file_get_contents($fs);
                $svg = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', ' ', $svg) ?? $svg;
                echo $svg;

                exit;
            }
            if ($size > 0) {
                readfile($fs);
            }
            exit;
        }
    }
    if (!function_exists('rateb_site_content_bundled_asset_public_url')) {
        /** Static /public/cms-bundle-* URLs (reliable for SVG; avoids PHP nosniff edge cases). */
        function rateb_site_content_bundled_asset_public_url(string $baseUrl, string $fsPath): string
        {
            $root = dirname(__DIR__);
            $rel = str_replace('\\', '/', substr($fsPath, strlen($root) + 1));
            if (!str_starts_with($rel, 'public/cms-bundle-')) {
                return '';
            }
            $v = is_file($fsPath) ? (int) filemtime($fsPath) : time();

            return rtrim($baseUrl, '/') . '/' . $rel . '?v=' . $v;
        }
    }
    if (!function_exists('rateb_site_content_media_public_url')) {
        function rateb_site_content_media_public_url(string $baseUrl, string $stored): string
        {
            $name = rateb_site_content_media_filename_from_token($stored);
            if ($name === '') {
                return '';
            }
            $fs = rateb_site_content_media_resolve_fs($name);
            if ($fs === null) {
                return '';
            }
            $direct = rateb_site_content_bundled_asset_public_url($baseUrl, $fs);
            if ($direct !== '') {
                return $direct;
            }
            $uploadDirect = rateb_site_content_media_upload_direct_url($baseUrl, $fs);
            if ($uploadDirect !== '') {
                return $uploadDirect;
            }
            $v = (int) filemtime($fs);
            $script = rateb_site_content_media_endpoint_script();

            return rtrim($baseUrl, '/') . $script . '?f=' . rawurlencode($name) . '&v=' . $v;
        }
    }
    if (!function_exists('rateb_site_content_media_default_token')) {
        function rateb_site_content_media_default_token(string $contentKey, string $bundledRel): string
        {
            $ext = strtolower((string) pathinfo($bundledRel, PATHINFO_EXTENSION));
            if ($ext === '') {
                return '';
            }

            return rateb_site_content_media_token_from_filename(
                rateb_site_content_media_filename_for_key($contentKey, $ext)
            );
        }
    }
    if (!function_exists('rateb_site_content_resolve_public_media_rel')) {
        function rateb_site_content_resolve_public_media_rel(string $rel): string
        {
            if (function_exists('rateb_public_resolve_profile_media_rel')) {
                return rateb_public_resolve_profile_media_rel($rel);
            }

            return ltrim(str_replace('\\', '/', $rel), '/');
        }
    }
    if (!function_exists('rateb_site_content_scmedia_bundled_rel')) {
        /**
         * When a CMS upload (scmedia:) is missing on disk, serve bundled assets instead.
         */
        function rateb_site_content_scmedia_bundled_rel(string $fileName): string
        {
            $fileName = basename(str_replace('\\', '/', $fileName));
            $flat = [
                'gov-government-control.png' => 'public/cms-bundle-gov-control-v2.png',
                'gov-government-inspections.png' => 'public/cms-bundle-gov-inspections.png',
                'gov-tracking-map.png' => 'public/cms-bundle-gov-tracking.png',
                'gov-worker-mobile-onboarding.png' => 'public/cms-bundle-gov-onboarding.png',
            ];
            if (isset($flat[$fileName])) {
                return $flat[$fileName];
            }

            return '';
        }
    }
    /**
     * @param string $stored     Empty = use fallback; or full URL; or site-relative path e.g. assets/images/x.svg
     * @param string $fallbackRel from project root, e.g. assets/images/program-preview-pipeline.svg
     * @param string $fallbackFs  absolute filesystem path to fallback file (for mtime)
     */
    function rateb_site_content_asset_url(string $baseUrl, string $stored, string $fallbackRel, string $fallbackFs): string
    {
        $stored = trim($stored);
        if ($stored !== '') {
            if (preg_match('#^https?://#i', $stored)) {
                return $stored;
            }
            $tokUrl = rateb_site_content_media_public_url($baseUrl, $stored);
            if ($tokUrl !== '') {
                return $tokUrl;
            }
            if (!str_starts_with($stored, 'scmedia:')) {
                $rel = rateb_site_content_resolve_public_media_rel($stored);
                $root = dirname(__DIR__);
                $fs = $root . '/' . str_replace('/', DIRECTORY_SEPARATOR, $rel);
                if (is_file($fs)) {
                    $v = (int) filemtime($fs);

                    return rtrim($baseUrl, '/') . '/' . $rel . '?v=' . $v;
                }
            }
        }
        $resolvedFallback = rateb_site_content_resolve_public_media_rel($fallbackRel);
        $resolvedFs = dirname(__DIR__) . '/' . str_replace('/', DIRECTORY_SEPARATOR, ltrim($resolvedFallback, '/'));
        $v = (int) (@filemtime($resolvedFs) ?: (@filemtime($fallbackFs) ?: 1));

        return rtrim($baseUrl, '/') . '/' . ltrim($resolvedFallback, '/') . '?v=' . $v;
    }
}

