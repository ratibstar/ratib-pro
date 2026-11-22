<?php
declare(strict_types=1);

require dirname(__DIR__) . '/config/bootstrap.php';
require_once dirname(__DIR__) . '/app/notify/mail.php';

$ini = dirname(__DIR__) . '/.user.ini';
if (is_readable($ini) && preg_match('/^\s*auto_prepend_file\s*=\s*"?([^"\r\n]+?)"?\s*$/m', (string) file_get_contents($ini), $match)) {
    $prepend = trim($match[1]);
    if (is_readable($prepend)) {
        require $prepend;
    }
}

$cfg = rateb_mail_config();
echo 'enabled=' . (!empty($cfg['enabled']) ? 'yes' : 'no') . PHP_EOL;
echo 'from_present=' . (trim((string) $cfg['from']) !== '' ? 'yes' : 'no') . PHP_EOL;
echo 'from_name_present=' . (trim((string) $cfg['from_name']) !== '' ? 'yes' : 'no') . PHP_EOL;
echo 'mail_fn=' . (function_exists('mail') ? 'yes' : 'no') . PHP_EOL;
echo 'env.RATEB_MAIL_ENABLED=' . (getenv('RATEB_MAIL_ENABLED') !== false ? 'set' : 'unset') . PHP_EOL;

$pdo = $GLOBALS['pdo'] ?? null;
if ($pdo instanceof PDO) {
    $rows = $pdo->query("SELECT status, COUNT(*) c FROM outbound_email_log GROUP BY status")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        echo 'log.' . $row['status'] . '=' . $row['c'] . PHP_EOL;
    }
    $last = $pdo->query("SELECT template_key, status, created_at FROM outbound_email_log ORDER BY id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($last as $row) {
        echo 'last=' . $row['created_at'] . ' ' . $row['template_key'] . ' ' . $row['status'] . PHP_EOL;
    }
}
