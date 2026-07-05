<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Repositories\V2\Contracts;

use Rateb\App\Pos\Domain\V2\ValueObjects\PosV2FeatureFlagLayers;
use Rateb\App\Pos\Domain\V2\ValueObjects\PosV2ResolvedFeatureFlags;

/**
 * Request-scoped cache for feature-flag layers and resolved snapshots.
 */
interface FeatureFlagCacheInterface
{
    public function getLayers(string $cacheKey): ?PosV2FeatureFlagLayers;

    public function setLayers(string $cacheKey, PosV2FeatureFlagLayers $layers): void;

    public function getResolved(string $cacheKey): ?PosV2ResolvedFeatureFlags;

    public function setResolved(string $cacheKey, PosV2ResolvedFeatureFlags $flags): void;

    public function forget(string $cacheKey): void;

    public function flush(): void;
}
