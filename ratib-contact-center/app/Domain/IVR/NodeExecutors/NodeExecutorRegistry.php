<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Domain\IVR\NodeExecutors;

use Ratib\ContactCenter\App\Application\Contracts\PbxCommandGatewayInterface;
use Ratib\ContactCenter\App\Domain\IVR\Enums\IvrNodeType;
use Ratib\ContactCenter\App\Domain\IVR\IvrNode;
use Ratib\ContactCenter\App\Domain\IVR\IvrSession;
use Ratib\ContactCenter\App\Domain\IVR\NodeExecutionResult;

/**
 * Resolves node type → executor (Strategy pattern).
 */
final class NodeExecutorRegistry
{
    /** @var list<NodeExecutorInterface> */
    private array $executors;

    /** @param list<NodeExecutorInterface> $executors */
    public function __construct(array $executors)
    {
        $this->executors = $executors;
    }

    public function resolve(IvrNodeType $type): NodeExecutorInterface
    {
        foreach ($this->executors as $executor) {
            if ($executor->supports($type->value)) {
                return $executor;
            }
        }
        throw new \RuntimeException('No executor registered for node type: ' . $type->value);
    }

    public function executeNode(
        IvrSession $session,
        IvrNode $node,
        PbxCommandGatewayInterface $pbx
    ): NodeExecutionResult {
        return $this->resolve($node->type)->execute($session, $node, $pbx);
    }
}
