<?php
declare(strict_types=1);

namespace Rateb\App\Pos\Services\Drivers;

use Rateb\App\Pos\Contracts\PosCashDrawerHardwareInterface;

final class NullPosCashDrawerHardware implements PosCashDrawerHardwareInterface
{
    public function open(): bool
    {
        return false;
    }

    public function deviceId(): string
    {
        return 'null-drawer';
    }
}
