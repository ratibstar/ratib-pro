<?php
declare(strict_types=1);

namespace Rateb\App\Pos\Services\Drivers;

use Rateb\App\Pos\Contracts\PosScannerInterface;

final class NullPosScanner implements PosScannerInterface
{
    public function readBarcode(): ?string
    {
        return null;
    }

    public function deviceId(): string
    {
        return 'null-scanner';
    }
}
