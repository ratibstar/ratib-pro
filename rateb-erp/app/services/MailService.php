<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\EmailTemplate;

final class MailService
{
    public function send(string $to, string $subject, string $htmlBody, ?string $textBody = null, bool $recordQueue = true, ?string $replyTo = null, ?string $cc = null): bool
    {
        $cfg = (new MailConfigService())->resolve();
        $fromEmail = $cfg['from_email'] !== '' ? $cfg['from_email'] : 'info@rateb.sa';
        $fromName = $cfg['from_name'] !== '' ? $cfg['from_name'] : 'Rateb ERP';
        $host = $cfg['host'];

        if ($host === '' || $cfg['pass'] === '') {
            return $this->sendPhpMail($fromEmail, $fromName, $to, $subject, $htmlBody, $recordQueue, $replyTo, $cc);
        }

        $sent = $this->sendSmtp(
            $host,
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

        if ($recordQueue) {
            (new NotificationService())->queueEmail($to, $subject, $htmlBody, $sent ? 'sent' : 'failed');
        }
        if (!$sent) {
            Logger::warning('Email send failed', ['to' => $to, 'subject' => $subject]);
        }
        return $sent;
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

    private function sendPhpMail(string $fromEmail, string $fromName, string $to, string $subject, string $htmlBody, bool $recordQueue, ?string $replyTo = null, ?string $cc = null): bool
    {
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            'From: ' . $this->encodeAddress($fromName, $fromEmail),
            'Reply-To: ' . ($replyTo !== null && $replyTo !== '' ? $replyTo : $fromEmail),
            'X-Mailer: RTAB-ERP',
        ];
        if ($cc !== null && $cc !== '' && filter_var($cc, FILTER_VALIDATE_EMAIL)) {
            $headers[] = 'Cc: ' . $cc;
        }
        $sent = @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $htmlBody, implode("\r\n", $headers));
        if ($recordQueue) {
            (new NotificationService())->queueEmail($to, $subject, $htmlBody, $sent ? 'sent' : 'failed');
        }
        return (bool) $sent;
    }

    private function sendSmtp(string $host, int $port, string $encryption, string $user, string $pass, string $fromEmail, string $fromName, string $to, string $subject, string $body, ?string $replyTo = null, ?string $cc = null): bool
    {
        $remote = $encryption === 'ssl' ? 'ssl://' . $host . ':' . $port : 'tcp://' . $host . ':' . $port;
        $fp = @stream_socket_client($remote, $errno, $errstr, 20);
        if (!$fp) {
            Logger::error('SMTP connect failed', ['host' => $host, 'error' => $errstr]);
            return false;
        }
        stream_set_timeout($fp, 20);
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
        $write('EHLO rateb-erp.local');
        $ehlo = $read();

        if ($encryption === 'tls' && stripos($ehlo, 'STARTTLS') !== false) {
            $write('STARTTLS');
            $tlsResp = $read();
            if (strpos($tlsResp, '220') === false) {
                fclose($fp);
                return false;
            }
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                fclose($fp);
                return false;
            }
            $write('EHLO rateb-erp.local');
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
                return false;
            }
        }
        $write('MAIL FROM:<' . $fromEmail . '>');
        $read();
        $write('RCPT TO:<' . $to . '>');
        $read();
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
        return strpos($result, '250') !== false;
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
