<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Controllers\V2;

use Rateb\App\Pos\Application\V2\PosV2ApplicationFactory;
use Rateb\App\Pos\Application\V2\PosV2BootstrapExceptionHandler;
use Rateb\App\Pos\Application\V2\PosV2ResponseFactory;
use Rateb\App\Pos\Controllers\PosBaseController;
use Rateb\App\Pos\DTO\V2\Bootstrap\PosV2BootstrapMeta;
use Rateb\App\Pos\UseCases\V2\Register\AccessRegisterBootstrapUseCaseFactory;
use Throwable;

/**
 * V2 register bootstrap API (T05 wiring, T06 use case).
 */
final class PosV2BootstrapApiController extends PosBaseController
{
    public function __construct(
        private readonly PosV2ApplicationFactory $applicationFactory = new PosV2ApplicationFactory(),
        private readonly AccessRegisterBootstrapUseCaseFactory $useCaseFactory = new AccessRegisterBootstrapUseCaseFactory(),
        private readonly PosV2BootstrapExceptionHandler $exceptionHandler = new PosV2BootstrapExceptionHandler(
            new PosV2ResponseFactory(),
        ),
    ) {
    }

    public function bootstrap(): void
    {
        try {
            $application = $this->applicationFactory->create();
            $context = $application->bootstrapRegister();
            $bootstrap = $this->useCaseFactory->create()->execute($context);
            $application->responses()->bootstrapSuccess(
                $bootstrap,
                PosV2BootstrapMeta::fromBootstrapResponse($bootstrap),
            )->send();
        } catch (Throwable $throwable) {
            $this->exceptionHandler->handle($throwable)->send();
        }
    }
}
