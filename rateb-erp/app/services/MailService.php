<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\EmailTemplate;

final class MailService
{
    private ?string $lastErrorCode = null;
    private ?string $lastError = null;
    private ?string $lastSmtpHost = null;

    public function lastSmtpHost(): ?string
    {
        return $this->lastSmtpHost;
    }

    public function lastError(): ?string
    {
        return $this->lastError;
    }

    public function lastErrorCode(): ?string
    {
        return $this->lastErrorCode;
    }

    /** @return array{success:bool,error_code:?string,error:?string,smtp_host:?string} */
    public function sendDetailed(string $to, string $subject, string $htmlBody, ?string $replyTo = null, ?string $cc = null, ?string $bcc = null): array
    {
        $this->lastError = null;
        $this->lastErrorCode = null;
        $this->lastSmtpHost = null;
        $cfg = (new MailConfigService())->resolve();
        $fromEmail = $cfg['from_email'] !== '' ? $cfg['from_email'] : 'info@rateb.sa';
        $fromName = $cfg['from_name'] !== '' ? $cfg['from_name'] : 'Rateb ERP';

        if ($cfg['host'] === '' || $cfg['pass'] === '') {
            $this->setError('smtp_not_configured', __('comm_email_smtp_required'));
            return ['success' => false, 'error_code' => $this->lastErrorCode, 'error' => $this->lastError];
        }

        $profiles = $this->smtpProfiles($cfg, $to);
        $primaryError = null;
        $sent = false;

        foreach ($profiles as $profile) {
            $ok = $this->sendSmtp(
                $profile['host'],
                $profile['port'],
                $profile['encryption'],
                $cfg['user'],
                $cfg['pass'],
                $fromEmail,
                $fromName,
                $to,
                $subject,
                $htmlBody,
                $replyTo,
                $cc,
                $bcc
            );
            if ($ok) {
                $sent = true;
                $this->lastSmtpHost = $profile['host'];
                break;
            }
            if ($primaryError === null) {
                $primaryError = $this->lastError;
            }
        }

        if (!$sent && $primaryError !== null) {
            $this->lastError = $primaryError;
        }

        (new NotificationService())->queueEmail($to, $subject, $htmlBody, $sent ? 'sent' : 'failed');
        if ($sent) {
            Logger::info('Email sent', ['to' => $to, 'subject' => $subject, 'smtp_host' => $this->lastSmtpHost]);
        } elseif (!$sent) {
            Logger::warning('Email send failed', [
                'to' => $to,
                'subject' => $subject,
                'code' => $this->lastErrorCode,
                'error' => $this->lastError,
                'profiles' => $profiles,
            ]);
        }
        return [
            'success' => $sent,
            'error_code' => $this->lastErrorCode,
            'error' => $this->lastError,
            'smtp_host' => $this->lastSmtpHost,
            'via_localhost' => $sent && $this->lastSmtpHost !== null && $this->isLoopbackHost($this->lastSmtpHost),
        ];
    }

    public function send(string $to, string $subject, string $htmlBody, ?string $textBody = null, bool $recordQueue = true, ?string $replyTo = null, ?string $cc = null, ?string $bcc = null): bool
    {
        return $this->sendDetailed($to, $subject, $htmlBody, $replyTo, $cc, $bcc)['success'];
    }

    public function isSmtpConfigured(): bool
    {
        return (new MailConfigService())->isReady();
    }

    public function queue(string $to, string $subject, string $htmlBody): bool
    {
        (new NotificationService())->queueEmail($to, $subject, $htmlBody, 'pending');
        return true;
    }

    public function sendTemplate(string $to, string $slug, array $vars = [], bool $async = false): bool
    {
        $tpl = (new EmailTemplate())->queryOne('SELECT * FROM rateb_email_templates WHERE slug = :s AND is_active = 1 LIMIT 1', ['s' => $slug]);
        if (!$tpl) {
            return false;
        }
        $subject = $this->replaceVars((string) $tpl['subject'], $vars);
        $body = $this->replaceVars((string) $tpl['body_html'], $vars);
        if ($async) {
            return $this->queue($to, $subject, $body);
        }
        return $this->send($to, $subject, $body);
    }

