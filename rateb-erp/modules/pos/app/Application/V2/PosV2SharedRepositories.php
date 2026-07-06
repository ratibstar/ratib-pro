<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Application\V2;

use Rateb\App\Pos\Repositories\V2\Contracts\FeatureFlagCacheInterface;
use Rateb\App\Pos\Repositories\V2\Contracts\FeatureFlagRepositoryInterface;
use Rateb\App\Pos\Repositories\V2\Contracts\PosV2PosSettingsCacheInterface;
use Rateb\App\Pos\Repositories\V2\Contracts\PosV2PosSettingsPortInterface;

/** Request-scoped repository and cache instances shared across POS V2 (T07.5). */
final class PosV2SharedRepositories
{
    public function __construct(
        public readonly FeatureFlagCacheInterface $featureFlagCache,
        public readonly FeatureFlagRepositoryInterface $featureFlagRepository,
        public readonly PosV2PosSettingsCacheInterface $posSettingsCache,
        public readonly PosV2PosSettingsPortInterface $posSettingsRepository,
    ) {
    }
}
