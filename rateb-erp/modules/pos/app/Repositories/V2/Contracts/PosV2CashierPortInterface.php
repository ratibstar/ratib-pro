<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Repositories\V2\Contracts;

/** Resolves authenticated cashier identity. */
interface PosV2CashierPortInterface
{
    public function userId(): int;

    public function displayName(): string;
}
