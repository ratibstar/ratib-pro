<?php
declare(strict_types=1);

namespace Rateb\App\Services\Push;

/**
 * APNs placeholder — no SDK in I.2.
 */
final class StubApnsPushProvider implements ApnsPushProviderInterface
{
    public function __construct(
        private readonly bool $configured = false
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->configured;
    }

    public function send(string $deviceToken, array $payload): PushSendResult
    {
        if ($deviceToken === '') {
            return PushSendResult::failure('invalid_token', 'Empty device token', true);
        }
        if (!$this->isConfigured()) {
            return PushSendResult::failure('apns_not_configured', 'APNs credentials not configured');
        }

        return PushSendResult::failure('apns_sdk_pending', 'APNs SDK not implemented in I.2');
    }
}
