<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Domain\Conversation;

use Ratib\ContactCenter\App\Core\Events\EventBus;
use Ratib\ContactCenter\App\Core\Events\EventType;
use Ratib\ContactCenter\App\Infrastructure\Persistence\Repositories\ConversationMessageRepository;
use Ratib\ContactCenter\App\Infrastructure\Persistence\Repositories\ConversationRepository;

/**
 * Unified conversation layer — ALL channels become one thread.
 * Agent assignment MUST go through this engine.
 */
final class ConversationEngine
{
    public function __construct(
        private readonly IdentityResolver $identityResolver = new IdentityResolver(),
        private readonly ChannelNormalizer $normalizer = new ChannelNormalizer(),
        private readonly ConversationPriorityEngine $priorityEngine = new ConversationPriorityEngine(),
        private readonly ConversationRepository $conversations = new ConversationRepository(),
        private readonly ConversationMessageRepository $messages = new ConversationMessageRepository(),
        private readonly ?EventBus $eventBus = null
    ) {
    }

    /** @return array<string, mixed> */
    public function fromIncomingCall(
        int $tenantId,
        int $callId,
        string $callerNumber,
        ?int $ivrSessionId = null
    ): array {
        $identity = $this->identityResolver->resolve($tenantId, $callerNumber, null, null);
        $existing = $this->conversations->findOpenByIdentity($tenantId, $identity['identity'])
            ?? $this->conversations->findByCallId($tenantId, $callId);

        $msg = $this->normalizer->fromVoiceCall([
            'call_id' => $callId,
            'caller_number' => $callerNumber,
            'status' => 'incoming',
        ]);

        if ($existing !== null) {
            return $this->appendToConversation($tenantId, (int) $existing['conversation_id'], $msg, [
                'call_id' => $callId,
                'ivr_session_id' => $ivrSessionId,
                'add_channel' => 'voice',
            ]);
        }

        $priority = $this->priorityEngine->compute('voice', $identity['erp_profile'] ?? []);

        $conversationId = $this->conversations->create($tenantId, [
            'customer_id' => $identity['customer_id'],
            'customer_identity' => $identity['identity'],
            'priority' => $priority['priority'],
            'sla_status' => $priority['sla_risk'],
            'priority_score' => $priority['score'],
            'status' => 'open',
            'last_channel' => 'voice',
            'last_message' => $msg->message,
            'channels' => ['voice'],
            'call_id' => $callId,
            'ivr_session_id' => $ivrSessionId,
            'metadata' => [
                'identity' => $identity,
            ],
        ]);

        $this->messages->append($tenantId, $conversationId, $msg);
        $this->conversations->linkCall($tenantId, $callId, $conversationId);

        return $this->emitCreated($tenantId, $conversationId);
    }

    /** @return array<string, mixed> */
    public function fromCall(
        int $tenantId,
        int $callId,
        ?int $agentId,
        ?string $remoteNumber,
        ?array $erpProfile = null,
        ?int $ivrSessionId = null
    ): array {
        $phone = $remoteNumber ?? '';
        $identity = $this->identityResolver->resolve($tenantId, $phone, null, null);
        if ($erpProfile !== null) {
            $identity['erp_profile'] = $erpProfile;
        }

        $conversation = $this->conversations->findByCallId($tenantId, $callId)
            ?? $this->conversations->findOpenByIdentity($tenantId, $identity['identity']);

        $msg = $this->normalizer->fromVoiceCall([
            'call_id' => $callId,
            'remote_number' => $phone,
            'agent_id' => $agentId,
            'status' => 'connected',
            'ivr_session_id' => $ivrSessionId,
        ]);

        $erpCtx = is_array($erpProfile) ? ['flags' => $this->flagsFromErp($erpProfile)] : [];
        $priority = $this->priorityEngine->compute('voice', $erpCtx);

        if ($conversation === null) {
            $conversationId = $this->conversations->create($tenantId, [
                'customer_id' => $identity['customer_id'],
                'customer_identity' => $identity['identity'],
                'assigned_agent_id' => $agentId,
                'priority' => $priority['priority'],
                'sla_status' => $priority['sla_risk'],
                'priority_score' => $priority['score'],
                'status' => 'open',
                'last_channel' => 'voice',
                'last_message' => $msg->message,
                'channels' => ['voice'],
                'call_id' => $callId,
                'ivr_session_id' => $ivrSessionId,
                'metadata' => ['erp_customer' => $erpProfile, 'identity' => $identity],
            ]);
            $this->messages->append($tenantId, $conversationId, $msg);
            $this->conversations->linkCall($tenantId, $callId, $conversationId);
            return $this->emitCreated($tenantId, $conversationId);
        }

        return $this->appendToConversation($tenantId, (int) $conversation['conversation_id'], $msg, [
            'assigned_agent_id' => $agentId,
            'call_id' => $callId,
            'ivr_session_id' => $ivrSessionId,
            'add_channel' => 'voice',
            'priority' => $priority['priority'],
            'sla_status' => $priority['sla_risk'],
            'priority_score' => $priority['score'],
            'metadata' => array_merge($conversation['metadata'] ?? [], ['erp_customer' => $erpProfile]),
        ]);
    }

