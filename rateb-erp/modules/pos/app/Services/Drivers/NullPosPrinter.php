<?php
declare(strict_types=1);

namespace Rateb\App\Pos\Services\Drivers;

use Rateb\App\Pos\Contracts\PosPrinterInterface;

final class NullPosPrinter implements PosPrinterInterface
{
    public function printReceipt(array $payload): bool
    {
        return false;
    }

    public function deviceId(): string
    {
        return 'null-printer';
    }
}
