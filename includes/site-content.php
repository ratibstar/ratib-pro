<?php
/**
 * Public marketing copy stored in the control-panel database (key/value).
 * Safe no-op if DB unavailable — callers use defaults.
 */
if (!defined('RATIB_SITE_CONTENT_HOME_SNAPSHOT_KEY')) {
    /** Reserved row in ratib_site_content: full homepage JSON when disk cache cannot be written. */
    define('RATIB_SITE_CONTENT_HOME_SNAPSHOT_KEY', '__ratib_home_json_snapshot.v1__');
}

if (!function_exists('ratib_site_content_db_credentials')) {
    /**
     * Resolve connection params for reading ratib_site_content (control DB).
     * Public site often uses DB_NAME for the tenant DB; the CMS lives in CONTROL_PANEL_DB_NAME.
     *
     * Optional env (recommended when the app DB user has no access to the control DB):
     *   RATIB_SITE_CONTENT_DB_HOST, RATIB_SITE_CONTENT_DB_PORT, RATIB_SITE_CONTENT_DB_USER,
     *   RATIB_SITE_CONTENT_DB_PASS, RATIB_SITE_CONTENT_DB_NAME
     * Or getenv CONTROL_DB_USER / CONTROL_DB_PASS (same as control-panel/config/env.php), CONTROL_PANEL_DB_USER / CONTROL_PANEL_DB_PASS, or DB_*.
     *
     * Homepage JSON snapshot path (optional — overrides automatic candidates):
     *   RATIB_SITE_CONTENT_CACHE_FILE=/absolute/or/project-relative/path/ratib_site_content_home.json
     *
     * @return array{0:string,1:int,2:string,3:string,4:string}|null
     */
    function ratib_site_content_db_credentials(): ?array
    {
        if (!defined('CONTROL_PANEL_DB_NAME')) {
            return null;
        }
        $host = getenv('RATIB_SITE_CONTENT_DB_HOST');
        if ($host === false || $host === '') {
            $hCp = getenv('CONTROL_DB_HOST');
            $host = ($hCp !== false && $hCp !== '') ? (string) $hCp : (defined('DB_HOST') ? DB_HOST : '');
        } else {
            $host = (string) $host;
        }
        $portRaw = getenv('RATIB_SITE_CONTENT_DB_PORT');
        if ($portRaw !== false && $portRaw !== '') {
            $port = (int) $portRaw;
        } else {
            $pCp = getenv('CONTROL_DB_PORT');
            $port = ($pCp !== false && $pCp !== '') ? (int) $pCp : (defined('DB_PORT') ? (int) DB_PORT : 3306);
        }
        $dbName = getenv('RATIB_SITE_CONTENT_DB_NAME');
        $dbName = ($dbName !== false && $dbName !== '') ? (string) $dbName : CONTROL_PANEL_DB_NAME;

        $user = getenv('RATIB_SITE_CONTENT_DB_USER');
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
        $pass = getenv('RATIB_SITE_CONTENT_DB_PASS');
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

if (!function_exists('ratib_site_content_db_credentials_app_to_control')) {
    /**
     * Same pattern as get_control_lookup_conn(): app DB_USER + DB_PASS opening CONTROL_PANEL_DB_NAME.
     * Used when CONTROL_PANEL_DB_USER / CONTROL_PANEL_DB_PASS are wrong but the tenant user has SELECT on ratib_site_content.
     *
     * @return array{0:string,1:int,2:string,3:string,4:string}|null
     */
    function ratib_site_content_db_credentials_app_to_control(): ?array
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

if (!function_exists('ratib_site_content_db_try_mysqli_once')) {
    /**
     * Single mysqli attempt (no localhost fallback).
     *
     * @param array{0:string,1:int,2:string,3:string,4:string} $cred
     */
    function ratib_site_content_db_try_mysqli_once(array $cred): ?mysqli
    {
        [$host, $port, $user, $pass, $dbName] = $cred;
        if ($host === '' || $user === '' || $dbName === '') {
            return null;
        }
        try {
            $c = new mysqli($host, $user, $pass, $dbName, $port);
            if ($c->connect_errno) {
                error_log('ratib_site_content_db: mysqli failed for user "' . $user . '" @ "' . $host . '" (' . $c->connect_errno . ') ' . $c->connect_error);
                $c->close();

                return null;
            }
            $c->set_charset('utf8mb4');

            return $c;
        } catch (Throwable $e) {
            error_log('ratib_site_content_db: ' . $e->getMessage());

            return null;
        }
    }
}

if (!function_exists('ratib_site_content_db_try_mysqli')) {
    /**
     * Try mysqli; if host is "localhost" and connection fails, retry 127.0.0.1 (TCP vs socket mismatch on some hosts).
     *
     * @param array{0:string,1:int,2:string,3:string,4:string} $cred
     */
    function ratib_site_content_db_try_mysqli(array $cred): ?mysqli
    {
        $c = ratib_site_content_db_try_mysqli_once($cred);
        if ($c instanceof mysqli) {
            return $c;
        }
        [$host] = $cred;
        if (strtolower((string) $host) === 'localhost') {
            $cred2 = $cred;
            $cred2[0] = '127.0.0.1';

            return ratib_site_content_db_try_mysqli_once($cred2);
        }

        return null;
    }
}

if (!function_exists('ratib_site_content_db_can_read_table')) {
    /**
     * True when this mysqli can SELECT from ratib_site_content (GRANT / wrong schema / missing table).
     */
    function ratib_site_content_db_can_read_table(mysqli $c): bool
    {
        $res = @$c->query('SELECT 1 FROM ratib_site_content LIMIT 1');

        return $res !== false;
    }
}

if (!function_exists('ratib_site_content_db')) {
    /**
     * Connection order (important):
     * 0) Control panel: always use $GLOBALS['control_conn'] — same mysqli as INSERT/UPDATE on site-content.php.
     *    Otherwise the editor reads via ratib_site_content_get() from a different connection than Save and looks "disconnected".
     * 1) Dedicated reader when RATIB_SITE_CONTENT_DB_HOST is set (same as merged credentials — attempted first when env points away from DB_HOST).
     * 2) Merged credentials (RATIB_SITE_CONTENT_DB_* / CONTROL_PANEL_DB_USER / DB_* → CONTROL_PANEL_DB_NAME).
     * 3) App DB_USER → CONTROL_PANEL_DB_NAME (explicit corridor user).
     * 4) get_control_lookup_conn() on SINGLE_URL_MODE — must still pass SELECT on ratib_site_content (shared mysqli is not closed).
     *
     * Each candidate is accepted only if SELECT on ratib_site_content succeeds. Otherwise PHP would "connect" to the
     * control DB but return empty reads — leaving stale JSON/cache visible forever.
     *
     * @param bool $resetCachedPool When true, drop the public worker's cached mysqli (e.g. after "server has gone away").
     */
    function ratib_site_content_db(bool $resetCachedPool = false): ?mysqli
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
            if (ratib_site_content_db_can_read_table($c)) {
                return $c;
            }
            error_log(
                'ratib_site_content_db: mysqli connected but ratib_site_content not readable ('
                . $c->errno . ') ' . $c->error
            );
            $c->close();

            return null;
        };

        $dedicatedHost = getenv('RATIB_SITE_CONTENT_DB_HOST');
        $hasDedicatedHost = ($dedicatedHost !== false && trim((string) $dedicatedHost) !== '');

        if ($hasDedicatedHost) {
            $cred = ratib_site_content_db_credentials();
            if ($cred !== null) {
                $c = ratib_site_content_db_try_mysqli($cred);
                $c = $acceptOwned($c);
                if ($c instanceof mysqli) {
                    $conn = $c;

                    return $conn;
                }
            }
        }

        $credMerged = ratib_site_content_db_credentials();
        if ($credMerged !== null) {
            $c = ratib_site_content_db_try_mysqli($credMerged);
            $c = $acceptOwned($c);
            if ($c instanceof mysqli) {
                $conn = $c;

                return $conn;
            }
        }

        $credApp = ratib_site_content_db_credentials_app_to_control();
        if ($credApp !== null) {
            $c = ratib_site_content_db_try_mysqli($credApp);
            $c = $acceptOwned($c);
            if ($c instanceof mysqli) {
                $conn = $c;

                return $conn;
            }
        }

        if (defined('SINGLE_URL_MODE') && SINGLE_URL_MODE && function_exists('get_control_lookup_conn')) {
            $lk = get_control_lookup_conn();
            if ($lk instanceof mysqli && ratib_site_content_db_can_read_table($lk)) {
                $conn = $lk;

                return $conn;
            }
            if ($lk instanceof mysqli) {
                error_log(
                    'ratib_site_content_db: get_control_lookup_conn() mysqli cannot read ratib_site_content ('
                    . $lk->errno . ') ' . $lk->error
                );
            }
        }

        return null;
    }
}

if (!function_exists('ratib_site_content_key_allowed')) {
    /**
     * Keys are internal dotted identifiers — never pass user-controlled strings here without validation.
     */
    function ratib_site_content_key_allowed(string $key): bool
    {
        return $key !== '' && (bool) preg_match('/^[a-zA-Z0-9._-]{1,190}$/', $key);
    }
}

if (!function_exists('ratib_site_content_mysqli_lost_connection')) {
    function ratib_site_content_mysqli_lost_connection(int $errno): bool
    {
        // 2006 = MySQL server has gone away, 2013 = Lost connection during query
        return $errno === 2006 || $errno === 2013;
    }
}

if (!function_exists('ratib_site_content_fetch_value_by_key')) {
    /**
     * Read one cell via mysqli::query() (max compatibility). Prepared statements break on some PHP builds
     * without mysqlnd / with buggy libmysqlclient + mysqli_stmt combinations — this path matches phpMyAdmin-style reads.
     *
     * @return ?string null when missing row or query error
     */
    function ratib_site_content_fetch_value_by_key(mysqli $conn, string $key, bool $allowReconnect = true): ?string
    {
        if (!ratib_site_content_key_allowed($key)) {
            return null;
        }
        $esc = $conn->real_escape_string($key);
        $sql = "SELECT content_value FROM ratib_site_content WHERE content_key = '" . $esc . "' LIMIT 1";
        $res = $conn->query($sql);
        if ($res === false) {
            $errno = (int) $conn->errno;
            error_log('ratib_site_content_fetch_value_by_key: query failed: ' . $conn->error);
            if ($allowReconnect && ratib_site_content_mysqli_lost_connection($errno) && function_exists('ratib_site_content_db')) {
                ratib_site_content_db(true);
                $c2 = ratib_site_content_db();
                if ($c2 instanceof mysqli) {
                    return ratib_site_content_fetch_value_by_key($c2, $key, false);
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

if (!function_exists('ratib_site_content_fetch_key_values')) {
    /**
     * Batch load key => value in one SELECT so top-of-page fields (phone, WhatsApp, etc.) cannot disagree
     * with each other due to separate queries or timing.
     *
     * @param list<string> $keys
     *
     * @return array<string, string> Only keys that exist in the table (missing keys omitted).
     */
    function ratib_site_content_fetch_key_values(array $keys, bool $allowReconnect = true): array
    {
        $clean = [];
        foreach ($keys as $k) {
            $k = (string) $k;
            if (ratib_site_content_key_allowed($k)) {
                $clean[$k] = true;
            }
        }
        $uniq = array_keys($clean);
        if ($uniq === []) {
            return [];
        }
        $conn = ratib_site_content_db();
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
            $sql = 'SELECT content_key, content_value FROM ratib_site_content WHERE content_key IN (' . implode(',', $parts) . ')';
            $res = $conn->query($sql);
            if ($res === false) {
                $errno = (int) $conn->errno;
                error_log('ratib_site_content_fetch_key_values: chunk query failed: ' . $conn->error);
                if ($allowReconnect && ratib_site_content_mysqli_lost_connection($errno) && function_exists('ratib_site_content_db')) {
                    ratib_site_content_db(true);

                    return ratib_site_content_fetch_key_values($keys, false);
                }
                // Do not drop keys for this chunk — fetch row-by-row (same connection) so the homepage cannot mix
                // fresh rows with defaults/cache for only some fields (e.g. phone vs WhatsApp).
                foreach ($chunk as $k) {
                    if (array_key_exists($k, $out)) {
                        continue;
                    }
                    $one = ratib_site_content_fetch_value_by_key($conn, $k, false);
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

if (!function_exists('ratib_site_content_get')) {
    function ratib_site_content_get(string $key, string $default = ''): string
    {
        $conn = ratib_site_content_db();
        if (!$conn) {
            return $default;
        }
        // Prefer mysqli::query — mysqli_stmt prepared SELECT fails on many hosts without mysqlnd.
        $val = ratib_site_content_fetch_value_by_key($conn, $key);
        if ($val !== null) {
            return $val;
        }

        return $default;
    }
}

if (!function_exists('ratib_site_content_home_snapshot_db_read')) {
    /**
     * Full homepage JSON blob stored in DB when filesystem export is impossible (reserved content_key).
     */
    function ratib_site_content_home_snapshot_db_read(): ?string
    {
        $conn = ratib_site_content_db();
        if (!$conn) {
            return null;
        }
        $key = RATIB_SITE_CONTENT_HOME_SNAPSHOT_KEY;
        $val = ratib_site_content_fetch_value_by_key($conn, $key);
        if ($val === null || $val === '') {
            return null;
        }

        return $val;
    }
}

if (!function_exists('ratib_site_content_home_snapshot_db_save')) {
    function ratib_site_content_home_snapshot_db_save(string $json): bool
    {
        $conn = ratib_site_content_db();
        if (!$conn) {
            return false;
        }
        $key = RATIB_SITE_CONTENT_HOME_SNAPSHOT_KEY;
        $stmt = $conn->prepare(
            'INSERT INTO ratib_site_content (content_key, content_value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE content_value = VALUES(content_value), updated_at = CURRENT_TIMESTAMP'
        );
        if (!$stmt) {
            error_log('ratib_site_content_home_snapshot_db_save: prepare failed: ' . $conn->error);

            return false;
        }
        $stmt->bind_param('ss', $key, $json);
        $ok = $stmt->execute();
        $stmt->close();
        if (!$ok) {
            error_log('ratib_site_content_home_snapshot_db_save: execute failed');
        }

        return $ok;
    }
}

if (!function_exists('ratib_site_content_home_snapshot_db_delete')) {
    function ratib_site_content_home_snapshot_db_delete(): void
    {
        $conn = ratib_site_content_db();
        if (!$conn) {
            return;
        }
        $key = RATIB_SITE_CONTENT_HOME_SNAPSHOT_KEY;
        $stmt = $conn->prepare('DELETE FROM ratib_site_content WHERE content_key = ? LIMIT 1');
        if (!$stmt) {
            return;
        }
        $stmt->bind_param('s', $key);
        $stmt->execute();
        $stmt->close();
    }
}

if (!function_exists('ratib_site_content_cache_unlink_json_candidates')) {
    /** Remove snapshot files so an older JSON does not override DB snapshot reads. */
    function ratib_site_content_cache_unlink_json_candidates(): void
    {
        foreach (ratib_site_content_cache_file_candidates() as $path) {
            if ($path !== '' && is_file($path)) {
                @unlink($path);
            }
        }
    }
}

if (!function_exists('ratib_site_content_cache_abs_project_root')) {
    function ratib_site_content_cache_abs_project_root(): string
    {
        return dirname(__DIR__);
    }
}

if (!function_exists('ratib_site_content_cache_path_is_absolute')) {
    function ratib_site_content_cache_path_is_absolute(string $p): bool
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

if (!function_exists('ratib_site_content_cache_resolve_optional_path')) {
    /**
     * Relative paths are resolved from the project root (parent of includes/).
     */
    function ratib_site_content_cache_resolve_optional_path(string $raw): string
    {
        $raw = trim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $raw));
        if ($raw === '') {
            return '';
        }
        if (ratib_site_content_cache_path_is_absolute($raw)) {
            return $raw;
        }

        return ratib_site_content_cache_abs_project_root() . DIRECTORY_SEPARATOR . $raw;
    }
}

if (!function_exists('ratib_site_content_cache_file_candidates')) {
    /**
     * Ordered list of JSON snapshot paths (first preferred).
     * Tries: env RATIB_SITE_CONTENT_CACHE_FILE, constant RATIB_SITE_CONTENT_CACHE_FILE,
     * storage/, cache/, then uploads/ratib_cms_cache/ under each candidate from ratib_uploads_base.php
     * (same writable roots as worker document uploads — often works when storage/ is root-owned).
     *
     * @return list<string>
     */
    function ratib_site_content_cache_file_candidates(): array
    {
        $root = ratib_site_content_cache_abs_project_root();
        $out = [];

        $envFile = getenv('RATIB_SITE_CONTENT_CACHE_FILE');
        if ($envFile !== false && trim((string) $envFile) !== '') {
            $out[] = ratib_site_content_cache_resolve_optional_path((string) $envFile);
        }
        if (defined('RATIB_SITE_CONTENT_CACHE_FILE') && (string) RATIB_SITE_CONTENT_CACHE_FILE !== '') {
            $out[] = ratib_site_content_cache_resolve_optional_path((string) RATIB_SITE_CONTENT_CACHE_FILE);
        }

        $out[] = $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'ratib_site_content_home.json';
        $out[] = $root . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'ratib_site_content_home.json';

        $relUnderUpload = DIRECTORY_SEPARATOR . 'ratib_cms_cache' . DIRECTORY_SEPARATOR . 'ratib_site_content_home.json';
        $uplPhp = $root . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'ratib_uploads_base.php';
        if (is_readable($uplPhp)) {
            require_once $uplPhp;
            if (function_exists('ratib_uploads_read_valid_marker')) {
                $marker = ratib_uploads_read_valid_marker();
                if (is_string($marker) && $marker !== '') {
                    $out[] = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $marker), DIRECTORY_SEPARATOR) . $relUnderUpload;
                }
            }
            if (function_exists('ratib_uploads_candidate_base_dirs')) {
                foreach (ratib_uploads_candidate_base_dirs(false) as $base) {
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

if (!function_exists('ratib_site_content_public_cache_path')) {
    /**
     * First candidate path (legacy / primary file name). See ratib_site_content_cache_file_candidates().
     */
    function ratib_site_content_public_cache_path(): string
    {
        $paths = ratib_site_content_cache_file_candidates();

        if ($paths !== []) {
            return $paths[0];
        }

        return ratib_site_content_cache_abs_project_root() . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'ratib_site_content_home.json';
    }
}

if (!function_exists('ratib_site_content_public_cache_path_for_read')) {
    /**
     * Best readable cache file: newest by mtime among all candidates.
     * Previously the first path in the list won even when an older file existed — public could stay on stale
     * phone/copy while the CMS had saved to another writable directory later in the list.
     */
    function ratib_site_content_public_cache_path_for_read(): ?string
    {
        $bestPath = null;
        $bestMtime = -1;
        foreach (ratib_site_content_cache_file_candidates() as $path) {
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

if (!function_exists('ratib_site_content_normalize_sa_mobile_digits')) {
    /**
     * Saudi mobile: collapse accidental mega-pastes to 966 + 9 digits (12 total).
     * Used only for unusually long digit strings — not for normal CMS edits.
     */
    function ratib_site_content_normalize_sa_mobile_digits(string $digitsOnly): string
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

if (!function_exists('ratib_site_content_phone_digits_for_links')) {
    /**
     * Digits for tel:/wa.me — uses what you saved (digits only). Extra formatting is stripped;
     * we only collapse absurdly long pasted strings so tel:/wa.me stay bounded.
     */
    function ratib_site_content_phone_digits_for_links(string $display, string $fallbackDigits = '966599863868'): string
    {
        $d = preg_replace('/\D+/', '', $display);
        if (strlen($d) < 8) {
            return $fallbackDigits;
        }
        if (strlen($d) > 18) {
            $d = ratib_site_content_normalize_sa_mobile_digits($d);
        }

        return $d;
    }
}

if (!function_exists('ratib_site_content_db_fingerprint')) {
    /**
     * Safe DB identity string for troubleshooting (no secrets).
     * Example: db=outratib_control_panel_db;host=mysql01;port=3306;user=outratib_out@localhost
     */
    function ratib_site_content_db_fingerprint(?mysqli $conn = null): string
    {
        $c = $conn instanceof mysqli ? $conn : ratib_site_content_db();
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

if (!function_exists('ratib_site_content_revision_token')) {
    /**
     * Monotonic content revision token based on ratib_site_content.updated_at (unix timestamp as string).
     * Empty string when DB is unavailable.
     */
    function ratib_site_content_revision_token(): string
    {
        $c = ratib_site_content_db();
        if (!$c instanceof mysqli) {
            return '';
        }
        $res = @$c->query("SELECT COALESCE(UNIX_TIMESTAMP(MAX(updated_at)), 0) AS rev FROM ratib_site_content");
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

if (!function_exists('ratib_site_content_home_flat_from_db')) {
    /**
     * Live ratib_site_content rows for homepage keys — single SELECT … IN (…) via ratib_site_content_fetch_key_values.
     * Returns null only when the marketing DB connection is unavailable (then caller uses caches).
     *
     * @param array<string, string> $defaults
     *
     * @return array<string, string>|null
     */
    function ratib_site_content_home_flat_from_db(array $defaults): ?array
    {
        if (!ratib_site_content_db()) {
            return null;
        }
        if ($defaults === []) {
            return [];
        }
        if (!function_exists('ratib_site_content_fetch_key_values')) {
            $out = [];
            foreach ($defaults as $key => $def) {
                $out[$key] = ratib_site_content_get($key, $def);
            }

            return $out;
        }
        $keys = array_keys($defaults);
        $rows = ratib_site_content_fetch_key_values($keys);
        $out = [];
        foreach ($defaults as $key => $def) {
            // Never use PHP $def when a row may exist: batch IN (...) can omit keys; per-key read matches CMS.
            $out[$key] = array_key_exists($key, $rows)
                ? $rows[$key]
                : ratib_site_content_get($key, $def);
        }

        return $out;
    }
}

if (!function_exists('ratib_site_content_export_public_cache')) {
    /**
     * After CMS save: write JSON snapshot to disk for fast public reads; if disk is not writable,
     * store the same JSON in ratib_site_content under RATIB_SITE_CONTENT_HOME_SNAPSHOT_KEY.
     */
    function ratib_site_content_export_public_cache(): bool
    {
        if (!function_exists('ratib_site_content_defaults_home')) {
            return false;
        }
        $defaults = ratib_site_content_defaults_home();
        $keys = array_keys($defaults);
        $rows = function_exists('ratib_site_content_fetch_key_values')
            ? ratib_site_content_fetch_key_values($keys)
            : [];
        $out = [];
        foreach ($defaults as $key => $def) {
            $out[$key] = array_key_exists($key, $rows) ? $rows[$key] : ratib_site_content_get($key, $def);
        }
        $json = json_encode($out, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            error_log('ratib_site_content_export_public_cache: json_encode failed');

            return false;
        }
        foreach (ratib_site_content_cache_file_candidates() as $path) {
            $dir = dirname($path);
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            if (@file_put_contents($path, $json, LOCK_EX) !== false) {
                foreach (ratib_site_content_cache_file_candidates() as $other) {
                    if ($other !== $path && is_file($other)) {
                        @unlink($other);
                    }
                }
                if (function_exists('ratib_site_content_home_snapshot_db_delete')) {
                    ratib_site_content_home_snapshot_db_delete();
                }

                return true;
            }
            error_log('ratib_site_content_export_public_cache: cannot write ' . $path);
        }

        if (function_exists('ratib_site_content_home_snapshot_db_save') && ratib_site_content_home_snapshot_db_save($json)) {
            ratib_site_content_cache_unlink_json_candidates();

            return true;
        }

        return false;
    }
}

if (!function_exists('ratib_site_content_defaults_public_home')) {
    /** @return array<string, string> */
    function ratib_site_content_defaults_public_home(): array
    {
        return ratib_site_content_defaults_home();
    }
}

if (!function_exists('ratib_site_content_public_home')) {
    /**
     * Resolved copy for pages/home.php (subset for legacy callers).
     *
     * @return array{eyebrow:string,lead:string,platform_title:string,platform_sub:string,program_img:array{0:string,1:string,2:string}}
     */
    function ratib_site_content_public_home(): array
    {
        $f = ratib_site_content_home_flat();

        $p1 = $p2 = $p3 = '';
        if (function_exists('ratib_site_content_home_program_slots_from_flat')) {
            $items = ratib_site_content_home_program_slots_from_flat($f);
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

if (!function_exists('ratib_site_content_asset_url')) {
    if (!function_exists('ratib_site_content_media_storage_dir')) {
        function ratib_site_content_media_storage_dir(): string
        {
            $root = dirname(__DIR__);
            if (!function_exists('ratib_uploads_base_dir')) {
                $uploadsBaseFile = __DIR__ . '/ratib_uploads_base.php';
                if (is_file($uploadsBaseFile)) {
                    require_once $uploadsBaseFile;
                }
            }
            if (function_exists('ratib_uploads_base_dir')) {
                $base = (string) ratib_uploads_base_dir();
                if ($base !== '') {
                    return rtrim($base, '/\\') . DIRECTORY_SEPARATOR . 'ratib_cms_media';
                }
            }

            return $root . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'ratib_cms_media';
        }
    }
    if (!function_exists('ratib_site_content_media_token_from_filename')) {
        function ratib_site_content_media_token_from_filename(string $fileName): string
        {
            return 'scmedia:' . $fileName;
        }
    }
    if (!function_exists('ratib_site_content_media_filename_from_token')) {
        function ratib_site_content_media_filename_from_token(string $stored): string
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
    if (!function_exists('ratib_site_content_media_public_url')) {
        function ratib_site_content_media_public_url(string $baseUrl, string $stored): string
        {
            $name = ratib_site_content_media_filename_from_token($stored);
            if ($name === '') {
                return '';
            }

            return rtrim($baseUrl, '/') . '/api/site-content-media.php?f=' . rawurlencode($name);
        }
    }
    /**
     * @param string $stored     Empty = use fallback; or full URL; or site-relative path e.g. assets/images/x.svg
     * @param string $fallbackRel from project root, e.g. assets/images/program-preview-pipeline.svg
     * @param string $fallbackFs  absolute filesystem path to fallback file (for mtime)
     */
    function ratib_site_content_asset_url(string $baseUrl, string $stored, string $fallbackRel, string $fallbackFs): string
    {
        $stored = trim($stored);
        if ($stored !== '') {
            if (preg_match('#^https?://#i', $stored)) {
                return $stored;
            }
            $tokUrl = ratib_site_content_media_public_url($baseUrl, $stored);
            if ($tokUrl !== '') {
                $name = ratib_site_content_media_filename_from_token($stored);
                $mediaFs = ratib_site_content_media_storage_dir() . DIRECTORY_SEPARATOR . $name;
                $v = is_file($mediaFs) ? (int) filemtime($mediaFs) : time();

                return $tokUrl . '&v=' . $v;
            }
            $rel = ltrim(str_replace('\\', '/', $stored), '/');
            $root = dirname(__DIR__);
            $fs = $root . '/' . str_replace('/', DIRECTORY_SEPARATOR, $rel);
            $v = is_file($fs) ? (int) filemtime($fs) : time();

            return rtrim($baseUrl, '/') . '/' . $rel . '?v=' . $v;
        }
        $v = (int) (@filemtime($fallbackFs) ?: 1);

        return rtrim($baseUrl, '/') . '/' . ltrim($fallbackRel, '/') . '?v=' . $v;
    }
}

