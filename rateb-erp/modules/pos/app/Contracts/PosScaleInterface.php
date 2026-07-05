<?php
declare(strict_types=1);

namespace Rateb\App\Pos\Contracts;

interface PosScaleInterface
{
    public function readWeight(): ?float;

    public function deviceId(): string;
}
