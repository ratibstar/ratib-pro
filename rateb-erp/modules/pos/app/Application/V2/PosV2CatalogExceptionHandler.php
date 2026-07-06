<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Application\V2;

use Rateb\App\Pos\Application\V2\Http\PosV2JsonResponse;
use Rateb\App\Pos\Domain\V2\Exceptions\PosV2CatalogNotFoundException;
use Rateb\App\Pos\Domain\V2\Exceptions\PosV2CatalogValidationException;
use Rateb\App\Pos\Domain\V2\Exceptions\PosV2ForbiddenException;
use Rateb\App\Pos\Domain\V2\Exceptions\PosV2RegisterBootstrapValidationException;
use Rateb\App\Pos\Domain\V2\Exceptions\PosV2UnauthorizedException;
use Rateb\App\Pos\DTO\V2\Catalog\CatalogProductResponse;
use Rateb\App\Pos\DTO\V2\Catalog\CatalogSearchResponse;
use RuntimeException;
use Throwable;

/** Maps catalog exceptions to OpenAPI-aligned JSON responses (T08). */
final class PosV2CatalogExceptionHandler
{
    public function __construct(
        private readonly PosV2ResponseFactory $responses,
        private readonly PosV2BootstrapExceptionHandler $bootstrapHandler = new PosV2BootstrapExceptionHandler(
            new PosV2ResponseFactory(),
        ),
    ) {
    }

    public function handle(Throwable $throwable): PosV2JsonResponse
    {
        if ($throwable instanceof PosV2CatalogValidationException) {
            return $this->responses->catalogError(
                $throwable->errorCode,
                $throwable->getMessage(),
                422,
            );
        }

        if ($throwable instanceof PosV2CatalogNotFoundException) {
            return $this->responses->catalogError(
                $throwable->errorCode,
                $throwable->getMessage(),
                404,
            );
        }

        if ($throwable instanceof PosV2ForbiddenException) {
            return $this->responses->catalogError(
                'FORBIDDEN',
                $throwable->getMessage() !== '' ? $throwable->getMessage() : 'Forbidden',
                403,
            );
        }

        if ($throwable instanceof PosV2UnauthorizedException) {
            return $this->responses->catalogError(
                'UNAUTHORIZED',
                $throwable->getMessage() !== '' ? $throwable->getMessage() : 'Unauthorized',
                401,
            );
        }

        if ($throwable instanceof PosV2RegisterBootstrapValidationException) {
            return $this->bootstrapHandler->handle($throwable);
        }

        if ($throwable instanceof RuntimeException) {
            return $this->responses->catalogError(
                'REGISTER_CONTEXT_INVALID',
                $throwable->getMessage(),
                422,
            );
        }

        return $this->responses->catalogError(
            'INTERNAL_ERROR',
            'An unexpected error occurred.',
            500,
        );
    }
}
