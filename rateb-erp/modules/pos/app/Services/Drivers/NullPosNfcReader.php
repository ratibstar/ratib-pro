<?php
declare(strict_types=1);

namespace Rateb\App\Pos\Services\Drivers;

use Rateb\App\Pos\Contracts\PosNfcInterface;

final class NullPosNfcReader implements PosNfcInterface
{
    public function readToken(): ?string
    {
        return null;
    }

    public function deviceId(): string
    {
        return 'null-nfc';
    }
}
