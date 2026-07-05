<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Repositories\V2\Contracts;

/** Resolves locale, RTL, timezone, and currency for register context. */
interface PosV2LocalePortInterface
{
    public function locale(): string;

    public function isRtl(): bool;

    public function timezone(): string;

    public function currency(): string;
}
