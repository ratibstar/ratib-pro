<?php
declare(strict_types=1);
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');

$lines = [];

try {
    require_once __DIR__ . '/../includes/config.php';
    require_once __DIR__ . '/../includes/rateb-legacy-host.php';
    require_once __DIR__ . '/../includes/control_lookup_conn.php';

    $pro = defined('RATEB_PRO_URL') ? (string) RATEB_PRO_URL : (defined('SITE_URL') ? (string) SITE_URL : '');
    $cp = defined('CONTROL_PANEL_DB_NAME') ? (string) CONTROL_PANEL_DB_NAME : '';
    $lines[] = 'RATEB_PRO_URL=' . ($pro !== '' ? $pro : '(empty)');
    $lines[] = 'CONTROL_PANEL_DB_NAME=' . ($cp !== '' ? $cp : '(empty)');

    $ctrl = function_exists('get_control_lookup_conn') ? get_control_lookup_conn() : null;
    if (!$ctrl instanceof mysqli) {
        $lines[] = 'control_conn=FAIL';
    } else {
        $lines[] = 'control_conn=ok';
        $res = $ctrl->query(
            'SELECT id, name, site_url, db_name, db_user FROM control_agencies ORDER BY id ASC LIMIT 50'
        );
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $id = (int) ($row['id'] ?? 0);
                $site = trim((string) ($row['site_url'] ?? ''));
                $legacy = $site !== '' && rateb_url_host_is_legacy_ratib($site);
                $lines[] = sprintf(
                    'agency_%d name=%s site_url=%s legacy=%s db=%s user=%s',
                    $id,
                    (string) ($row['name'] ?? ''),
                    $site !== '' ? $site : '(empty)',
                    $legacy ? 'YES' : 'no',
                    (string) ($row['db_name'] ?? ''),
                    (string) ($row['db_user'] ?? '')
                );
            }
        }
        $cms = $ctrl->query(
            "SELECT COUNT(*) AS c FROM rateb_site_content WHERE content_value LIKE '%ratib.sa%'"
        );
        if ($cms && ($cRow = $cms->fetch_assoc())) {
            $lines[] = 'cms_rows_with_ratib.sa=' . (string) ($cRow['c'] ?? '0');
        }
    }
} catch (Throwable $e) {
    $lines[] = 'FAIL ' . $e->getMessage();
}

echo implode("\n", $lines) . "\n";
