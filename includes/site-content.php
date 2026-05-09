<?php
/**
 * Public marketing copy stored in the control-panel database (key/value).
 * Safe no-op if DB unavailable — callers use defaults.
 */
if (!function_exists('ratib_site_content_db_credentials')) {
    /**
     * Resolve connection params for reading ratib_site_content (control DB).
     * Public site often uses DB_NAME for the tenant DB; the CMS lives in CONTROL_PANEL_DB_NAME.
     * Optional env (recommended when the app DB user has no access to the control DB):
     *   RATIB_SITE_CONTENT_DB_HOST, RATIB_SITE_CONTENT_DB_PORT, RATIB_SITE_CONTENT_DB_USER,
     *   RATIB_SITE_CONTENT_DB_PASS, RATIB_SITE_CONTENT_DB_NAME
     * Or define CONTROL_PANEL_DB_USER / CONTROL_PANEL_DB_PASS in the host env file after DB_*.
     *
     * @return array{0:string,1:int,2:string,3:string,4:string}|null
     */
    function ratib_site_content_db_credentials(): ?array
    {
        if (!defined('CONTROL_PANEL_DB_NAME')) {
            return null;
        }
        $host = getenv('RATIB_SITE_CONTENT_DB_HOST');
        $host = ($host !== false && $host !== '') ? (string) $host : (defined('DB_HOST') ? DB_HOST : '');
        $portRaw = getenv('RATIB_SITE_CONTENT_DB_PORT');
        $port = ($portRaw !== false && $portRaw !== '') ? (int) $portRaw : (defined('DB_PORT') ? (int) DB_PORT : 3306);
        $dbName = getenv('RATIB_SITE_CONTENT_DB_NAME');
        $dbName = ($dbName !== false && $dbName !== '') ? (string) $dbName : CONTROL_PANEL_DB_NAME;

        $user = getenv('RATIB_SITE_CONTENT_DB_USER');
        if ($user === false || $user === '') {
            $user = (defined('CONTROL_PANEL_DB_USER') && (string) CONTROL_PANEL_DB_USER !== '') ? (string) CONTROL_PANEL_DB_USER : (defined('DB_USER') ? (string) DB_USER : '');
        } else {
            $user = (string) $user;
        }
        $pass = getenv('RATIB_SITE_CONTENT_DB_PASS');
        if ($pass === false) {
            $pass = (defined('CONTROL_PANEL_DB_PASS')) ? (string) CONTROL_PANEL_DB_PASS : (defined('DB_PASS') ? (string) DB_PASS : '');
        } else {
            $pass = (string) $pass;
        }

        if ($host === '' || $user === '') {
            return null;
        }

        return [$host, $port, $user, $pass, $dbName];
    }
}

if (!function_exists('ratib_site_content_db')) {
    function ratib_site_content_db(): ?mysqli
    {
        static $conn = null;
        static $tried = false;
        if ($tried) {
            return $conn instanceof mysqli ? $conn : null;
        }
        $tried = true;
        $cred = ratib_site_content_db_credentials();
        if ($cred === null) {
            return null;
        }
        [$host, $port, $user, $pass, $dbName] = $cred;
        try {
            $c = new mysqli($host, $user, $pass, $dbName, $port);
            if ($c->connect_errno) {
                error_log('ratib_site_content_db: connection failed (' . $c->connect_errno . ') ' . $c->connect_error);

                return null;
            }
            $c->set_charset('utf8mb4');
            $conn = $c;

            return $conn;
        } catch (Throwable $e) {
            error_log('ratib_site_content_db: ' . $e->getMessage());

            return null;
        }
    }
}

if (!function_exists('ratib_site_content_stmt_fetch_value')) {
    /**
     * Fetch single string column; works without mysqlnd (get_result unavailable).
     */
    function ratib_site_content_stmt_fetch_value(mysqli_stmt $stmt): ?string
    {
        if (!$stmt->execute()) {
            return null;
        }
        $res = $stmt->get_result();
        if ($res !== false) {
            $row = $res->fetch_assoc();

            return isset($row['content_value']) ? (string) $row['content_value'] : null;
        }
        $contentValue = null;
        $stmt->bind_result($contentValue);
        if ($stmt->fetch()) {
            return $contentValue !== null ? (string) $contentValue : '';
        }

        return null;
    }
}

if (!function_exists('ratib_site_content_get')) {
    function ratib_site_content_get(string $key, string $default = ''): string
    {
        $conn = ratib_site_content_db();
        if (!$conn) {
            return $default;
        }
        $stmt = $conn->prepare('SELECT content_value FROM ratib_site_content WHERE content_key = ? LIMIT 1');
        if (!$stmt) {
            error_log('ratib_site_content_get: prepare failed: ' . $conn->error);

            return $default;
        }
        $stmt->bind_param('s', $key);
        $val = ratib_site_content_stmt_fetch_value($stmt);
        $stmt->close();
        if ($val === null) {
            return $default;
        }

        return $val;
    }
}

require_once __DIR__ . '/site-content-home-data.php';

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

        return [
            'eyebrow' => $f['home.hero.eyebrow'] ?? '',
            'lead' => $f['home.hero.lead'] ?? '',
            'platform_title' => $f['home.platform.title'] ?? '',
            'platform_sub' => $f['home.platform.sub'] ?? '',
            'program_img' => [
                $f['home.program.img1'] ?? '',
                $f['home.program.img2'] ?? '',
                $f['home.program.img3'] ?? '',
            ],
        ];
    }
}

if (!function_exists('ratib_site_content_asset_url')) {
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
