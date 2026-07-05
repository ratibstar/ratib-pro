<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Repositories\V2;

use Rateb\App\Pos\Domain\V2\ValueObjects\PosV2MergedPosSettings;
use Rateb\App\Pos\Repositories\V2\Contracts\PosV2PosSettingsCacheInterface;

/** Request-scoped in-memory cache for merged POS settings. */
final class InMemoryPosSettingsCache implements PosV2PosSettingsCacheInterface
{
    /** @var array<string, PosV2MergedPosSettings> */
    private array $store = [];

    public function get(string $cacheKey): ?PosV2MergedPosSettings
    {
        return $this->store[$cacheKey] ?? null;
    }

    public function set(string $cacheKey, PosV2MergedPosSettings $settings): void
    {
        $this->store[$cacheKey] = $settings;
    }
}
