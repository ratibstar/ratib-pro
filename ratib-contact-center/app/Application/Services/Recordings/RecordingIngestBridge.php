<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Application\Services\Recordings;

use Ratib\ContactCenter\App\Core\Events\EventSubscriberInterface;
use Ratib\ContactCenter\App\Core\Events\EventType;
use Ratib\ContactCenter\App\Core\Events\RealtimeEvent;

/** Ingest Asterisk recordings when calls end. */
final class RecordingIngestBridge implements EventSubscriberInterface
{
    public function __construct(private readonly RecordingService $recordings = new RecordingService())
    {
    }

    public function onEvent(RealtimeEvent $event): void
    {
        if ($event->type !== EventType::CALL_ENDED) {
            return;
        }
        $path = (string) ($event->payload['recording_path'] ?? $event->payload['recording_file'] ?? '');
        if ($path === '' || !is_file($path)) {
            $base = getenv('RCC_RECORDINGS_PATH') ?: (dirname(__DIR__, 3) . '/storage/recordings');
            $callId = (int) ($event->payload['call_id'] ?? 0);
            if ($callId > 0) {
                $candidate = rtrim((string) $base, '/') . '/call-' . $callId . '.wav';
                if (is_file($candidate)) {
                    $path = $candidate;
                }
            }
        }
        if ($path === '' || !is_file($path)) {
            return;
        }
        $this->recordings->ingest($event->tenantId, [
            'call_id' => $event->payload['call_id'] ?? null,
            'conversation_id' => $event->payload['conversation_id'] ?? null,
            'contact_id' => $event->payload['contact_id'] ?? null,
            'agent_id' => $event->agentId,
            'caller_number' => $event->payload['caller_number'] ?? null,
            'duration_seconds' => (int) ($event->payload['duration_seconds'] ?? 0),
            'file_path' => $path,
            'file_size' => (int) filesize($path),
            'asterisk_uniqueid' => $event->payload['uniqueid'] ?? null,
        ]);
    }
}
