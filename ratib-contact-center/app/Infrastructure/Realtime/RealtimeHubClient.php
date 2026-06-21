<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Infrastructure\Realtime;

/**
 * Pushes events to the realtime hub over TCP (newline-delimited JSON).
 * EventBus → Hub → WebSocket clients (no polling).
 */
final class RealtimeHubClient
{
    private string $host;
    private int $port;

    public function __construct(?string $host = null, ?int $port = null)
    {
        $this->host = $host ?? (getenv('RCC_REALTIME_HUB_HOST') ?: '127.0.0.1');
        $this->port = $port ?? (int) (getenv('RCC_REALTIME_HUB_PORT') ?: 9701);
    }

    /**
     * @param list<string> $rooms
     */
    public function publish(array $rooms, string $jsonPayload): void
    {
        $message = json_encode([
            'rooms' => $rooms,
            'event' => json_decode($jsonPayload, true),
        ], JSON_UNESCAPED_UNICODE);

        if ($message === false) {
            return;
        }

        $socket = @stream_socket_client(
            'tcp://' . $this->host . ':' . $this->port,
            $errno,
            $errstr,
            1.0
        );
        if ($socket === false) {
            error_log('[RCC RealtimeHub] Unreachable ' . $this->host . ':' . $this->port . ' — ' . $errstr);
            return;
        }
        fwrite($socket, $message . "\n");
        fclose($socket);
    }
}
