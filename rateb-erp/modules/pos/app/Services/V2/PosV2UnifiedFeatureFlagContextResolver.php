<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Services\V2;

use Rateb\App\Pos\Domain\V2\ValueObjects\PosV2FeatureFlagContext;
use Rateb\App\Pos\Repositories\V2\Contracts\PosV2CashierPortInterface;
use Rateb\App\Pos\Repositories\V2\Contracts\PosV2PosContextPortInterface;

/**
 * Single feature-flag context path: register metadata after V1 sync (middleware + bootstrap).
 */
final class PosV2UnifiedFeatureFlagContextResolver
{
    public function __construct(
        private readonly PosV2PosContextPortInterface $posContext,
        private readonly PosV2CashierPortInterface $cashier,
    ) {
    }

    public function resolve(): ?PosV2FeatureFlagContext
    {
        $this->posContext->bootstrapTenant();

        $cashierUserId = $this->cashier->userId();
        $metadata = $this->posContext->getRegisterMetadata();
        $companyId = (int) ($metadata['company_id'] ?? 0);

        if ($companyId > 0 && $cashierUserId > 0) {
            $this->posContext->syncRegisterFromOpenShift($companyId, $cashierUserId);
            $metadata = $this->posContext->getRegisterMetadata();
        }

        if ($companyId < 1) {
            return null;
        }

        return new PosV2FeatureFlagContext(
            companyId: $companyId,
            branchId: (int) ($metadata['branch_id'] ?? 0),
            terminalId: (int) ($metadata['terminal']['id'] ?? 0),
        );
    }
}