    public function sendTemplateAsync(string $to, string $slug, array $vars = []): bool
    {
        return $this->sendTemplate($to, $slug, $vars, true);
    }

    /**
     * @param array{host:string,port:int,encryption:string,user:string,pass:string,from_email:string,from_name:string} $cfg
     * @return list<array{host:string,port:int,encryption:string}>
     */
    private function smtpProfiles(array $cfg, string $to): array
    {
        $primary = ['host' => $cfg['host'], 'port' => $cfg['port'], 'encryption' => $cfg['encryption']];
        $mailTls = ['host' => 'mail.rateb.sa', 'port' => 587, 'encryption' => 'tls'];
        $mailSsl = ['host' => 'mail.rateb.sa', 'port' => 465, 'encryption' => 'ssl'];
        $localhost = ['host' => 'localhost', 'port' => 587, 'encryption' => 'tls'];
        $loopback = ['host' => '127.0.0.1', 'port' => 587, 'encryption' => 'tls'];

        if ($this->isExternalRecipient($to, (string) $cfg['from_email'])) {
            if ($this->isExternalSmtpRelay($primary['host'])) {
                $candidates = [$primary];
            } elseif ($this->isLoopbackHost($primary['host'])) {
                $candidates = [$mailTls, $mailSsl, $primary, $localhost, $loopback];
            } else {
                $candidates = [$primary, $mailTls, $mailSsl];
            }
        } else {
            $candidates = [$primary, $localhost, $loopback, $mailTls];
        }

        $profiles = [];
        $seen = [];
        foreach ($candidates as $candidate) {
            $key = $this->profileKey($candidate);
            if (in_array($key, $seen, true)) {
                continue;
            }
            $profiles[] = $candidate;
            $seen[] = $key;
        }
        return $profiles;
    }

    private function isLoopbackHost(string $host): bool
    {
        return in_array(strtolower(trim($host)), ['localhost', '127.0.0.1', '::1'], true);
    }

    private function isExternalSmtpRelay(string $host): bool
    {
        $h = strtolower(trim($host));
        if ($h === '' || $this->isLoopbackHost($h) || $h === 'mail.rateb.sa') {
            return false;
        }
        foreach (['sendgrid.net', 'mailgun.org', 'amazonaws.com', 'postmarkapp.com', 'sparkpostmail.com', 'resend.com', 'smtp2go.com', 'elasticemail.com', 'brevo.com'] as $marker) {
            if (str_contains($h, $marker)) {
                return true;
            }
        }
        return false;
    }

    private function isExternalRecipient(string $to, string $fromEmail): bool
    {
        $toDomain = \Rateb\App\Helpers\Str::emailDomain($to);
        $fromDomain = \Rateb\App\Helpers\Str::emailDomain($fromEmail);
        if ($toDomain === '' || $fromDomain === '') {
            return true;
        }
        return $toDomain !== $fromDomain;
    }

    /** @param array{host:string,port:int,encryption:string} $profile */
    private function profileKey(array $profile): string
    {
        return strtolower($profile['host']) . ':' . $profile['port'] . ':' . $profile['encryption'];
    }

    private function setError(string $code, string $message): void
    {
        $this->lastErrorCode = $code;
        $this->lastError = $message;
    }

