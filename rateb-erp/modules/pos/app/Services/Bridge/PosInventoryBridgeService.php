<?php
declare(strict_types=1);

namespace Rateb\App\Pos\Services\Bridge;

use Rateb\App\Core\Database;
use Rateb\App\Core\SessionManager;
use Rateb\App\Core\TenantContext;
use Rateb\App\Models\Inventory;
use Rateb\App\Models\InventoryBatch;
use Rateb\App\Pos\Services\PosInventoryReservationService;
use Rateb\App\Pos\Services\PosSellPriceService;
use Rateb\App\Pos\Support\PosFkValidator;
use Rateb\App\Services\DocumentBarcodeService;
use Rateb\App\Services\InventoryWorkflowService;
use Rateb\App\Services\StockMovementService;

/**
 * Inventory bridge — read/validate/reserve only (Phase 4). Stock posting stays in Phase 5+.
 */
final class PosInventoryBridgeService
{
    public function __construct(
        private PosInventoryReservationService $reservations = new PosInventoryReservationService(),
        private PosSellPriceService $sellPrices = new PosSellPriceService(),
        private PosAuditBridgeService $audit = new PosAuditBridgeService(),
    ) {
    }

    /** @param array<string, mixed> $data */
    public function recordMovement(array $data): int
    {
        return 0;
    }

    public function stockMovementService(): StockMovementService
    {
        return new StockMovementService();
    }

    public function workflowService(): InventoryWorkflowService
    {
        return new InventoryWorkflowService();
    }

