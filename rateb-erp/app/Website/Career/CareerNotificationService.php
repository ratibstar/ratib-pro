<?php
declare(strict_types=1);

namespace Rateb\App\Website\Career;

use Rateb\App\Core\View;
use Rateb\App\Models\CmsContactSettings;
use Rateb\App\Models\SystemSetting;
use Rateb\App\Models\User;
use Rateb\App\Services\CmsService;
use Rateb\App\Services\MailService;
use Rateb\App\Website\TenantWebsiteRepository;

/**
 * Phase WEBSITE-06 — Email notifications for career applications (SMS/WhatsApp hooks optional).
 */
final class CareerNotificationService
{
    private TenantWebsiteRepository $repo;

    public function __construct(?TenantWebsiteRepository $repo = null)
    {
        $this->repo = $repo ?? new TenantWebsiteRepository();
    }

    /**
     * @param array<string, mixed> $job
     * @param array{id:int,public_uuid:string,candidate_no:string} $candidate
     * @param array<string, mixed> $formData
     */
    public function notifyApplication(int $applicationId, array $job, array $candidate, array $formData): void
    {
        $this->notifyRecruiter($applicationId, $job, $candidate, $formData);
        $this->notifyCandidate($job, $formData);
    }

    /**
     * @param array<string, mixed> $job
     * @param array{id:int,public_uuid:string,candidate_no:string} $candidate
     * @param array<string, mixed> $formData
     */
    private function notifyRecruiter(int $applicationId, array $job, array $candidate, array $formData): void
    {
        $recruiterId = (int) ($job['recruiter_user_id'] ?? 0);
        $to = '';
        if ($recruiterId > 0) {
            $user = (new User())->find($recruiterId);
            $to = trim((string) ($user['email'] ?? ''));
        }
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $to = $this->staffInboxEmail();
        }
        if ($to === '') {
            return;
        }

        $jobTitle = CmsService::pickLocale($job, 'title');
        $name = trim((string) ($formData['full_name'] ?? ''));
        $email = trim((string) ($formData['email'] ?? ''));
        $subject = 'New career application: ' . $jobTitle;
        $adminUrl = rateb_site_origin() . rateb_url('recruitment/candidates/' . (int) $candidate['id']);
        $body = '<div dir="rtl" style="font-family:Tajawal,Arial,sans-serif;line-height:1.6">';
        $body .= '<h2>' . View::escape($jobTitle) . '</h2>';
        $body .= '<p><strong>Applicant:</strong> ' . View::escape($name) . '</p>';
        $body .= '<p><strong>Email:</strong> ' . View::escape($email) . '</p>';
        $body .= '<p><strong>Application ID:</strong> ' . (int) $applicationId . '</p>';
        $body .= '<p><strong>Candidate:</strong> ' . View::escape((string) ($candidate['candidate_no'] ?? '')) . '</p>';
        $body .= '<p><a href="' . View::escape($adminUrl) . '">View in ATS</a></p>';
        $body .= '</div>';

        $mail = new MailService();
        $replyTo = $email !== '' ? $email : null;
        $mail->send($to, $subject, $body, null, true, $replyTo);
    }

    /** @param array<string, mixed> $job @param array<string, mixed> $formData */
    private function notifyCandidate(array $job, array $formData): void
    {
        $email = trim((string) ($formData['email'] ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }
        $name = trim((string) ($formData['full_name'] ?? ''));
        $jobTitle = CmsService::pickLocale($job, 'title');
        $subject = 'Application received — ' . $jobTitle;
        $portalUrl = rateb_url('site/candidate');
        $body = '<div dir="rtl" style="font-family:Tajawal,Arial,sans-serif;line-height:1.6">';
        $body .= '<p>' . View::escape('Hello ' . ($name !== '' ? $name : 'Candidate')) . '</p>';
        $body .= '<p>' . View::escape('Your application for "' . $jobTitle . '" has been received.') . '</p>';
        $body .= '<p><a href="' . View::escape(rateb_site_origin() . $portalUrl) . '">Track your applications</a></p>';
        $body .= '</div>';

        (new MailService())->send($email, $subject, $body);
    }

    private function staffInboxEmail(): string
    {
        try {
            [$where, $params] = $this->repo->companyWhere();
            $row = $this->repo->fetchOne(
                "SELECT email FROM rateb_cms_contact_settings WHERE {$where} ORDER BY id ASC LIMIT 1",
                $params
            );
            $fromCms = trim((string) ($row['email'] ?? ''));
            if ($fromCms !== '' && filter_var($fromCms, FILTER_VALIDATE_EMAIL)) {
                return $fromCms;
            }
        } catch (\Throwable $e) {
            error_log('CareerNotification contact read: ' . $e->getMessage());
        }

        $support = trim((string) ((new SystemSetting())->get('support_email', '') ?? ''));
        if ($support !== '' && filter_var($support, FILTER_VALIDATE_EMAIL)) {
            return $support;
        }

        return '';
    }
}
