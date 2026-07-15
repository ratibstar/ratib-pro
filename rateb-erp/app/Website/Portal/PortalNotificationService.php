<?php
declare(strict_types=1);

namespace Rateb\App\Website\Portal;

use Rateb\App\Core\TenantContext;
use Rateb\App\Website\TenantWebsiteRepository;

/**
 * Phase WEBSITE-07 — Notifications via NotificationService / MailService.
 */
final class PortalNotificationService
{
    private TenantWebsiteRepository $repo;

    public function __construct(?TenantWebsiteRepository $repo = null)
    {
        $this->repo = $repo ?? new TenantWebsiteRepository();
    }

    /** @return list<array<string, mixed>> */
    public function listInApp(): array
    {
        TenantContext::setCompanyId($this->repo->companyId());
        try {
            return $this->repo->fetchAll(
                'SELECT id, title, message, type, is_read, created_at
                 FROM rateb_notifications
                 WHERE company_id = :cid
                 ORDER BY id DESC LIMIT 50',
                ['cid' => $this->repo->companyId()]
            );
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function notifyCompany(string $title, string $message): void
    {
        TenantContext::setCompanyId($this->repo->companyId());
        try {
            if (class_exists(\Rateb\App\Services\NotificationService::class)) {
                (new \Rateb\App\Services\NotificationService())->notifyCompany(
                    $this->repo->companyId(),
                    $title,
                    $message,
                    'info',
                    'website_portal'
                );
            }
        } catch (\Throwable $e) {
            error_log('PortalNotificationService: ' . $e->getMessage());
        }
    }

    public function email(string $to, string $subject, string $body): void
    {
        try {
            if (class_exists(\Rateb\App\Services\MailService::class)) {
                (new \Rateb\App\Services\MailService())->send($to, $subject, $body);
            }
        } catch (\Throwable $e) {
            error_log('PortalNotificationService mail: ' . $e->getMessage());
        }
    }
}
