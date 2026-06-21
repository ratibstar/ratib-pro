<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\Auth;
use Rateb\App\Core\SessionManager;
use Rateb\App\Core\TenantContext;
use Rateb\App\Helpers\LineItems;
use Rateb\App\Models\HrDepartment;
use Rateb\App\Models\Inventory;
use Rateb\App\Models\PurchaseOrder;
use Rateb\App\Models\PurchaseRequest;
use Rateb\App\Models\SupplierQuotation;

final class ProcurementService
{
    /** @return array<int, string> */
    public function departmentOptions(): array
    {
        $companyId = (int) (TenantContext::companyId() ?? 0);
        if ($companyId < 1 && function_exists('rateb_resolve_ops_company_id')) {
            $companyId = rateb_resolve_ops_company_id();
        }
        if ($companyId < 1) {
            return [];
        }

        $seen = [];
        $out = [];

        $hrRows = (new HrDepartment())->query(
            "SELECT name FROM rateb_hr_departments
             WHERE company_id = :cid AND status = 'active' AND TRIM(name) <> ''
             ORDER BY name",
            ['cid' => $companyId]
        );
        foreach ($hrRows as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $key = mb_strtolower($name);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $name;
        }

        $rows = (new PurchaseRequest())->query(
            "SELECT DISTINCT department FROM rateb_purchase_requests
             WHERE company_id = :cid AND department IS NOT NULL AND TRIM(department) <> ''
             ORDER BY department LIMIT 50",
            ['cid' => $companyId]
        );
        foreach ($rows as $row) {
            $d = trim((string) ($row['department'] ?? ''));
            if ($d === '') {
                continue;
            }
            $key = mb_strtolower($d);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $d;
        }

        sort($out, SORT_FLAG_CASE | SORT_NATURAL);
        return $out;
    }

    public function convertRequestToOrder(int $requestId): int
    {
        $pr = (new PurchaseRequest())->find($requestId);
        if (!$pr) {
            throw new \RuntimeException(__('record_not_found'));
        }
        $lines = LineItems::loadPurchaseRequestItems($requestId);
        $firstSupplier = null;
        $firstWarehouse = null;
        foreach ($lines as $line) {
            if ($firstSupplier === null && !empty($line['supplier_id'])) {
                $firstSupplier = (int) $line['supplier_id'];
            }
            if ($firstWarehouse === null && !empty($line['warehouse_id'])) {
                $firstWarehouse = (int) $line['warehouse_id'];
            }
        }
        $poModel = new PurchaseOrder();
        $poId = $poModel->create([
            'order_no' => $poModel->generateOrderNo(),
            'purchase_request_id' => $requestId,
            'supplier_id' => $firstSupplier,
            'warehouse_id' => $firstWarehouse,
            'status' => 'draft',
            'order_date' => date('Y-m-d'),
            'expected_date' => $pr['expected_date'] ?? null,
            'currency' => $pr['currency'] ?? 'SAR',
            'notes' => $pr['notes'] ?? null,
            'subtotal' => (float) ($pr['total_estimated'] ?? 0),
            'tax_amount' => 0,
            'total_amount' => (float) ($pr['total_estimated'] ?? 0),
        ]);
        $mapped = [];
        foreach ($lines as $line) {
            $mapped[] = array_merge($line, ['delivered_qty' => 0, 'invoiced_qty' => 0]);
        }
        LineItems::syncPurchaseOrderItems($poId, $mapped);
        (new DocumentBarcodeService())->ensure('purchase_order', $poId);
        return $poId;
    }

    public function createOrderFromQuotation(int $quotationId): int
    {
        $q = (new SupplierQuotation())->find($quotationId);
        if (!$q) {
            throw new \RuntimeException(__('record_not_found'));
        }
        $amount = (float) ($q['amount'] ?? 0);
        $poModel = new PurchaseOrder();
        $poId = $poModel->create([
            'order_no' => $poModel->generateOrderNo(),
            'supplier_id' => (int) ($q['supplier_id'] ?? 0) ?: null,
            'quotation_id' => $quotationId,
            'status' => 'draft',
            'order_date' => date('Y-m-d'),
            'currency' => 'SAR',
            'notes' => $q['notes'] ?? null,
            'subtotal' => $amount,
            'tax_amount' => 0,
            'total_amount' => $amount,
        ]);
        (new DocumentBarcodeService())->ensure('purchase_order', $poId);
        return $poId;
    }

