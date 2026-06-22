<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Infrastructure\Omnichannel;

use Ratib\ContactCenter\App\Infrastructure\Omnichannel\Channels\EmailOutboundService;
use Ratib\ContactCenter\App\Infrastructure\Omnichannel\Channels\WhatsAppOutboundService;

final class OutboundDispatcher
{
    public function dispatch(int $tenantId, int $conversationId, string $channel, string $message, array $conversation): void
    {
        $channel = strtolower($channel);
        match ($channel) {
            'whatsapp' => (new WhatsAppOutboundService())->send($tenantId, $conversationId, $message, $conversation),
            'email' => (new EmailOutboundService())->send($tenantId, $conversationId, $message, $conversation),
            default => null,
        };
    }
}
