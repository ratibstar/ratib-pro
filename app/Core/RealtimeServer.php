<?php
declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class RealtimeServer
{
    public function __construct(private readonly string $channel = 'default')
    {
    }

    public function publish(string $eventType, array $payload): void
    {
        $entry = [
            'id' => $this->nextEventId(),
            'type' => trim($eventType),
            'timestamp' => gmdate('c'),
            'payload' => $payload,
        ];
        $encoded = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            return;
        }
        $logPath = $this->eventLogPath();
        $dir = dirname($logPath);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('Unable to initialize realtime storage');
        }
        file_put_contents($logPath, $encoded . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    public function streamSse(int $lastEventId = 0): void
    {
        @set_time_limit(0);
        @ini_set('output_buffering', 'off');
        @ini_set('zlib.output_compression', '0');

        header('Content-Type: text/event-stream; charset=UTF-8');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');

        $this->emit('ready', ['channel' => $this->channel], max($lastEventId, 0));
        $cursor = max($lastEventId, 0);
        $idleHeartbeats = 0;
        while (!connection_aborted()) {
            $sent = $this->emitNewEvents($cursor);
            if ($sent > 0) {
                $cursor += $sent;
                $idleHeartbeats = 0;
            } else {
                $idleHeartbeats++;
                if ($idleHeartbeats >= 3) {
                    $this->emit('ping', ['ts' => gmdate('c')], $cursor);
                    $idleHeartbeats = 0;
                }
            }
            usleep(1000000);
        }
    }

    private function emitNewEvents(int $afterId): int
    {
        $path = $this->eventLogPath();
        if (!is_file($path)) {
            return 0;
        }
        $fh = @fopen($path, 'rb');
        if (!is_resource($fh)) {
            return 0;
        }
        $sent = 0;
        while (($line = fgets($fh)) !== false) {
            $row = json_decode(trim($line), true);
            if (!is_array($row)) {
                continue;
            }
            $id = (int) ($row['id'] ?? 0);
            if ($id <= $afterId) {
                continue;
            }
            $type = (string) ($row['type'] ?? 'message');
            $payload = is_array($row['payload'] ?? null) ? $row['payload'] : [];
            $this->emit($type, $payload, $id);
            $sent++;
        }
        fclose($fh);

        return $sent;
    }

    private function emit(string $eventType, array $payload, int $eventId): void
    {
        echo "id: {$eventId}\n";
        echo 'event: ' . preg_replace('/[^a-zA-Z0-9_.-]/', '_', $eventType) . "\n";
        $data = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($data === false) {
            $data = '{}';
        }
        foreach (explode("\n", $data) as $line) {
            echo 'data: ' . $line . "\n";
        }
        echo "\n";
        @ob_flush();
        @flush();
    }

    private function nextEventId(): int
    {
        $cursorPath = $this->cursorPath();
        $dir = dirname($cursorPath);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('Unable to initialize realtime cursor');
        }
        $fh = @fopen($cursorPath, 'c+');
        if (!is_resource($fh)) {
            throw new RuntimeException('Unable to lock realtime cursor');
        }
        flock($fh, LOCK_EX);
        $raw = stream_get_contents($fh);
        $current = (int) trim((string) $raw);
        $next = $current + 1;
        ftruncate($fh, 0);
        rewind($fh);
        fwrite($fh, (string) $next);
        fflush($fh);
        flock($fh, LOCK_UN);
        fclose($fh);

        return $next;
    }

    private function eventLogPath(): string
    {
        return $this->storageBase() . DIRECTORY_SEPARATOR . 'events.log';
    }

    private function cursorPath(): string
    {
        return $this->storageBase() . DIRECTORY_SEPARATOR . 'cursor.txt';
    }

    private function storageBase(): string
    {
        return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'realtime' . DIRECTORY_SEPARATOR . $this->channel;
    }
}
