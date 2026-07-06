<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Application\V2;

use Rateb\App\Pos\Application\V2\Http\PosV2JsonResponse;
use Rateb\App\Pos\Domain\V2\Payment\Exceptions\PosV2PaymentValidationException;
use Rateb\App\Pos\Domain\V2\Exceptions\PosV2ForbiddenException;
use Rateb\App\Pos\Domain\V2\Exceptions\PosV2RegisterBootstrapValidationException;
use Rateb\App\Pos\Domain\V2\Exceptions\PosV2UnauthorizedException;
use RuntimeException;
use Throwable;

final class PosV2PaymentExceptionHandler
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
        if ($throwable instanceof PosV2PaymentValidationException) {
            return $this->responses->paymentError(
                $throwable->errorCode,
                $throwable->getMessage(),
                422,
            );
        }

        if ($throwable instanceof PosV2ForbiddenException) {
            return $this->responses->paymentError(
                'FORBIDDEN',
                $throwable->getMessage() !== '' ? $throwable->getMessage() : 'Forbidden',
                403,
            );
        }

        if ($throwable instanceof PosV2UnauthorizedException) {
            return $this->responses->paymentError(
                'UNAUTHORIZED',
                $throwable->getMessage() !== '' ? $throwable->getMessage() : 'Unauthorized',
                401,
            );
        }

        if ($throwable instanceof PosV2RegisterBootstrapValidationException) {
            return $this->bootstrapHandler->handle($throwable);
        }

        if ($throwable instanceof RuntimeException) {
            return $this->responses->paymentError(
                'REGISTER_CONTEXT_INVALID',
                $throwable->getMessage(),
                422,
            );
        }

        return $this->cartHandler->handle($throwable);
    }
}
