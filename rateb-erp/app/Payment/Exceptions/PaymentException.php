<?php
declare(strict_types=1);

namespace Rateb\App\Payment\Exceptions;

use RuntimeException;

final class PaymentException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $errorCode = 'payment_error',
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
