<?php
declare(strict_types=1);

namespace Rateb\App\Pos\Services\Bridge;

/** Barcode lookup bridge — delegates to PosInventoryBridgeService. */
final class PosBarcodeLookupBridgeService
{
    public function __construct(
        private PosInventoryBridgeService $inventory = new PosInventoryBridgeService(),
    ) {
    }

    /** @return array<string, mixed>|null */
    public function lookupInventoryBarcode(
        string $code,
        int $companyId,
        ?int $warehouseId = null,
        ?int $branchId = null,
        ?int $sessionId = null
    ): ?array {
        return $this->inventory->lookupByBarcode($code, $companyId, $warehouseId, $branchId, $sessionId);
    }
}
