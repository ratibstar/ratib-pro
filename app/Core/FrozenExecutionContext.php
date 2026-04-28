<?php
declare(strict_types=1);

namespace App\Core;

final class FrozenExecutionContext
{
    /** @param array<string, mixed> $policy */
    public function __construct(
        public readonly string $mode,
        public readonly array $policy
    ) {
    }
}
