<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Infrastructure\Voice;

use Ratib\ContactCenter\App\Application\Contracts\PbxCommandGatewayInterface;

/**
 * Sends AMI actions to Asterisk for IVR playback and routing.
 */
final class AsteriskPbxCommandGateway implements PbxCommandGatewayInterface
{
    /** @var callable|null */
    private $amiSender;

    /** @param callable|null $amiSender function(string $action, array $params): void */
    public function __construct(?callable $amiSender = null)
    {
        $this->amiSender = $amiSender;
    }

    public function playMessage(string $channelId, string $message, ?string $audioUrl = null, string $locale = 'ar'): void
    {
        if ($audioUrl !== null && $audioUrl !== '') {
            $this->send('Playback', [
                'Channel' => $channelId,
                'Filename' => $audioUrl,
            ]);
            return;
        }

        $this->send('SetVar', [
            'Channel' => $channelId,
            'Variable' => 'RCC_TTS_MESSAGE',
            'Value' => $message,
        ]);
        $this->send('SetVar', [
            'Channel' => $channelId,
            'Variable' => 'RCC_TTS_LOCALE',
            'Value' => $locale,
        ]);
        $this->send('Exec', [
            'Channel' => $channelId,
            'Application' => 'AGI',
            'Data' => 'rcc-tts.php',
        ]);
    }

    public function collectDtmf(string $channelId, int $timeoutSeconds, int $maxDigits = 1): void
    {
        $this->send('SetVar', [
            'Channel' => $channelId,
            'Variable' => 'RCC_DTMF_TIMEOUT',
            'Value' => (string) max(5, min(60, $timeoutSeconds)),
        ]);
        $this->send('Exec', [
            'Channel' => $channelId,
            'Application' => 'Read',
            'Data' => 'RCC_INPUT,,,' . max(1, $maxDigits) . ',' . max(5, $timeoutSeconds),
        ]);
    }

    public function routeToQueue(string $channelId, string $queueCode, int $tenantId): void
    {
        $this->send('SetVar', [
            'Channel' => $channelId,
            'Variable' => 'RCC_TENANT_ID',
            'Value' => (string) $tenantId,
        ]);
        $this->send('Exec', [
            'Channel' => $channelId,
            'Application' => 'Queue',
            'Data' => $queueCode . ',t,,,300',
        ]);
    }

    public function routeToExtension(string $channelId, string $extension, int $tenantId): void
    {
        $this->send('Redirect', [
            'Channel' => $channelId,
            'Exten' => $extension,
            'Context' => 'rcc-tenant-' . $tenantId,
            'Priority' => '1',
        ]);
    }

    public function hangup(string $channelId): void
    {
        $this->send('Hangup', ['Channel' => $channelId]);
    }

    /** @param array<string, string> $params */
    private function send(string $action, array $params): void
    {
        if ($this->amiSender !== null) {
            ($this->amiSender)($action, $params);
            return;
        }
        error_log('[RCC AMI] ' . $action . ' ' . json_encode($params, JSON_UNESCAPED_UNICODE));
    }
}
