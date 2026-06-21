<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Domain\IVR\NodeExecutors;

use Ratib\ContactCenter\App\Application\Contracts\PbxCommandGatewayInterface;
use Ratib\ContactCenter\App\Domain\IVR\IvrNode;
use Ratib\ContactCenter\App\Domain\IVR\IvrSession;
use Ratib\ContactCenter\App\Domain\IVR\NodeExecutionResult;

interface NodeExecutorInterface
{
    public function supports(string $nodeType): bool;

    public function execute(
        IvrSession $session,
        IvrNode $node,
        PbxCommandGatewayInterface $pbx
    ): NodeExecutionResult;
}
