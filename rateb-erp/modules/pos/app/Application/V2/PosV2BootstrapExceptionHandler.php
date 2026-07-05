<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Application\V2;

use Rateb\App\Pos\Application\V2\Http\PosV2JsonResponse;
use Rateb\App\Pos\Domain\V2\Exceptions\PosV2ForbiddenException;
use Rateb\App\Pos\Domain\V2\Exceptions\PosV2RegisterBootstrapValidationException;
use Rateb\App\Pos\Domain\V2\Exceptions\PosV2UnauthorizedException;
use RuntimeException;
use Throwable;

/** Maps bootstrap exceptions to OpenAPI-aligned JSON responses. */
final class PosV2BootstrapExceptionHandler
{
    public function __construct(
        private readonly PosV2ResponseFactory $responses,
    ) {
    }

    public function handle(Throwable $throwable): PosV2JsonResponse
    {
        if ($throwable instanceof PosV2UnauthorizedException) {
            return $this->responses->bootstrapError(
                'UNAUTHORIZED',
                $throwable->getMessage() !== '' ? $throwable->getMessage() : 'Unauthorized',
                401,
            );
        }

        if ($throwable instanceof PosV2ForbiddenException) {
            return $this->responses->bootstrapError(
                'FORBIDDEN',
                $throwable->getMessage() !== '' ? $throwable->getMessage() : 'Forbidden',
                403,
            );
        }

        if ($throwable instanceof PosV2RegisterBootstrapValidationException) {
            return $this->responses->bootstrapError(
                $throwable->errorCode,
                $throwable->getMessage(),
                422,
            );
        }

        if ($throwable instanceof RuntimeException) {
            return $this->responses->bootstrapError(
                'REGISTER_CONTEXT_INVALID',
                $throwable->getMessage(),
                422,
            );
        }

        return $this->responses->bootstrapError(
            'INTERNAL_ERROR',
            'An unexpected error occurred.',
            500,
        );
    }
}
