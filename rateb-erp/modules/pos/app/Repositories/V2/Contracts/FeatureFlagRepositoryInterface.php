<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Repositories\V2\Contracts;

use Rateb\App\Pos\Domain\V2\ValueObjects\PosV2FeatureFlagContext;
use Rateb\App\Pos\Domain\V2\ValueObjects\PosV2FeatureFlagLayers;

/**
 * Loads raw V2 configuration layers from rateb_pos_settings and rateb_pos_terminals.
 */
interface FeatureFlagRepositoryInterface
{
    public function loadLayers(PosV2FeatureFlagContext $context): PosV2FeatureFlagLayers;
}
