<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Application\V2;

use Rateb\App\Pos\Application\V2\Http\PosV2JsonResponse;
use Rateb\App\Pos\Domain\V2\Cart\Exceptions\PosV2CartException;
use Rateb\App\Pos\Domain\V2\Cart\Exceptions\PosV2CartInvalidQuantityException;
use Rateb\App\Pos\Domain\V2\Cart\Exceptions\PosV2CartLineNotFoundException;
use Rateb\App\Pos\Domain\V2\Cart\Exceptions\PosV2CartProductInactiveException;
use Rateb\App\Pos\Domain\V2\Cart\Exceptions\PosV2CartProductNotFoundException;
use Rateb\App\Pos\Domain\V2\Exceptions\PosV2ForbiddenException;
use Rateb\App\Pos\Domain\V2\Exceptions\PosV2RegisterBootstrapValidationException;
use Rateb\App\Pos\Domain\V2\Exceptions\PosV2UnauthorizedException;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/** Maps cart exceptions to OpenAPI-aligned JSON responses (T09). */
final class PosV2CartExceptionHandler
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
        if ($throwable instanceof PosV2CartInvalidQuantityException) {
            return $this->responses->cartError(
                $throwable->errorCode,
                $throwable->getMessage(),
                422,
            );
        }

        if ($throwable instanceof PosV2CartProductNotFoundException) {
            return $this->responses->cartError(
                $throwable->errorCode,
                $throwable->getMessage(),
                404,
            );
        }

        if ($throwable instanceof PosV2CartProductInactiveException) {
            return $this->responses->cartError(
                $throwable->errorCode,
                $throwable->getMessage(),
                422,
            );
        }

        if ($throwable instanceof PosV2CartLineNotFoundException) {
            return $this->responses->cartError(
                $throwable->errorCode,
                $throwable->getMessage(),
                404,
            );
        }

        if ($throwable instanceof PosV2CartException) {
            return $this->responses->cartError(
                $throwable->errorCode,
                $throwable->getMessage(),
                422,
            );
        }

        if ($throwable instanceof InvalidArgumentException) {
            return $this->responses->cartError(
                'INVALID_REQUEST',
                $throwable->getMessage(),
                422,
            );
        }

        if ($throwable instanceof PosV2ForbiddenException) {
            return $this->responses->cartError(
                'FORBIDDEN',
                $throwable->getMessage() !== '' ? $throwable->getMessage() : 'Forbidden',
                403,
            );
        }

        if ($throwable instanceof PosV2UnauthorizedException) {
            return $this->responses->cartError(
                'UNAUTHORIZED',
                $throwable->getMessage() !== '' ? $throwable->getMessage() : 'Unauthorized',
                401,
            );
        }

        if ($throwable instanceof PosV2RegisterBootstrapValidationException) {
            return $this->bootstrapHandler->handle($throwable);
        }

        if ($throwable instanceof RuntimeException) {
            return $this->responses->cartError(
                'REGISTER_CONTEXT_INVALID',
                $throwable->getMessage(),
                422,
            );
        }

        return $this->responses->cartError(
            'INTERNAL_ERROR',
            'An unexpected error occurred.',
            500,
        );
    }
}
