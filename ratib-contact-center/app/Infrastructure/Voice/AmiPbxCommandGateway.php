<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Infrastructure\Voice;

use Ratib\ContactCenter\App\Application\Contracts\PbxCommandGatewayInterface;

/**
 * Production AMI command gateway — all PBX actions execute via AMI.
 */
final class AmiPbxCommandGateway implements PbxCommandGatewayInterface
{
    public function playMessage(string $channelId, string $message, ?string $audioUrl = null, string $locale = 'ar'): void
    {
        AmiConnectionPool::withClient(function (AmiClient $ami) use ($channelId, $message, $audioUrl, $locale): void {
            if ($audioUrl !== null && $audioUrl !== '') {
                $ami->sendAction([
                    'Action' => 'Playback',
                    'Channel' => $channelId,
                    'Filename' => $audioUrl,
                ]);
                return;
            }

            $ami->sendAction([
                'Action' => 'Setvar',
                'Channel' => $channelId,
                'Variable' => 'RCC_TTS_MESSAGE',
                'Value' => $message,
            ]);
            $ami->sendAction([
                'Action' => 'Setvar',
                'Channel' => $channelId,
                'Variable' => 'RCC_TTS_LOCALE',
                'Value' => $locale,
            ]);
            $ami->sendAction([
                'Action' => 'Exec',
                'Channel' => $channelId,
                'Application' => 'AGI',
                'Data' => 'rcc-tts.php',
            ]);
        });
    }

    public function collectDtmf(string $channelId, int $timeoutSeconds, int $maxDigits = 1): void
    {
        AmiConnectionPool::withClient(function (AmiClient $ami) use ($channelId, $timeoutSeconds, $maxDigits): void {
            $timeout = (string) max(5, min(60, $timeoutSeconds));
            $digits = (string) max(1, $maxDigits);
            $ami->sendAction([
                'Action' => 'Setvar',
                'Channel' => $channelId,
                'Variable' => 'RCC_DTMF_TIMEOUT',
                'Value' => $timeout,
            ]);
            $ami->sendAction([
                'Action' => 'Exec',
                'Channel' => $channelId,
                'Application' => 'Read',
                'Data' => 'RCC_INPUT,,,' . $digits . ',' . $timeout,
            ]);
        });
    }

    public function routeToQueue(string $channelId, string $queueCode, int $tenantId, ?int $preferredAgentId = null): void
    {
        AmiConnectionPool::withClient(function (AmiClient $ami) use ($channelId, $queueCode, $tenantId, $preferredAgentId): void {
            $ami->sendAction([
                'Action' => 'Setvar',
                'Channel' => $channelId,
                'Variable' => 'RCC_TENANT_ID',
                'Value' => (string) $tenantId,
            ]);
            if ($preferredAgentId !== null && $preferredAgentId > 0) {
                $ami->sendAction([
                    'Action' => 'Setvar',
                    'Channel' => $channelId,
                    'Variable' => 'RCC_PREFERRED_AGENT_ID',
                    'Value' => (string) $preferredAgentId,
                ]);
            }
            $ami->sendAction([
                'Action' => 'Exec',
                'Channel' => $channelId,
                'Application' => 'Queue',
                'Data' => $queueCode . ',t,,,300',
            ]);
        });
    }

    public function routeToExtension(string $channelId, string $extension, int $tenantId): void
    {
        AmiConnectionPool::withClient(function (AmiClient $ami) use ($channelId, $extension, $tenantId): void {
            $ami->sendAction([
                'Action' => 'Redirect',
                'Channel' => $channelId,
                'Exten' => $extension,
                'Context' => $ami->tenantContext($tenantId),
                'Priority' => '1',
            ]);
        });
    }

    public function hangup(string $channelId): void
    {
        AmiConnectionPool::withClient(function (AmiClient $ami) use ($channelId): void {
            $ami->sendAction([
                'Action' => 'Hangup',
                'Channel' => $channelId,
            ]);
        });
    }

    public function originateToAgent(
        int $tenantId,
        int $agentId,
        string $extension,
        string $callerChannelId,
        int $callId,
        int $queueId
    ): void {
        AmiConnectionPool::withClient(function (AmiClient $ami) use ($tenantId, $extension, $callerChannelId, $callId, $queueId, $agentId): void {
            $endpoint = $ami->pjsipEndpoint($tenantId, $extension);
            $ami->sendActionAsync([
                'Action' => 'Originate',
                'Channel' => $endpoint,
                'Context' => 'rcc-agent-connect',
                'Exten' => 's',
                'Priority' => '1',
                'CallerID' => 'RCC Queue <' . $callId . '>',
                'Variable' => 'RCC_TENANT_ID=' . $tenantId
                    . ',RCC_CALL_ID=' . $callId
                    . ',RCC_QUEUE_ID=' . $queueId
                    . ',RCC_AGENT_ID=' . $agentId
                    . ',RCC_CALLER_CHANNEL=' . $callerChannelId,
                'Timeout' => '30000',
            ]);
        });
    }

    public function blindTransferChannel(string $channelId, string $targetExtension, int $tenantId): void
    {
        AmiConnectionPool::withClient(function (AmiClient $ami) use ($channelId, $targetExtension, $tenantId): void {
            $ami->sendAction([
                'Action' => 'Redirect',
                'Channel' => $channelId,
                'Exten' => $targetExtension,
                'Context' => $ami->tenantContext($tenantId),
                'Priority' => '1',
            ]);
        });
    }

    public function attendedTransferConsult(
        string $channelId,
        string $targetExtension,
        int $tenantId
    ): void {
        AmiConnectionPool::withClient(function (AmiClient $ami) use ($channelId, $targetExtension, $tenantId): void {
            $ami->sendAction([
                'Action' => 'Setvar',
                'Channel' => $channelId,
                'Variable' => 'RCC_ATTENDED_TARGET',
                'Value' => $targetExtension,
            ]);
            $ami->sendAction([
                'Action' => 'Atxfer',
                'Channel' => $channelId,
                'Exten' => $targetExtension,
                'Context' => $ami->tenantContext($tenantId),
                'Priority' => '1',
            ]);
        });
    }

    public function holdChannel(string $channelId): void
    {
        AmiConnectionPool::withClient(function (AmiClient $ami) use ($channelId): void {
            $ami->sendAction([
                'Action' => 'Hold',
                'Channel' => $channelId,
            ]);
        });
    }

    public function resumeChannel(string $channelId): void
    {
        AmiConnectionPool::withClient(function (AmiClient $ami) use ($channelId): void {
            $ami->sendAction([
                'Action' => 'Unhold',
                'Channel' => $channelId,
            ]);
        });
    }
}
