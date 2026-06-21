<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Domain\Softphone;

use Ratib\ContactCenter\App\Application\Services\SoftphoneErpService;
use Ratib\ContactCenter\App\Core\Events\EventBus;
use Ratib\ContactCenter\App\Core\Events\EventType;
use Ratib\ContactCenter\App\Core\TenantContext;
use Ratib\ContactCenter\App\Domain\Agents\AgentStateService;
use Ratib\ContactCenter\App\Domain\Softphone\Enums\SoftphoneCallStatus;
use Ratib\ContactCenter\App\Domain\Softphone\Enums\SoftphoneDirection;
use Ratib\ContactCenter\App\Infrastructure\Persistence\Repositories\AgentSipSessionRepository;
use Ratib\ContactCenter\App\Infrastructure\Persistence\Repositories\SoftphoneCallRepository;
use Ratib\ContactCenter\App\Infrastructure\WebRTC\SipGateway;

/**
 * Browser softphone call control — signaling + EventBus only (NO media processing).
 */
final class CallControlEngine
{
    public function __construct(
        private readonly SipGateway $sipGateway = new SipGateway(),
        private readonly AgentSipSessionRepository $sipSessions = new AgentSipSessionRepository(),
        private readonly SoftphoneCallRepository $calls = new SoftphoneCallRepository(),
        private readonly MediaSessionManager $mediaSessions = new MediaSessionManager(),
        private readonly TransferEngine $transferEngine = new TransferEngine(),
        private readonly AgentStateService $agentState = new AgentStateService(),
        private readonly SoftphoneErpService $erpService = new SoftphoneErpService(),
        private readonly ?EventBus $eventBus = null
    ) {
    }

    /**
     * Agent login → return WebRTC/SIP credentials for browser registration.
     *
     * @return array<string, mixed>
     */
    public function registerAgentSession(
        int $tenantId,
        int $agentId,
        ?int $userId = null,
        ?string $userAgent = null
    ): array {
        TenantContext::set($tenantId);
        $this->sipGateway->assertTenantAccess($tenantId, $agentId);

        $creds = $this->sipGateway->buildWebRtcConfig($tenantId, $agentId);
        $token = bin2hex(random_bytes(16));

        $this->sipSessions->upsertOnline(
            $tenantId,
            $agentId,
            (string) $creds['authorizationUsername'],
            parse_url((string) $creds['server'], PHP_URL_HOST) ?: 'pbx',
            $token,
            $userAgent
        );

        $this->agentState->login($tenantId, $agentId, $userId);

        ($this->eventBus ?? EventBus::instance())->emit([
            'type' => EventType::SIP_REGISTERED,
            'tenant_id' => $tenantId,
            'agent_id' => $agentId,
            'payload' => [
                'extension' => $creds['authorizationUsername'],
                'wss_uri' => $creds['server'],
            ],
        ]);

        return [
            'session_token' => $token,
            'webrtc' => $creds,
            'auto_answer_queue_calls' => $this->mediaSessions->tenantAutoAnswerEnabled($tenantId),
            'realtime_rooms' => [
                'tenant:' . $tenantId,
                'agent:' . $agentId,
                'dashboard:' . $tenantId,
            ],
        ];
    }

    public function pingSession(int $tenantId, int $agentId): void
    {
        $this->sipGateway->assertTenantAccess($tenantId, $agentId);
        $this->sipSessions->ping($tenantId, $agentId);
    }

    public function unregisterAgentSession(int $tenantId, int $agentId): void
    {
        $this->sipSessions->setOffline($tenantId, $agentId);
        $this->agentState->setOffline($tenantId, $agentId, 'sip_unregister');
        ($this->eventBus ?? EventBus::instance())->emit([
            'type' => EventType::SIP_UNREGISTERED,
            'tenant_id' => $tenantId,
            'agent_id' => $agentId,
            'payload' => [],
        ]);
    }

    /** @return array<string, mixed> */
    public function initiateOutbound(int $tenantId, int $agentId, string $destination): array
    {
        TenantContext::set($tenantId);
        $this->sipGateway->assertTenantAccess($tenantId, $agentId);

        $call = $this->calls->create(
            $tenantId,
            $agentId,
            $destination,
            SoftphoneDirection::Outbound
        );

        $state = $this->mediaSessions->publishState($tenantId, $agentId, $call);
        return ['call' => $state, 'signaling' => 'browser_invite'];
    }

    /** @return array<string, mixed> */
    public function acceptInbound(
        int $tenantId,
        int $agentId,
        int $callId,
        string $remoteNumber,
        ?int $queueId = null,
        ?string $sipCallId = null,
        ?string $channelId = null
    ): array {
        TenantContext::set($tenantId);
        $existing = $this->calls->findActiveByAgent($tenantId, $agentId);
        if ($existing !== null) {
            $call = $existing;
        } else {
            $call = $this->calls->create(
                $tenantId,
                $agentId,
                $remoteNumber,
                SoftphoneDirection::Inbound,
                $callId,
                $queueId,
                $sipCallId
            );
        }

        ($this->eventBus ?? EventBus::instance())->emit([
            'type' => EventType::CALL_ACCEPTED,
            'tenant_id' => $tenantId,
            'agent_id' => $agentId,
            'call_id' => $callId ?: ($call['call_id'] ?? null),
            'queue_id' => $queueId,
            'payload' => [
                'remote_number' => $remoteNumber,
                'softphone_call_id' => $call['id'],
                'channel_id' => $channelId,
            ],
        ]);

        return $this->markConnected($tenantId, $agentId, (int) $call['id'], $callId, $queueId, $remoteNumber);
    }

