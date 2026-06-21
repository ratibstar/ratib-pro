<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Domain\IVR\NodeExecutors;

use Ratib\ContactCenter\App\Application\Contracts\PbxCommandGatewayInterface;
use Ratib\ContactCenter\App\Domain\IVR\Enums\IvrNodeType;
use Ratib\ContactCenter\App\Domain\IVR\IvrNode;
use Ratib\ContactCenter\App\Domain\IVR\IvrSession;
use Ratib\ContactCenter\App\Domain\IVR\NodeExecutionResult;

final class CollectInputExecutor implements NodeExecutorInterface
{
    public function supports(string $nodeType): bool
    {
        return $nodeType === IvrNodeType::CollectInput->value;
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

        $prompt = $node->localizedMessage($session->locale);
        if ($prompt !== '') {
            $pbx->playMessage($channelId, $prompt, null, $session->locale);
        }

        $maxDigits = (int) ($node->payload['max_digits'] ?? 1);
        $pbx->collectDtmf($channelId, $node->timeoutSeconds, max(1, $maxDigits));

        return NodeExecutionResult::waitForInput(
            [
                'collect_node_id' => $node->id,
                'awaiting_input' => true,
                'timeout_at' => time() + $node->timeoutSeconds,
            ],
            $session->retryCount
        );
    }
}
