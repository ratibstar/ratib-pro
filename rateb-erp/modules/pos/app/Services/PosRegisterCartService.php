<?php
declare(strict_types=1);

namespace Rateb\App\Pos\Services;

use Rateb\App\Pos\Services\Bridge\PosInventoryBridgeService;

/**
 * Register cart — pricing preview + inventory validation (no order completion).
 */
final class PosRegisterCartService
{
    public function __construct(
        private PosPricingService $pricing = new PosPricingService(),
        private PosInventoryBridgeService $inventory = new PosInventoryBridgeService(),
        private PosSellPriceService $sellPrices = new PosSellPriceService(),
    ) {
    }

    /**
     * @param array<int, array<string, mixed>> $lines
     * @param array<string, mixed>|null $customer
     */
    public function totals(
        array $lines,
        int $companyId = 0,
        int $branchId = 0,
        ?array $customer = null,
        float $taxRate = 0.15
    ): array
    {
        $normalized = $this->normalizeLines($lines);
        if ($companyId > 0 && $branchId > 0) {
            $normalized = $this->sellPrices->applyToLines($normalized, $companyId, $branchId, $customer);
        }
        return $this->pricing->calculate($normalized, [], $taxRate);
    }

    /**
     * @param array<int, array<string, mixed>> $lines
     * @return array<int, array<string, mixed>>
     */
    public function normalizeLines(array $lines): array
    {
        $out = [];
        foreach ($lines as $line) {
            if (!is_array($line)) {
                continue;
            }
            $qty = max(0, (float) ($line['quantity'] ?? 0));
            if ($qty <= 0) {
                continue;
            }
            $unit = max(0, round((float) ($line['unit_price'] ?? 0), 2));
            $out[] = [
                'id' => (string) ($line['id'] ?? ''),
                'product_id' => (int) ($line['product_id'] ?? 0),
                'item_code' => trim((string) ($line['item_code'] ?? '')),
                'item_name' => trim((string) ($line['item_name'] ?? '')),
                'barcode' => trim((string) ($line['barcode'] ?? '')),
                'quantity' => $qty,
                'unit_price' => $unit,
                'unit' => trim((string) ($line['unit'] ?? '')),
                'line_total' => round($qty * $unit, 2),
                'serial_no' => trim((string) ($line['serial_no'] ?? '')) ?: null,
                'price_override' => !empty($line['price_override']),
                'price_source' => (string) ($line['price_source'] ?? ''),
                'discount_amount' => max(0, round((float) ($line['discount_amount'] ?? 0), 2)),
                'discount_percent' => max(0, round((float) ($line['discount_percent'] ?? 0), 2)),
                'notes' => trim((string) ($line['notes'] ?? '')),
                'available_qty' => isset($line['available_qty']) ? (float) $line['available_qty'] : null,
                'batch_preview' => is_array($line['batch_preview'] ?? null) ? $line['batch_preview'] : null,
                'requires_serial' => (bool) ($line['requires_serial'] ?? false),
                'has_batches' => (bool) ($line['has_batches'] ?? false),
            ];
        }
        return $out;
    }

    /**
     * @param array<int, array<string, mixed>> $lines
     * @return array{ok: bool, lines?: array<int, array<string, mixed>>, error?: string, line?: array<string, mixed>}
     */
    public function addProduct(
        array $lines,
        array $product,
        float $quantity,
        int $companyId,
        ?int $warehouseId,
        ?int $branchId,
        ?int $sessionId,
        ?string $serialNo = null
    ): array {
        $productId = (int) ($product['id'] ?? 0);
        $qty = max(0.001, $quantity);
        $serialNo = $serialNo !== null ? trim($serialNo) : trim((string) ($product['matched_serial'] ?? ''));
        $mergeSerial = $serialNo === '' && empty($product['requires_serial']);

        $existingQty = 0.0;
        $mergeLineId = null;
        if ($mergeSerial) {
            foreach ($lines as $line) {
                if ((int) ($line['product_id'] ?? 0) === $productId && empty($line['serial_no'])) {
                    $existingQty = (float) ($line['quantity'] ?? 0);
                    $mergeLineId = (string) ($line['id'] ?? '');
                    break;
                }
            }
        }

        $requestedTotal = $mergeSerial ? ($existingQty + $qty) : $qty;
        $check = $this->inventory->validateCartLine(
            $productId,
            $requestedTotal,
            $companyId,
            $warehouseId,
            $branchId,
            $sessionId,
            $lines,
            $mergeLineId,
            $serialNo !== '' ? $serialNo : null
        );
        if (!$check['ok']) {
            return ['ok' => false, 'error' => (string) ($check['error'] ?? __('invalid_request'))];
        }

        if ($mergeSerial && $mergeLineId !== null) {
            foreach ($lines as &$line) {
                if ((string) ($line['id'] ?? '') === $mergeLineId) {
                    $line['quantity'] = round($requestedTotal, 3);
                    $line['line_total'] = round($requestedTotal * (float) ($line['unit_price'] ?? 0), 2);
                    $line['available_qty'] = (float) ($check['available'] ?? 0);
                    $line['batch_preview'] = $check['batch_preview'] ?? [];
                    $line['has_batches'] = (bool) ($check['has_batches'] ?? false);
                    $updatedLine = $line;
                    unset($line);
                    return ['ok' => true, 'lines' => $this->normalizeLines($lines), 'line' => $updatedLine];
                }
            }
            unset($line);
        }

        $unitPrice = (float) ($product['unit_price'] ?? 0);
        $newLine = [
            'id' => bin2hex(random_bytes(8)),
            'product_id' => $productId,
            'item_code' => (string) ($product['item_code'] ?? ''),
            'item_name' => (string) ($product['item_name'] ?? ''),
            'barcode' => (string) ($product['barcode'] ?? ''),
            'quantity' => $qty,
            'unit_price' => $unitPrice,
            'price_source' => (string) ($product['price_source'] ?? 'default'),
            'price_override' => false,
            'unit' => (string) ($product['unit'] ?? ''),
            'line_total' => round($qty * $unitPrice, 2),
            'serial_no' => $serialNo !== '' ? $serialNo : null,
            'available_qty' => (float) ($check['available'] ?? 0),
            'batch_preview' => $check['batch_preview'] ?? [],
            'requires_serial' => (bool) ($check['requires_serial'] ?? false),
            'has_batches' => (bool) ($check['has_batches'] ?? false),
        ];
        $lines[] = $newLine;

        return ['ok' => true, 'lines' => $this->normalizeLines($lines), 'line' => $newLine];
    }

    /** @param array<int, array<string, mixed>> $lines */
    public function clear(): array
    {
        return [];
    }
}
