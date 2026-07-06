<?php

declare(strict_types=1);

namespace Rateb\App\Pos\UseCases\V2\Register;

use Rateb\App\Pos\Application\V2\PosV2RequestScope;
use Rateb\App\Pos\Services\V2\Register\PosV2RegisterBootstrapAssembler;
use Rateb\App\Pos\Services\V2\Register\PosV2RegisterBootstrapValidator;
use Rateb\App\Pos\Services\V2\Register\RegisterBootstrapProvidersFactory;

/** Wires AccessRegisterBootstrapUseCase (T06 + T07 providers). */
final class AccessRegisterBootstrapUseCaseFactory
{
    public function __construct(
        private readonly RegisterBootstrapProvidersFactory $providersFactory = new RegisterBootstrapProvidersFactory(),
    ) {
    }

    public function create(): AccessRegisterBootstrapUseCase
    {
        PosV2RequestScope::ensure();

        return new AccessRegisterBootstrapUseCase(
            new PosV2RegisterBootstrapValidator(),
            new PosV2RegisterBootstrapAssembler(
                $this->providersFactory->createOrchestrator(),
            ),
        );
    }
}
