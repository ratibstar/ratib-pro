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

    /**
     * @param array<string, mixed>|null $portalUser
     * @return list<array<string, mixed>>
     */
    public function listInApp(?array $portalUser = null): array
    {
        if ($portalUser === null || (int) ($portalUser['id'] ?? 0) < 1) {
            return [];
        }
        TenantContext::setCompanyId($this->repo->companyId());
        try {
            return $this->repo->fetchAll(
                "SELECT id, title, message, type, is_read, created_at
                 FROM rateb_notifications
                 WHERE company_id = :cid
                   AND entity_type = 'website_portal_user'
                   AND entity_id = :uid
                 ORDER BY id DESC LIMIT 50",
                [
                    'cid' => $this->repo->companyId(),
                    'uid' => (int) $portalUser['id'],
                ]
            );
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function notifyCompany(string $title, string $message, ?string $entityType = null, ?int $entityId = null): void
    {
        TenantContext::setCompanyId($this->repo->companyId());
        try {
            if (class_exists(\Rateb\App\Services\NotificationService::class)) {
                (new \Rateb\App\Services\NotificationService())->notifyCompany(
                    $this->repo->companyId(),
                    $title,
                    $message,
                    'info',
                    'website_portal',
                    $entityType,
                    $entityId
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

    /**
     * Phase WEBSITE-09 — Status notification for online service requests.
     *
     * @param array<string, mixed> $portalUser
     */
    public function notifyServiceStatus(array $portalUser, int $serviceRequestId, string $status, string $message): void
    {
        $title = 'Service #' . $serviceRequestId . ' — ' . $status;
        $uid = (int) ($portalUser['id'] ?? 0);
        $this->notifyCompany($title, $message, 'website_portal_user', $uid > 0 ? $uid : null);
        $email = trim((string) ($portalUser['email'] ?? ''));
        if ($email !== '') {
            $this->email($email, $title, $message);
        }
    }
}
