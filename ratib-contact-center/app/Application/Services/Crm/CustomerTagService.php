<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Application\Services\Crm;

use Ratib\ContactCenter\App\Application\Services\RccAuditService;
use Ratib\ContactCenter\App\Core\Events\EventBus;
use Ratib\ContactCenter\App\Core\Events\EventType;
use Ratib\ContactCenter\App\Infrastructure\Persistence\Repositories\Crm\CrmTagRepository;

final class CustomerTagService
{
    public function __construct(
        private readonly CrmTagRepository $tags = new CrmTagRepository(),
        private readonly RccAuditService $audit = new RccAuditService()
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function list(int $tenantId, int $contactId): array
    {
        return $this->tags->listForContact($tenantId, $contactId);
    }

    public function add(int $tenantId, int $contactId, string $tag, ?int $userId, ?string $color = null): void
    {
        $this->tags->add($tenantId, $contactId, $tag, $userId, $color);
        $this->audit->log($tenantId, 'crm.tag.add', $userId, 'contact', $contactId, ['tag' => $tag]);
        EventBus::instance()->emit([
            'type' => EventType::CRM_TAG_UPDATED,
            'tenant_id' => $tenantId,
            'payload' => ['contact_id' => $contactId, 'tag' => $tag, 'action' => 'add'],
        ]);
    }

    public function remove(int $tenantId, int $contactId, string $tag, ?int $userId): bool
    {
        $ok = $this->tags->remove($tenantId, $contactId, $tag);
        if ($ok) {
            $this->audit->log($tenantId, 'crm.tag.remove', $userId, 'contact', $contactId, ['tag' => $tag]);
            EventBus::instance()->emit([
                'type' => EventType::CRM_TAG_UPDATED,
                'tenant_id' => $tenantId,
                'payload' => ['contact_id' => $contactId, 'tag' => $tag, 'action' => 'remove'],
            ]);
        }
        return $ok;
    }
}
