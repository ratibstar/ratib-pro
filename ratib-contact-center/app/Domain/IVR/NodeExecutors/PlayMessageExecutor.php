<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Domain\IVR\NodeExecutors;

use Ratib\ContactCenter\App\Application\Contracts\PbxCommandGatewayInterface;
use Ratib\ContactCenter\App\Domain\IVR\Enums\IvrNodeType;
use Ratib\ContactCenter\App\Domain\IVR\IvrNode;
use Ratib\ContactCenter\App\Domain\IVR\IvrSession;
use Ratib\ContactCenter\App\Domain\IVR\NodeExecutionResult;

final class PlayMessageExecutor implements NodeExecutorInterface
{
    public function supports(string $nodeType): bool
    {
        return $nodeType === IvrNodeType::PlayMessage->value;
    }

    public function execute(
        IvrSession $session,
        IvrNode $node,
        PbxCommandGatewayInterface $pbx
    ): NodeExecutionResult {
        $channelId = $session->channelId ?? '';
        if ($channelId === '') {
            return NodeExecutionResult::fail(['error' => 'missing_channel']);
        }

        $message = $node->localizedMessage($session->locale);
        $audioUrl = isset($node->payload['audio_url']) ? (string) $node->payload['audio_url'] : null;
        if ($audioUrl === '') {
            $localeKey = $session->locale === 'ar' ? 'audio_url_ar' : 'audio_url_en';
            $audioUrl = isset($node->payload[$localeKey]) ? (string) $node->payload[$localeKey] : null;
        }

        $pbx->playMessage($channelId, $message, $audioUrl, $session->locale);

        return NodeExecutionResult::advance(
            $node->nextNodeId,
            ['last_played' => $message, 'last_node_id' => $node->id]
        );
    }
}
