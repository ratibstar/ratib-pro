<?php
declare(strict_types=1);

namespace Rateb\App\Logistics\Services\Integration;

use Rateb\App\Logistics\Contracts\CompanyNotifier;
use Rateb\App\Services\NotificationService;

final class ErpCompanyNotifier implements CompanyNotifier
{
    public function __construct(private NotificationService $notifications = new NotificationService())
    {
    }

    public function notifyCompany(
        int $companyId,
        string $title,
        string $message,
        string $type = 'info',
        ?string $triggerType = null,
        ?string $entityType = null,
        ?int $entityId = null
    ): int {
        return $this->notifications->notifyCompany(
            $companyId,
            $title,
            $message,
            $type,
            $triggerType,
            $entityType,
            $entityId
        );
    }
}
