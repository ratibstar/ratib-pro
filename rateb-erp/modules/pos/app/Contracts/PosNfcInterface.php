<?php
declare(strict_types=1);

namespace Rateb\App\Pos\Contracts;

interface PosNfcInterface
{
    public function readToken(): ?string;

    public function deviceId(): string;
}
