<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Application\Services\Crm;

use Ratib\ContactCenter\App\Application\Services\RccAuditService;
use Ratib\ContactCenter\App\Core\Events\EventBus;
use Ratib\ContactCenter\App\Core\Events\EventType;
use Ratib\ContactCenter\App\Infrastructure\Persistence\Repositories\Crm\CrmActivityRepository;

final class CustomerTimelineService
{
    public function __construct(
        private readonly CrmActivityRepository $activities = new CrmActivityRepository(),
        private readonly RccAuditService $audit = new RccAuditService()
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function timeline(int $tenantId, int $contactId): array
    {
        return $this->activities->timeline($tenantId, $contactId);
    }

    /** @return list<array<string, mixed>> */
    public function interactions(int $tenantId, int $contactId): array
    {
        return $this->activities->interactionHistory($tenantId, $contactId);
    }

    public function record(
        int $tenantId,
        int $contactId,
        string $activityType,
        string $title,
        ?int $userId = null,
        ?array $payload = null
    ): int {
        $id = $this->activities->add($tenantId, $contactId, $activityType, $title, null, null, null, null, null, $payload);
        $this->audit->log($tenantId, 'crm.activity.record', $userId, 'contact_activity', $id, ['type' => $activityType]);
        EventBus::instance()->emit([
            'type' => EventType::CRM_ACTIVITY_RECORDED,
            'tenant_id' => $tenantId,
            'payload' => ['contact_id' => $contactId, 'activity_id' => $id],
        ]);
        return $id;
    }
}
