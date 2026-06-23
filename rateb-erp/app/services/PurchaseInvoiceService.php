<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\Database;
use Rateb\App\Models\Inventory;
use Rateb\App\Models\PurchaseInvoice;
use Rateb\App\Models\PurchaseOrder;
use Rateb\App\Helpers\LineItems;

final class PurchaseInvoiceService
{
    /** @return array<string, mixed>|null */
    public function findOrDraftForOrder(int $purchaseOrderId): ?array
    {
        $po = (new PurchaseOrder())->find($purchaseOrderId);
        if (!$po) {
            return null;
        }
        $existing = (new PurchaseInvoice())->findByPurchaseOrderId($purchaseOrderId);
        if ($existing) {
            return $existing;
        }
        return $this->draftFromPurchaseOrder($po);
    }

    /** @param array<string, mixed> $po */
    public function draftFromPurchaseOrder(array $po): array
    {
        $poId = (int) ($po['id'] ?? 0);
        $lines = LineItems::loadPurchaseOrderItems($poId);
        $agg = LineItems::aggregateTotals($lines);
        $discount = max(0, (float) ($po['discount_amount'] ?? 0));
        $lineSubtotal = $agg['subtotal'];
        $tax = $agg['tax'];

        return [
            'id' => 0,
            'purchase_order_id' => $poId,
            'supplier_id' => $po['supplier_id'] ?? null,
            'invoice_no' => '',
            'invoice_date' => date('Y-m-d'),
            'status' => 'draft',
            'currency' => (string) ($po['currency'] ?? 'SAR'),
            'line_subtotal' => $lineSubtotal,
            'discount_amount' => $discount,
            'tax_amount' => $tax,
            'shipping_amount' => 0,
            'customs_clearance_amount' => 0,
            'total_amount' => round($lineSubtotal - $discount + $tax, 2),
            'customs_declaration_no' => null,
            'customs_clearance_date' => null,
            'customs_broker_id' => null,
            'customs_clearance_status' => '',
            'notes' => null,
        ];
    }

    /** @param array<string, mixed> $data */
    public function applyTotals(array &$data): void
    {
        $lineSubtotal = max(0, (float) ($data['line_subtotal'] ?? 0));
        $discount = max(0, (float) ($data['discount_amount'] ?? 0));
        $tax = max(0, (float) ($data['tax_amount'] ?? 0));
        $shipping = max(0, (float) ($data['shipping_amount'] ?? 0));
        $customs = max(0, (float) ($data['customs_clearance_amount'] ?? 0));
        $data['line_subtotal'] = $lineSubtotal;
        $data['total_amount'] = round($lineSubtotal - $discount + $tax + $shipping + $customs, 2);
    }

    /** @param array<string, mixed> $data */
    public function save(int $purchaseOrderId, array $data): int
    {
        $po = (new PurchaseOrder())->find($purchaseOrderId);
        if (!$po) {
            throw new \RuntimeException(__('record_not_found'));
        }
        $model = new PurchaseInvoice();
        $existing = $model->findByPurchaseOrderId($purchaseOrderId);
        $lines = LineItems::loadPurchaseOrderItems($purchaseOrderId);
        $agg = LineItems::aggregateTotals($lines);
        $data['purchase_order_id'] = $purchaseOrderId;
        $data['supplier_id'] = $po['supplier_id'] ?? null;
        $data['line_subtotal'] = $agg['subtotal'];
        $data['tax_amount'] = $agg['tax'];
        $data['discount_amount'] = max(0, (float) ($data['discount_amount'] ?? $po['discount_amount'] ?? 0));
        if (trim((string) ($data['invoice_no'] ?? '')) === '') {
            $data['invoice_no'] = $model->generateInvoiceNo();
        }
        if (trim((string) ($data['invoice_date'] ?? '')) === '') {
            $data['invoice_date'] = date('Y-m-d');
        }
        foreach (['customs_broker_id'] as $fk) {
            if (array_key_exists($fk, $data) && (string) ($data[$fk] ?? '') === '') {
                $data[$fk] = null;
            }
        }
        if (array_key_exists('customs_clearance_date', $data) && trim((string) ($data['customs_clearance_date'] ?? '')) === '') {
            $data['customs_clearance_date'] = null;
        }
        $this->applyTotals($data);

        if ($existing) {
            $id = (int) $existing['id'];
            $model->update($id, $data);
        } else {
            $id = $model->create($data);
        }

        if (($data['status'] ?? '') === 'posted') {
            $this->applyLandedCostsToInventory($id);
            try {
                (new AccountingService())->autoPostPurchaseInvoice($id);
            } catch (\Throwable $e) {
                error_log('Purchase invoice accounting post failed: ' . $e->getMessage());
            }
        }

        return $id;
    }

