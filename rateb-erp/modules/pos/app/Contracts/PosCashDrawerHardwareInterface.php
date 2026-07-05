<?php
declare(strict_types=1);

namespace Rateb\App\Pos\Contracts;

interface PosCashDrawerHardwareInterface
{
    public function open(): bool;

    public function deviceId(): string;
}
