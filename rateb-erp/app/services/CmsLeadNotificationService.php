<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\View;
use Rateb\App\Models\CmsContactSettings;
use Rateb\App\Models\SystemSetting;

final class CmsLeadNotificationService
{
    /** @param array<string, mixed> $lead */
    public function notifyStaff(int $leadId, string $type, array $lead): void
    {
        $to = $this->staffInboxEmail();
        if ($to === '') {
            return;
        }

        $name = trim((string) ($lead['name'] ?? ''));
        $email = trim((string) ($lead['email'] ?? ''));
        $phone = trim((string) ($lead['phone'] ?? ''));
        $company = trim((string) ($lead['company'] ?? ''));
        $message = trim((string) ($lead['message'] ?? ''));
        $typeLabel = $this->typeLabel($type);

        $subject = __('cms_lead_mail_staff_subject', ['type' => $typeLabel, 'name' => $name]);
        $adminUrl = rateb_site_origin() . rateb_url('admin/cms/leads/' . $leadId);
        $rows = [
            __('name') => $name,
            __('email') => $email,
            __('phone') => $phone,
            __('company') => $company,
            __('lead_type') => $typeLabel,
            __('message') => $message !== '' ? $message : '—',
            'ID' => (string) $leadId,
        ];

        $body = '<div dir="rtl" style="font-family:Tajawal,Arial,sans-serif;line-height:1.6">';
        $body .= '<h2 style="margin:0 0 12px">' . View::escape($typeLabel) . '</h2>';
        $body .= '<table style="border-collapse:collapse;width:100%;max-width:560px">';
        foreach ($rows as $label => $value) {
            $body .= '<tr><td style="padding:6px 10px;border:1px solid #ddd;font-weight:600;width:35%">'
                . View::escape((string) $label) . '</td><td style="padding:6px 10px;border:1px solid #ddd">'
                . nl2br(View::escape((string) $value)) . '</td></tr>';
        }
        $body .= '</table>';
        $body .= '<p style="margin-top:16px"><a href="' . View::escape($adminUrl) . '">'
            . View::escape(__('cms_lead_mail_admin_link')) . '</a></p>';
        $body .= '</div>';

        $mail = new MailService();
        $replyTo = $email !== '' ? $email : null;
        if (!$mail->send($to, $subject, $body, null, true, $replyTo)) {
            Logger::warning('CMS lead staff email failed', [
                'lead_id' => $leadId,
                'type' => $type,
                'to' => $to,
                'error' => $mail->lastError(),
            ]);
        }
    }

    /** @param array<string, mixed> $lead */
    public function notifyCustomer(string $type, array $lead): void
    {
        $email = trim((string) ($lead['email'] ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $name = trim((string) ($lead['name'] ?? ''));
        $typeLabel = $this->typeLabel($type);
        $subject = __('cms_lead_mail_customer_subject', ['type' => $typeLabel]);
        $body = '<div dir="rtl" style="font-family:Tajawal,Arial,sans-serif;line-height:1.6">';
        $body .= '<p>' . View::escape(__('cms_lead_mail_customer_greeting', ['name' => $name])) . '</p>';
        $body .= '<p>' . View::escape(__('cms_lead_mail_customer_body', ['type' => $typeLabel])) . '</p>';
        $body .= '<p style="color:#666;font-size:14px">' . View::escape(__('cms_lead_mail_customer_footer')) . '</p>';
        $body .= '</div>';

        $mail = new MailService();
        if (!$mail->send($email, $subject, $body)) {
            Logger::warning('CMS lead customer auto-reply failed', [
                'type' => $type,
                'email' => $email,
                'error' => $mail->lastError(),
            ]);
        }
    }

    private function staffInboxEmail(): string
    {
        try {
            $row = (new CmsContactSettings())->queryOne('SELECT email FROM rateb_cms_contact_settings ORDER BY id ASC LIMIT 1');
            $fromCms = trim((string) ($row['email'] ?? ''));
            if ($fromCms !== '' && filter_var($fromCms, FILTER_VALIDATE_EMAIL)) {
                return $fromCms;
            }
        } catch (\Throwable $e) {
            Logger::warning('CMS contact settings read failed', ['error' => $e->getMessage()]);
        }

        $support = trim((string) ((new SystemSetting())->get('support_email', '') ?? ''));
        if ($support !== '' && filter_var($support, FILTER_VALIDATE_EMAIL)) {
            return $support;
        }

        return 'info@rateb.sa';
    }

    private function typeLabel(string $type): string
    {
        return match ($type) {
            'demo' => __('cms_lead_type_demo'),
            'quote' => __('cms_lead_type_quote'),
            'contact' => __('cms_lead_type_contact'),
            default => $type,
        };
    }
}