    private function tlsCryptoMethod(): int
    {
        $method = STREAM_CRYPTO_METHOD_TLS_CLIENT;
        if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
            $method |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
        }
        if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) {
            $method |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
        }
        return $method;
    }

    private function sendSmtp(string $host, int $port, string $encryption, string $user, string $pass, string $fromEmail, string $fromName, string $to, string $subject, string $body, ?string $replyTo = null, ?string $cc = null, ?string $bcc = null): bool
    {
        $remote = $encryption === 'ssl' ? 'ssl://' . $host . ':' . $port : 'tcp://' . $host . ':' . $port;
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ],
        ]);
        $fp = @stream_socket_client($remote, $errno, $errstr, 25, STREAM_CLIENT_CONNECT, $context);
        if (!$fp) {
            $detail = trim($errstr) !== '' ? $errstr : __('mail_error_connect_unknown');
            $this->setError('smtp_connect', __('mail_error_connect', [
                'host' => $host,
                'port' => (string) $port,
                'enc' => $encryption,
                'detail' => $detail,
            ]));
            Logger::error('SMTP connect failed', ['host' => $host, 'port' => $port, 'enc' => $encryption, 'error' => $errstr, 'errno' => $errno]);
            return false;
        }
        stream_set_timeout($fp, 25);
        $read = static function () use ($fp): string {
            $data = '';
            while ($line = fgets($fp, 515)) {
                $data .= $line;
                if (isset($line[3]) && $line[3] === ' ') {
                    break;
                }
            }
            return $data;
        };
        $write = static function (string $cmd) use ($fp): void {
            fwrite($fp, $cmd . "\r\n");
        };

        $read();
        $ehloHost = $this->ehloHostname($host, $fromEmail);
        $write('EHLO ' . $ehloHost);
        $ehlo = $read();

        if ($encryption === 'tls' && stripos($ehlo, 'STARTTLS') !== false) {
            $write('STARTTLS');
            $tlsResp = $read();
            if (strpos($tlsResp, '220') === false) {
                fclose($fp);
                $this->setError('smtp_tls', __('mail_error_tls', ['host' => $host, 'port' => (string) $port]));
                return false;
            }
            $crypto = @stream_socket_enable_crypto($fp, true, $this->tlsCryptoMethod());
            if ($crypto !== true) {
                fclose($fp);
                $this->setError('smtp_tls', __('mail_error_tls', ['host' => $host, 'port' => (string) $port]));
                return false;
            }
            $write('EHLO ' . $ehloHost);
            $read();
        }

        if ($user !== '') {
            $write('AUTH LOGIN');
            $read();
            $write(base64_encode($user));
            $read();
            $write(base64_encode($pass));
            $auth = $read();
            if (strpos($auth, '235') === false) {
                fclose($fp);
                $this->setError('smtp_auth', __('mail_error_auth', ['user' => $user]));
                return false;
            }
        }
        $write('MAIL FROM:<' . $fromEmail . '>');
        $fromResp = $read();
        if (strpos($fromResp, '250') === false) {
            fclose($fp);
            $this->setError('smtp_from', __('mail_error_from', ['email' => $fromEmail]));
            return false;
        }
        $write('RCPT TO:<' . $to . '>');
        $rcptResp = $read();
        if (strpos($rcptResp, '250') === false && strpos($rcptResp, '251') === false) {
            fclose($fp);
            $this->setError('smtp_rcpt', __('mail_error_rcpt', ['email' => $to]));
            return false;
        }
        if ($cc !== null && $cc !== '' && \Rateb\App\Helpers\Str::isValidEmail($cc)) {
            $write('RCPT TO:<' . $cc . '>');
            $ccResp = $read();
            if (strpos($ccResp, '250') === false && strpos($ccResp, '251') === false) {
                fclose($fp);
                $this->setError('smtp_rcpt', __('mail_error_rcpt', ['email' => $cc]));
                return false;
            }
        }
        if ($bcc !== null && $bcc !== '' && \Rateb\App\Helpers\Str::isValidEmail($bcc) && strcasecmp($bcc, $to) !== 0 && strcasecmp($bcc, (string) $cc) !== 0) {
            $write('RCPT TO:<' . $bcc . '>');
            $bccResp = $read();
            if (strpos($bccResp, '250') === false && strpos($bccResp, '251') === false) {
                fclose($fp);
                $this->setError('smtp_rcpt', __('mail_error_rcpt', ['email' => $bcc]));
                return false;
            }
        }
        $write('DATA');
        $read();
        $msgDomain = $this->messageIdDomain($fromEmail, $ehloHost);
        $headers = 'From: ' . $this->encodeAddress($fromName, $fromEmail) . "\r\n";
        $headers .= 'To: <' . $to . ">\r\n";
        if ($cc !== null && $cc !== '' && \Rateb\App\Helpers\Str::isValidEmail($cc)) {
            $headers .= 'Cc: <' . $cc . ">\r\n";
        }
        $headers .= 'Reply-To: ' . ($replyTo !== null && $replyTo !== '' ? $replyTo : $fromEmail) . "\r\n";
        $headers .= 'Date: ' . date('r') . "\r\n";
        $headers .= 'Message-ID: <' . bin2hex(random_bytes(8)) . '@' . $msgDomain . '>' . "\r\n";
        $headers .= 'Subject: =?UTF-8?B?' . base64_encode($subject) . "?=\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= $this->mimeBodyHeaders($body);
        $write($headers . $body . "\r\n.");
        $result = $read();
        $write('QUIT');
        fclose($fp);
        if (strpos($result, '250') === false) {
            $this->setError('smtp_data', __('mail_error_data'));
            return false;
        }
        return true;
    }

    /** @param array<string, string> $vars */
    private function replaceVars(string $text, array $vars): string
    {
        foreach ($vars as $key => $value) {
            $text = str_replace('{' . $key . '}', $value, $text);
        }
        return $text;
    }

    private function encodeAddress(string $name, string $email): string
    {
        return '=?UTF-8?B?' . base64_encode($name) . '?= <' . $email . '>';
    }

    /** EHLO must match PTR (e.g. mail.rateb.sa) for Gmail delivery. */
    private function ehloHostname(string $smtpHost, string $fromEmail): string
    {
        $smtpHost = strtolower(trim($smtpHost));
        if ($smtpHost !== '' && !$this->isLoopbackHost($smtpHost) && str_contains($smtpHost, '.')) {
            return $smtpHost;
        }
        $domain = strtolower(\Rateb\App\Helpers\Str::emailDomain($fromEmail));
        if ($domain !== '') {
            return 'mail.' . $domain;
        }
        return 'mail.rateb.sa';
    }

    private function messageIdDomain(string $fromEmail, string $ehloHost): string
    {
        $domain = strtolower(\Rateb\App\Helpers\Str::emailDomain($fromEmail));
        if ($domain !== '') {
            return $domain;
        }
        $parts = explode('.', strtolower($ehloHost));
        if (count($parts) >= 2) {
            return implode('.', array_slice($parts, -2));
        }
        return 'rateb.sa';
    }

  /** Returns Content-Type headers + multipart body (plain + html). */
    private function mimeBodyHeaders(string &$body): string
    {
        $plain = $this->htmlToPlain($body);
        $boundary = 'rateb_' . bin2hex(random_bytes(8));
        $mime = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"' . "\r\n\r\n";
        $mime .= '--' . $boundary . "\r\n";
        $mime .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $mime .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $mime .= chunk_split(base64_encode($plain)) . "\r\n";
        $mime .= '--' . $boundary . "\r\n";
        $mime .= "Content-Type: text/html; charset=UTF-8\r\n";
        $mime .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $mime .= chunk_split(base64_encode($body)) . "\r\n";
        $mime .= '--' . $boundary . '--';
        $body = $mime;
        return '';
    }

    private function htmlToPlain(string $html): string
    {
        $text = preg_replace('/<br\s*\/?>/i', "\n", $html) ?? $html;
        $text = preg_replace('/<\/(p|div|tr|li|h[1-6])>/i', "\n", $text) ?? $text;
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/[ \t]+/", ' ', $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;
        return trim($text);
    }
}
