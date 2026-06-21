<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Infrastructure\Realtime;

use Ratib\ContactCenter\App\Core\Events\RealtimeEvent;

/**
 * Multi-tenant WebSocket broadcast gateway.
 *
 * Channel isolation via room names:
 *   tenant:{id} | agent:{id} | queue:{id} | dashboard:{tenantId}
 */
final class WebSocketGateway
{
    private RealtimeHubClient $hubClient;

    /** @var array<int, resource> in-process subscribers (same-process testing) */
    private static array $localClients = [];

    public function __construct(?RealtimeHubClient $hubClient = null)
    {
        $this->hubClient = $hubClient ?? new RealtimeHubClient();
    }

    public function broadcast(RealtimeEvent $event): void
    {
        $json = $event->toJson();
        $rooms = $this->resolveRooms($event);

        $this->hubClient->publish($rooms, $json);
        $this->broadcastLocal($rooms, $json);
    }

    /**
     * @return list<string>
     */
    public function resolveRooms(RealtimeEvent $event): array
    {
        $rooms = [
            'tenant:' . $event->tenantId,
            'dashboard:' . $event->tenantId,
        ];
        if ($event->agentId !== null && $event->agentId > 0) {
            $rooms[] = 'agent:' . $event->agentId;
        }
        if ($event->queueId !== null && $event->queueId > 0) {
            $rooms[] = 'queue:' . $event->queueId;
        }
        if ($event->ivrSessionId !== null && $event->ivrSessionId > 0) {
            $rooms[] = 'ivr:' . $event->ivrSessionId;
        }
        return array_values(array_unique($rooms));
    }

    /** @param list<string> $rooms */
    private function broadcastLocal(array $rooms, string $json): void
    {
        foreach (self::$localClients as $client) {
            $subscribed = $client['rooms'] ?? [];
            if ($subscribed === [] || array_intersect($rooms, $subscribed) !== []) {
                @fwrite($client['socket'], WebSocketProtocol::encode($json));
            }
        }
    }

    /** @param list<string> $rooms */
    public static function registerLocalClient($socket, array $rooms): void
    {
        self::$localClients[(int) $socket] = ['socket' => $socket, 'rooms' => $rooms];
    }

    public static function unregisterLocalClient($socket): void
    {
        unset(self::$localClients[(int) $socket]);
    }
}
