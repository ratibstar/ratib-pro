<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Infrastructure\Channels;

use Ratib\ContactCenter\App\Domain\Conversation\ChannelNormalizer;
use Ratib\ContactCenter\App\Domain\Conversation\ConversationEngine;

final class WhatsAppChannelAdapter
{
    public function __construct(
        private readonly ConversationEngine $engine = new ConversationEngine(),
        private readonly ChannelNormalizer $normalizer = new ChannelNormalizer()
    ) {
    }

    /** @param array<string, mixed> $webhook */
    public function ingest(int $tenantId, array $webhook): array
    {
        $phone = (string) ($webhook['from'] ?? $webhook['sender'] ?? '');
        $message = $this->normalizer->fromWhatsApp($webhook);
        return $this->engine->ingestInbound($tenantId, $message, $phone, null, null);
    }
}