    /** @return array<string, mixed> */
    public function attachIvrSession(int $tenantId, int $callId, int $ivrSessionId, array $ivrData): array
    {
        $conversation = $this->conversations->findByCallId($tenantId, $callId);
        if ($conversation === null) {
            return $this->fromIncomingCall($tenantId, $callId, (string) ($ivrData['caller_number'] ?? ''), $ivrSessionId);
        }

        $msg = $this->normalizer->fromIvrSession(array_merge($ivrData, ['ivr_session_id' => $ivrSessionId]));
        return $this->appendToConversation($tenantId, (int) $conversation['conversation_id'], $msg, [
            'ivr_session_id' => $ivrSessionId,
        ]);
    }

    /** @return array<string, mixed> */
    public function assignFromRouting(
        int $tenantId,
        int $callId,
        int $agentId,
        ?int $queueId,
        array $routingDecision = []
    ): array {
        $conversation = $this->conversations->findByCallId($tenantId, $callId);
        if ($conversation === null) {
            return [];
        }

        $slaRisk = (string) ($routingDecision['sla_risk'] ?? $conversation['sla_status']);
        $priority = $this->priorityEngine->compute(
            'voice',
            $conversation['metadata']['erp_context'] ?? [],
            $routingDecision,
            $slaRisk
        );

        $updated = $this->conversations->update($tenantId, (int) $conversation['conversation_id'], [
            'assigned_agent_id' => $agentId,
            'priority' => $priority['priority'],
            'sla_status' => $priority['sla_risk'],
            'priority_score' => $priority['score'],
        ]);

        if ($updated === null) {
            return [];
        }

        if ($updated['priority'] !== $conversation['priority']) {
            $this->emit($tenantId, EventType::CONVERSATION_PRIORITY_CHANGED, (int) $conversation['conversation_id'], $agentId, [
                'old_priority' => $conversation['priority'],
                'new_priority' => $updated['priority'],
                'conversation' => $updated,
            ]);
        }

        return $this->emitAssigned($tenantId, (int) $conversation['conversation_id'], $agentId, $updated);
    }

    /**
     * Inbound message from any channel adapter.
     *
     * @return array<string, mixed>
     */
    public function ingestInbound(
        int $tenantId,
        ConversationMessage $message,
        ?string $phone = null,
        ?string $email = null,
        ?int $erpCustomerId = null
    ): array {
        $identity = $this->identityResolver->resolve($tenantId, $phone, $email, $erpCustomerId);
        $existing = $this->conversations->findOpenByIdentity($tenantId, $identity['identity']);

        $erpCtx = is_array($identity['erp_profile'] ?? null) ? $identity['erp_profile'] : [];
        $priority = $this->priorityEngine->compute($message->channel, $erpCtx);

        if ($existing !== null) {
            return $this->appendToConversation($tenantId, (int) $existing['conversation_id'], $message, [
                'add_channel' => $message->channel,
                'priority' => $priority['priority'],
                'sla_status' => $priority['sla_risk'],
                'priority_score' => $priority['score'],
                'unread_count' => ((int) $existing['unread_count']) + 1,
            ]);
        }

        $conversationId = $this->conversations->create($tenantId, [
            'customer_id' => $identity['customer_id'],
            'customer_identity' => $identity['identity'],
            'priority' => $priority['priority'],
            'sla_status' => $priority['sla_risk'],
            'priority_score' => $priority['score'],
            'status' => 'open',
            'last_channel' => $message->channel,
            'last_message' => $message->message,
            'channels' => [$message->channel],
            'metadata' => ['identity' => $identity],
        ]);
        $this->messages->append($tenantId, $conversationId, $message);

        $created = $this->emitCreated($tenantId, $conversationId);
        $this->emit($tenantId, EventType::MESSAGE_RECEIVED, $conversationId, null, [
            'message' => $message->toArray(),
            'conversation' => $created,
        ]);
        return $created;
    }

