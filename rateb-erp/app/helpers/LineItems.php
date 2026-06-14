<?php
declare(strict_types=1);

namespace Rateb\App\Helpers;

final class LineItems
{
    /** @return list<string> */
    public static function unitOptions(): array
    {
        return ['each', 'box', 'kg', 'liter', 'meter', 'pack', 'unit'];
    }

    /** @return list<string> */
    public static function taxPresets(): array
    {
        return [
            'Local Sales 0%',
            'VAT 15%',
            'VAT 5%',
            'Exempt',
        ];
    }

    /** @return array{subtotal: float, tax: float, total: float} */
    public static function lineTotals(float $qty, float $unitPrice, float $taxRate, bool $excludingTax): array
    {
        $base = round($qty * $unitPrice, 2);
        if ($taxRate <= 0) {
            return ['subtotal' => $base, 'tax' => 0.0, 'total' => $base];
        }
        if ($excludingTax) {
            $tax = round($base * ($taxRate / 100), 2);
            return ['subtotal' => $base, 'tax' => $tax, 'total' => round($base + $tax, 2)];
        }
        $tax = round($base - ($base / (1 + ($taxRate / 100))), 2);
        $subtotal = round($base - $tax, 2);
        return ['subtotal' => $subtotal, 'tax' => $tax, 'total' => $base];
    }

    /** @return array<int, array<string, mixed>> */
    public static function collectFromRequest(): array
    {
        $names = $_POST['line_item_name'] ?? [];
        if (!is_array($names)) {
            return [];
        }
        $lines = [];
        foreach (array_keys($names) as $i) {
            $name = trim((string) ($names[$i] ?? ''));
            if ($name === '') {
                continue;
            }
            $qty = (float) ($_POST['line_quantity'][$i] ?? 1);
            $price = (float) ($_POST['line_unit_price'][$i] ?? 0);
            $taxRate = (float) ($_POST['line_tax_rate'][$i] ?? 0);
            $excludingTax = (int) ($_POST['line_excluding_tax'][$i] ?? 1) === 1;
            $totals = self::lineTotals(max(0.001, $qty), $price, $taxRate, $excludingTax);
            $lines[] = [
                'item_name' => $name,
                'description' => trim((string) ($_POST['line_description'][$i] ?? '')),
                'sku' => trim((string) ($_POST['line_sku'][$i] ?? '')),
                'quantity' => max(0.001, $qty),
                'delivered_qty' => (float) ($_POST['line_delivered_qty'][$i] ?? 0),
                'invoiced_qty' => (float) ($_POST['line_invoiced_qty'][$i] ?? 0),
                'unit' => trim((string) ($_POST['line_unit'][$i] ?? 'unit')) ?: 'unit',
                'unit_price' => $price,
                'tax_name' => trim((string) ($_POST['line_tax_name'][$i] ?? 'Local Sales 0%')) ?: 'Local Sales 0%',
                'tax_rate' => $taxRate,
                'excluding_tax' => $excludingTax ? 1 : 0,
                'total_price' => $totals['total'],
            ];
        }
        return $lines;
    }

    /** @param array<int, array<string, mixed>> $lines */
    public static function aggregateTotals(array $lines): array
    {
        $subtotal = 0.0;
        $tax = 0.0;
        foreach ($lines as $line) {
            $totals = self::lineTotals(
                (float) ($line['quantity'] ?? 0),
                (float) ($line['unit_price'] ?? 0),
                (float) ($line['tax_rate'] ?? 0),
                !empty($line['excluding_tax'])
            );
            $subtotal += $totals['subtotal'];
            $tax += $totals['tax'];
        }
        return [
            'subtotal' => round($subtotal, 2),
            'tax' => round($tax, 2),
            'total' => round($subtotal + $tax, 2),
        ];
    }

    /** @param array<int, array<string, mixed>> $lines */
    public static function syncPurchaseOrderItems(int $orderId, array $lines): float
    {
        $model = new \Rateb\App\Models\PurchaseItem();
        $db = \Rateb\App\Core\Database::connection();
        $db->prepare('DELETE FROM rateb_purchase_items WHERE purchase_order_id = :oid')->execute(['oid' => $orderId]);
        $agg = self::aggregateTotals($lines);
        foreach ($lines as $line) {
            $model->create(array_merge($line, ['purchase_order_id' => $orderId]));
        }
        return $agg['total'];
    }

    /** @param array<int, array<string, mixed>> $lines */
    public static function syncPurchaseRequestItems(int $requestId, array $lines): float
    {
        $model = new \Rateb\App\Models\PurchaseRequestItem();
        $db = \Rateb\App\Core\Database::connection();
        $db->prepare('DELETE FROM rateb_purchase_request_items WHERE purchase_request_id = :rid')->execute(['rid' => $requestId]);
        $agg = self::aggregateTotals($lines);
        foreach ($lines as $line) {
            $payload = $line;
            unset($payload['delivered_qty'], $payload['invoiced_qty']);
            $model->create(array_merge($payload, ['purchase_request_id' => $requestId]));
        }
        return $agg['total'];
    }

    /** @return array<int, array<string, mixed>> */
    public static function loadPurchaseOrderItems(int $orderId): array
    {
        return (new \Rateb\App\Models\PurchaseItem())->all(200, 0, ['purchase_order_id' => $orderId]);
    }

    /** @return array<int, array<string, mixed>> */
    public static function loadPurchaseRequestItems(int $requestId): array
    {
        return (new \Rateb\App\Models\PurchaseRequestItem())->all(200, 0, ['purchase_request_id' => $requestId]);
    }
}