    /** @return array{invoice: array<string, mixed>, order: array<string, mixed>}|null */
    public function findInvoiceContext(int $invoiceId): ?array
    {
        $invoice = (new PurchaseInvoice())->find($invoiceId);
        if (!$invoice) {
            return null;
        }
        $poId = (int) ($invoice['purchase_order_id'] ?? 0);
        $order = $poId > 0 ? (new PurchaseOrder())->find($poId) : null;
        if (!$order) {
            return null;
        }
        return ['invoice' => $invoice, 'order' => $order];
    }

    /** @param array<string, mixed> $data */
    public function saveByInvoiceId(int $invoiceId, array $data): int
    {
        $ctx = $this->findInvoiceContext($invoiceId);
        if (!$ctx) {
            throw new \RuntimeException(__('record_not_found'));
        }
        $invoice = $ctx['invoice'];
        $poId = (int) ($invoice['purchase_order_id'] ?? 0);
        if (trim((string) ($data['invoice_no'] ?? '')) === '' && trim((string) ($invoice['invoice_no'] ?? '')) !== '') {
            $data['invoice_no'] = (string) $invoice['invoice_no'];
        }
        if (trim((string) ($data['invoice_date'] ?? '')) === '' && !empty($invoice['invoice_date'])) {
            $data['invoice_date'] = (string) $invoice['invoice_date'];
        }
        if (!isset($data['status']) || trim((string) $data['status']) === '') {
            $data['status'] = (string) ($invoice['status'] ?? 'draft');
        }
        return $this->save($poId, $data);
    }

    public function applyLandedCostsToInventory(int $invoiceId): void
    {
        $invoice = (new PurchaseInvoice())->find($invoiceId);
        if (!$invoice) {
            return;
        }
        $landed = max(0, (float) ($invoice['shipping_amount'] ?? 0))
            + max(0, (float) ($invoice['customs_clearance_amount'] ?? 0));
        if ($landed <= 0) {
            return;
        }
        $poId = (int) ($invoice['purchase_order_id'] ?? 0);
        if ($poId < 1) {
            return;
        }
        $items = LineItems::loadPurchaseOrderItems($poId);
        $allocations = [];
        $totalBase = 0.0;
        foreach ($items as $item) {
            $delivered = (float) ($item['delivered_qty'] ?? 0);
            $unitPrice = (float) ($item['unit_price'] ?? 0);
            if ($delivered <= 0 || $unitPrice <= 0) {
                continue;
            }
            $lineValue = $delivered * $unitPrice;
            $inventoryId = (int) ($item['inventory_id'] ?? 0);
            if ($inventoryId < 1 || $lineValue <= 0) {
                continue;
            }
            $allocations[] = [
                'inventory_id' => $inventoryId,
                'delivered' => $delivered,
                'unit_price' => $unitPrice,
                'line_value' => $lineValue,
            ];
            $totalBase += $lineValue;
        }
        if ($totalBase <= 0 || $allocations === []) {
            return;
        }
        $invModel = new Inventory();
        foreach ($allocations as $row) {
            $share = $landed * ($row['line_value'] / $totalBase);
            $extraPerUnit = $share / $row['delivered'];
            $inv = $invModel->find((int) $row['inventory_id']);
            if (!$inv) {
                continue;
            }
            $newCost = round((float) ($inv['unit_cost'] ?? $row['unit_price']) + $extraPerUnit, 4);
            $invModel->update((int) $row['inventory_id'], ['unit_cost' => $newCost]);
        }
    }
}
