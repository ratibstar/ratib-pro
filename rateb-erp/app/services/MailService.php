<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\EmailTemplate;

final class MailService
{
    private ?string $lastErrorCode = null;
    private ?string $lastError = null;

    public function lastError(): ?string
    {
        return $this->lastError;
    }

    public function lastErrorCode(): ?string
    {
        return $this->lastErrorCode;
    }

    /** @return array{success:bool,error_code:?string,error:?string} */
    public function sendDetailed(string $to, string $subject, string $htmlBody, ?string $replyTo = null, ?string $cc = null): array
    {
        $this->lastError = null;
        $this->lastErrorCode = null;
        $cfg = (new MailConfigService())->resolve();
        $fromEmail = $cfg['from_email'] !== '' ? $cfg['from_email'] : 'info@rateb.sa';
        $fromName = $cfg['from_name'] !== '' ? $cfg['from_name'] : 'Rateb ERP';

        if ($cfg['host'] === '' || $cfg['pass'] === '') {
            $this->setError('smtp_not_configured', __('comm_email_smtp_required'));
            return ['success' => false, 'error_code' => $this->lastErrorCode, 'error' => $this->lastError];
        }

        $sent = $this->sendSmtp(
            $cfg['host'],
            $cfg['port'],
            $cfg['encryption'],
            $cfg['user'],
            $cfg['pass'],
            $fromEmail,
            $fromName,
            $to,
            $subject,
            $htmlBody,
            $replyTo,
            $cc
        );

        if (!$sent && $this->lastErrorCode === 'smtp_connect' && $cfg['host'] !== 'localhost') {
            $sent = $this->sendSmtp(
                'localhost',
                $cfg['port'],
                $cfg['encryption'],
                $cfg['user'],
                $cfg['pass'],
                $fromEmail,
                $fromName,
                $to,
                $subject,
                $htmlBody,
                $replyTo,
                $cc
            );
        }

        (new NotificationService())->queueEmail($to, $subject, $htmlBody, $sent ? 'sent' : 'failed');
        if (!$sent) {
            Logger::warning('Email send failed', [
                'to' => $to,
                'subject' => $subject,
                'code' => $this->lastErrorCode,
                'error' => $this->lastError,
            ]);
        }
        return [
            'success' => $sent,
            'error_code' => $this->lastErrorCode,
            'error' => $this->lastError,
        ];
    }

    public function send(string $to, string $subject, string $htmlBody, ?string $textBody = null, bool $recordQueue = true, ?string $replyTo = null, ?string $cc = null): bool
    {
        $result = $this->sendDetailed($to, $subject, $htmlBody, $replyTo, $cc);
        return $result['success'];
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

    private function setError(string $code, string $message): void
    {
        $this->lastErrorCode = $code;
        $this->lastError = $message;
    }

    private function sendSmtp(string $host, int $port, string $encryption, string $user, string $pass, string $fromEmail, string $fromName, string $to, string $subject, string $body, ?string $replyTo = null, ?string $cc = null): bool
    {
        $remote = $encryption === 'ssl' ? 'ssl://' . $host . ':' . $port : 'tcp://' . $host . ':' . $port;
        $fp = @stream_socket_client($remote, $errno, $errstr, 25);
        if (!$fp) {
            $this->setError('smtp_connect', __('mail_error_connect', ['host' => $host, 'port' => (string) $port, 'detail' => $errstr !== '' ? $errstr : (string) $errno]));
            Logger::error('SMTP connect failed', ['host' => $host, 'port' => $port, 'error' => $errstr]);
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
        $write('EHLO rateb.sa');
        $ehlo = $read();

        if ($encryption === 'tls' && stripos($ehlo, 'STARTTLS') !== false) {
            $write('STARTTLS');
            $tlsResp = $read();
            if (strpos($tlsResp, '220') === false) {
                fclose($fp);
                $this->setError('smtp_tls', __('mail_error_tls'));
                return false;
            }
            $crypto = @stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            if ($crypto !== true) {
                fclose($fp);
                $this->setError('smtp_tls', __('mail_error_tls'));
                return false;
            }
            $write('EHLO rateb.sa');
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
        if ($cc !== null && $cc !== '' && filter_var($cc, FILTER_VALIDATE_EMAIL)) {
            $write('RCPT TO:<' . $cc . '>');
            $read();
        }
        $write('DATA');
        $read();
        $headers = 'From: ' . $this->encodeAddress($fromName, $fromEmail) . "\r\n";
        $headers .= 'To: <' . $to . ">\r\n";
        if ($cc !== null && $cc !== '' && filter_var($cc, FILTER_VALIDATE_EMAIL)) {
            $headers .= 'Cc: <' . $cc . ">\r\n";
        }
        $headers .= 'Reply-To: ' . ($replyTo !== null && $replyTo !== '' ? $replyTo : $fromEmail) . "\r\n";
        $headers .= 'Subject: =?UTF-8?B?' . base64_encode($subject) . "?=\r\n";
        $headers .= "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\n\r\n";
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
}
