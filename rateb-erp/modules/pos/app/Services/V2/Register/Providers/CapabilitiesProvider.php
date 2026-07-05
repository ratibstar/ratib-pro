<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Services\V2\Register\Providers;

use Rateb\App\Pos\DTO\V2\Context\PosV2RequestContext;
use Rateb\App\Pos\DTO\V2\Register\PosV2RegisterCapabilities;
use Rateb\App\Pos\Services\V2\Register\PosV2RegisterCapabilitiesResolver;

final class CapabilitiesProvider
{
    public function __construct(
        private readonly PosV2RegisterCapabilitiesResolver $resolver,
    ) {
    }

    public function provide(PosV2RequestContext $context): PosV2RegisterCapabilities
    {
        return $this->resolver->resolve($context->register);
    }
}
