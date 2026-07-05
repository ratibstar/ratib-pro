<?php
declare(strict_types=1);

namespace Rateb\App\Pos\Services;

use Rateb\App\Core\Database;
use Rateb\App\Core\TenantContext;
use Rateb\App\Pos\Models\PosOrder;
use Rateb\App\Pos\Models\PosOrderLine;
use Rateb\App\Pos\Models\PosPayment;
use Rateb\App\Pos\Services\Bridge\PosAuditBridgeService;
use Rateb\App\Pos\Services\Bridge\PosAccountingBridgeService;
use Rateb\App\Pos\Services\Bridge\PosInventoryBridgeService;
use Rateb\App\Pos\Support\PosDocumentCodes;

/** Exchange — return lines + new sale lines in one atomic transaction. */
final class PosExchangeService
{
    private const PAYMENT_METHODS = ['cash', 'card', 'bank', 'wallet', 'gift_card'];

    public function __construct(
        private PosOrderQueryService $queries = new PosOrderQueryService(),
        private PosPricingService $pricing = new PosPricingService(),
        private PosSellPriceService $sellPrices = new PosSellPriceService(),
        private PosInventoryBridgeService $inventory = new PosInventoryBridgeService(),
        private PosRefundService $refunds = new PosRefundService(),
        private PosReceiptService $receipts = new PosReceiptService(),
        private PosAuditBridgeService $audit = new PosAuditBridgeService(),
        private PosAccountingBridgeService $accounting = new PosAccountingBridgeService(),
        private PosRewardService $rewards = new PosRewardService(),
        private PosCashDrawerService $drawers = new PosCashDrawerService(),
        private PosDiscountGuardService $discountGuard = new PosDiscountGuardService(),
    ) {
    }

