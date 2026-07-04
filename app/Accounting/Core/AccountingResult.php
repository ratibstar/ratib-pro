<?php
declare(strict_types=1);

namespace App\Accounting\Core;

final class AccountingResult
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        public readonly bool $success,
        public readonly ?string $message = null,
        public readonly array $data = [],
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function ok(array $data = [], ?string $message = null): self
    {
        return new self(true, $message, $data);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fail(string $message, array $data = []): self
    {
        return new self(false, $message, $data);
    }
}
