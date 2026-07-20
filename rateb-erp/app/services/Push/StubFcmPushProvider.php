<?php
declare(strict_types=1);

namespace Rateb\App\Services\Push;

/**
 * Stub FCM provider — no Firebase SDK. Returns not_configured unless injected test double.
 */
final class StubFcmPushProvider implements FcmPushProviderInterface
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
            return PushSendResult::failure('fcm_not_configured', 'FCM credentials not configured');
        }

        // Real HTTP/Firebase Admin send lands in a later phase.
        return PushSendResult::failure('fcm_sdk_pending', 'FCM SDK not implemented in I.2');
    }
}
