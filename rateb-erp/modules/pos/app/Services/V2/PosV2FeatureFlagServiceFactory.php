<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Services\V2;

use Rateb\App\Pos\Models\PosSetting;
use Rateb\App\Pos\Models\PosTerminal;
use Rateb\App\Pos\Repositories\V2\Contracts\FeatureFlagCacheInterface;
use Rateb\App\Pos\Repositories\V2\FeatureFlagRepository;
use Rateb\App\Pos\Repositories\V2\InMemoryFeatureFlagCache;

/**
 * Wires T01 feature-flag components (route/middleware infrastructure only).
 */
final class PosV2FeatureFlagServiceFactory
{
    private ?FeatureFlagCacheInterface $cache = null;

    public function create(): PosV2FeatureFlagService
    {
        if ($this->cache === null) {
            $this->cache = new InMemoryFeatureFlagCache();
        }

        return new PosV2FeatureFlagService(
            new FeatureFlagRepository(
                new PosSetting(),
                new PosTerminal(),
                $this->cache,
            ),
            new PosV2FeatureFlagResolver(
                new PosV2EnvironmentFlagReader(),
            ),
            $this->cache,
        );
    }
}