    private function cogsBridge(): PosCogsBridgeService
    {
        return new PosCogsBridgeService();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function searchProducts(
        string $query,
        int $companyId,
        ?int $warehouseId = null,
        ?int $branchId = null,
        ?int $sessionId = null,
        int $limit = 24
    ): array {
        $term = trim($query);
        if ($term === '' || $companyId < 1) {
            return [];
        }
        TenantContext::setCompanyId($companyId);
        $safeLimit = max(1, min(50, $limit));
        $filters = [];
        if ($warehouseId !== null && $warehouseId > 0) {
            $filters['warehouse_id'] = $warehouseId;
        }
        $rows = (new Inventory())->all($safeLimit, 0, $filters, $term);
        if ($rows === [] && $warehouseId !== null && $warehouseId > 0) {
            $rows = (new Inventory())->all($safeLimit, 0, [], $term);
        }
        $out = [];
        foreach ($rows as $row) {
            if (!$this->itemMatchesScope($row, $warehouseId, $branchId)) {
                continue;
            }
            $out[] = $this->enrichProduct($row, $companyId, $warehouseId, $sessionId, null, $branchId);
        }
        return $out;
    }

    /**
     * Batch catalog enrichment — sell price and availability without per-row getProduct().
     *
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    public function enrichCatalogRows(
        array $rows,
        int $companyId,
        ?int $warehouseId = null,
        ?int $branchId = null,
        ?int $sessionId = null,
    ): array {
        if ($rows === [] || $companyId < 1) {
            return $rows;
        }

        TenantContext::setCompanyId($companyId);
        $inventoryIds = [];
        foreach ($rows as $row) {
            $inventoryId = (int) ($row['id'] ?? 0);
            if ($inventoryId > 0) {
                $inventoryIds[] = $inventoryId;
            }
        }
        if ($inventoryIds === []) {
            return $rows;
        }

        $reservedMap = $this->reservations->reservedQuantitiesForIds($companyId, $inventoryIds, $sessionId);
        $out = [];
        foreach ($rows as $row) {
            if (!$this->itemMatchesScope($row, $warehouseId, $branchId)) {
                continue;
            }

            $inventoryId = (int) ($row['id'] ?? 0);
            if ($inventoryId < 1) {
                continue;
            }

            $onHand = (float) ($row['quantity'] ?? 0);
            $reservedOther = (float) ($reservedMap[$inventoryId] ?? 0);
            $available = max(0, round($onHand - $reservedOther, 3));
            $priceBranch = $branchId ?? (int) ($row['branch_id'] ?? 0);
            $resolved = $this->sellPrices->resolveLine(
                ['product_id' => $inventoryId, 'quantity' => 1],
                $companyId,
                $priceBranch > 0 ? $priceBranch : 0,
                null,
            );

            $out[] = array_merge($row, [
                'unit_price' => (float) ($resolved['unit_price'] ?? 0),
                'price_source' => (string) ($resolved['price_source'] ?? 'default'),
                'availability' => [
                    'on_hand' => $onHand,
                    'reserved_other' => $reservedOther,
                    'available' => $available,
                    'can_add' => $available > 0,
                ],
            ]);
        }

        return $out;
    }

    /** @return array<string, mixed>|null */
    public function lookupByBarcode(
        string $code,
        int $companyId,
        ?int $warehouseId = null,
        ?int $branchId = null,
        ?int $sessionId = null
    ): ?array {
        $term = trim($code);
        if ($term === '' || $companyId < 1) {
            return null;
        }
        TenantContext::setCompanyId($companyId);

        $serialHit = $this->findBySerialNo($term, $companyId, $warehouseId, $branchId);
        if ($serialHit !== null) {
            return $this->enrichProduct($serialHit, $companyId, $warehouseId, $sessionId, $term, $branchId);
        }

        $doc = (new DocumentBarcodeService())->resolveForAuthenticatedUser($term);
        if (is_array($doc) && ($doc['type'] ?? '') === 'inventory' && !empty($doc['record_id'])) {
            $row = (new Inventory())->find((int) $doc['record_id']);
            if ($row && $this->itemMatchesScope($row, $warehouseId, $branchId)) {
                return $this->enrichProduct($row, $companyId, $warehouseId, $sessionId, null, $branchId);
            }
        }

        $filters = [];
        if ($warehouseId !== null && $warehouseId > 0) {
            $filters['warehouse_id'] = $warehouseId;
        }
        $rows = (new Inventory())->all(8, 0, $filters, $term);
        foreach ($rows as $row) {
            if (!$this->itemMatchesScope($row, $warehouseId, $branchId)) {
                continue;
            }
            $barcode = trim((string) ($row['barcode'] ?? ''));
            $sku = trim((string) ($row['sku'] ?? ''));
            $itemCode = trim((string) ($row['item_code'] ?? ''));
            if ($barcode === $term || $sku === $term || $itemCode === $term) {
                return $this->enrichProduct($row, $companyId, $warehouseId, $sessionId, null, $branchId);
            }
        }
        if ($rows !== []) {
            $first = $rows[0];
            if ($this->itemMatchesScope($first, $warehouseId, $branchId)) {
                return $this->enrichProduct($first, $companyId, $warehouseId, $sessionId, null, $branchId);
            }
        }
        return null;
    }

    /** @return array<string, mixed>|null */
    public function getProduct(
        int $inventoryId,
        int $companyId,
        ?int $warehouseId = null,
        ?int $branchId = null,
        ?int $sessionId = null
    ): ?array {
        if ($inventoryId < 1 || $companyId < 1) {
            return null;
        }
        try {
            $row = $this->lockInventoryRow($inventoryId, $companyId, false);
        } catch (\Throwable $e) {
            return null;
        }
        if (!$this->itemMatchesScope($row, $warehouseId, $branchId)) {
            $resolved = $this->resolveInventoryInScope($row, $companyId, $warehouseId, $branchId);
            if ($resolved === null) {
                return null;
            }
            $row = $resolved;
        }
        return $this->enrichProduct($row, $companyId, $warehouseId, $sessionId, null, $branchId);
    }

    /** @return array<string, mixed> */
    public function availabilitySnapshot(
        int $inventoryId,
        int $companyId,
        ?int $warehouseId = null,
        ?int $branchId = null,
        ?int $sessionId = null
    ): array {
        $product = $this->getProduct($inventoryId, $companyId, $warehouseId, $branchId, $sessionId);
        if ($product === null) {
            return ['ok' => false, 'error' => __('no_records')];
        }
        return ['ok' => true, 'availability' => $product['availability'] ?? []];
    }

    /**
     * FEFO allocation preview — read-only mirror of InventoryWorkflowService::consumeBatches order.
     *
     * @return array{method: string, allocations: array<int, array<string, mixed>>, unallocated: float}
     */
    public function previewFefoAllocation(int $inventoryId, float $quantity, int $companyId): array
    {
        if ($inventoryId < 1 || $quantity <= 0 || $companyId < 1) {
            return ['method' => 'fefo', 'allocations' => [], 'unallocated' => max(0, $quantity)];
        }
        if (!$this->tableExists('rateb_inventory_batches')) {
            return ['method' => 'fefo', 'allocations' => [], 'unallocated' => max(0, $quantity)];
        }
        $db = Database::connection();
        $invStmt = $db->prepare(
            'SELECT id FROM rateb_inventory WHERE id = :id AND company_id = :cid LIMIT 1'
        );
        $invStmt->execute(['id' => $inventoryId, 'cid' => $companyId]);
        if (!$invStmt->fetchColumn()) {
            throw new \RuntimeException(__('pos_product_not_found'));
        }
        $batches = (new InventoryBatch())->query(
            'SELECT b.id, b.batch_no, b.quantity, b.expiry_date, b.production_date, b.warehouse_id
             FROM rateb_inventory_batches b
             INNER JOIN rateb_inventory i ON i.id = b.inventory_id AND i.company_id = :cid
             WHERE b.inventory_id = :iid AND b.quantity > 0
               AND (b.expiry_date IS NULL OR b.expiry_date >= CURDATE())
             ORDER BY b.expiry_date ASC, b.id ASC',
            ['iid' => $inventoryId, 'cid' => $companyId]
        );
        $remaining = $quantity;
        $allocations = [];
        foreach ($batches as $batch) {
            if ($remaining <= 0) {
                break;
            }
            $batchQty = (float) ($batch['quantity'] ?? 0);
            $take = min($batchQty, $remaining);
            if ($take <= 0) {
                continue;
            }
            $allocations[] = [
                'batch_id' => (int) ($batch['id'] ?? 0),
                'batch_no' => (string) ($batch['batch_no'] ?? ''),
                'quantity' => round($take, 3),
                'expiry_date' => (string) ($batch['expiry_date'] ?? ''),
                'production_date' => (string) ($batch['production_date'] ?? ''),
            ];
            $remaining -= $take;
        }
        return [
            'method' => 'fefo',
            'allocations' => $allocations,
            'unallocated' => round(max(0, $remaining), 3),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function listAvailableSerials(
        int $inventoryId,
        int $companyId,
        ?int $warehouseId = null,
        ?int $branchId = null,
        int $limit = 100
    ): array {
        if ($inventoryId < 1 || $companyId < 1 || !$this->tableExists('rateb_inventory_serials')) {
            return [];
        }
        $sql = 'SELECT id, serial_no, warehouse_id, status FROM rateb_inventory_serials
                WHERE company_id = :cid AND inventory_id = :iid AND status = :st';
        $params = ['cid' => $companyId, 'iid' => $inventoryId, 'st' => 'available'];
        if ($warehouseId !== null && $warehouseId > 0) {
            $sql .= ' AND (warehouse_id IS NULL OR warehouse_id = :wh)';
            $params['wh'] = $warehouseId;
        }
        if ($branchId !== null && $branchId > 0) {
            $sql .= ' AND (branch_id IS NULL OR branch_id = :bid)';
            $params['bid'] = $branchId;
        }
        $sql .= ' ORDER BY serial_no ASC LIMIT ' . max(1, min(200, $limit));
        $db = Database::connection();
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'id' => (int) ($row['id'] ?? 0),
                'serial_no' => (string) ($row['serial_no'] ?? ''),
                'warehouse_id' => (int) ($row['warehouse_id'] ?? 0),
                'read_only' => true,
            ];
        }
        return $out;
    }

    /**
     * @param array<int, array<string, mixed>> $cartLines
     * @return array{ok: bool, error?: string, available?: float, batch_preview?: array<string, mixed>, requires_serial?: bool}
     */
    public function validateCartLine(
        int $inventoryId,
        float $requestedQty,
        int $companyId,
        ?int $warehouseId,
        ?int $branchId,
        ?int $sessionId,
        array $cartLines,
        ?string $lineId = null,
        ?string $serialNo = null,
        bool $withinTransaction = false
    ): array {
        if ($inventoryId < 1 || $companyId < 1 || $requestedQty <= 0) {
            return ['ok' => false, 'error' => __('invalid_request')];
        }

        $serialNo = $serialNo !== null ? trim($serialNo) : '';
        $serials = $this->listAvailableSerials($inventoryId, $companyId, $warehouseId, $branchId, 1);
        $requiresSerial = $serials !== [] || ($serialNo !== '');

        if ($requiresSerial && $serialNo === '') {
            return ['ok' => false, 'error' => __('pos_serial_required'), 'requires_serial' => true];
        }
        if ($requiresSerial && $requestedQty > 1) {
            return ['ok' => false, 'error' => __('pos_serial_qty_one'), 'requires_serial' => true];
        }
        if ($serialNo !== '') {
            $serialCheck = $this->assertSerialAvailable($serialNo, $inventoryId, $companyId, $cartLines, $lineId);
            if (!$serialCheck['ok']) {
                return $serialCheck;
            }
        }

        $db = Database::connection();
        $startedHere = false;
        if (!$withinTransaction) {
            $db->beginTransaction();
            $startedHere = true;
        } elseif (!$db->inTransaction()) {
            throw new \RuntimeException(__('invalid_request'));
        }
        try {
            $row = $this->lockInventoryRow($inventoryId, $companyId, true);
            if (!$this->itemMatchesScope($row, $warehouseId, $branchId)) {
                throw new \RuntimeException(__('access_denied'));
            }

            $onHand = (float) ($row['quantity'] ?? 0);
            $reservedOther = $this->reservations->reservedQuantity($companyId, $inventoryId, $sessionId);
            $cartQtyOtherLines = $this->cartQtyForProduct($cartLines, $inventoryId, $lineId);
            $available = max(0, round($onHand - $reservedOther - $cartQtyOtherLines, 3));

            if ($requestedQty > $available) {
                if ($startedHere && $db->inTransaction()) {
                    $db->rollBack();
                }
                return [
                    'ok' => false,
                    'error' => __('pos_insufficient_stock'),
                    'available' => $available,
                    'on_hand' => $onHand,
                    'reserved_other' => $reservedOther,
                ];
            }

            $batchPreview = $this->previewFefoAllocation($inventoryId, $requestedQty, $companyId);
            if ($startedHere) {
                $db->commit();
            }

            return [
                'ok' => true,
                'available' => $available,
                'on_hand' => $onHand,
                'reserved_other' => $reservedOther,
                'batch_preview' => $batchPreview,
                'requires_serial' => $requiresSerial,
                'has_batches' => ($batchPreview['allocations'] ?? []) !== [],
            ];
        } catch (\Throwable $e) {
            if ($startedHere && $db->inTransaction()) {
                $db->rollBack();
            }
            return ['ok' => false, 'error' => $e->getMessage() !== '' ? $e->getMessage() : __('invalid_request')];
        }
    }

    /** @param array<int, array<string, mixed>> $cartLines */
    public function validateAndSyncCart(
        array $cartLines,
        int $companyId,
        int $branchId,
        ?int $warehouseId,
        ?int $sessionId,
        bool $withinTransaction = false
    ): array {
        $normalized = [];
        $errors = [];
        $seenSerials = [];

        foreach ($cartLines as $line) {
            if (!is_array($line)) {
                continue;
            }
            $invId = (int) ($line['product_id'] ?? 0);
            $qty = (float) ($line['quantity'] ?? 0);
            $lineId = (string) ($line['id'] ?? '');
            $serialNo = trim((string) ($line['serial_no'] ?? ''));
            if ($invId < 1 || $qty <= 0) {
                continue;
            }
            if ($serialNo !== '') {
                if (isset($seenSerials[$serialNo])) {
                    $errors[] = __('pos_serial_duplicate');
                    continue;
                }
                $seenSerials[$serialNo] = true;
            }

            $check = $this->validateCartLine(
                $invId,
                $qty,
                $companyId,
                $warehouseId,
                $branchId,
                $sessionId,
                $normalized,
                $lineId !== '' ? $lineId : null,
                $serialNo !== '' ? $serialNo : null,
                $withinTransaction
            );
            if (!$check['ok']) {
                $errors[] = (string) ($check['error'] ?? __('invalid_request'));
                continue;
            }

            $product = $this->getProduct($invId, $companyId, $warehouseId, $branchId, $sessionId);
            $normalized[] = array_merge($line, [
                'product_id' => $invId,
                'quantity' => $qty,
                'serial_no' => $serialNo !== '' ? $serialNo : null,
                'available_qty' => (float) ($check['available'] ?? 0),
                'batch_preview' => $check['batch_preview'] ?? [],
                'requires_serial' => (bool) ($check['requires_serial'] ?? false),
                'has_batches' => (bool) ($check['has_batches'] ?? false),
                'item_code' => (string) ($line['item_code'] ?? $product['item_code'] ?? ''),
                'item_name' => (string) ($line['item_name'] ?? $product['item_name'] ?? ''),
                'unit_price' => (float) ($line['unit_price'] ?? $product['unit_price'] ?? 0),
                'unit' => (string) ($line['unit'] ?? $product['unit'] ?? ''),
                'line_total' => round($qty * (float) ($line['unit_price'] ?? $product['unit_price'] ?? 0), 2),
            ]);
        }

        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors, 'lines' => $normalized];
        }

