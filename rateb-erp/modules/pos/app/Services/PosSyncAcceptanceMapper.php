<?php
declare(strict_types=1);

namespace Rateb\App\Pos\Services;

/**
 * Phase 13 — map acceptance payload → PosCheckoutService::complete() inputs.
 * Mapping only. No inventory/GL/business rules.
 *
 * Payment policy: if payments missing → default cash for totals.total
 * (payment_policy=default_cash).
 */
final class PosSyncAcceptanceMapper
{
    public const PAYMENT_POLICY_DEFAULT_CASH = 'default_cash';

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $auth company_id, user_id from auth only
     * @return array{
     *   lines: list<array<string, mixed>>,
     *   payments: list<array<string, mixed>>,
     *   invoice_discount: array<string, mixed>,
     *   scope: array<string, mixed>,
     *   customer: ?array<string, mixed>,
     *   payment_policy: ?string
     * }
     */
    public function map(array $payload, array $auth): array
    {
        $companyId = (int) ($auth['company_id'] ?? 0);
        $userId = (int) ($auth['user_id'] ?? 0);
        if ($companyId < 1) {
            throw new \InvalidArgumentException('company_id required from auth');
        }
        if ($userId < 1) {
            throw new \InvalidArgumentException('user_id required');
        }

        $meta = is_array($payload['metadata'] ?? null) ? $payload['metadata'] : [];
        $branchId = (int) ($payload['branch_id'] ?? $meta['branch_id'] ?? 0);
        $warehouseId = (int) ($payload['warehouse_id'] ?? $meta['warehouse_id'] ?? 0);
        if ($branchId < 1) {
            throw new \InvalidArgumentException('branch_id required');
        }
        if ($warehouseId < 1) {
            throw new \InvalidArgumentException('warehouse_id required');
        }

        $syncKey = trim((string) ($payload['sync_key'] ?? ''));
        if ($syncKey === '') {
            throw new \InvalidArgumentException('sync_key required');
        }

        $lines = [];
        foreach ($payload['lines'] ?? [] as $line) {
            if (!is_array($line)) {
                continue;
            }
            $qty = $line['quantity'] ?? $line['qty'] ?? null;
            $productId = $line['product_id'] ?? $line['inventory_id'] ?? null;
            if ($productId === null || $productId === '' || !is_numeric($qty) || (float) $qty <= 0) {
                continue;
            }
            $mapped = [
                'product_id' => (int) $productId > 0 ? (int) $productId : $productId,
                'inventory_id' => (int) $productId > 0 ? (int) $productId : null,
                'quantity' => (float) $qty,
                'qty' => (float) $qty,
            ];
            if (isset($line['unit_price'])) {
                $mapped['unit_price'] = (float) $line['unit_price'];
            }
            if (isset($line['name'])) {
                $mapped['name'] = (string) $line['name'];
            }
            $lines[] = $mapped;
        }
        if ($lines === []) {
            throw new \InvalidArgumentException('lines required');
        }

        $totals = is_array($payload['totals'] ?? null) ? $payload['totals'] : [];
        $total = (float) ($totals['total'] ?? 0);
        $paymentPolicy = null;
        $payments = is_array($payload['payments'] ?? null) ? $payload['payments'] : [];
        if ($payments === []) {
            if ($total <= 0) {
                throw new \InvalidArgumentException('totals.total required for default_cash');
            }
            $payments = [[
                'method' => 'cash',
                'amount' => $total,
            ]];
            $paymentPolicy = self::PAYMENT_POLICY_DEFAULT_CASH;
        }

        $discount = is_array($payload['invoice_discount'] ?? $payload['discount'] ?? null)
            ? ($payload['invoice_discount'] ?? $payload['discount'])
            : [];

        $customer = is_array($payload['customer'] ?? null) ? $payload['customer'] : null;

        $scope = [
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'warehouse_id' => $warehouseId,
            'user_id' => $userId,
            'terminal_id' => (int) ($payload['terminal_id'] ?? $meta['terminal_id'] ?? 0) ?: null,
            'shift_id' => (int) ($payload['shift_id'] ?? $meta['shift_id'] ?? 0) ?: null,
            'session_id' => (int) ($payload['session_id'] ?? $meta['session_id'] ?? 0) ?: null,
            'idempotency_key' => $syncKey,
            'device_id' => $payload['device_id'] ?? null,
            'payment_policy' => $paymentPolicy,
        ];

        return [
            'lines' => $lines,
            'payments' => $payments,
            'invoice_discount' => is_array($discount) ? $discount : [],
            'scope' => $scope,
            'customer' => $customer,
            'payment_policy' => $paymentPolicy,
        ];
    }
}
