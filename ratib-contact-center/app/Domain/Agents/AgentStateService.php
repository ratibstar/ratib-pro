<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Domain\Agents;

use Ratib\ContactCenter\App\Core\Database;
use Ratib\ContactCenter\App\Core\Events\EventBus;
use Ratib\ContactCenter\App\Core\Events\EventSubscriberInterface;
use Ratib\ContactCenter\App\Core\Events\EventType;
use Ratib\ContactCenter\App\Core\Events\RealtimeEvent;

/**
 * Agent presence engine — tracks availability, current call, queue, pause reason.
 * State changes emit AGENT_* events via EventBus only.
 */
final class AgentStateService implements EventSubscriberInterface
{
    private EventBus $eventBus;

    public function __construct(?EventBus $eventBus = null)
    {
        $this->eventBus = $eventBus ?? EventBus::instance();
    }

    public function onEvent(RealtimeEvent $event): void
    {
        if ($event->type === EventType::CALL_CONNECTED && $event->agentId !== null) {
            $this->setBusy($event->tenantId, $event->agentId, $event->callId, $event->queueId);
        }
        if ($event->type === EventType::CALL_ENDED && $event->agentId !== null) {
            $this->setWrapup($event->tenantId, $event->agentId, $event->callId);
        }
        if ($event->type === EventType::QUEUE_ASSIGNED && $event->agentId !== null) {
            $this->attachQueue($event->tenantId, $event->agentId, $event->queueId);
        }
        if ($event->type === EventType::CALL_ASSIGNED && $event->agentId !== null) {
            $this->attachQueue($event->tenantId, $event->agentId, $event->queueId);
        }
        if ($event->type === EventType::CALL_HOLD && $event->agentId !== null) {
            $this->pause($event->tenantId, $event->agentId, 'on_call_hold');
        }
        if ($event->type === EventType::CALL_RESUME && $event->agentId !== null) {
            $this->setBusy($event->tenantId, $event->agentId, $event->callId, $event->queueId);
        }
    }

    public function login(int $tenantId, int $agentId, ?int $userId = null): array
    {
        $this->upsertState($tenantId, $agentId, [
            'status' => 'ready',
            'user_id' => $userId,
            'session_started_at' => gmdate('Y-m-d H:i:s'),
            'current_call_id' => null,
            'pause_reason' => null,
        ]);

        $this->eventBus->emit([
            'type' => EventType::AGENT_LOGIN,
            'tenant_id' => $tenantId,
            'agent_id' => $agentId,
            'payload' => $this->getState($tenantId, $agentId) ?? [],
        ]);

        return $this->setReady($tenantId, $agentId);
    }

    public function setReady(int $tenantId, int $agentId): array
    {
        $this->upsertState($tenantId, $agentId, [
            'status' => 'ready',
            'current_call_id' => null,
            'pause_reason' => null,
        ]);
        $state = $this->getState($tenantId, $agentId) ?? [];
        $this->eventBus->emit([
            'type' => EventType::AGENT_READY,
            'tenant_id' => $tenantId,
            'agent_id' => $agentId,
            'payload' => $state,
        ]);
        $this->emitStateUpdated($tenantId, $agentId, $state);
        return $state;
    }

    public function setBusy(int $tenantId, int $agentId, ?int $callId = null, ?int $queueId = null): array
    {
        $this->upsertState($tenantId, $agentId, [
            'status' => 'busy',
            'current_call_id' => $callId,
            'queue_id' => $queueId,
        ]);
        $state = $this->getState($tenantId, $agentId) ?? [];
        $this->eventBus->emit([
            'type' => EventType::AGENT_BUSY,
            'tenant_id' => $tenantId,
            'agent_id' => $agentId,
            'call_id' => $callId,
            'queue_id' => $queueId,
            'payload' => $state,
        ]);
        $this->emitStateUpdated($tenantId, $agentId, $state);
        return $state;
    }

    public function setWrapup(int $tenantId, int $agentId, ?int $callId = null): array
    {
        $this->upsertState($tenantId, $agentId, [
            'status' => 'wrapup',
            'current_call_id' => $callId,
        ]);
        $state = $this->getState($tenantId, $agentId) ?? [];
        $this->eventBus->emit([
            'type' => EventType::AGENT_WRAPUP,
            'tenant_id' => $tenantId,
            'agent_id' => $agentId,
            'call_id' => $callId,
            'payload' => $state,
        ]);
        $this->emitStateUpdated($tenantId, $agentId, $state);
        return $state;
    }

    public function setOffline(int $tenantId, int $agentId, ?string $reason = null): array
    {
        $this->upsertState($tenantId, $agentId, [
            'status' => 'offline',
            'current_call_id' => null,
            'queue_id' => null,
            'pause_reason' => $reason,
        ]);
        $state = $this->getState($tenantId, $agentId) ?? [];
        $this->eventBus->emit([
            'type' => EventType::AGENT_OFFLINE,
            'tenant_id' => $tenantId,
            'agent_id' => $agentId,
            'payload' => array_merge($state, ['reason' => $reason]),
        ]);
        $this->emitStateUpdated($tenantId, $agentId, $state);
        return $state;
    }

