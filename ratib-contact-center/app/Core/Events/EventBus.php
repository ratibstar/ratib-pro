<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Core\Events;

use Ratib\ContactCenter\App\Core\TenantContext;
use Ratib\ContactCenter\App\Infrastructure\Persistence\Repositories\RealtimeEventRepository;
use Ratib\ContactCenter\App\Infrastructure\Realtime\WebSocketGateway;

/**
 * Central event dispatcher — ALL live updates MUST pass through here.
 *
 * Flow: normalize → enrich → persist → WebSocket → subscribers
 */
final class EventBus
{
    private static ?self $instance = null;

    private WebSocketGateway $webSocketGateway;
    private RealtimeEventRepository $repository;

    /** @var list<EventSubscriberInterface> */
    private array $subscribers = [];

    public function __construct(
        ?WebSocketGateway $webSocketGateway = null,
        ?RealtimeEventRepository $repository = null
    ) {
        $this->webSocketGateway = $webSocketGateway ?? new WebSocketGateway();
        $this->repository = $repository ?? new RealtimeEventRepository();
    }

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function setInstance(self $bus): void
    {
        self::$instance = $bus;
    }

    public function subscribe(EventSubscriberInterface $subscriber): void
    {
        $this->subscribers[] = $subscriber;
    }

    /**
     * @param array<string, mixed> $event
     */
    public function emit(array $event): RealtimeEvent
    {
        $normalized = $this->normalize($event);
        $enriched = $this->enrich($normalized);
        $realtimeEvent = RealtimeEvent::fromNormalized($enriched);

        try {
            $this->repository->persist($realtimeEvent);
        } catch (\Throwable $e) {
            error_log('[RCC EventBus] Persist failed: ' . $e->getMessage());
        }

        $this->webSocketGateway->broadcast($realtimeEvent);

        foreach ($this->subscribers as $subscriber) {
            try {
                $subscriber->onEvent($realtimeEvent);
            } catch (\Throwable $e) {
                error_log('[RCC EventBus] Subscriber error: ' . $e->getMessage());
            }
        }

        return $realtimeEvent;
    }

    /**
     * @param array<string, mixed> $event
     * @return array<string, mixed>
     */
    private function normalize(array $event): array
    {
        $type = strtoupper(trim((string) ($event['type'] ?? '')));
        if ($type === '') {
            throw new \InvalidArgumentException('Event type is required.');
        }

        $tenantId = (int) ($event['tenant_id'] ?? TenantContext::tenantId() ?? 0);
        if ($tenantId < 1) {
            throw new \InvalidArgumentException('tenant_id is required for realtime events.');
        }

        $payload = $event['payload'] ?? [];
        if (!is_array($payload)) {
            $payload = ['value' => $payload];
        }

        return [
            'type' => $type,
            'tenant_id' => $tenantId,
            'agent_id' => isset($event['agent_id']) ? (int) $event['agent_id'] : null,
            'call_id' => isset($event['call_id']) ? (int) $event['call_id'] : null,
            'queue_id' => isset($event['queue_id']) ? (int) $event['queue_id'] : null,
            'ivr_session_id' => isset($event['ivr_session_id']) ? (int) $event['ivr_session_id'] : null,
            'payload' => $payload,
        ];
    }

    /**
     * @param array<string, mixed> $event
     * @return array<string, mixed>
     */
    private function enrich(array $event): array
    {
        $event['event_uuid'] = $this->uuid4();
        $event['timestamp'] = gmdate('Y-m-d\TH:i:s.v\Z');

        $event['payload']['tenant_id'] = $event['tenant_id'];
        $event['payload']['erp_company_id'] = TenantContext::erpCompanyId();
        $event['payload']['locale'] = TenantContext::locale();

        if ($event['agent_id'] !== null) {
            $event['payload']['agent_id'] = $event['agent_id'];
        }
        if ($event['call_id'] !== null) {
            $event['payload']['call_id'] = $event['call_id'];
        }
        if ($event['queue_id'] !== null) {
            $event['payload']['queue_id'] = $event['queue_id'];
        }
        if ($event['ivr_session_id'] !== null) {
            $event['payload']['ivr_session_id'] = $event['ivr_session_id'];
        }

        return $event;
    }

    private function uuid4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s-%s-%s-%s-%s', str_split(bin2hex($data), 4));
    }
}
