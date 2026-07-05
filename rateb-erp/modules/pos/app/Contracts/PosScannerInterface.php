<?php
declare(strict_types=1);

namespace Rateb\App\Pos\Contracts;

interface PosScannerInterface
{
    public function readBarcode(): ?string;

    public function deviceId(): string;
}
