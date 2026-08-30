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
        $result = $this->dispatchTransactional(
            $mail,
            $to,
            (string) __('mail_test_subject'),
            (string) __('mail_test_body'),
            null,
            null,
            null
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

        if ($isExternal) {
            $dns = (new MailDnsCheckService())->check($fromDomain !== '' ? $fromDomain : 'rateb.sa');
            $base = __('mail_test_ok', ['email' => $to, 'host' => $host]);
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

    /**
     * Supplier comms + modules: identical SMTP stack as sendTest().
     *
     * @return array{success:bool,error_code:?string,error:?string,smtp_host:?string,via_localhost:bool}
     */
    public function sendTransactionalMail(
        string $to,
        string $subject,
        string $plainBody,
        ?string $replyTo = null,
        ?string $cc = null,
        ?string $bcc = null
    ): array {
        $to = trim($to);
        if ($to === '' || !Str::isValidEmail($to)) {
            return [
                'success' => false,
                'error_code' => 'invalid_email',
                'error' => __('mail_test_invalid'),
                'smtp_host' => null,
                'via_localhost' => false,
            ];
        }
        $mailSvc = new MailConfigService();
        if (!$mailSvc->isReady()) {
            return [
                'success' => false,
                'error_code' => 'smtp_not_configured',
                'error' => __('mail_password_env_hint'),
                'smtp_host' => null,
                'via_localhost' => false,
            ];
        }
        return $this->dispatchTransactional(
            new MailService(),
            $to,
            $subject,
            $plainBody,
            $replyTo,
            $cc,
            $bcc
        );
    }

    /**
     * @return array{success:bool,error_code:?string,error:?string,smtp_host:?string,via_localhost:bool}
     */
    private function dispatchTransactional(
        MailService $mail,
        string $to,
        string $subject,
        string $plainBody,
        ?string $replyTo,
        ?string $cc,
        ?string $bcc
    ): array {
        $result = $mail->sendTransactional($to, $subject, $plainBody, $replyTo, $cc, $bcc);
        return [
            'success' => (bool) ($result['success'] ?? false),
            'error_code' => $result['error_code'] ?? null,
            'error' => $result['error'] ?? $mail->lastError(),
            'smtp_host' => $result['smtp_host'] ?? $mail->lastSmtpHost(),
            'via_localhost' => !empty($result['via_localhost']),
        ];
    }

    /** @return array{external:bool,recipient_mx_ok:bool,sender_dns_ready:bool,warnings:list<string>} */
    public function assessExternalDelivery(string $to): array
    {
        $cfg = (new MailConfigService())->resolve();
        $fromDomain = Str::emailDomain((string) ($cfg['from_email'] ?? ''));
        if ($fromDomain === '' && trim((string) ($cfg['user'] ?? '')) !== '' && Str::isValidEmail((string) $cfg['user'])) {
            $fromDomain = Str::emailDomain((string) $cfg['user']);
        }
        $toDomain = Str::emailDomain($to);
        $isExternal = $toDomain !== '' && $fromDomain !== '' && strcasecmp($toDomain, $fromDomain) !== 0;
        if (!$isExternal) {
            return [
                'external' => false,
                'recipient_mx_ok' => true,
                'sender_dns_ready' => true,
                'warnings' => [],
            ];
        }

        $senderDomain = $fromDomain !== '' ? $fromDomain : 'rateb.sa';
        $dns = (new MailDnsCheckService())->checkFast($senderDomain);
        $recipientMx = (new MailDnsCheckService())->recipientMxStatus($to);
        $warnings = [];
        if (!$recipientMx['ok']) {
            $warnings[] = (string) __('comm_email_recipient_mx_warn', ['domain' => $toDomain]);
        }
        if (!$dns['ready_for_external']) {
            $warnings[] = (string) __('mail_test_external_dns_warn', ['email' => $to]);
        }

        return [
            'external' => true,
            'recipient_mx_ok' => $recipientMx['ok'],
            'sender_dns_ready' => (bool) ($dns['ready_for_external'] ?? false),
            'warnings' => $warnings,
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
