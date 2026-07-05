<?php

declare(strict_types=1);

namespace Rateb\App\Pos\UseCases\V2\Register;

use Rateb\App\Pos\DTO\V2\Context\PosV2RequestContext;
use Rateb\App\Pos\DTO\V2\Register\RegisterBootstrapResponse;
use Rateb\App\Pos\Services\V2\Register\PosV2RegisterBootstrapAssembler;
use Rateb\App\Pos\Services\V2\Register\PosV2RegisterBootstrapValidator;

/** Orchestrates register bootstrap assembly for the POS register screen (T06). */
final class AccessRegisterBootstrapUseCase
{
    public function __construct(
        private readonly PosV2RegisterBootstrapValidator $validator,
        private readonly PosV2RegisterBootstrapAssembler $assembler,
    ) {
    }

    public function execute(PosV2RequestContext $context): RegisterBootstrapResponse
    {
        $this->validator->validate($context);

        return $this->assembler->assemble($context);
    }
}
