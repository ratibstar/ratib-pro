<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Repositories\V2\Contracts;

use Rateb\App\Pos\Domain\V2\ValueObjects\PosV2MergedPosSettings;

/** Loads merged POS settings_json for company + branch scope. */
interface PosV2PosSettingsPortInterface
{
    public function loadMerged(int $companyId, int $branchId): PosV2MergedPosSettings;
}
