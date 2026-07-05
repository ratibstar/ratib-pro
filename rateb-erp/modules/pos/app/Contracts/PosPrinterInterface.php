<?php
declare(strict_types=1);

namespace Rateb\App\Pos\Contracts;

interface PosPrinterInterface
{
    /** @param array<string, mixed> $payload */
    public function printReceipt(array $payload): bool;

    public function deviceId(): string;
}
