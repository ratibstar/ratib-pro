<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Domain\Conversation;

/**
 * Normalizes channel-specific payloads into ConversationMessage.
 */
final class ChannelNormalizer
{
    public function fromVoiceCall(array $callData, string $direction = 'inbound'): ConversationMessage
    {
        $status = (string) ($callData['status'] ?? 'connected');
        $remote = (string) ($callData['remote_number'] ?? $callData['caller_number'] ?? '');
        $message = match ($status) {
            'connected' => 'Voice call connected with ' . $remote,
            'ended' => 'Voice call ended',
            'incoming' => 'Incoming voice call from ' . $remote,
            default => 'Voice call event: ' . $status,
        };

        return new ConversationMessage(
            channel: 'voice',
            direction: $direction,
            message: $message,
            payload: [
                'call_id' => $callData['call_id'] ?? null,
                'softphone_call_id' => $callData['softphone_call_id'] ?? null,
                'remote_number' => $remote,
                'queue_id' => $callData['queue_id'] ?? null,
                'ivr_session_id' => $callData['ivr_session_id'] ?? null,
                'status' => $status,
            ],
            externalId: isset($callData['call_id']) ? 'call:' . $callData['call_id'] : null,
            senderType: $direction === 'inbound' ? 'contact' : 'agent',
            senderId: isset($callData['agent_id']) ? (int) $callData['agent_id'] : null,
        );
    }

    public function fromIvrSession(array $ivrData): ConversationMessage
    {
        $input = (string) ($ivrData['last_input'] ?? '');
        $node = (string) ($ivrData['node_type'] ?? 'ivr');

        return new ConversationMessage(
            channel: 'voice',
            direction: 'inbound',
            message: $input !== '' ? 'IVR input: ' . $input : 'IVR session started',
            payload: [
                'ivr_session_id' => $ivrData['ivr_session_id'] ?? null,
                'flow_id' => $ivrData['flow_id'] ?? null,
                'node_type' => $node,
                'last_input' => $input,
            ],
            externalId: isset($ivrData['ivr_session_id']) ? 'ivr:' . $ivrData['ivr_session_id'] : null,
            senderType: 'contact',
        );
    }

    public function fromWhatsApp(array $webhook): ConversationMessage
    {
        $text = (string) ($webhook['text'] ?? $webhook['body'] ?? '');
        $from = (string) ($webhook['from'] ?? $webhook['sender'] ?? '');

        return new ConversationMessage(
            channel: 'whatsapp',
            direction: 'inbound',
            message: $text !== '' ? $text : '[WhatsApp message]',
            payload: $webhook,
            externalId: isset($webhook['message_id']) ? (string) $webhook['message_id'] : null,
            senderType: 'contact',
        );
    }

    public function fromEmail(array $email): ConversationMessage
    {
        $subject = (string) ($email['subject'] ?? '(no subject)');
        $body = (string) ($email['body_text'] ?? $email['body'] ?? '');
        $preview = mb_substr(trim($body !== '' ? $body : $subject), 0, 500);

        return new ConversationMessage(
            channel: 'email',
            direction: 'inbound',
            message: $subject . ($preview !== '' ? "\n" . $preview : ''),
            payload: $email,
            externalId: isset($email['message_id']) ? (string) $email['message_id'] : null,
            senderType: 'contact',
        );
    }

    public function fromWebChat(array $chat): ConversationMessage
    {
        $text = (string) ($chat['message'] ?? $chat['text'] ?? '');

        return new ConversationMessage(
            channel: 'chat',
            direction: (string) ($chat['direction'] ?? 'inbound'),
            message: $text !== '' ? $text : '[Chat message]',
            payload: $chat,
            externalId: isset($chat['session_id']) ? 'chat:' . $chat['session_id'] : null,
            senderType: (string) ($chat['sender_type'] ?? 'contact'),
            senderId: isset($chat['sender_id']) ? (int) $chat['sender_id'] : null,
        );
    }

    public function outbound(string $channel, string $message, int $agentId, array $payload = []): ConversationMessage
    {
        return new ConversationMessage(
            channel: $channel,
            direction: 'outbound',
            message: $message,
            payload: $payload,
            senderType: 'agent',
            senderId: $agentId,
        );
    }
}
