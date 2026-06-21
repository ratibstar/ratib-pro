<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Domain\IVR\NodeExecutors;

use Ratib\ContactCenter\App\Application\Contracts\PbxCommandGatewayInterface;
use Ratib\ContactCenter\App\Domain\IVR\Enums\IvrNodeType;
use Ratib\ContactCenter\App\Domain\IVR\IvrNode;
use Ratib\ContactCenter\App\Domain\IVR\IvrSession;
use Ratib\ContactCenter\App\Domain\IVR\NodeExecutionResult;

final class HangupExecutor implements NodeExecutorInterface
{
    public function supports(string $nodeType): bool
    {
        return $nodeType === IvrNodeType::Hangup->value;
    }

    public function execute(
        IvrSession $session,
        IvrNode $node,
        PbxCommandGatewayInterface $pbx
    ): NodeExecutionResult {
        $channelId = $session->channelId ?? '';
        if ($channelId !== '') {
            $pbx->hangup($channelId);
        }
        return NodeExecutionResult::complete(['hangup_reason' => $node->payload['reason'] ?? 'normal']);
    }
}