    /** @param array<int|string, float> $receiveQtys keyed by purchase_item id */
    public function receiveOrder(int $orderId, array $receiveQtys, ?int $warehouseId = null): void
    {
        $order = (new PurchaseOrder())->find($orderId);
        if (!$order) {
            throw new \RuntimeException(__('record_not_found'));
        }
        $warehouseId = $warehouseId ?: (int) ($order['warehouse_id'] ?? 0) ?: null;
        $items = LineItems::loadPurchaseOrderItems($orderId);
        $stock = new StockMovementService();
        $poModel = new PurchaseOrder();
        $allReceived = true;
        $anyReceived = false;

        foreach ($items as $item) {
            $lineId = (int) ($item['id'] ?? 0);
            $qty = (float) ($receiveQtys[$lineId] ?? $receiveQtys[(string) $lineId] ?? 0);
            if ($qty <= 0) {
                if ((float) ($item['delivered_qty'] ?? 0) < (float) ($item['quantity'] ?? 0)) {
                    $allReceived = false;
                }
                continue;
            }
            $ordered = (float) ($item['quantity'] ?? 0);
            $delivered = (float) ($item['delivered_qty'] ?? 0);
            $remaining = max(0, $ordered - $delivered);
            $receive = min($qty, $remaining);
            if ($receive <= 0) {
                continue;
            }
            $anyReceived = true;
            $newDelivered = $delivered + $receive;
            (new \Rateb\App\Models\PurchaseItem())->update($lineId, ['delivered_qty' => $newDelivered]);

            $inventoryId = (int) ($item['inventory_id'] ?? 0);
            if ($inventoryId < 1) {
                $inventoryId = $this->findOrCreateInventory($item, $warehouseId);
                if ($inventoryId > 0) {
                    (new \Rateb\App\Models\PurchaseItem())->update($lineId, ['inventory_id' => $inventoryId]);
                }
            }
            if ($inventoryId > 0) {
                $stock->record([
                    'inventory_id' => $inventoryId,
                    'warehouse_id' => $warehouseId,
                    'movement_type' => 'in',
                    'quantity' => $receive,
                    'reference_type' => 'purchase_order',
                    'reference_id' => $orderId,
                    'notes' => __('grn_receive') . ' #' . ($order['order_no'] ?? $orderId),
                ]);
            }
            if ($newDelivered < $ordered) {
                $allReceived = false;
            }
        }

        if (!$anyReceived) {
            throw new \InvalidArgumentException(__('grn_no_qty'));
        }
        $status = $allReceived ? 'received' : 'partial';
        $poModel->update($orderId, ['status' => $status]);
        if (in_array($status, ['received', 'partial'], true)) {
            try {
                (new AccountingService())->autoPostPurchaseOrder($orderId);
            } catch (\Throwable $e) {
                // stock saved even if accounting fails
            }
        }
    }

    /** @param array<string, mixed> $line */
    private function findOrCreateInventory(array $line, ?int $warehouseId): int
    {
        $sku = trim((string) ($line['sku'] ?? ''));
        $invModel = new Inventory();
        if ($sku !== '') {
            $rows = $invModel->all(1, 0, ['sku' => $sku]);
            if ($rows !== []) {
                return (int) ($rows[0]['id'] ?? 0);
            }
        }
        return $invModel->create([
            'warehouse_id' => $warehouseId,
            'item_name' => (string) ($line['item_name'] ?? ''),
            'sku' => $sku !== '' ? $sku : null,
            'quantity' => 0,
            'unit' => (string) ($line['unit'] ?? 'unit'),
            'unit_cost' => (float) ($line['unit_price'] ?? 0),
            'status' => 'active',
        ]);
    }

    /** @param array<string, mixed> $data */
    public function applyOrderTotals(array &$data, array $lines): void
    {
        if ($lines === []) {
            return;
        }
        $agg = LineItems::aggregateTotals($lines);
        $discount = max(0, (float) ($data['discount_amount'] ?? 0));
        $shipping = max(0, (float) ($data['shipping_amount'] ?? 0));
        $customs = max(0, (float) ($data['customs_clearance_amount'] ?? 0));
        $data['subtotal'] = $agg['subtotal'];
        $data['tax_amount'] = $agg['tax'];
        $data['total_amount'] = round($agg['total'] - $discount + $shipping + $customs, 2);
    }

    public function stampRequestedBy(array &$data): void
    {
        if (!empty($data['requested_by'])) {
            return;
        }
        $user = Auth::user();
        if ($user) {
            $data['requested_by'] = (int) $user['id'];
        }
    }

    public function saveQuoteAttachments(string $entityType, int $entityId): void
    {
        $companyId = (int) (TenantContext::companyId() ?? 0);
        if ($companyId < 1 || !isset($_FILES['quote_attachment'])) {
            return;
        }
        $files = $_FILES['quote_attachment'];
        if (!is_array($files['name'] ?? null)) {
            $files = [
                'name' => [$files['name'] ?? ''],
                'type' => [$files['type'] ?? ''],
                'tmp_name' => [$files['tmp_name'] ?? ''],
                'error' => [$files['error'] ?? UPLOAD_ERR_NO_FILE],
                'size' => [$files['size'] ?? 0],
            ];
        }
        $doc = new DocumentService();
        foreach (array_keys($files['name']) as $i) {
            if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $file = [
                'name' => $files['name'][$i],
                'type' => $files['type'][$i],
                'tmp_name' => $files['tmp_name'][$i],
                'error' => $files['error'][$i],
                'size' => $files['size'][$i],
            ];
            $upload = $doc->storeUpload($file, $entityType, $entityId, __('quote_attachment'));
            if (!($upload['success'] ?? false) && !empty($upload['error'])) {
                SessionManager::flash('error', (string) $upload['error']);
            }
        }
    }
}
