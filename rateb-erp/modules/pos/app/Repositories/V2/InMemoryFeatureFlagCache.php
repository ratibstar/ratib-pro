<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Repositories\V2;

use Rateb\App\Pos\Domain\V2\ValueObjects\PosV2FeatureFlagLayers;
use Rateb\App\Pos\Domain\V2\ValueObjects\PosV2ResolvedFeatureFlags;
use Rateb\App\Pos\Repositories\V2\Contracts\FeatureFlagCacheInterface;

/**
 * In-memory cache for feature-flag layers and resolved snapshots (per request / worker).
 */
final class InMemoryFeatureFlagCache implements FeatureFlagCacheInterface
{
    /** @var array<string, PosV2FeatureFlagLayers> */
    private array $layers = [];

    /** @var array<string, PosV2ResolvedFeatureFlags> */
    private array $resolved = [];

    public function getLayers(string $cacheKey): ?PosV2FeatureFlagLayers
    {
        return $this->layers[$cacheKey] ?? null;
    }

    public function setLayers(string $cacheKey, PosV2FeatureFlagLayers $layers): void
    {
        $this->layers[$cacheKey] = $layers;
    }

    public function getResolved(string $cacheKey): ?PosV2ResolvedFeatureFlags
    {
        return $this->resolved[$cacheKey] ?? null;
    }

    public function setResolved(string $cacheKey, PosV2ResolvedFeatureFlags $flags): void
    {
        $this->resolved[$cacheKey] = $flags;
    }

    public function forget(string $cacheKey): void
    {
        unset($this->layers[$cacheKey], $this->resolved[$cacheKey]);
    }

    public function flush(): void
    {
        $this->layers = [];
        $this->resolved = [];
    }
}
