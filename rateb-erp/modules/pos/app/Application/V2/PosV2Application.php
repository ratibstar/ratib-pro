<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Application\V2;

use Rateb\App\Pos\DTO\V2\Context\PosV2RequestContext;

/**
 * V2 application entry point — single runtime layer for controllers.
 */
final class PosV2Application
{
    public function __construct(
        private readonly PosV2Bootstrap $bootstrap,
        private readonly PosV2ResponseFactory $responses,
    ) {
    }

    public function bootstrap(): PosV2Bootstrap
    {
        return $this->bootstrap;
    }

    public function responses(): PosV2ResponseFactory
    {
        return $this->responses;
    }

    public function bootstrapRegister(?string $channel = null): PosV2RequestContext
    {
        return $this->bootstrap->bootstrapRegister($channel);
    }
}
