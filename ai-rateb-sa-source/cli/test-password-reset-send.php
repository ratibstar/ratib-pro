<?php
declare(strict_types=1);

/**
 * CLI: send one password-reset email for a known account (presence/status only).
 * Usage: php cli/test-password-reset-send.php email@example.com
 */
require dirname(__DIR__) . '/config/bootstrap.php';
require_once dirname(__DIR__) . '/app/auth/reset-lib.php';

$ini = dirname(__DIR__) . '/.user.ini';
if (is_readable($ini) && preg_match('/^\s*auto_prepend_file\s*=\s*"?([^"\r\n]+?)"?\s*$/m', (string) file_get_contents($ini), $match)) {
    $prepend = trim($match[1]);
    if (is_readable($prepend)) {
        require $prepend;
    }
}

$email = strtolower(trim((string) ($argv[1] ?? '')));
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Usage: php cli/test-password-reset-send.php email@example.com\n");
    exit(1);
}

$pdo = $GLOBALS['pdo'] ?? null;
if (!$pdo instanceof PDO) {
    fwrite(STDERR, "No database\n");
    exit(1);
}

$before = (int) $pdo->query("SELECT COUNT(*) FROM outbound_email_log WHERE template_key = 'password_reset'")->fetchColumn();
password_reset_request($pdo, $email);
$after = (int) $pdo->query("SELECT COUNT(*) FROM outbound_email_log WHERE template_key = 'password_reset'")->fetchColumn();
$last = $pdo->query("SELECT status, recipient, created_at FROM outbound_email_log WHERE template_key = 'password_reset' ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: [];

echo 'mail_enabled=' . (!empty(rateb_mail_config()['enabled']) ? 'yes' : 'no') . PHP_EOL;
echo 'log_before=' . $before . PHP_EOL;
echo 'log_after=' . $after . PHP_EOL;
echo 'last_status=' . (string) ($last['status'] ?? 'none') . PHP_EOL;
echo 'last_recipient_set=' . (!empty($last['recipient']) ? 'yes' : 'no') . PHP_EOL;
echo 'last_at=' . (string) ($last['created_at'] ?? '') . PHP_EOL;
