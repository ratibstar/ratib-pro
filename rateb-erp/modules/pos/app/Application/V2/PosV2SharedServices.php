<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Application\V2;

use Rateb\App\Pos\Services\V2\PosV2FeatureFlagService;

/** Request-scoped services built from shared repositories (T07.5). */
final class PosV2SharedServices
{
    public function __construct(
        public readonly PosV2FeatureFlagService $featureFlags,
    ) {
    }
}
