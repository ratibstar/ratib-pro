<?php
declare(strict_types=1);

namespace Rateb\App\Pos\Services\Drivers;

use Rateb\App\Pos\Contracts\PosCustomerDisplayInterface;

final class NullPosCustomerDisplay implements PosCustomerDisplayInterface
{
    public function show(array $lines): bool
    {
        return false;
    }

    public function clear(): bool
    {
        return true;
    }

    public function deviceId(): string
    {
        return 'null-display';
    }
}