    /**
     * @param array<int, array<string, mixed>> $returnLines
     * @param array<int, array<string, mixed>> $saleLines cart lines for new items
     * @param array<int, array<string, mixed>> $payments net payments (positive = customer pays)
     * @param array<int, array<string, mixed>> $refunds net refunds (when return > sale)
     * @param array<string, mixed> $scope
     */
    public function processExchange(
        int $originalOrderId,
        array $returnLines,
        array $saleLines,
        array $payments,
        array $refunds,
        array $scope,
        ?array $customer = null,
        array $invoiceDiscount = []
    ): array {
        $companyId = (int) ($scope['company_id'] ?? 0);
        $branchId = (int) ($scope['branch_id'] ?? 0);
        $userId = (int) ($scope['user_id'] ?? 0);
        if ($companyId < 1 || $branchId < 1 || $userId < 1) {
            throw new \RuntimeException(__('invalid_request'));
        }
        if ($returnLines === [] || $saleLines === []) {
            throw new \RuntimeException(__('pos_exchange_requires_both'));
        }
        TenantContext::setCompanyId($companyId);

        $original = $this->queries->findOrder($originalOrderId, $companyId, $branchId);
        if (!$original || (string) ($original['order_type'] ?? '') !== 'sale' || (string) ($original['status'] ?? '') !== 'completed') {
            throw new \RuntimeException(__('pos_return_invalid_order'));
        }

        $originalLines = $this->queries->orderLines($originalOrderId, $companyId);
        $linesById = [];
        foreach ($originalLines as $ol) {
            $linesById[(int) ($ol['id'] ?? 0)] = $ol;
        }

        $returnCart = $this->buildReturnCart($returnLines, $linesById);
        $returnPricing = $this->pricing->calculate($returnCart, [], (float) ($scope['tax_rate'] ?? 0.15));

        $db = Database::connection();
        $db->beginTransaction();
        try {
            $this->queries->lockOriginalOrderForReturn($originalOrderId, $companyId, $branchId);
            $this->queries->assertReturnLineQuantities($returnLines, $linesById, $companyId);

            $validated = $this->inventory->revalidateForCheckout(
                $saleLines,
                $companyId,
                (int) ($scope['warehouse_id'] ?? 0) ?: null,
                $branchId,
                (int) ($scope['session_id'] ?? 0) ?: null,
                true
            );
            if (!$validated['ok']) {
                throw new \RuntimeException((string) ($validated['error'] ?? __('pos_insufficient_stock')));
            }
            $saleCart = $this->sellPrices->applyToLines($validated['lines'] ?? [], $companyId, $branchId, $customer);
            $this->discountGuard->assertManualDiscountAllowed($saleCart, $invoiceDiscount);
            $customerIdPre = !empty($customer['id']) ? (int) $customer['id'] : (int) ($original['customer_id'] ?? 0);
            $preSalePricing = $this->pricing->calculate($saleCart, $invoiceDiscount, (float) ($scope['tax_rate'] ?? 0.15));
            $couponCode = trim((string) ($scope['coupon_code'] ?? ''));
            $rewardBundle = $this->rewards->buildRewardDiscounts(
                $invoiceDiscount,
                $couponCode !== '' ? $couponCode : null,
                (float) ($scope['points_redeem'] ?? 0),
                $companyId,
                $customerIdPre,
                (float) ($preSalePricing['net_subtotal'] ?? $preSalePricing['subtotal'] ?? 0)
            );
            $salePricing = $this->pricing->calculate(
                $saleCart,
                $rewardBundle['invoice_discount'],
                (float) ($scope['tax_rate'] ?? 0.15)
            );
            $netTotal = round((float) $salePricing['total'] - (float) $returnPricing['total'], 2);

            if ($netTotal > 0.02) {
                $this->assertPayments($netTotal, $payments, $companyId);
            } elseif ($netTotal < -0.02) {
                $this->assertRefunds(abs($netTotal), $refunds);
            } else {
                $payments = [];
                $refunds = [];
            }

            $orderNo = (new PosOrder())->generateDocumentCode(PosDocumentCodes::ORDER, 'order_no');
            $customerId = $customerIdPre > 0 ? $customerIdPre : null;
            $orderId = (new PosOrder())->create([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'warehouse_id' => (int) ($scope['warehouse_id'] ?? 0) ?: null,
                'terminal_id' => (int) ($scope['terminal_id'] ?? 0) ?: null,
                'shift_id' => (int) ($scope['shift_id'] ?? 0) ?: null,
                'session_id' => (int) ($scope['session_id'] ?? 0) ?: null,
                'order_no' => $orderNo,
                'order_type' => 'exchange',
                'status' => 'processing',
                'customer_id' => $customerId,
                'original_order_id' => $originalOrderId,
                'created_by' => $userId,
                'subtotal' => round((float) $salePricing['subtotal'] - (float) $returnPricing['subtotal'], 2),
                'discount_total' => (float) $salePricing['discount_total'],
                'coupon_code' => $rewardBundle['coupon_code'],
                'tax' => round((float) $salePricing['tax'] - (float) $returnPricing['tax'], 2),
                'total' => $netTotal,
            ]);

            $returnStock = $this->persistReturnLines($orderId, $companyId, $originalOrderId, $returnPricing, $returnCart);
            $saleStock = $this->persistSaleLines($orderId, $companyId, $salePricing, $saleCart);

            $this->inventory->postReturnForOrderInTransaction(
                $orderId,
                $orderNo,
                $companyId,
                (int) ($scope['warehouse_id'] ?? 0) ?: null,
                $returnStock
            );
            $this->inventory->postSaleForOrderInTransaction(
                $orderId,
                $orderNo,
                $companyId,
                (int) ($scope['warehouse_id'] ?? 0) ?: null,
                $saleStock
            );

            $paymentRows = [];
            if ($netTotal > 0.02) {
                $paymentRows = $this->persistPayments($orderId, $companyId, $payments);
            }
            $refundRows = [];
            if ($netTotal < -0.02) {
                $refundRows = $this->refunds->assertAndPersist(
                    abs($netTotal),
                    $refunds,
                    $orderId,
                    $originalOrderId,
                    $companyId,
                    $branchId,
                    $userId,
                    $customerId
                );
            }

            if ($customerId !== null) {
                $rewardResult = $this->rewards->finalizeInTransaction(
                    $orderId,
                    $companyId,
                    $branchId,
                    $userId,
                    $customerId,
                    $payments,
                    $saleCart,
                    $salePricing,
                    $rewardBundle
                );
                (new PosOrder())->update($orderId, [
                    'loyalty_points_redeemed' => (float) ($rewardBundle['points_redeemed'] ?? 0),
                    'loyalty_points_earned' => (float) ($rewardResult['points_earned'] ?? 0),
                    'promotion_id' => $rewardResult['promotion_id'] ?? null,
                ]);
            } elseif ($payments !== []) {
                $this->rewards->finalizeInTransaction(
                    $orderId,
                    $companyId,
                    $branchId,
                    $userId,
                    null,
                    $payments,
                    $saleCart,
                    $salePricing,
                    $rewardBundle
                );
            }

            $shiftId = (int) ($scope['shift_id'] ?? 0);
            if ($shiftId > 0) {
                $cashDelta = PosCashDrawerService::sumCashAmount($paymentRows)
                    - PosCashDrawerService::sumCashRefunds($refundRows);
                if (abs($cashDelta) >= 0.0001) {
                    $this->drawers->applyCashDeltaInTransaction($shiftId, $companyId, $cashDelta);
                }
            }

            $this->rewards->reverseOnReturnInTransaction(
                $orderId,
                $original,
                (float) $returnPricing['total'],
                $companyId,
                $branchId,
                $userId,
                $customerId,
                $refundRows
            );

            $this->accounting->postExchangeInTransaction(
                $orderId,
                ['order_no' => $orderNo, 'created_at' => date('Y-m-d H:i:s')],
                $salePricing,
                $returnPricing,
                $paymentRows !== [] ? $paymentRows : $payments,
                $refundRows !== [] ? $refundRows : $refunds,
                $saleStock,
                $returnStock,
                $companyId,
                $branchId
            );

            $completedAt = date('Y-m-d H:i:s');
            $receipt = $this->receipts->buildExchange(
                ['order_no' => $orderNo, 'completed_at' => $completedAt, 'original_order_no' => $original['order_no'] ?? ''],
                $returnPricing,
                $salePricing,
                $paymentRows,
                $refundRows,
                ['customer' => $customer, 'net_total' => $netTotal]
            );

            (new PosOrder())->update($orderId, [
                'status' => 'completed',
                'completed_at' => $completedAt,
                'receipt_json' => json_encode($receipt, JSON_UNESCAPED_UNICODE),
            ]);

            $db->commit();
            $this->audit->log('pos_exchange', 'pos_order', $orderId, [
                'order_no' => $orderNo,
                'original_order_id' => $originalOrderId,
                'net_total' => $netTotal,
                'company_id' => $companyId,
            ]);

            return [
                'ok' => true,
                'order_id' => $orderId,
                'order_no' => $orderNo,
                'net_total' => $netTotal,
                'receipt' => $receipt,
            ];
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /** @param array<int, array<string, mixed>> $returnLines @param array<int, array<string, mixed>> $linesById @return array<int, array<string, mixed>> */
    private function buildReturnCart(array $returnLines, array $linesById): array
    {
        $cart = [];
        foreach ($returnLines as $req) {
            if (!is_array($req)) {
                continue;
            }
            $origLineId = (int) ($req['original_line_id'] ?? 0);
            $qty = (float) ($req['quantity'] ?? 0);
            if ($origLineId < 1 || $qty <= 0 || !isset($linesById[$origLineId])) {
                throw new \RuntimeException(__('invalid_request'));
            }
            $orig = $linesById[$origLineId];
            $cart[] = [
                'id' => bin2hex(random_bytes(8)),
                'product_id' => (int) ($orig['inventory_id'] ?? 0),
                'quantity' => $qty,
                'unit_price' => (float) ($orig['unit_price'] ?? 0),
                'original_line_id' => $origLineId,
            ];
        }
        if ($cart === []) {
            throw new \RuntimeException(__('pos_return_empty'));
        }
        return $cart;
    }

    /** @return array<int, array<string, mixed>> */
    private function persistReturnLines(
        int $orderId,
        int $companyId,
        int $originalOrderId,
        array $returnPricing,
        array $returnCart
    ): array {
        $originalLines = $this->queries->orderLines($originalOrderId, $companyId);
        $byId = [];
        foreach ($originalLines as $ol) {
            $byId[(int) ($ol['id'] ?? 0)] = $ol;
        }
        $stock = [];
        $lineNo = 1;
        $cartById = [];
        foreach ($returnCart as $c) {
            $cartById[(string) ($c['id'] ?? '')] = $c;
        }
        foreach ($returnPricing['lines'] as $priced) {
            $cartLine = $cartById[(string) ($priced['id'] ?? '')] ?? null;
            if ($cartLine === null) {
                continue;
            }
            $origLineId = (int) ($cartLine['original_line_id'] ?? 0);
            $orig = $byId[$origLineId] ?? null;
            if ($orig === null) {
                continue;
            }
            $returnQty = (float) ($priced['quantity'] ?? 0);
            $batchRestorations = $this->queries->computeReturnBatchAllocations($origLineId, $returnQty, $companyId);
            $returnBatchJson = $this->encodeReturnBatchJson($orig, $returnQty, $batchRestorations);
            $orderLineId = (new PosOrderLine())->create([
                'order_id' => $orderId,
                'company_id' => $companyId,
                'inventory_id' => (int) ($orig['inventory_id'] ?? 0),
                'batch_id' => (int) ($orig['batch_id'] ?? 0) ?: null,
                'batch_allocations_json' => $returnBatchJson,
                'serial_no' => trim((string) ($orig['serial_no'] ?? '')) ?: null,
                'serial_id' => (int) ($orig['serial_id'] ?? 0) ?: null,
                'line_no' => $lineNo++,
                'line_kind' => 'return',
                'original_line_id' => $origLineId,
                'description' => (string) ($orig['description'] ?? ''),
                'quantity' => $returnQty,
                'unit_price' => (float) ($priced['unit_price'] ?? 0),
                'discount_amount' => (float) ($priced['discount_amount'] ?? 0),
                'tax_amount' => (float) ($priced['tax_amount'] ?? 0),
                'line_total' => (float) ($priced['line_total'] ?? 0),
            ]);
            $stock[] = [
                'inventory_id' => (int) ($orig['inventory_id'] ?? 0),
                'quantity' => $returnQty,
                'serial_no' => (string) ($orig['serial_no'] ?? ''),
                'original_line_id' => $origLineId,
                'order_line_id' => $orderLineId,
                'batch_restorations' => $batchRestorations,
            ];
        }
        return $stock;
    }

    /** @return array<int, array<string, mixed>> */
    private function persistSaleLines(int $orderId, int $companyId, array $salePricing, array $saleCart): array
    {
        $stock = [];
        $lineNo = 100;
        foreach ($salePricing['lines'] as $priced) {
            $cartLine = null;
            foreach ($saleCart as $c) {
                if ((string) ($c['id'] ?? '') === (string) ($priced['id'] ?? '')) {
                    $cartLine = $c;
                    break;
                }
            }
            if ($cartLine === null) {
                continue;
            }
            $batchJson = $this->encodeSaleBatchJson($cartLine);
            $orderLineId = (new PosOrderLine())->create([
                'order_id' => $orderId,
                'company_id' => $companyId,
                'inventory_id' => (int) ($cartLine['product_id'] ?? 0),
                'batch_id' => $this->primaryBatch($cartLine),
                'batch_allocations_json' => $batchJson,
                'serial_no' => trim((string) ($cartLine['serial_no'] ?? '')) ?: null,
                'line_no' => $lineNo++,
                'line_kind' => 'sale',
                'description' => trim((string) ($cartLine['item_name'] ?? '')),
                'quantity' => (float) ($priced['quantity'] ?? 0),
                'unit_price' => (float) ($priced['unit_price'] ?? 0),
                'discount_amount' => (float) ($priced['discount_amount'] ?? 0),
                'tax_amount' => (float) ($priced['tax_amount'] ?? 0),
                'line_total' => (float) ($priced['line_total'] ?? 0),
            ]);
            $stock[] = array_merge($cartLine, [
                'product_id' => (int) ($cartLine['product_id'] ?? 0),
                'order_line_id' => $orderLineId,
                'quantity' => (float) ($priced['quantity'] ?? 0),
            ]);
        }
        return $stock;
    }

    /** @param array<string, mixed> $line */
    private function primaryBatch(array $line): ?int
    {
        $alloc = $line['batch_allocations'] ?? ($line['batch_preview']['allocations'] ?? []);
        if (!is_array($alloc) || $alloc === []) {
            return null;
        }
        $id = (int) ($alloc[0]['batch_id'] ?? 0);
        return $id > 0 ? $id : null;
    }

    /** @param array<string, mixed> $cartLine */
    private function encodeSaleBatchJson(array $cartLine): ?string
    {
        $alloc = $cartLine['batch_allocations'] ?? ($cartLine['batch_preview']['allocations'] ?? []);
        if (!is_array($alloc) || $alloc === []) {
            return null;
        }
        return json_encode($alloc, JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param array<string, mixed> $orig
     * @param array<int, array<string, mixed>> $restorations
     */
    private function encodeReturnBatchJson(array $orig, float $returnQty, array $restorations): ?string
    {
        if ($restorations !== []) {
            return json_encode($restorations, JSON_UNESCAPED_UNICODE);
        }
        $batchId = (int) ($orig['batch_id'] ?? 0);
        if ($batchId > 0 && $returnQty > 0) {
            return json_encode([['batch_id' => $batchId, 'quantity' => round($returnQty, 3)]], JSON_UNESCAPED_UNICODE);
        }
        return null;
    }

    /** @param array<int, array<string, mixed>> $payments */
    private function assertPayments(float $total, array $payments, int $companyId = 0): void
    {
        $sum = 0.0;
        foreach ($payments as $p) {
            if (!is_array($p)) {
                continue;
            }
            $method = strtolower(trim((string) ($p['method'] ?? '')));
            if (!in_array($method, self::PAYMENT_METHODS, true)) {
                throw new \RuntimeException(__('pos_payment_invalid_method'));
            }
            $amount = (float) ($p['amount'] ?? 0);
            if ($amount <= 0) {
                throw new \RuntimeException(__('pos_payment_invalid_amount'));
            }
            if ($method === 'gift_card' && $companyId > 0) {
                $code = trim((string) ($p['reference_no'] ?? $p['gift_card_code'] ?? ''));
                $check = $this->rewards->validateGiftCard($code, $companyId, $amount);
                if (!$check['ok']) {
                    throw new \RuntimeException((string) ($check['error'] ?? __('pos_gift_card_invalid')));
                }
            }
            $sum += $amount;
        }
        if (abs($sum - $total) > 0.02) {
            throw new \RuntimeException(__('pos_payment_mismatch'));
        }
    }

    /** @param array<int, array<string, mixed>> $refunds */
    private function assertRefunds(float $total, array $refunds): void
    {
        $sum = 0.0;
        foreach ($refunds as $r) {
            if (!is_array($r)) {
                continue;
            }
            $sum += (float) ($r['amount'] ?? 0);
        }
        if (abs($sum - $total) > 0.02) {
            throw new \RuntimeException(__('pos_refund_mismatch'));
        }
    }

    /** @param array<int, array<string, mixed>> $payments @return array<int, array<string, mixed>> */
    private function persistPayments(int $orderId, int $companyId, array $payments): array
    {
        $rows = [];
        foreach ($payments as $payment) {
            if (!is_array($payment)) {
                continue;
            }
            $method = strtolower(trim((string) ($payment['method'] ?? 'cash')));
            $id = (new PosPayment())->create([
                'order_id' => $orderId,
                'company_id' => $companyId,
                'payment_method' => $method,
                'amount' => round((float) ($payment['amount'] ?? 0), 2),
                'reference_no' => trim((string) ($payment['reference_no'] ?? '')) ?: null,
            ]);
            $rows[] = ['id' => $id, 'payment_method' => $method, 'amount' => round((float) ($payment['amount'] ?? 0), 2)];
        }
        return $rows;
    }
}