    /** @return array<string, mixed> */
    public function sendOutbound(
        int $tenantId,
        int $conversationId,
        int $agentId,
        string $channel,
        string $messageText,
        array $payload = []
    ): array {
        $conversation = $this->conversations->findById($tenantId, $conversationId);
        if ($conversation === null) {
            throw new \RuntimeException('Conversation not found.');
        }

        $msg = $this->normalizer->outbound($channel, $messageText, $agentId, $payload);
        $this->messages->append($tenantId, $conversationId, $msg);

        $updated = $this->conversations->update($tenantId, $conversationId, [
            'last_channel' => $channel,
            'last_message' => $messageText,
            'last_message_at' => gmdate('Y-m-d H:i:s'),
            'assigned_agent_id' => $agentId,
            'add_channel' => $channel,
        ]);

        $this->emit($tenantId, EventType::MESSAGE_SENT, $conversationId, $agentId, [
            'message' => $msg->toArray(),
            'conversation' => $updated,
        ]);

        return $updated ?? $conversation;
    }

    /** @return array<string, mixed> */
    public function markPending(int $tenantId, int $conversationId, ?int $agentId = null): array
    {
        $updated = $this->conversations->update($tenantId, $conversationId, ['status' => 'pending']);
        if ($updated === null) {
            throw new \RuntimeException('Conversation not found.');
        }
        return $this->emitUpdated($tenantId, $conversationId, $agentId, $updated);
    }

    /** @return array<string, mixed> */
    public function closeConversation(int $tenantId, int $conversationId, ?int $agentId = null): array
    {
        $updated = $this->conversations->update($tenantId, $conversationId, ['status' => 'closed']);
        if ($updated === null) {
            throw new \RuntimeException('Conversation not found.');
        }
        return $this->emitUpdated($tenantId, $conversationId, $agentId, $updated);
    }

    /** @return list<array<string, mixed>> */
    public function inboxForAgent(int $tenantId, int $agentId): array
    {
        return $this->conversations->listForAgent($tenantId, $agentId);
    }

    /** @return array<string, mixed> */
    public function thread(int $tenantId, int $conversationId): array
    {
        $conversation = $this->conversations->findById($tenantId, $conversationId);
        if ($conversation === null) {
            throw new \RuntimeException('Conversation not found.');
        }
        return [
            'conversation' => $conversation,
            'messages' => $this->messages->listByConversation($tenantId, $conversationId),
        ];
    }

    /** @param array<string, mixed> $patch */
    private function appendToConversation(
        int $tenantId,
        int $conversationId,
        ConversationMessage $message,
        array $patch = []
    ): array {
        $this->messages->append($tenantId, $conversationId, $message);

        $updated = $this->conversations->update($tenantId, $conversationId, array_merge($patch, [
            'last_channel' => $message->channel,
            'last_message' => $message->message,
            'last_message_at' => gmdate('Y-m-d H:i:s'),
        ]));

        $this->emit($tenantId, EventType::MESSAGE_RECEIVED, $conversationId, $message->senderId, [
            'message' => $message->toArray(),
            'conversation' => $updated,
        ]);

        return $this->emitUpdated($tenantId, $conversationId, $patch['assigned_agent_id'] ?? null, $updated ?? []);
    }

    /** @return array<string, mixed> */
    private function emitCreated(int $tenantId, int $conversationId): array
    {
        $conversation = $this->conversations->findById($tenantId, $conversationId) ?? [];
        $this->emit($tenantId, EventType::CONVERSATION_CREATED, $conversationId, null, [
            'conversation' => $conversation,
        ]);
        return $conversation;
    }

    /** @param array<string, mixed> $conversation */
    private function emitUpdated(int $tenantId, int $conversationId, ?int $agentId, array $conversation): array
    {
        $this->emit($tenantId, EventType::CONVERSATION_UPDATED, $conversationId, $agentId, [
            'conversation' => $conversation,
        ]);
        return $conversation;
    }

    /** @param array<string, mixed> $conversation */
    private function emitAssigned(int $tenantId, int $conversationId, int $agentId, array $conversation): array
    {
        $this->emit($tenantId, EventType::CONVERSATION_ASSIGNED, $conversationId, $agentId, [
            'conversation' => $conversation,
        ]);
        return $conversation;
    }

    /** @param array<string, mixed> $payload */
    private function emit(int $tenantId, string $type, int $conversationId, ?int $agentId, array $payload): void
    {
        ($this->eventBus ?? EventBus::instance())->emit([
            'type' => $type,
            'tenant_id' => $tenantId,
            'agent_id' => $agentId,
            'call_id' => $payload['conversation']['call_id'] ?? $payload['call_id'] ?? null,
            'payload' => array_merge($payload, ['conversation_id' => $conversationId]),
        ]);
    }

    /** @param array<string, mixed> $erpProfile */
    private function flagsFromErp(array $erpProfile): array
    {
        $contact = is_array($erpProfile['contact'] ?? null) ? $erpProfile['contact'] : [];
        return [
            'vip_customer' => ($contact['contact_type'] ?? '') === 'vip',
            'open_sla_breach' => ($erpProfile['sla_status'] ?? '') === 'breached',
            'repeat_caller' => false,
        ];
    }
}
