<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Helpers\Str;

final class MailTestService
{
    /** @return array{level:string,message:string,detail:array<string, mixed>} */
    public function sendTest(string $to): array
    {
        $to = trim($to);
        if ($to === '' || !Str::isValidEmail($to)) {
            return [
                'level' => 'error',
                'message' => __('mail_test_invalid'),
                'detail' => [],
            ];
        }

        $mailSvc = new MailConfigService();
        if (!$mailSvc->isReady()) {
            return [
                'level' => 'error',
                'message' => __('mail_password_env_hint'),
                'detail' => ['ready' => false],
            ];
        }

        $cfg = $mailSvc->resolve();
        $from = trim((string) ($cfg['from_email'] ?? ''));
        $fromDomain = Str::emailDomain($from);
        $toDomain = Str::emailDomain($to);
        $isExternal = $toDomain !== '' && $fromDomain !== '' && strcasecmp($toDomain, $fromDomain) !== 0;
        $port25Warn = null;
        if ($isExternal) {
            $dnsPre = (new MailDnsCheckService())->checkFast($fromDomain !== '' ? $fromDomain : 'rateb.sa');
            if (empty($dnsPre['port25']['ok']) && empty($dnsPre['smtp_relay'])) {
                $port25Warn = __('mail_port25_blocked_hint');
            }
        }

        $mail = new MailService();
        $bcc = null;
        if ($isExternal && $from !== '' && Str::isValidEmail($from) && strcasecmp($from, $to) !== 0) {
            $bcc = $from;
        } elseif (!$isExternal && $from !== '' && Str::isValidEmail($from) && strcasecmp($from, $to) !== 0) {
            $bcc = $from;
        }
        $result = $mail->sendDetailed(
            $to,
            __('mail_test_subject'),
            '<div dir="auto" style="font-family:Tajawal,sans-serif"><p>' . htmlspecialchars(__('mail_test_body'), ENT_QUOTES, 'UTF-8') . '</p></div>',
            null,
            null,
            $bcc
        );

        $sent = (bool) ($result['success'] ?? false);
        $host = (string) ($result['smtp_host'] ?? $mail->lastSmtpHost() ?? ($cfg['host'] ?? ''));
        $detail = [
            'ready' => true,
            'host' => $host,
            'configured_host' => (string) ($cfg['host'] ?? ''),
            'via_localhost' => !empty($result['via_localhost']),
            'external' => $isExternal,
            'error_code' => $result['error_code'] ?? null,
            'error' => $result['error'] ?? $mail->lastError(),
        ];
        if ($port25Warn !== null) {
            $detail['port25_warn'] = $port25Warn;
        }

        if (!$sent) {
            return [
                'level' => 'error',
                'message' => (string) ($result['error'] ?? $mail->lastError() ?? __('mail_test_failed')),
                'detail' => $detail,
            ];
        }

        if ($isExternal && !empty($result['via_localhost'])) {
            return [
                'level' => 'error',
                'message' => __('mail_test_localhost_failed'),
                'detail' => $detail,
            ];
        }

        if ($isExternal) {
            $dns = (new MailDnsCheckService())->check($fromDomain !== '' ? $fromDomain : 'rateb.sa');
            $base = __('mail_test_ok', ['email' => $to, 'host' => $host]);
            if ($bcc !== null) {
                $base .= ' — ' . __('mail_test_inbox_copy', ['email' => $bcc]);
            }
            if ($port25Warn !== null) {
                $base .= ' — ' . $port25Warn;
            }
            if (!$dns['ready_for_external']) {
                return [
                    'level' => 'warning',
                    'message' => $base . ' — ' . __('mail_test_external_dns_warn', ['email' => $to]),
                    'detail' => array_merge($detail, ['dns' => $dns]),
                ];
            }
            return [
                'level' => 'warning',
                'message' => $base . ' — ' . __('mail_test_external_bounce_hint'),
                'detail' => array_merge($detail, ['dns' => $dns]),
            ];
        }

        return [
            'level' => 'success',
            'message' => __('mail_test_ok', ['email' => $to, 'host' => $host]),
            'detail' => $detail,
        ];
    }

    /** @return array<string, mixed> */
    public function diagnostics(): array
    {
        $mailSvc = new MailConfigService();
        $cfg = $mailSvc->resolve();
        $host = (string) ($cfg['host'] ?? '');
        return [
            'host' => $host,
            'port' => (int) ($cfg['port'] ?? 587),
            'encryption' => (string) ($cfg['encryption'] ?? 'tls'),
            'user' => (string) ($cfg['user'] ?? ''),
            'from_email' => (string) ($cfg['from_email'] ?? ''),
            'ready' => $mailSvc->isReady(),
            'localhost' => $mailSvc->isLocalRelayHost($host),
            'relay' => $mailSvc->isSmtpRelayHost($host),
        ];
    }
}
