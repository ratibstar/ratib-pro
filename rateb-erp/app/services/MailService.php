<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\EmailTemplate;
use Rateb\App\Models\SystemSetting;

final class MailService
{
    public function send(string $to, string $subject, string $htmlBody, ?string $textBody = null): bool
    {
        $fromEmail = (new SystemSetting())->get('smtp_from_email', 'noreply@rateb.sa');
        $fromName = (new SystemSetting())->get('smtp_from_name', 'RTAB ERP');

        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            'From: ' . $this->encodeAddress((string) $fromName, (string) $fromEmail),
            'Reply-To: ' . (string) $fromEmail,
            'X-Mailer: RTAB-ERP',
        ];

        $sent = @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $htmlBody, implode("\r\n", $headers));

        (new NotificationService())->queueEmail($to, $subject, $htmlBody, $sent ? 'sent' : 'failed');

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
