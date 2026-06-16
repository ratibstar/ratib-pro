<?php
declare(strict_types=1);
/**
 * Browser status check — no SSH. Open:
 *   https://rateb.sa/pages/rateb-fix-status.php?control=1
 */
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');

if (!isset($_GET['control']) || (string) $_GET['control'] !== '1') {
    http_response_code(403);
    exit('Add ?control=1 to URL');
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/control_lookup_conn.php';
require_once __DIR__ . '/../control-panel/api/control/agency-db-helper.php';

$items = [];

$items[] = [
    'label' => 'PHP helper rateb_users_primary_key_column',
    'ok' => function_exists('rateb_users_primary_key_column'),
    'hint' => 'Deploy includes/rateb-users-schema.php then hard-refresh browser',
];

$items[] = [
    'label' => '.env DB_PASS set',
    'ok' => defined('DB_PASS') && (string) DB_PASS !== '',
    'hint' => 'DirectAdmin File Manager → domains/rateb.sa/public_html/.env → DB_PASS=admin_rateb password',
];

$ctrl = function_exists('get_control_lookup_conn') ? get_control_lookup_conn() : null;
$items[] = [
    'label' => 'Control panel DB connect',
    'ok' => $ctrl instanceof mysqli,
    'hint' => 'Check DB_USER/DB_PASS in .env matches DirectAdmin MySQL user admin_rateb',
];

$stalePass = 0;
if ($ctrl instanceof mysqli) {
    $r = $ctrl->query("SELECT COUNT(*) AS c FROM control_agencies WHERE db_user='admin_rateb' AND TRIM(COALESCE(db_pass,'')) <> ''");
    if ($r && ($row = $r->fetch_assoc())) {
        $stalePass = (int) ($row['c'] ?? 0);
    }
}
$items[] = [
    'label' => 'control_agencies.db_pass empty for admin_rateb',
    'ok' => $stalePass === 0,
    'hint' => 'phpMyAdmin → admin_control_panel_db → run CLEAR_CONTROL_AGENCIES_DB_PASS.sql',
];

$testDbs = ['admin_bangladesh', 'admin_philippines', 'admin_kenya'];
foreach ($testDbs as $db) {
    $ok = false;
    $hint = '';
    if ($ctrl instanceof mysqli) {
        $st = $ctrl->prepare('SELECT db_host, db_port, db_user, db_pass, db_name, country_id FROM control_agencies WHERE db_name = ? AND is_active = 1 LIMIT 1');
        if ($st) {
            $st->bind_param('s', $db);
            $st->execute();
            $rs = $st->get_result();
            $ag = $rs ? $rs->fetch_assoc() : null;
            $st->close();
            if (is_array($ag)) {
                $acct = getAgencyDbConnection($ag, (int) ($ag['country_id'] ?? 0));
                if ($acct && isset($acct['conn']) && $acct['conn'] instanceof mysqli) {
                    $ok = true;
                    $acct['conn']->close();
                } else {
                    $hint = function_exists('getAgencyDbConnectionLastError') ? getAgencyDbConnectionLastError() : 'connect failed';
                }
            } else {
                $hint = 'no agency row';
            }
        }
    }
    $items[] = [
        'label' => 'Connect ' . $db,
        'ok' => $ok,
        'hint' => $hint !== '' ? $hint : 'DirectAdmin → admin_rateb → Full access on this database',
    ];
}

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RATEB fix status</title>
    <style>
        body{font-family:system-ui,sans-serif;margin:2rem;background:#0f172a;color:#e2e8f0}
        h1{color:#a78bfa}
        .ok{color:#4ade80}.bad{color:#f87171}
        li{margin:.75rem 0;padding:.75rem 1rem;background:#1e293b;border-radius:8px;list-style:none}
        .hint{font-size:.9rem;color:#94a3b8;margin-top:.35rem}
    </style>
</head>
<body>
<h1>RATEB fix status (browser)</h1>
<ul>
<?php foreach ($items as $it): ?>
    <li>
        <span class="<?= $it['ok'] ? 'ok' : 'bad' ?>"><?= $it['ok'] ? '✓' : '✗' ?></span>
        <?= htmlspecialchars((string) $it['label'], ENT_QUOTES, 'UTF-8') ?>
        <?php if (!$it['ok'] && !empty($it['hint'])): ?>
            <div class="hint"><?= htmlspecialchars((string) $it['hint'], ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
    </li>
<?php endforeach; ?>
</ul>
<p class="hint">When all ✓ — test https://rateb.sa/philippines/login (admin / 123456)</p>
</body>
</html>
