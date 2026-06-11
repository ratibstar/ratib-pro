<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\EmailTemplate;
use Rateb\App\Models\SystemSetting;

final class MailService
{
    public function send(string $to, string $subject, string $htmlBody, ?string $textBody = null, bool $recordQueue = true): bool
    {
        $settings = new SystemSetting();
        $fromEmail = $settings->get('smtp_from_email', 'noreply@rateb.sa');
        $fromName = $settings->get('smtp_from_name', 'RTAB ERP');
        $host = trim((string) $settings->get('smtp_host', ''));

        $sent = false;
        if ($host !== '') {
            $sent = $this->sendSmtp(
                $host,
                (int) ($settings->get('smtp_port', '587') ?: 587),
                (string) $settings->get('smtp_user', ''),
                (string) $settings->get('smtp_pass', ''),
                (string) $fromEmail,
                (string) $fromName,
                $to,
                $subject,
                $htmlBody
            );
        } else {
            $headers = [
                'MIME-Version: 1.0',
                'Content-type: text/html; charset=UTF-8',
                'From: ' . $this->encodeAddress((string) $fromName, (string) $fromEmail),
                'Reply-To: ' . (string) $fromEmail,
                'X-Mailer: RTAB-ERP',
            ];
            $sent = @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $htmlBody, implode("\r\n", $headers));
        }

        if ($recordQueue) {
            (new NotificationService())->queueEmail($to, $subject, $htmlBody, $sent ? 'sent' : 'failed');
        }
        if (!$sent) {
            Logger::warning('Email send failed', ['to' => $to, 'subject' => $subject]);
        }
        return $sent;
    }

    public function sendTemplate(string $to, string $slug, array $vars = []): bool
    {
        $tpl = (new EmailTemplate())->queryOne('SELECT * FROM rateb_email_templates WHERE slug = :s AND is_active = 1 LIMIT 1', ['s' => $slug]);
        if (!$tpl) {
            return false;
        }
        $subject = $this->replaceVars((string) $tpl['subject'], $vars);
        $body = $this->replaceVars((string) $tpl['body_html'], $vars);
        return $this->send($to, $subject, $body);
    }

    private function sendSmtp(string $host, int $port, string $user, string $pass, string $fromEmail, string $fromName, string $to, string $subject, string $body): bool
    {
        $fp = @fsockopen($host, $port, $errno, $errstr, 15);
        if (!$fp) {
            Logger::error('SMTP connect failed', ['host' => $host, 'error' => $errstr]);
            return false;
        }
        stream_set_timeout($fp, 15);
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
        $read();
        if ($user !== '') {
            $write('AUTH LOGIN');
            $read();
            $write(base64_encode($user));
            $read();
            $write(base64_encode($pass));
            $ehlo = $read();
            if (strpos($ehlo, '235') === false) {
                fclose($fp);
                return false;
            }
        }
        $write('MAIL FROM:<' . $fromEmail . '>');
        $read();
        $write('RCPT TO:<' . $to . '>');
        $read();
        $write('DATA');
        $read();
        $headers = 'From: ' . $this->encodeAddress($fromName, $fromEmail) . "\r\n";
        $headers .= 'To: <' . $to . ">\r\n";
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
