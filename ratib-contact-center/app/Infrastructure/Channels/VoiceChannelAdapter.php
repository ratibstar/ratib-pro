<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Infrastructure\Channels;

use Ratib\ContactCenter\App\Domain\Conversation\ChannelNormalizer;
use Ratib\ContactCenter\App\Domain\Conversation\ConversationEngine;

final class VoiceChannelAdapter
{
    public function __construct(
        private readonly ConversationEngine $engine = new ConversationEngine(),
        private readonly ChannelNormalizer $normalizer = new ChannelNormalizer()
    ) {
    }

    /** @return array<string, mixed> */
    public function onIncoming(int $tenantId, int $callId, string $callerNumber, ?int $ivrSessionId = null): array
    {
        return $this->engine->fromIncomingCall($tenantId, $callId, $callerNumber, $ivrSessionId);
    }

    /** @return array<string, mixed> */
    public function onConnected(
        int $tenantId,
        int $callId,
        ?int $agentId,
        ?string $remoteNumber,
        ?array $erpProfile = null
    ): array {
        return $this->engine->fromCall($tenantId, $callId, $agentId, $remoteNumber, $erpProfile);
    }
}