    /** @return array<string, mixed> */
    public function markConnected(
        int $tenantId,
        int $agentId,
        int $softphoneCallId,
        ?int $callId = null,
        ?int $queueId = null,
        ?string $remoteNumber = null
    ): array {
        $call = $this->calls->updateStatus($softphoneCallId, $tenantId, SoftphoneCallStatus::Connected);
        if ($call === null) {
            throw new \RuntimeException('Softphone call not found.');
        }

        $this->agentState->setBusy($tenantId, $agentId, $callId ?? $call['call_id'], $queueId);

        $erpProfile = null;
        if ($remoteNumber !== null && $remoteNumber !== '') {
            $erpProfile = $this->erpService->customerProfileByPhone($tenantId, $remoteNumber);
        } elseif (!empty($call['remote_number'])) {
            $erpProfile = $this->erpService->customerProfileByPhone($tenantId, (string) $call['remote_number']);
        }

        ($this->eventBus ?? EventBus::instance())->emit([
            'type' => EventType::CALL_CONNECTED,
            'tenant_id' => $tenantId,
            'agent_id' => $agentId,
            'call_id' => $callId ?? $call['call_id'],
            'queue_id' => $queueId ?? $call['queue_id'],
            'payload' => [
                'softphone_call_id' => $softphoneCallId,
                'remote_number' => $call['remote_number'],
                'erp_customer' => $erpProfile,
            ],
        ]);

        $call['erp_customer'] = $erpProfile;
        return $this->mediaSessions->publishState($tenantId, $agentId, $call);
    }

    /** @return array<string, mixed> */
    public function holdCall(int $tenantId, int $agentId, int $softphoneCallId): array
    {
        $call = $this->calls->updateStatus($softphoneCallId, $tenantId, SoftphoneCallStatus::Held);
        if ($call === null) {
            throw new \RuntimeException('Call not found.');
        }
        ($this->eventBus ?? EventBus::instance())->emit([
            'type' => EventType::CALL_HOLD,
            'tenant_id' => $tenantId,
            'agent_id' => $agentId,
            'call_id' => $call['call_id'],
            'payload' => ['softphone_call_id' => $softphoneCallId],
        ]);
        return $this->mediaSessions->publishState($tenantId, $agentId, $call);
    }

    /** @return array<string, mixed> */
    public function resumeCall(int $tenantId, int $agentId, int $softphoneCallId): array
    {
        $call = $this->calls->updateStatus($softphoneCallId, $tenantId, SoftphoneCallStatus::Connected);
        if ($call === null) {
            throw new \RuntimeException('Call not found.');
        }
        ($this->eventBus ?? EventBus::instance())->emit([
            'type' => EventType::CALL_RESUME,
            'tenant_id' => $tenantId,
            'agent_id' => $agentId,
            'call_id' => $call['call_id'],
            'payload' => ['softphone_call_id' => $softphoneCallId],
        ]);
        $this->agentState->setBusy($tenantId, $agentId, $call['call_id'], $call['queue_id']);
        return $this->mediaSessions->publishState($tenantId, $agentId, $call);
    }

    /** @return array<string, mixed> */
    public function hangup(int $tenantId, int $agentId, int $softphoneCallId): array
    {
        $call = $this->calls->findById($softphoneCallId, $tenantId);
        if ($call === null) {
            throw new \RuntimeException('Call not found.');
        }
        $duration = (int) ($call['duration'] ?? 0);
        $updated = $this->calls->updateStatus($softphoneCallId, $tenantId, SoftphoneCallStatus::Ended, $duration);

        ($this->eventBus ?? EventBus::instance())->emit([
            'type' => EventType::CALL_ENDED,
            'tenant_id' => $tenantId,
            'agent_id' => $agentId,
            'call_id' => $call['call_id'],
            'payload' => [
                'softphone_call_id' => $softphoneCallId,
                'duration' => $duration,
            ],
        ]);

        return $this->mediaSessions->publishState($tenantId, $agentId, $updated ?? $call);
    }

    public function blindTransfer(
        int $tenantId,
        int $agentId,
        int $softphoneCallId,
        string $targetExtension,
        ?string $channelId = null
    ): array {
        return $this->transferEngine->blindTransfer($tenantId, $agentId, $softphoneCallId, $targetExtension, $channelId);
    }

    public function attendedTransfer(
        int $tenantId,
        int $agentId,
        int $softphoneCallId,
        string $targetExtension,
        bool $complete = false,
        ?string $channelId = null
    ): array {
        if ($complete) {
            return $this->transferEngine->attendedTransferComplete($tenantId, $agentId, $softphoneCallId, $channelId);
        }
        return $this->transferEngine->attendedTransferInit($tenantId, $agentId, $softphoneCallId, $targetExtension);
    }

    /** @return array<string, mixed>|null */
    public function activeCall(int $tenantId, int $agentId): ?array
    {
        return $this->calls->findActiveByAgent($tenantId, $agentId);
    }

    /** Queue auto-answer: browser should accept when QUEUE_ASSIGNED + tenant setting. */
    public function shouldAutoAnswer(int $tenantId): bool
    {
        return $this->mediaSessions->tenantAutoAnswerEnabled($tenantId);
    }
}
