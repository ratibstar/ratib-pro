<?php
declare(strict_types=1);

namespace Rateb\App\Pos\Services\Bridge;

use Rateb\App\Services\NotificationService;

/** Notification bridge — ERP NotificationService only. */
final class PosNotificationBridgeService
{
    public function notifyCompany(
        ?int $companyId,
        string $title,
        string $message,
        string $type = 'info',
        ?string $triggerType = null,
        ?string $entityType = null,
        ?int $entityId = null
    ): int {
        return (new NotificationService())->notifyCompany(
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
