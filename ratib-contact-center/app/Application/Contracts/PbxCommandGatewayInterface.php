<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Application\Contracts;

/**
 * Sends playback / hangup / queue commands to PBX (Asterisk AMI / ARI).
 */
interface PbxCommandGatewayInterface
{
    public function playMessage(string $channelId, string $message, ?string $audioUrl = null, string $locale = 'ar'): void;

    public function collectDtmf(string $channelId, int $timeoutSeconds, int $maxDigits = 1): void;

    public function routeToQueue(string $channelId, string $queueCode, int $tenantId): void;

    public function routeToExtension(string $channelId, string $extension, int $tenantId): void;

    public function hangup(string $channelId): void;
}
