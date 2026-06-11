<?php
declare(strict_types=1);

namespace Rateb\App\Helpers;

final class LineItems
{
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
            $lines[] = [
                'item_name' => $name,
                'sku' => trim((string) ($_POST['line_sku'][$i] ?? '')),
                'quantity' => max(0.001, $qty),
                'unit' => trim((string) ($_POST['line_unit'][$i] ?? 'unit')) ?: 'unit',
                'unit_price' => $price,
                'total_price' => round(max(0.001, $qty) * $price, 2),
            ];
        }
        return $lines;
    }

    /** @param array<int, array<string, mixed>> $lines */
    public static function syncPurchaseOrderItems(int $orderId, array $lines): float
    {
        $model = new \Rateb\App\Models\PurchaseItem();
        $db = \Rateb\App\Core\Database::connection();
        $db->prepare('DELETE FROM rateb_purchase_items WHERE purchase_order_id = :oid')->execute(['oid' => $orderId]);
        $total = 0.0;
        foreach ($lines as $line) {
            $model->create(array_merge($line, ['purchase_order_id' => $orderId]));
            $total += (float) $line['total_price'];
        }
        return $total;
    }

    /** @param array<int, array<string, mixed>> $lines */
    public static function syncPurchaseRequestItems(int $requestId, array $lines): float
    {
        $model = new \Rateb\App\Models\PurchaseRequestItem();
        $db = \Rateb\App\Core\Database::connection();
        $db->prepare('DELETE FROM rateb_purchase_request_items WHERE purchase_request_id = :rid')->execute(['rid' => $requestId]);
        $total = 0.0;
        foreach ($lines as $line) {
            $model->create(array_merge($line, ['purchase_request_id' => $requestId]));
            $total += (float) $line['total_price'];
        }
        return $total;
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
