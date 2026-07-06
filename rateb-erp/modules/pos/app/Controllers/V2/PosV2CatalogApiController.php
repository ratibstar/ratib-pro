<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Controllers\V2;

use Rateb\App\Pos\Application\V2\PosV2ApplicationFactory;
use Rateb\App\Pos\Application\V2\PosV2CatalogExceptionHandler;
use Rateb\App\Pos\Application\V2\PosV2ResponseFactory;
use Rateb\App\Pos\Controllers\PosBaseController;
use Rateb\App\Pos\DTO\V2\Catalog\CatalogSearchRequest;
use Rateb\App\Pos\UseCases\V2\Catalog\CatalogUseCaseFactory;
use Throwable;

/** V2 catalog API (T08). */
final class PosV2CatalogApiController extends PosBaseController
{
    public function __construct(
        private readonly PosV2ApplicationFactory $applicationFactory = new PosV2ApplicationFactory(),
        private readonly CatalogUseCaseFactory $useCaseFactory = new CatalogUseCaseFactory(),
        private readonly PosV2CatalogExceptionHandler $exceptionHandler = new PosV2CatalogExceptionHandler(
            new PosV2ResponseFactory(),
        ),
    ) {
    }

    public function search(): void
    {
        try {
            $application = $this->applicationFactory->create();
            $context = $application->bootstrapRegister('api');
            $request = CatalogSearchRequest::fromQueryParams($_GET);
            $result = $this->useCaseFactory->createSearch()->execute($context, $request);
            $application->responses()->catalogSuccess($result)->send();
        } catch (Throwable $throwable) {
            $this->exceptionHandler->handle($throwable)->send();
        }
    }

    public function product(int $productId): void
    {
        try {
            $application = $this->applicationFactory->create();
            $context = $application->bootstrapRegister('api');
            $result = $this->useCaseFactory->createGetProduct()->execute($context, $productId);
            $application->responses()->catalogProductSuccess($result)->send();
        } catch (Throwable $throwable) {
            $this->exceptionHandler->handle($throwable)->send();
        }
    }

    public function barcode(): void
    {
        try {
            $application = $this->applicationFactory->create();
            $context = $application->bootstrapRegister('api');
            $code = (string) ($_GET['code'] ?? $_GET['barcode'] ?? '');
            $result = $this->useCaseFactory->createBarcodeLookup()->execute($context, $code);
            $application->responses()->catalogProductSuccess($result)->send();
        } catch (Throwable $throwable) {
            $this->exceptionHandler->handle($throwable)->send();
        }
    }
}
