<?php
declare(strict_types=1);

namespace Rateb\App\Services\Push;

/**
 * FCM provider contract — implementations must not live inside NotificationService.
 * Phase I.2 ships a stub; real Firebase Admin SDK is a later phase.
 */
interface FcmPushProviderInterface
{
    /**
     * @param array<string,mixed> $payload title, body, data
     */
    public function send(string $deviceToken, array $payload): PushSendResult;

    public function isConfigured(): bool;
}
