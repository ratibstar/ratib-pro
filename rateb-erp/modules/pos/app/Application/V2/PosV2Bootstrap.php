<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Application\V2;

use Rateb\App\Pos\DTO\V2\Context\PosV2RequestContext;

/**
 * Prepares V2 runtime context for incoming register requests.
 */
final class PosV2Bootstrap
{
    public function __construct(
        private readonly PosV2ContextFactory $contextFactory,
    ) {
    }

    public function bootstrapRegister(?string $channel = null): PosV2RequestContext
    {
        return $this->contextFactory->createRequestContext($channel);
    }
}
