<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Application\Services;

use Ratib\ContactCenter\App\Core\Events\EventBus;
use Ratib\ContactCenter\App\Core\Events\EventType;

/**
 * Streams IVR path visualization events to dashboard (via EventBus — no direct UI coupling).
 */
final class IvrStateStreamer
{
    private EventBus $eventBus;

    public function __construct(?EventBus $eventBus = null)
    {
        $this->eventBus = $eventBus ?? EventBus::instance();
    }

    public function emitStarted(
        int $tenantId,
        int $ivrSessionId,
        int $callId,
        int $flowId,
        ?int $entryNodeId,
        ?string $channelId
    ): void {
        $this->eventBus->emit([
            'type' => EventType::IVR_STARTED,
            'tenant_id' => $tenantId,
            'ivr_session_id' => $ivrSessionId,
            'call_id' => $callId,
            'payload' => [
                'flow_id' => $flowId,
                'entry_node_id' => $entryNodeId,
                'channel_id' => $channelId,
                'ivr_path' => [],
            ],
        ]);
    }

    public function emitNodeEntered(
        int $tenantId,
        int $ivrSessionId,
        int $callId,
        int $nodeId,
        string $nodeType,
        ?string $channelId
    ): void {
        $this->eventBus->emit([
            'type' => EventType::IVR_NODE_ENTERED,
            'tenant_id' => $tenantId,
            'ivr_session_id' => $ivrSessionId,
            'call_id' => $callId,
            'payload' => [
                'node_id' => $nodeId,
                'node_type' => $nodeType,
                'channel_id' => $channelId,
            ],
        ]);
    }

    public function emitWaitingInput(
        int $tenantId,
        int $ivrSessionId,
        int $callId,
        int $nodeId,
        int $timeoutSeconds
    ): void {
        $this->eventBus->emit([
            'type' => EventType::IVR_WAITING_INPUT,
            'tenant_id' => $tenantId,
            'ivr_session_id' => $ivrSessionId,
            'call_id' => $callId,
            'payload' => [
                'node_id' => $nodeId,
                'timeout_seconds' => $timeoutSeconds,
            ],
        ]);
    }

    public function emitCompleted(
        int $tenantId,
        int $ivrSessionId,
        int $callId,
        string $status
    ): void {
        $this->eventBus->emit([
            'type' => EventType::IVR_COMPLETED,
            'tenant_id' => $tenantId,
            'ivr_session_id' => $ivrSessionId,
            'call_id' => $callId,
            'payload' => ['final_status' => $status],
        ]);
    }
}
