<?php
declare(strict_types=1);

namespace Rateb\App\Services\Push;

/**
 * Provider-agnostic push send result.
 */
final class PushSendResult
{
    public function __construct(
        public readonly bool $ok,
        public readonly string $code = '',
        public readonly string $message = '',
        public readonly bool $invalidToken = false
    ) {
    }

    public static function success(): self
    {
        return new self(true, 'ok', '');
    }

    public static function failure(string $code, string $message, bool $invalidToken = false): self
    {
        return new self(false, $code, $message, $invalidToken);
    }
}
