<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Infrastructure\Channels;

use Ratib\ContactCenter\App\Domain\Conversation\ChannelNormalizer;
use Ratib\ContactCenter\App\Domain\Conversation\ConversationEngine;

final class EmailChannelAdapter
{
    public function __construct(
        private readonly ConversationEngine $engine = new ConversationEngine(),
        private readonly ChannelNormalizer $normalizer = new ChannelNormalizer()
    ) {
    }

    /** @param array<string, mixed> $email */
    public function ingest(int $tenantId, array $email): array
    {
        $fromEmail = (string) ($email['from'] ?? $email['sender_email'] ?? '');
        $message = $this->normalizer->fromEmail($email);
        return $this->engine->ingestInbound($tenantId, $message, null, $fromEmail, null);
    }
}
