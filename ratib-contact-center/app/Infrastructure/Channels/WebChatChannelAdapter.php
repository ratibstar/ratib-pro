<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Infrastructure\Channels;

use Ratib\ContactCenter\App\Domain\Conversation\ChannelNormalizer;
use Ratib\ContactCenter\App\Domain\Conversation\ConversationEngine;

final class WebChatChannelAdapter
{
    public function __construct(
        private readonly ConversationEngine $engine = new ConversationEngine(),
        private readonly ChannelNormalizer $normalizer = new ChannelNormalizer()
    ) {
    }

    /** @param array<string, mixed> $chat */
    public function ingest(int $tenantId, array $chat): array
    {
        $message = $this->normalizer->fromWebChat($chat);
        $email = isset($chat['email']) ? (string) $chat['email'] : null;
        $phone = isset($chat['phone']) ? (string) $chat['phone'] : null;
        return $this->engine->ingestInbound($tenantId, $message, $phone, $email, null);
    }
}
