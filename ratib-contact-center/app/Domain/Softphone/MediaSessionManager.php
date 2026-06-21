<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Domain\Softphone;

use Ratib\ContactCenter\App\Core\Database;
use Ratib\ContactCenter\App\Core\Events\EventBus;
use Ratib\ContactCenter\App\Core\Events\EventType;
use Ratib\ContactCenter\App\Infrastructure\Persistence\Repositories\SoftphoneCallRepository;

/**
 * Signaling-only session tracker — NO media on backend.
 */
final class MediaSessionManager
{
    public function __construct(
        private readonly SoftphoneCallRepository $calls = new SoftphoneCallRepository(),
        private readonly ?EventBus $eventBus = null
    ) {
    }

    /** @return array<string, mixed> */
    public function publishState(int $tenantId, int $agentId, array $callState): array
    {
        $bus = $this->eventBus ?? EventBus::instance();
        $bus->emit([
            'type' => EventType::SOFTPHONE_STATE,
            'tenant_id' => $tenantId,
            'agent_id' => $agentId,
            'call_id' => $callState['call_id'] ?? null,
            'queue_id' => $callState['queue_id'] ?? null,
            'payload' => $callState,
        ]);
        return $callState;
    }

    public function tenantAutoAnswerEnabled(int $tenantId): bool
    {
        $stmt = Database::connection()->prepare(
            'SELECT setting_value FROM rcc_settings
             WHERE tenant_id = :tid AND group_key = \'softphone\' AND setting_key = \'auto_answer_queue_calls\' LIMIT 1'
        );
        $stmt->execute(['tid' => $tenantId]);
        $val = $stmt->fetchColumn();
        if ($val === false) {
            return false;
        }
        return in_array(strtolower((string) $val), ['1', 'true', 'yes'], true);
    }
}
