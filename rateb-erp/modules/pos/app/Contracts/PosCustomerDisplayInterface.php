<?php
declare(strict_types=1);

namespace Rateb\App\Pos\Contracts;

interface PosCustomerDisplayInterface
{
    /** @param array<string, mixed> $lines */
    public function show(array $lines): bool;

    public function clear(): bool;

    public function deviceId(): string;
}