    public function pause(int $tenantId, int $agentId, string $reason): array
    {
        $this->upsertState($tenantId, $agentId, [
            'status' => 'paused',
            'pause_reason' => $reason,
        ]);
        $state = $this->getState($tenantId, $agentId) ?? [];
        $this->emitStateUpdated($tenantId, $agentId, $state);
        return $state;
    }

    public function attachQueue(int $tenantId, int $agentId, ?int $queueId): void
    {
        $this->upsertState($tenantId, $agentId, ['queue_id' => $queueId]);
        $state = $this->getState($tenantId, $agentId);
        if ($state !== null) {
            $this->emitStateUpdated($tenantId, $agentId, $state);
        }
    }

    /** @return array<string, mixed>|null */
    public function getState(int $tenantId, int $agentId): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT agent_id, tenant_id, user_id, status, current_call_id, queue_id,
                    pause_reason, session_started_at, last_update, state_json
             FROM rcc_agent_live_state WHERE tenant_id = :tid AND agent_id = :aid LIMIT 1'
        );
        $stmt->execute(['tid' => $tenantId, 'aid' => $agentId]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }
        return $this->rowToState($row);
    }

    /** @return list<array<string, mixed>> */
    public function listByTenant(int $tenantId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT agent_id, tenant_id, user_id, status, current_call_id, queue_id,
                    pause_reason, session_started_at, last_update, state_json
             FROM rcc_agent_live_state WHERE tenant_id = :tid ORDER BY agent_id ASC'
        );
        $stmt->execute(['tid' => $tenantId]);
        $list = [];
        foreach ($stmt->fetchAll() as $row) {
            $list[] = $this->rowToState($row);
        }
        return $list;
    }

    /** @param array<string, mixed> $patch */
    private function upsertState(int $tenantId, int $agentId, array $patch): void
    {
        $existing = $this->getState($tenantId, $agentId);
        $merged = array_merge($existing ?? [
            'agent_id' => $agentId,
            'tenant_id' => $tenantId,
        ], $patch);

        $stmt = Database::connection()->prepare(
            'INSERT INTO rcc_agent_live_state
             (agent_id, tenant_id, user_id, status, current_call_id, queue_id, pause_reason, session_started_at, state_json, last_update)
             VALUES
             (:aid, :tid, :uid, :status, :call, :qid, :pause, :started, :json, NOW(3))
             ON DUPLICATE KEY UPDATE
               user_id = COALESCE(VALUES(user_id), user_id),
               status = VALUES(status),
               current_call_id = VALUES(current_call_id),
               queue_id = COALESCE(VALUES(queue_id), queue_id),
               pause_reason = VALUES(pause_reason),
               session_started_at = COALESCE(VALUES(session_started_at), session_started_at),
               state_json = VALUES(state_json),
               last_update = NOW(3)'
        );
        $stmt->execute([
            'aid' => $agentId,
            'tid' => $tenantId,
            'uid' => $merged['user_id'] ?? null,
            'status' => $merged['status'] ?? 'offline',
            'call' => $merged['current_call_id'] ?? null,
            'qid' => $merged['queue_id'] ?? null,
            'pause' => $merged['pause_reason'] ?? null,
            'started' => $merged['session_started_at'] ?? null,
            'json' => json_encode($merged, JSON_UNESCAPED_UNICODE),
        ]);
    }

    /** @param array<string, mixed> $state */
    private function emitStateUpdated(int $tenantId, int $agentId, array $state): void
    {
        $this->eventBus->emit([
            'type' => EventType::AGENT_STATE_UPDATED,
            'tenant_id' => $tenantId,
            'agent_id' => $agentId,
            'call_id' => $state['current_call_id'] ?? null,
            'queue_id' => $state['queue_id'] ?? null,
            'payload' => $state,
        ]);
    }

    /** @param array<string, mixed> $row */
    private function rowToState(array $row): array
    {
        return [
            'agent_id' => (int) $row['agent_id'],
            'tenant_id' => (int) $row['tenant_id'],
            'user_id' => isset($row['user_id']) ? (int) $row['user_id'] : null,
            'status' => (string) $row['status'],
            'current_call_id' => isset($row['current_call_id']) ? (int) $row['current_call_id'] : null,
            'queue_id' => isset($row['queue_id']) ? (int) $row['queue_id'] : null,
            'pause_reason' => $row['pause_reason'] ?? null,
            'session_started_at' => $row['session_started_at'] ?? null,
            'last_update' => $row['last_update'] ?? null,
        ];
    }
}
