<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Application\V2;

use Rateb\App\Pos\Models\PosSetting;
use Rateb\App\Pos\Models\PosTerminal;
use Rateb\App\Pos\Repositories\V2\FeatureFlagRepository;
use Rateb\App\Pos\Repositories\V2\InMemoryFeatureFlagCache;
use Rateb\App\Pos\Repositories\V2\InMemoryPosSettingsCache;
use Rateb\App\Pos\Repositories\V2\PosSettingsRepository;
use Rateb\App\Pos\Services\V2\PosV2EnvironmentFlagReader;
use Rateb\App\Pos\Services\V2\PosV2FeatureFlagResolver;
use Rateb\App\Pos\Services\V2\PosV2FeatureFlagService;

/**
 * Single request-scoped object graph for POS V2 infrastructure (T07.5).
 */
final class PosV2RequestCompositionRoot
{
    public function __construct(
        public readonly PosV2SharedRepositories $repositories,
        public readonly PosV2SharedServices $services,
    ) {
    }

    public static function create(): self
    {
        $featureFlagCache = new InMemoryFeatureFlagCache();
        $posSettingsCache = new InMemoryPosSettingsCache();
        $posSetting = new PosSetting();
        $posTerminal = new PosTerminal();

        $featureFlagRepository = new FeatureFlagRepository(
            $posSetting,
            $posTerminal,
            $featureFlagCache,
        );

        $posSettingsRepository = new PosSettingsRepository(
            $posSetting,
            $posSettingsCache,
        );

        $featureFlagService = new PosV2FeatureFlagService(
            $featureFlagRepository,
            new PosV2FeatureFlagResolver(
                new PosV2EnvironmentFlagReader(),
            ),
            $featureFlagCache,
        );

        return new self(
            new PosV2SharedRepositories(
                $featureFlagCache,
                $featureFlagRepository,
                $posSettingsCache,
                $posSettingsRepository,
            ),
            new PosV2SharedServices($featureFlagService),
        );
    }
}