        if ($normalized === []) {
            if ($sessionId !== null && $sessionId > 0) {
                $this->reservations->releaseSession($companyId, $sessionId);
            }
            return ['ok' => true, 'lines' => []];
        }

        if ($sessionId !== null && $sessionId > 0 && $branchId > 0 && !$withinTransaction) {
            $this->reservations->syncSessionCart($companyId, $branchId, $sessionId, $normalized);
        }

        return ['ok' => true, 'lines' => $normalized];
    }

    /**
     * Final checkout revalidation — locks inventory rows and serials.
     * When $withinTransaction is true, locks are held until the caller commits or rolls back.
     *
     * @param array<int, array<string, mixed>> $cartLines
     * @return array{ok: bool, error?: string, lines?: array<int, array<string, mixed>>}
     */
    public function revalidateForCheckout(
        array $cartLines,
        int $companyId,
        ?int $warehouseId,
        int $branchId,
        ?int $sessionId,
        bool $withinTransaction = false
    ): array {
        $result = $this->validateAndSyncCart($cartLines, $companyId, $branchId, $warehouseId, $sessionId, $withinTransaction);
        if (!$result['ok']) {
            return [
                'ok' => false,
                'error' => is_array($result['errors'] ?? null)
                    ? implode('; ', $result['errors'])
                    : __('pos_insufficient_stock'),
            ];
        }
        $lines = $result['lines'] ?? [];
        if ($lines === []) {
            return ['ok' => false, 'error' => __('pos_cart_empty')];
        }

        $db = Database::connection();
        $startedHere = false;
        if (!$withinTransaction) {
            $db->beginTransaction();
            $startedHere = true;
        } elseif (!$db->inTransaction()) {
            throw new \RuntimeException(__('invalid_request'));
        }

        try {
            foreach ($lines as $idx => $line) {
                $invId = (int) ($line['product_id'] ?? 0);
                if ($invId < 1) {
                    throw new \RuntimeException(__('invalid_request'));
                }
                $locked = $this->lockInventoryRow($invId, $companyId, true);
                $requestedQty = (float) ($line['quantity'] ?? 0);
                $onHand = (float) ($locked['quantity'] ?? 0);
                $reservedOther = $this->reservations->reservedQuantity($companyId, $invId, $sessionId);
                $available = max(0, round($onHand - $reservedOther, 3));
                if ($available + 0.0001 < $requestedQty) {
                    throw new \RuntimeException(__('pos_insufficient_stock'));
                }

                $serialNo = trim((string) ($line['serial_no'] ?? ''));
                if ($serialNo !== '') {
                    $this->lockSerialForCheckout($serialNo, $invId, $companyId);
                    $lines[$idx]['batch_allocations'] = [];
                } else {
                    $batchLock = $this->workflowService()->lockFefoBatchAllocations(
                        $invId,
                        $requestedQty,
                        $companyId
                    );
                    if ($batchLock['has_batches'] && (float) ($batchLock['unallocated'] ?? 0) > 0.0001) {
                        throw new \RuntimeException(__('pos_insufficient_stock'));
                    }
                    $lines[$idx]['batch_allocations'] = $this->cogsBridge()->enrichBatchAllocations(
                        $invId,
                        $companyId,
                        $batchLock['allocations'] ?? []
                    );
                    $this->workflowService()->assertBatchAllocationsNotExpired($lines[$idx]['batch_allocations']);
                    $lines[$idx]['batch_preview'] = [
                        'method' => 'fefo',
                        'allocations' => $batchLock['allocations'] ?? [],
                        'unallocated' => (float) ($batchLock['unallocated'] ?? 0),
                    ];
                    $lines[$idx]['has_batches'] = (bool) ($batchLock['has_batches'] ?? false);
                }
            }
            if ($startedHere) {
                $db->commit();
            }
        } catch (\Throwable $e) {
            if ($startedHere && $db->inTransaction()) {
                $db->rollBack();
            }
            return ['ok' => false, 'error' => $e->getMessage()];
        }
        return ['ok' => true, 'lines' => $lines];
    }

    /**
     * Post stock OUT inside caller transaction — atomic with POS order commit.
     *
     * @param array<int, array<string, mixed>> $lines
     */
    public function postSaleForOrderInTransaction(
        int $orderId,
        string $orderNo,
        int $companyId,
        ?int $warehouseId,
        array $lines
    ): void {
        if ($orderId < 1 || $companyId < 1 || $lines === []) {
            throw new \RuntimeException(__('invalid_request'));
        }
        $db = Database::connection();
        if (!$db->inTransaction()) {
            throw new \RuntimeException(__('invalid_request'));
        }
        TenantContext::setCompanyId($companyId);
        foreach ($lines as $line) {
            $invId = (int) ($line['product_id'] ?? 0);
            $qty = (float) ($line['quantity'] ?? 0);
            if ($invId < 1 || $qty <= 0) {
                continue;
            }
            $whId = $warehouseId ?? (int) ($line['warehouse_id'] ?? 0);
            $orderLineId = (int) ($line['order_line_id'] ?? 0);
            $batchAllocations = is_array($line['batch_allocations'] ?? null) ? $line['batch_allocations'] : null;
            $movementId = $this->stockMovementService()->recordWithinTransaction([
                'inventory_id' => $invId,
                'warehouse_id' => $whId > 0 ? $whId : null,
                'movement_type' => 'out',
                'quantity' => $qty,
                'reference_type' => 'pos_order',
                'reference_id' => $orderId,
                'notes' => 'POS sale ' . $orderNo,
            ], $batchAllocations);
            if (is_array($batchAllocations) && $batchAllocations !== []) {
                $this->recordBatchLedgerOut(
                    $companyId,
                    $orderId,
                    $orderLineId > 0 ? $orderLineId : null,
                    $movementId,
                    $batchAllocations,
                    'pos_order',
                    $orderId
                );
            }
            $serialNo = trim((string) ($line['serial_no'] ?? ''));
            if ($serialNo !== '') {
                $this->markSerialSold($serialNo, $invId, $companyId, $orderId);
            }
        }
    }

    /**
     * Post stock IN for returns inside caller transaction.
     *
     * @param array<int, array<string, mixed>> $lines
     */
    public function postReturnForOrderInTransaction(
        int $orderId,
        string $orderNo,
        int $companyId,
        ?int $warehouseId,
        array $lines
    ): void {
        if ($orderId < 1 || $companyId < 1 || $lines === []) {
            throw new \RuntimeException(__('invalid_request'));
        }
        $db = Database::connection();
        if (!$db->inTransaction()) {
            throw new \RuntimeException(__('invalid_request'));
        }
        TenantContext::setCompanyId($companyId);
        foreach ($lines as $line) {
            $invId = (int) ($line['inventory_id'] ?? $line['product_id'] ?? 0);
            $qty = (float) ($line['quantity'] ?? 0);
            if ($invId < 1 || $qty <= 0) {
                continue;
            }
            $whId = $warehouseId ?? (int) ($line['warehouse_id'] ?? 0);
            $orderLineId = (int) ($line['order_line_id'] ?? 0);
            $originalLineId = (int) ($line['original_line_id'] ?? 0);
            $batchRestorations = is_array($line['batch_restorations'] ?? null)
                ? $line['batch_restorations']
                : [];
            if ($batchRestorations !== []) {
                $this->workflowService()->lockBatchIdsForUpdate($batchRestorations);
            }
            $movementId = $this->stockMovementService()->recordWithinTransaction([
                'inventory_id' => $invId,
                'warehouse_id' => $whId > 0 ? $whId : null,
                'movement_type' => 'in',
                'quantity' => $qty,
                'reference_type' => 'pos_return',
                'reference_id' => $orderId,
                'notes' => 'POS return ' . $orderNo,
                'batch_restorations' => $batchRestorations !== [] ? $batchRestorations : null,
            ]);
            if ($batchRestorations !== []) {
                $this->recordBatchLedgerIn(
                    $companyId,
                    $orderId,
                    $orderLineId > 0 ? $orderLineId : null,
                    $originalLineId > 0 ? $originalLineId : null,
                    $movementId,
                    $batchRestorations,
                    'pos_return',
                    $orderId
                );
            }
            $serialNo = trim((string) ($line['serial_no'] ?? ''));
            if ($serialNo !== '') {
                $this->restoreSerialOnReturn($serialNo, $invId, $companyId, $orderId, $originalLineId);
            }
        }
    }

    /**
     * @deprecated Use postSaleForOrderInTransaction within checkout transaction.
     * @param array<int, array<string, mixed>> $lines
     */
    public function postSaleForOrder(
        int $orderId,
        string $orderNo,
        int $companyId,
        ?int $warehouseId,
        array $lines
    ): void {
        if ($orderId < 1 || $companyId < 1 || $lines === []) {
            throw new \RuntimeException(__('invalid_request'));
        }
        TenantContext::setCompanyId($companyId);
        foreach ($lines as $line) {
            $invId = (int) ($line['product_id'] ?? 0);
            $qty = (float) ($line['quantity'] ?? 0);
            if ($invId < 1 || $qty <= 0) {
                continue;
            }
            $whId = $warehouseId ?? (int) ($line['warehouse_id'] ?? 0);
            $this->stockMovementService()->record([
                'inventory_id' => $invId,
                'warehouse_id' => $whId > 0 ? $whId : null,
                'movement_type' => 'out',
                'quantity' => $qty,
                'reference_type' => 'pos_order',
                'reference_id' => $orderId,
                'notes' => 'POS sale ' . $orderNo,
            ]);
            $serialNo = trim((string) ($line['serial_no'] ?? ''));
            if ($serialNo !== '') {
                $this->markSerialSold($serialNo, $invId, $companyId, $orderId);
            }
        }
    }

    private function lockSerialForCheckout(string $serialNo, int $inventoryId, int $companyId): void
    {
        if (!$this->tableExists('rateb_inventory_serials')) {
            return;
        }
        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT id FROM rateb_inventory_serials
             WHERE company_id = :cid AND inventory_id = :iid AND serial_no = :sn AND status = :st
             LIMIT 1 FOR UPDATE'
        );
        $stmt->execute([
            'cid' => $companyId,
            'iid' => $inventoryId,
            'sn' => $serialNo,
            'st' => 'available',
        ]);
        if (!$stmt->fetchColumn()) {
            throw new \RuntimeException(__('pos_serial_unavailable'));
        }
    }

    private function markSerialSold(string $serialNo, int $inventoryId, int $companyId, int $orderId): void
    {
        if (!$this->tableExists('rateb_inventory_serials')) {
            return;
        }
        $db = Database::connection();
        $row = $this->lockSerialRow($serialNo, $inventoryId, $companyId, 'available');
        $serialId = (int) ($row['id'] ?? 0);
        $stmt = $db->prepare(
            'UPDATE rateb_inventory_serials SET status = :sold, updated_at = NOW()
             WHERE id = :id AND status = :avail'
        );
        $stmt->execute(['sold' => 'sold', 'id' => $serialId, 'avail' => 'available']);
        if ($stmt->rowCount() < 1) {
            throw new \RuntimeException(__('pos_serial_unavailable'));
        }
        $this->recordSerialHistory(
            $companyId,
            $serialId,
            $serialNo,
            $inventoryId,
            'pos_sale',
            'available',
            'sold',
            'pos_order',
            $orderId
        );
        $this->audit->log('pos_serial_sold', 'inventory_serial', $serialId, [
            'serial_no' => $serialNo,
            'inventory_id' => $inventoryId,
            'order_id' => $orderId,
            'company_id' => $companyId,
        ]);
    }

    private function restoreSerialOnReturn(
        string $serialNo,
        int $inventoryId,
        int $companyId,
        int $orderId,
        int $originalLineId = 0
    ): void {
        if (!$this->tableExists('rateb_inventory_serials')) {
            return;
        }
        $db = Database::connection();
        $row = $this->lockSerialRow($serialNo, $inventoryId, $companyId, 'sold');
        $serialId = (int) ($row['id'] ?? 0);
        $stmt = $db->prepare(
            'UPDATE rateb_inventory_serials SET status = :avail, updated_at = NOW()
             WHERE id = :id AND status = :sold'
        );
        $stmt->execute(['avail' => 'available', 'id' => $serialId, 'sold' => 'sold']);
        if ($stmt->rowCount() < 1) {
            throw new \RuntimeException(__('pos_serial_unavailable'));
        }
        $this->recordSerialHistory(
            $companyId,
            $serialId,
            $serialNo,
            $inventoryId,
            'pos_return',
            'sold',
            'available',
            'pos_return',
            $orderId
        );
        $this->audit->log('pos_serial_returned', 'inventory_serial', $serialId, [
            'serial_no' => $serialNo,
            'inventory_id' => $inventoryId,
            'order_id' => $orderId,
            'original_line_id' => $originalLineId,
            'company_id' => $companyId,
        ]);
    }

    /** @return array<string, mixed> */
    private function lockSerialRow(string $serialNo, int $inventoryId, int $companyId, string $status): array
    {
        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT id, serial_no, status FROM rateb_inventory_serials
             WHERE company_id = :cid AND inventory_id = :iid AND serial_no = :sn AND status = :st
             LIMIT 1 FOR UPDATE'
        );
        $stmt->execute([
            'cid' => $companyId,
            'iid' => $inventoryId,
            'sn' => $serialNo,
            'st' => $status,
        ]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            throw new \RuntimeException(__('pos_serial_unavailable'));
        }
        return $row;
    }

    /**
     * @param array<int, array<string, mixed>> $allocations
     */
    private function recordBatchLedgerOut(
        int $companyId,
        int $orderId,
        ?int $orderLineId,
        int $movementId,
        array $allocations,
        string $referenceType,
        int $referenceId
    ): void {
        if (!$this->tableExists('rateb_pos_batch_ledger')) {
            return;
        }
        $userId = SessionManager::get('rateb_user_id');
        $db = Database::connection();
        $stmt = $db->prepare(
            'INSERT INTO rateb_pos_batch_ledger
             (company_id, order_id, order_line_id, movement_id, batch_id, direction, quantity, reference_type, reference_id, created_by)
             VALUES (:cid, :oid, :lid, :mid, :bid, :dir, :qty, :rt, :rid, :uid)'
        );
        foreach ($allocations as $alloc) {
            $batchId = (int) ($alloc['batch_id'] ?? 0);
            $qty = (float) ($alloc['quantity'] ?? 0);
            if ($batchId < 1 || $qty <= 0) {
                continue;
            }
            $stmt->execute([
                'cid' => $companyId,
                'oid' => $orderId,
                'lid' => $orderLineId,
                'mid' => $movementId > 0 ? $movementId : null,
                'bid' => $batchId,
                'dir' => 'out',
                'qty' => $qty,
                'rt' => $referenceType,
                'rid' => $referenceId,
                'uid' => $userId ? (int) $userId : null,
            ]);
        }
        $this->audit->log('pos_batch_out', 'pos_order', $orderId, [
            'order_line_id' => $orderLineId,
            'allocations' => $allocations,
            'company_id' => $companyId,
        ]);
    }

    /**
     * @param array<int, array<string, mixed>> $restorations
     */
    private function recordBatchLedgerIn(
        int $companyId,
        int $orderId,
        ?int $orderLineId,
        ?int $originalLineId,
        int $movementId,
        array $restorations,
        string $referenceType,
        int $referenceId
    ): void {
        if (!$this->tableExists('rateb_pos_batch_ledger')) {
            return;
        }
        $userId = SessionManager::get('rateb_user_id');
        $db = Database::connection();
        $stmt = $db->prepare(
            'INSERT INTO rateb_pos_batch_ledger
             (company_id, order_id, order_line_id, original_line_id, movement_id, batch_id, direction, quantity, reference_type, reference_id, created_by)
             VALUES (:cid, :oid, :lid, :olid, :mid, :bid, :dir, :qty, :rt, :rid, :uid)'
        );
        foreach ($restorations as $alloc) {
            $batchId = (int) ($alloc['batch_id'] ?? 0);
            $qty = (float) ($alloc['quantity'] ?? 0);
            if ($batchId < 1 || $qty <= 0) {
                continue;
            }
            $stmt->execute([
                'cid' => $companyId,
                'oid' => $orderId,
                'lid' => $orderLineId,
                'olid' => $originalLineId,
                'mid' => $movementId > 0 ? $movementId : null,
                'bid' => $batchId,
                'dir' => 'in',
                'qty' => $qty,
                'rt' => $referenceType,
                'rid' => $referenceId,
                'uid' => $userId ? (int) $userId : null,
            ]);
        }
        $this->audit->log('pos_batch_in', 'pos_order', $orderId, [
            'order_line_id' => $orderLineId,
            'original_line_id' => $originalLineId,
            'restorations' => $restorations,
            'company_id' => $companyId,
        ]);
    }

    private function recordSerialHistory(
        int $companyId,
        int $serialId,
        string $serialNo,
        int $inventoryId,
        string $eventType,
        ?string $fromStatus,
        ?string $toStatus,
        ?string $referenceType,
        ?int $referenceId
    ): void {
        if (!$this->tableExists('rateb_pos_serial_history')) {
            return;
        }
        $userId = SessionManager::get('rateb_user_id');
        $db = Database::connection();
        $db->prepare(
            'INSERT INTO rateb_pos_serial_history
             (company_id, serial_id, serial_no, inventory_id, event_type, from_status, to_status, reference_type, reference_id, created_by)
             VALUES (:cid, :sid, :sn, :iid, :ev, :fs, :ts, :rt, :rid, :uid)'
        )->execute([
            'cid' => $companyId,
            'sid' => $serialId > 0 ? $serialId : null,
            'sn' => $serialNo,
            'iid' => $inventoryId,
            'ev' => $eventType,
            'fs' => $fromStatus,
            'ts' => $toStatus,
            'rt' => $referenceType,
            'rid' => $referenceId,
            'uid' => $userId ? (int) $userId : null,
        ]);
    }

    /** @param array<string, mixed> $row */
    private function enrichProduct(
        array $row,
        int $companyId,
        ?int $warehouseId,
        ?int $sessionId,
        ?string $matchedSerial = null,
        ?int $branchId = null
    ): array {
        $inventoryId = (int) ($row['id'] ?? 0);
        $onHand = (float) ($row['quantity'] ?? 0);
        $reservedOther = $this->reservations->reservedQuantity($companyId, $inventoryId, $sessionId);
        $available = max(0, round($onHand - $reservedOther, 3));
        $serials = $this->listAvailableSerials(
            $inventoryId,
            $companyId,
            $warehouseId,
            (int) ($row['branch_id'] ?? 0) ?: null,
            5
        );
        $batchPreview = $this->previewFefoAllocation($inventoryId, 1.0, $companyId);
        $priceBranch = $branchId ?? (int) ($row['branch_id'] ?? 0);
        $resolved = $this->sellPrices->resolveLine(
            ['product_id' => $inventoryId, 'quantity' => 1],
            $companyId,
            $priceBranch > 0 ? $priceBranch : 0,
            null
        );

        return [
            'id' => $inventoryId,
            'item_code' => (string) ($row['item_code'] ?? ''),
            'item_name' => (string) ($row['item_name'] ?? ''),
            'barcode' => (string) ($row['barcode'] ?? ''),
            'sku' => (string) ($row['sku'] ?? ''),
            'unit' => (string) ($row['unit'] ?? ''),
            'unit_price' => (float) ($resolved['unit_price'] ?? 0),
            'price_source' => (string) ($resolved['price_source'] ?? 'default'),
            'warehouse_id' => (int) ($row['warehouse_id'] ?? 0),
            'branch_id' => (int) ($row['branch_id'] ?? 0),
            'matched_serial' => $matchedSerial,
            'availability' => [
                'on_hand' => $onHand,
                'reserved_other' => $reservedOther,
                'available' => $available,
                'can_add' => $available > 0 || ($matchedSerial !== null && $matchedSerial !== ''),
            ],
            'has_batches' => ($batchPreview['allocations'] ?? []) !== [],
            'requires_serial' => $serials !== [] || $matchedSerial !== null,
            'serial_count' => count($serials),
        ];
    }

    /** @return array<string, mixed> */
    private function lockInventoryRow(int $inventoryId, int $companyId, bool $forUpdate): array
    {
        $db = Database::connection();
        $sql = 'SELECT * FROM rateb_inventory WHERE id = :id AND company_id = :cid LIMIT 1';
        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }
        $stmt = $db->prepare($sql);
        $stmt->execute(['id' => $inventoryId, 'cid' => $companyId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            throw new \RuntimeException(__('no_records'));
        }
        $branchId = (int) ($row['branch_id'] ?? 0);
        if ($branchId > 0) {
            PosFkValidator::assertBranchAccess($branchId);
        }
        return $row;
    }

    /** @return array<string, mixed>|null */
    private function findBySerialNo(
        string $serialNo,
        int $companyId,
        ?int $warehouseId,
        ?int $branchId
    ): ?array {
        if (!$this->tableExists('rateb_inventory_serials')) {
            return null;
        }
        $db = Database::connection();
        $sql = 'SELECT i.* FROM rateb_inventory_serials s
                INNER JOIN rateb_inventory i ON i.id = s.inventory_id
                WHERE s.company_id = :cid AND s.serial_no = :sn AND s.status = :st LIMIT 1';
        $stmt = $db->prepare($sql);
        $stmt->execute(['cid' => $companyId, 'sn' => $serialNo, 'st' => 'available']);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row || !$this->itemMatchesScope($row, $warehouseId, $branchId)) {
            return null;
        }
        return $row;
    }

    /**
     * @param array<int, array<string, mixed>> $cartLines
     * @return array{ok: bool, error?: string}
     */
    private function assertSerialAvailable(
        string $serialNo,
        int $inventoryId,
        int $companyId,
        array $cartLines,
        ?string $excludeLineId
    ): array {
        foreach ($cartLines as $line) {
            if ($excludeLineId !== null && (string) ($line['id'] ?? '') === $excludeLineId) {
                continue;
            }
            if (trim((string) ($line['serial_no'] ?? '')) === $serialNo) {
                return ['ok' => false, 'error' => __('pos_serial_duplicate')];
            }
        }
        if (!$this->tableExists('rateb_inventory_serials')) {
            return ['ok' => true];
        }
        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT id FROM rateb_inventory_serials
             WHERE company_id = :cid AND inventory_id = :iid AND serial_no = :sn AND status = :st LIMIT 1'
        );
        $stmt->execute([
            'cid' => $companyId,
            'iid' => $inventoryId,
            'sn' => $serialNo,
            'st' => 'available',
        ]);
        if (!$stmt->fetchColumn()) {
            return ['ok' => false, 'error' => __('pos_serial_unavailable')];
        }
        return ['ok' => true];
    }

    /** @param array<int, array<string, mixed>> $cartLines */
    private function cartQtyForProduct(array $cartLines, int $inventoryId, ?string $excludeLineId): float
    {
        $total = 0.0;
        foreach ($cartLines as $line) {
            if ($excludeLineId !== null && (string) ($line['id'] ?? '') === $excludeLineId) {
                continue;
            }
            if ((int) ($line['product_id'] ?? 0) === $inventoryId) {
                $total += (float) ($line['quantity'] ?? 0);
            }
        }
        return $total;
    }

    /** @param array<string, mixed> $row */
    private function resolveInventoryInScope(
        array $row,
        int $companyId,
        ?int $warehouseId,
        ?int $branchId
    ): ?array {
        if ($this->itemMatchesScope($row, $warehouseId, $branchId)) {
            return $row;
        }

        $itemCode = trim((string) ($row['item_code'] ?? ''));
        $sku = trim((string) ($row['sku'] ?? ''));
        $barcode = trim((string) ($row['barcode'] ?? ''));

        $filters = [];
        if ($warehouseId !== null && $warehouseId > 0) {
            $filters['warehouse_id'] = $warehouseId;
        }

        $terms = array_values(array_unique(array_filter([$itemCode, $sku, $barcode])));
        foreach ($terms as $term) {
            $candidates = (new Inventory())->all(12, 0, $filters, $term);
            foreach ($candidates as $candidate) {
                if (!$this->itemMatchesScope($candidate, $warehouseId, $branchId)) {
                    continue;
                }
                $cCode = trim((string) ($candidate['item_code'] ?? ''));
                $cSku = trim((string) ($candidate['sku'] ?? ''));
                $cBarcode = trim((string) ($candidate['barcode'] ?? ''));
                if ($itemCode !== '' && $cCode === $itemCode) {
                    return $candidate;
                }
                if ($sku !== '' && $cSku === $sku) {
                    return $candidate;
                }
                if ($barcode !== '' && $cBarcode === $barcode) {
                    return $candidate;
                }
            }
        }

        return null;
    }

    /** @param array<string, mixed> $row */
    private function itemMatchesScope(array $row, ?int $warehouseId, ?int $branchId): bool
    {
        if ($warehouseId !== null && $warehouseId > 0) {
            $rowWh = (int) ($row['warehouse_id'] ?? 0);
            if ($rowWh > 0 && $rowWh !== $warehouseId) {
                return false;
            }
        }
        if ($branchId !== null && $branchId > 0 && !$this->itemMatchesBranch($row, $branchId)) {
            return false;
        }
        return true;
    }

    /** @param array<string, mixed> $row */
    private function itemMatchesBranch(array $row, int $branchId): bool
    {
        $itemBranch = (int) ($row['branch_id'] ?? 0);
        return $itemBranch < 1 || $itemBranch === $branchId;
    }

    private function tableExists(string $table): bool
    {
        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t'
        );
        $stmt->execute(['t' => $table]);
        return (int) $stmt->fetchColumn() > 0;
    }
}
