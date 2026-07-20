<?php
declare(strict_types=1);

namespace Rateb\App\Services\Push;

/**
 * APNs provider contract — placeholder until APNs SDK wiring.
 */
interface ApnsPushProviderInterface
{
    /**
     * @param array<string,mixed> $payload title, body, data
     */
    public function send(string $deviceToken, array $payload): PushSendResult;

    public function isConfigured(): bool;
}
