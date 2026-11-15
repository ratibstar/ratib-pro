<?php
declare(strict_types=1);

require_once __DIR__ . '/../../migrations/payment_commerce_schema.php';

/** @param array<string,string> $headers */
function rateb_mail_config(): array
{
    $cfg = [];
    $localFile = dirname(__DIR__, 2) . '/config/ai.local.php';
    if (is_file($localFile)) {
        $loaded = require $localFile;
        if (is_array($loaded)) {
            $cfg = $loaded;
        }
    }
    return [
        'enabled' => in_array(strtolower((string) (getenv('RATEB_MAIL_ENABLED') ?: ($cfg['mail_enabled'] ?? '1'))), ['1', 'true', 'yes', 'on'], true),
        'from' => trim((string) (getenv('RATEB_MAIL_FROM') ?: ($cfg['mail_from'] ?? 'noreply@rateb.sa'))),
        'from_name' => trim((string) (getenv('RATEB_MAIL_FROM_NAME') ?: ($cfg['mail_from_name'] ?? 'RATEB AI'))),
    ];
}

function rateb_mail_send(PDO $pdo, string $to, string $subject, string $bodyText, string $templateKey = 'generic', ?int $userId = null, ?string $meta = null): bool
{
    $to = trim($to);
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    $config = rateb_mail_config();
    ensure_payment_commerce_schema($pdo);
    $status = 'skipped';
    $sent = false;
    if ($config['enabled']) {
        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'From: ' . rateb_mail_encode_header($config['from_name'], $config['from']),
        ];
        $sent = @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $bodyText, implode("\r\n", $headers));
        $status = $sent ? 'sent' : 'failed';
    } else {
        $status = 'disabled';
    }
  $pdo->prepare('INSERT INTO outbound_email_log (user_id, template_key, recipient, subject, status, meta) VALUES (?, ?, ?, ?, ?, ?)')
        ->execute([$userId, $templateKey, $to, mb_substr($subject, 0, 250), $status, $meta !== null ? mb_substr($meta, 0, 500) : null]);
    return $sent;
}

function rateb_mail_encode_header(string $name, string $email): string
{
    $safeEmail = str_replace(['"', "\r", "\n"], '', $email);
    $safeName = str_replace(["\r", "\n"], '', $name);
    if ($safeName === '') {
        return $safeEmail;
    }
    return '=?UTF-8?B?' . base64_encode($safeName) . '?= <' . $safeEmail . '>';
}