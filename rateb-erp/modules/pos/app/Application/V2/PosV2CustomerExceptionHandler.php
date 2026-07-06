<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Application\V2;

use Rateb\App\Pos\Application\V2\Http\PosV2JsonResponse;
use Rateb\App\Pos\Domain\V2\Customer\Exceptions\PosV2CustomerNotFoundException;
use Rateb\App\Pos\Domain\V2\Customer\Exceptions\PosV2CustomerValidationException;
use Rateb\App\Pos\Domain\V2\Exceptions\PosV2ForbiddenException;
use Rateb\App\Pos\Domain\V2\Exceptions\PosV2RegisterBootstrapValidationException;
use Rateb\App\Pos\Domain\V2\Exceptions\PosV2UnauthorizedException;
use RuntimeException;
use Throwable;

/** Maps customer exceptions to OpenAPI-aligned JSON responses (T10). */
final class PosV2CustomerExceptionHandler
{
    public function __construct(
        private readonly PosV2ResponseFactory $responses,
        private readonly PosV2BootstrapExceptionHandler $bootstrapHandler = new PosV2BootstrapExceptionHandler(
            new PosV2ResponseFactory(),
        ),
        private readonly PosV2CartExceptionHandler $cartHandler = new PosV2CartExceptionHandler(
            new PosV2ResponseFactory(),
        ),
    ) {
    }

    public function handle(Throwable $throwable): PosV2JsonResponse
    {
        if ($throwable instanceof PosV2CustomerValidationException) {
            return $this->responses->customerError(
                $throwable->errorCode,
                $throwable->getMessage(),
                422,
            );
        }

        if ($throwable instanceof PosV2CustomerNotFoundException) {
            return $this->responses->customerError(
                $throwable->errorCode,
                $throwable->getMessage(),
                404,
            );
        }

        if ($throwable instanceof PosV2ForbiddenException) {
            return $this->responses->customerError(
                'FORBIDDEN',
                $throwable->getMessage() !== '' ? $throwable->getMessage() : 'Forbidden',
                403,
            );
        }

        if ($throwable instanceof PosV2UnauthorizedException) {
            return $this->responses->customerError(
                'UNAUTHORIZED',
                $throwable->getMessage() !== '' ? $throwable->getMessage() : 'Unauthorized',
                401,
            );
        }

        if ($throwable instanceof PosV2RegisterBootstrapValidationException) {
            return $this->bootstrapHandler->handle($throwable);
        }

        if ($throwable instanceof RuntimeException) {
            return $this->responses->customerError(
                'REGISTER_CONTEXT_INVALID',
                $throwable->getMessage(),
                422,
            );
        }

        return $this->cartHandler->handle($throwable);
    }
}
