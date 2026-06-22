<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Domain\Softphone;

use Ratib\ContactCenter\App\Core\Events\EventBus;
use Ratib\ContactCenter\App\Core\Events\EventType;
use Ratib\ContactCenter\App\Domain\Agents\AgentStateService;
use Ratib\ContactCenter\App\Domain\Queue\QueueRealtimeService;
use Ratib\ContactCenter\App\Infrastructure\Persistence\Repositories\SoftphoneCallRepository;
use Ratib\ContactCenter\App\Infrastructure\Voice\AmiPbxCommandGateway;
use Ratib\ContactCenter\App\Domain\Softphone\Enums\SoftphoneCallStatus;

final class TransferEngine
{
    private AmiPbxCommandGateway $pbx;

    public function __construct(
        private readonly SoftphoneCallRepository $calls = new SoftphoneCallRepository(),
        private readonly ?EventBus $eventBus = null,
        ?AmiPbxCommandGateway $pbx = null,
        private readonly ?AgentStateService $agentState = null,
        private readonly ?QueueRealtimeService $queueRealtime = null
    ) {
        $this->pbx = $pbx ?? new AmiPbxCommandGateway();
    }

    public function blindTransfer(
        int $tenantId,
        int $agentId,
        int $softphoneCallId,
        string $targetExtension,
        ?string $channelId = null
    ): array {
        $call = $this->calls->findById($softphoneCallId, $tenantId);
        if ($call === null) {
            throw new \RuntimeException('Call not found.');
        }

        if ($channelId !== null && $channelId !== '') {
            $this->pbx->blindTransferChannel($channelId, $targetExtension, $tenantId);
        }

        $updated = $this->calls->updateStatus($softphoneCallId, $tenantId, SoftphoneCallStatus::Transferred);
        ($this->eventBus ?? EventBus::instance())->emit([
            'type' => EventType::CALL_TRANSFERRED,
            'tenant_id' => $tenantId,
            'agent_id' => $agentId,
            'call_id' => $call['call_id'],
            'payload' => [
                'transfer_type' => 'blind',
                'target_extension' => $targetExtension,
                'softphone_call_id' => $softphoneCallId,
            ],
        ]);

        return $updated ?? $call;
    }

    public function attendedTransferInit(
        int $tenantId,
        int $agentId,
        int $softphoneCallId,
        string $targetExtension,
        ?string $channelId = null
    ): array {
        $call = $this->calls->findById($softphoneCallId, $tenantId);
        if ($call === null) {
            throw new \RuntimeException('Call not found.');
        }

        if ($channelId !== null && $channelId !== '') {
            $this->pbx->attendedTransferConsult($channelId, $targetExtension, $tenantId);
        }

        ($this->eventBus ?? EventBus::instance())->emit([
            'type' => EventType::CALL_TRANSFERRED,
            'tenant_id' => $tenantId,
            'agent_id' => $agentId,
            'call_id' => $call['call_id'],
            'payload' => [
                'transfer_type' => 'attended_init',
                'target_extension' => $targetExtension,
                'softphone_call_id' => $softphoneCallId,
            ],
        ]);
        return $call;
    }

    public function attendedTransferComplete(
        int $tenantId,
        int $agentId,
        int $softphoneCallId,
        ?string $channelId = null
    ): array {
        $call = $this->calls->findById($softphoneCallId, $tenantId);
        if ($call === null) {
            throw new \RuntimeException('Call not found.');
        }

        if ($channelId !== null && $channelId !== '') {
            $this->pbx->attendedTransferConsult($channelId, (string) ($call['remote_number'] ?? ''), $tenantId);
        }

        $updated = $this->calls->updateStatus($softphoneCallId, $tenantId, SoftphoneCallStatus::Transferred);
        ($this->eventBus ?? EventBus::instance())->emit([
            'type' => EventType::CALL_TRANSFERRED,
            'tenant_id' => $tenantId,
            'agent_id' => $agentId,
            'call_id' => $call['call_id'],
            'payload' => [
                'transfer_type' => 'attended_complete',
                'softphone_call_id' => $softphoneCallId,
            ],
        ]);
        if ($call['queue_id'] !== null) {
            ($this->queueRealtime ?? new QueueRealtimeService())->publishSnapshot($tenantId, (int) $call['queue_id']);
        }
        return $updated ?? $call;
    }
}
