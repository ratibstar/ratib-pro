<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Infrastructure\Voice;

use Ratib\ContactCenter\App\Application\Contracts\PbxCommandGatewayInterface;

/**
 * Sends AMI actions to Asterisk for IVR playback and routing.
 */
final class AsteriskPbxCommandGateway implements PbxCommandGatewayInterface
{
    private AmiPbxCommandGateway $ami;

    public function __construct(?AmiPbxCommandGateway $ami = null)
    {
        $this->ami = $ami ?? new AmiPbxCommandGateway();
    }

    public function playMessage(string $channelId, string $message, ?string $audioUrl = null, string $locale = 'ar'): void
    {
        $this->ami->playMessage($channelId, $message, $audioUrl, $locale);
    }

    public function collectDtmf(string $channelId, int $timeoutSeconds, int $maxDigits = 1): void
    {
        $this->ami->collectDtmf($channelId, $timeoutSeconds, $maxDigits);
    }

    public function routeToQueue(string $channelId, string $queueCode, int $tenantId, ?int $preferredAgentId = null): void
    {
        $this->ami->routeToQueue($channelId, $queueCode, $tenantId, $preferredAgentId);
    }

    public function routeToExtension(string $channelId, string $extension, int $tenantId): void
    {
        $this->ami->routeToExtension($channelId, $extension, $tenantId);
    }

    public function hangup(string $channelId): void
    {
        $this->ami->hangup($channelId);
    }

    public function ami(): AmiPbxCommandGateway
    {
        return $this->ami;
    }
}
