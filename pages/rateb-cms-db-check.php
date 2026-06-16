<?php
declare(strict_types=1);
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');

$lines = [];

try {
    require_once __DIR__ . '/../includes/config.php';
    $lines[] = 'config=ok';

    $dbName = defined('CONTROL_PANEL_DB_NAME') ? (string) CONTROL_PANEL_DB_NAME : (getenv('CONTROL_PANEL_DB_NAME') ?: '');
    if ($dbName === '') {
        $dbName = defined('DB_NAME') ? (string) DB_NAME : (getenv('DB_NAME') ?: '');
    }
    $lines[] = 'DB_NAME=' . (defined('DB_NAME') ? (string) DB_NAME : '(empty)');
    $lines[] = 'CONTROL_PANEL_DB_NAME=' . ($dbName !== '' ? $dbName : '(empty)');
    if ($dbName !== '' && defined('DB_NAME') && (string) DB_NAME === $dbName) {
        $lines[] = 'WARN CONTROL_PANEL_DB_NAME equals DB_NAME — CMS and control_admins should use admin_control_panel_db';
    }

    require_once __DIR__ . '/../includes/site-content.php';
    $conn = rateb_site_content_db();
    if (!$conn instanceof mysqli) {
        $lines[] = 'cms_conn=FAIL (no readable connection)';
    } else {
        $lines[] = 'cms_conn=ok';
        $resolved = function_exists('rateb_site_content_resolve_table_name')
            ? rateb_site_content_resolve_table_name($conn)
            : null;
        $lines[] = 'cms_table=' . ($resolved ?? '(none — rateb_site_content missing in this DB)');
        if ($resolved !== null) {
            $res = rateb_site_content_mysqli_query_safe($conn, 'SELECT COUNT(*) AS c FROM `' . $resolved . '`');
            if ($res && ($row = $res->fetch_assoc())) {
                $lines[] = 'cms_row_count=' . (string) ($row['c'] ?? '?');
            }
            foreach (
                [
                    RATEB_SITE_CONTENT_HOME_SNAPSHOT_KEY,
                    defined('RATEB_SITE_CONTENT_HOME_SNAPSHOT_KEY_LEGACY')
                        ? RATEB_SITE_CONTENT_HOME_SNAPSHOT_KEY_LEGACY
                        : '__ratib_home_json_snapshot.v1__',
                ] as $snapKey
            ) {
                $val = rateb_site_content_fetch_value_by_key($conn, $snapKey);
                $lines[] = 'snapshot_' . $snapKey . '=' . ($val !== null && $val !== '' ? 'yes' : 'no');
            }
        }
    }
} catch (Throwable $e) {
    $lines[] = 'FAIL ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine();
}

echo implode("\n", $lines) . "\n";
