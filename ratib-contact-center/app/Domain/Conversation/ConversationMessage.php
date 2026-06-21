<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Domain\Conversation;

/**
 * Normalized message across all channels.
 */
final class ConversationMessage
{
    public function __construct(
        public readonly string $channel,
        public readonly string $direction,
        public readonly string $message,
        /** @var array<string, mixed> */
        public readonly array $payload = [],
        public readonly ?string $externalId = null,
        public readonly string $senderType = 'contact',
        public readonly ?int $senderId = null,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            channel: (string) ($data['channel'] ?? 'system'),
            direction: (string) ($data['direction'] ?? 'inbound'),
            message: (string) ($data['message'] ?? ''),
            payload: is_array($data['payload'] ?? null) ? $data['payload'] : [],
            externalId: isset($data['external_id']) ? (string) $data['external_id'] : null,
            senderType: (string) ($data['sender_type'] ?? 'contact'),
            senderId: isset($data['sender_id']) ? (int) $data['sender_id'] : null,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'channel' => $this->channel,
            'direction' => $this->direction,
            'message' => $this->message,
            'payload' => $this->payload,
            'external_id' => $this->externalId,
            'sender_type' => $this->senderType,
            'sender_id' => $this->senderId,
        ];
    }
}
