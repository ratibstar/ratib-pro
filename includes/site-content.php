<?php
/**
 * Public marketing copy stored in the control-panel database (key/value).
 * Safe no-op if DB unavailable — callers use defaults.
 */
if (!function_exists('ratib_site_content_db')) {
    function ratib_site_content_db(): ?mysqli
    {
        static $conn = null;
        static $tried = false;
        if ($tried) {
            return $conn instanceof mysqli ? $conn : null;
        }
        $tried = true;
        if (!defined('DB_HOST') || !defined('DB_USER') || !defined('CONTROL_PANEL_DB_NAME')) {
            return null;
        }
        $port = defined('DB_PORT') ? (int) DB_PORT : 3306;
        $dbName = CONTROL_PANEL_DB_NAME;
        try {
            $c = new mysqli(DB_HOST, DB_USER, DB_PASS, $dbName, $port);
            $c->set_charset('utf8mb4');
            $conn = $c;
            return $conn;
        } catch (Throwable $e) {
            error_log('ratib_site_content_db: ' . $e->getMessage());
            return null;
        }
    }
}

if (!function_exists('ratib_site_content_defaults_public_home')) {
    /** @return array<string, string> */
    function ratib_site_content_defaults_public_home(): array
    {
        return [
            'home.hero.eyebrow' => 'Recruitment Automation & Tracking Intelligence Base',
            'home.hero.lead' => 'Production control plane for sending-country agencies and host-market programs: lifecycle orchestration, workforce telemetry, compliance gates, and ledger-linked billing—same surfaces operations teams use daily, not a marketing shell.',
            'home.platform.title' => 'Built for regulated, high-volume recruitment operations',
            'home.platform.sub' => 'Deployed as a control plane: tenant-isolated data paths, encrypted transit, immutable workflow history, and finance-grade events organizations can reconcile—not narrative dashboards.',
            'home.program.img1' => '',
            'home.program.img2' => '',
            'home.program.img3' => '',
        ];
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
            return $default;
        }
        $stmt->bind_param('s', $key);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        if (!$row || !isset($row['content_value'])) {
            return $default;
        }
        return (string) $row['content_value'];
    }
}

if (!function_exists('ratib_site_content_public_home')) {
    /**
     * Resolved copy for pages/home.php (merged with defaults).
     *
     * @return array{eyebrow:string,lead:string,platform_title:string,platform_sub:string,program_img:array{0:string,1:string,2:string}}
     */
    function ratib_site_content_public_home(): array
    {
        $d = ratib_site_content_defaults_public_home();
        $eyebrow = ratib_site_content_get('home.hero.eyebrow', $d['home.hero.eyebrow']);
        $lead = ratib_site_content_get('home.hero.lead', $d['home.hero.lead']);
        $platformTitle = ratib_site_content_get('home.platform.title', $d['home.platform.title']);
        $platformSub = ratib_site_content_get('home.platform.sub', $d['home.platform.sub']);
        $img1 = ratib_site_content_get('home.program.img1', $d['home.program.img1']);
        $img2 = ratib_site_content_get('home.program.img2', $d['home.program.img2']);
        $img3 = ratib_site_content_get('home.program.img3', $d['home.program.img3']);

        return [
            'eyebrow' => $eyebrow,
            'lead' => $lead,
            'platform_title' => $platformTitle,
            'platform_sub' => $platformSub,
            'program_img' => [$img1, $img2, $img3],
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
