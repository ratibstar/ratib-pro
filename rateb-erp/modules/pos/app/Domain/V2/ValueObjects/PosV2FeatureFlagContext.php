<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Domain\V2\ValueObjects;

/**
 * Scope for feature-flag resolution (company required; branch/terminal optional).
 */
final readonly class PosV2FeatureFlagContext
{
    public function __construct(
        public int $companyId,
        public int $branchId = 0,
        public int $terminalId = 0,
    ) {
        if ($this->companyId < 1) {
            throw new \InvalidArgumentException('companyId must be positive for feature flag resolution.');
        }
    }

    public function cacheKey(): string
    {
        return sprintf('pos_v2_flags:%d:%d:%d', $this->companyId, $this->branchId, $this->terminalId);
    }
}
