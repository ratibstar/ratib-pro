<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Repositories\V2\Contracts;

use Rateb\App\Pos\Domain\V2\ValueObjects\PosV2MergedPosSettings;

/** Request-scoped cache for merged POS settings. */
interface PosV2PosSettingsCacheInterface
{
    public function get(string $cacheKey): ?PosV2MergedPosSettings;

    public function set(string $cacheKey, PosV2MergedPosSettings $settings): void;
}
