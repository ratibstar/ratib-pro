<?php
declare(strict_types=1);

namespace Rateb\App\Pos\Services\Drivers;

use Rateb\App\Pos\Contracts\PosScaleInterface;

final class NullPosScale implements PosScaleInterface
{
    public function readWeight(): ?float
    {
        return null;
    }

    public function deviceId(): string
    {
        return 'null-scale';
    }
}
