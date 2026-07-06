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



/** Atomic POS checkout — single DB transaction for order, stock, payments. */

final class PosCheckoutService

{

    private const PAYMENT_METHODS = ['cash', 'card', 'bank', 'wallet', 'gift_card'];



    public function __construct(

        private PosInventoryBridgeService $inventory = new PosInventoryBridgeService(),

        private PosPricingService $pricing = new PosPricingService(),

        private PosReceiptService $receipts = new PosReceiptService(),

        private PosInventoryReservationService $reservations = new PosInventoryReservationService(),

        private PosAuditBridgeService $audit = new PosAuditBridgeService(),

        private PosSellPriceService $sellPrices = new PosSellPriceService(),

        private PosAccountingBridgeService $accounting = new PosAccountingBridgeService(),

        private PosRewardService $rewards = new PosRewardService(),
        private PosDiscountGuardService $discountGuard = new PosDiscountGuardService(),
        private PosCashDrawerService $drawers = new PosCashDrawerService(),
        private PosCheckoutPricingResolver $pricingResolver = new PosCheckoutPricingResolver(),
    ) {

    }



    /**

     * @param array<int, array<string, mixed>> $cartLines

     * @param array<int, array<string, mixed>> $payments

     * @param array<string, mixed> $invoiceDiscount

     * @param array<string, mixed> $scope company_id, branch_id, warehouse_id, terminal_id, shift_id, session_id, user_id

     * @return array<string, mixed>

     */

    public function complete(

        array $cartLines,

        array $payments,

        array $invoiceDiscount,

        array $scope,

        ?array $customer = null,

        float $taxRate = 0.15,

        bool $giftReceipt = false

    ): array {

        $companyId = (int) ($scope['company_id'] ?? 0);

        $branchId = (int) ($scope['branch_id'] ?? 0);

        $userId = (int) ($scope['user_id'] ?? 0);

        if ($companyId < 1 || $branchId < 1 || $userId < 1) {

            throw new \RuntimeException(__('invalid_request'));

        }

        TenantContext::setCompanyId($companyId);

        if (!$giftReceipt) {
            $giftReceipt = !empty($scope['gift_receipt']);
        }



        $db = Database::connection();

        $db->beginTransaction();

        try {

            $idempotencyKey = trim((string) ($scope['idempotency_key'] ?? ''));
            if ($idempotencyKey !== '') {
                $existing = $this->findCompletedOrderByIdempotencyKey($companyId, $idempotencyKey, true);
                if ($existing !== null) {
                    $db->commit();
                    return $this->buildIdempotentCheckoutResponse($existing, $companyId);
                }
            }

            $this->discountGuard->assertManualDiscountAllowed($cartLines, $invoiceDiscount);

            $validated = $this->inventory->revalidateForCheckout(

                $cartLines,

                $companyId,

                (int) ($scope['warehouse_id'] ?? 0) ?: null,

                $branchId,

                (int) ($scope['session_id'] ?? 0) ?: null,

                true

            );

            if (!$validated['ok']) {

                throw new \RuntimeException((string) ($validated['error'] ?? __('pos_insufficient_stock')));

            }

            $lines = $validated['lines'] ?? [];

            if ($lines === []) {

                throw new \RuntimeException(__('pos_cart_empty'));

            }



            $priced = $this->pricingResolver->resolve($lines, $invoiceDiscount, $scope, $customer, $taxRate);
            $lines = $priced['lines'];
            $pricing = $priced['pricing'];
            $rewardBundle = $priced['reward_bundle'];

            $this->assertPayments($pricing['total'], $payments, $companyId);



            $orderNo = (new PosOrder())->generateDocumentCode(PosDocumentCodes::ORDER, 'order_no');

            try {
                $orderId = (new PosOrder())->create([

                    'company_id' => $companyId,

                    'branch_id' => $branchId,

                    'warehouse_id' => (int) ($scope['warehouse_id'] ?? 0) ?: null,

                    'terminal_id' => (int) ($scope['terminal_id'] ?? 0) ?: null,

                    'shift_id' => (int) ($scope['shift_id'] ?? 0) ?: null,

                    'session_id' => (int) ($scope['session_id'] ?? 0) ?: null,

                    'order_no' => $orderNo,

                    'idempotency_key' => $idempotencyKey !== '' ? $idempotencyKey : null,

                    'order_type' => 'sale',

                    'status' => 'processing',

                    'customer_id' => !empty($customer['id']) ? (int) $customer['id'] : null,

                    'gift_receipt' => $giftReceipt ? 1 : 0,

                    'subtotal' => $pricing['subtotal'],

                    'discount_total' => $pricing['discount_total'],

                    'coupon_code' => $rewardBundle['coupon_code'],

                    'tax' => $pricing['tax'],

                    'total' => $pricing['total'],

                ]);
            } catch (\PDOException $e) {
                if ($idempotencyKey !== '' && $this->isDuplicateIdempotencyKey($e)) {
                    if ($db->inTransaction()) {
                        $db->rollBack();
                    }
                    return $this->resolveIdempotentCheckout($companyId, $idempotencyKey);
                }
                throw $e;
            }



            $lineNo = 1;

            $orderLines = [];

            foreach ($pricing['lines'] as $pricedLine) {

                $cartLine = $this->findCartLine($lines, (string) ($pricedLine['id'] ?? ''));

                if ($cartLine === null) {

                    continue;

                }

                $batchId = $this->primaryBatchId($cartLine);

                $batchAllocJson = $this->encodeBatchAllocations($cartLine);

                $orderLineId = (new PosOrderLine())->create([

                    'order_id' => $orderId,

                    'company_id' => $companyId,

                    'inventory_id' => (int) ($cartLine['product_id'] ?? 0),

                    'batch_id' => $batchId,

                    'batch_allocations_json' => $batchAllocJson,

                    'serial_no' => trim((string) ($cartLine['serial_no'] ?? '')) ?: null,

                    'serial_id' => $this->resolveSerialId($cartLine, $companyId),

                    'line_no' => $lineNo++,

                    'description' => trim((string) ($cartLine['item_name'] ?? '')),

                    'quantity' => (float) ($pricedLine['quantity'] ?? 0),

                    'unit_price' => (float) ($pricedLine['unit_price'] ?? 0),

                    'discount_amount' => (float) ($pricedLine['discount_amount'] ?? 0),

                    'tax_amount' => (float) ($pricedLine['tax_amount'] ?? 0),

                    'line_total' => (float) ($pricedLine['line_total'] ?? 0),

                ]);

                $orderLines[] = array_merge($cartLine, [

                    'order_line_id' => $orderLineId,

                    'batch_id' => $batchId,

                    'discount_amount' => (float) ($pricedLine['discount_amount'] ?? 0),

                    'tax_amount' => (float) ($pricedLine['tax_amount'] ?? 0),

                    'line_total' => (float) ($pricedLine['line_total'] ?? 0),

                ]);

            }



            $paymentRows = $this->persistPayments($orderId, $companyId, $payments);

            $customerId = !empty($customer['id']) ? (int) $customer['id'] : 0;

            $shiftId = (int) ($scope['shift_id'] ?? 0);
            if ($shiftId > 0) {
                $cashIn = PosCashDrawerService::sumCashAmount($paymentRows);
                if ($cashIn > 0) {
                    $this->drawers->applyCashDeltaInTransaction($shiftId, $companyId, $cashIn);
                }
            }

            $rewardResult = $this->rewards->finalizeInTransaction(
                $orderId,
                $companyId,
                $branchId,
                $userId,
                $customerId > 0 ? $customerId : null,
                $payments,
                $lines,
                $pricing,
                $rewardBundle
            );

            (new PosOrder())->update($orderId, [
                'loyalty_points_redeemed' => (float) ($rewardBundle['points_redeemed'] ?? 0),
                'loyalty_points_earned' => (float) ($rewardResult['points_earned'] ?? 0),
                'promotion_id' => $rewardResult['promotion_id'] ?? null,
            ]);



            $this->inventory->postSaleForOrderInTransaction(

                $orderId,

                $orderNo,

                $companyId,

                (int) ($scope['warehouse_id'] ?? 0) ?: null,

                $orderLines

            );



            $this->accounting->postSaleInTransaction(
                $orderId,
                ['order_no' => $orderNo, 'created_at' => date('Y-m-d H:i:s')],
                $pricing,
                $paymentRows,
                $orderLines,
                $companyId,
                $branchId
            );



            $completedAt = date('Y-m-d H:i:s');

            $orderRow = (new PosOrder())->find($orderId) ?? [];

            $storedLines = $this->loadOrderLines($orderId, $companyId);

            $receipt = $this->receipts->build(

                array_merge($orderRow, ['completed_at' => $completedAt]),

                $storedLines,

                $paymentRows,

                $pricing,

                ['customer' => $customer, 'gift_receipt' => $giftReceipt]

            );



            (new PosOrder())->update($orderId, [

                'status' => 'completed',

                'completed_at' => $completedAt,

                'receipt_json' => json_encode($receipt, JSON_UNESCAPED_UNICODE),

            ]);



            $sessionId = (int) ($scope['session_id'] ?? 0);

            if ($sessionId > 0) {

                $this->reservations->releaseSession($companyId, $sessionId, false);

            }



            $db->commit();



            $this->audit->log('pos_checkout', 'pos_order', $orderId, [

                'order_no' => $orderNo,

                'total' => $pricing['total'],

                'company_id' => $companyId,

            ]);



            return [

                'ok' => true,

                'order_id' => $orderId,

                'order_no' => $orderNo,

                'pricing' => $pricing,

                'receipt' => $receipt,

            ];

        } catch (\Throwable $e) {

            if ($db->inTransaction()) {

                $db->rollBack();

            }

            throw $e;

        }

    }



    /**
     * @param array<int, array<string, mixed>> $cartLines
     * @param array<string, mixed> $invoiceDiscount
     * @param array<string, mixed> $scope company_id, branch_id, coupon_code?, points_redeem?
     * @return array{pricing: array<string, mixed>, lines: array<int, array<string, mixed>>, reward_bundle: array<string, mixed>}
     */
    public function resolvePricing(
        array $cartLines,
        array $invoiceDiscount,
        array $scope,
        ?array $customer = null,
        float $taxRate = 0.15,
    ): array {
        return $this->pricingResolver->resolve($cartLines, $invoiceDiscount, $scope, $customer, $taxRate);
    }



    /** @param array<int, array<string, mixed>> $payments */

    private function assertPayments(float $total, array $payments, int $companyId = 0): void

    {

        if ($total <= 0) {

            throw new \RuntimeException(__('invalid_request'));

        }

        $sum = 0.0;

        foreach ($payments as $payment) {

            if (!is_array($payment)) {

                continue;

            }

            $method = strtolower(trim((string) ($payment['method'] ?? '')));

            if (!in_array($method, self::PAYMENT_METHODS, true)) {

                throw new \RuntimeException(__('pos_payment_invalid_method'));

            }

            $amount = (float) ($payment['amount'] ?? 0);

            if ($amount <= 0) {

                throw new \RuntimeException(__('pos_payment_invalid_amount'));

            }

            if ($method === 'gift_card' && $companyId > 0) {

                $code = trim((string) ($payment['reference_no'] ?? $payment['gift_card_code'] ?? ''));

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

            $rows[] = [

                'id' => $id,

                'payment_method' => $method,

                'amount' => round((float) ($payment['amount'] ?? 0), 2),

                'reference_no' => trim((string) ($payment['reference_no'] ?? '')),

            ];

        }

        return $rows;

    }



    /** @param array<int, array<string, mixed>> $lines */

    private function findCartLine(array $lines, string $lineId): ?array

    {

        foreach ($lines as $line) {

            if ((string) ($line['id'] ?? '') === $lineId) {

                return $line;

            }

        }

        return null;

    }



    /** @param array<string, mixed> $cartLine */

    private function primaryBatchId(array $cartLine): ?int

    {

        $alloc = $cartLine['batch_allocations'] ?? null;

        if (!is_array($alloc) || $alloc === []) {

            $preview = $cartLine['batch_preview'] ?? null;

            if (!is_array($preview)) {

                return null;

            }

            $alloc = $preview['allocations'] ?? [];

        }

        if (!is_array($alloc) || $alloc === []) {

            return null;

        }

        $first = $alloc[0];

        $id = (int) ($first['batch_id'] ?? 0);

        return $id > 0 ? $id : null;

    }



    /** @param array<string, mixed> $cartLine */

    private function resolveSerialId(array $cartLine, int $companyId): ?int

    {

        $serialNo = trim((string) ($cartLine['serial_no'] ?? ''));

        if ($serialNo === '') {

            return null;

        }

        $db = Database::connection();

        $stmt = $db->prepare(

            'SELECT id FROM rateb_inventory_serials WHERE company_id = :cid AND serial_no = :sn LIMIT 1'

        );

        $stmt->execute(['cid' => $companyId, 'sn' => $serialNo]);

        $id = $stmt->fetchColumn();

        return $id ? (int) $id : null;

    }

    /** @param array<string, mixed> $cartLine */
    private function encodeBatchAllocations(array $cartLine): ?string
    {
        $alloc = $cartLine['batch_allocations'] ?? null;
        if (!is_array($alloc) || $alloc === []) {
            $preview = $cartLine['batch_preview'] ?? null;
            if (is_array($preview)) {
                $alloc = $preview['allocations'] ?? [];
            }
        }
        if (!is_array($alloc) || $alloc === []) {
            return null;
        }
        return json_encode($alloc, JSON_UNESCAPED_UNICODE);
    }



    /** @return array<int, array<string, mixed>> */

    private function loadOrderLines(int $orderId, int $companyId): array

    {

        $db = Database::connection();

        $stmt = $db->prepare(

            'SELECT * FROM rateb_pos_order_lines WHERE order_id = :oid AND company_id = :cid ORDER BY line_no'

        );

        $stmt->execute(['oid' => $orderId, 'cid' => $companyId]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

    }

    /** @return array<string, mixed>|null */
    private function findCompletedOrderByIdempotencyKey(int $companyId, string $key, bool $forUpdate = false): ?array
    {
        $sql = 'SELECT * FROM rateb_pos_orders WHERE company_id = :cid AND idempotency_key = :k LIMIT 1';
        if ($forUpdate) {
            $sql = 'SELECT * FROM rateb_pos_orders WHERE company_id = :cid AND idempotency_key = :k LIMIT 1 FOR UPDATE';
        }
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['cid' => $companyId, 'k' => $key]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        if ((string) ($row['status'] ?? '') === 'completed') {
            return $row;
        }
        if ((string) ($row['status'] ?? '') === 'processing') {
            throw new \RuntimeException(__('pos_checkout_in_progress'));
        }
        return null;
    }

    /** @param array<string, mixed> $orderRow @return array<string, mixed> */
    private function buildIdempotentCheckoutResponse(array $orderRow, int $companyId): array
    {
        $orderId = (int) ($orderRow['id'] ?? 0);
        $receiptJson = $orderRow['receipt_json'] ?? null;
        $receipt = is_string($receiptJson) ? json_decode($receiptJson, true) : (is_array($receiptJson) ? $receiptJson : []);
        return [
            'ok' => true,
            'order_id' => $orderId,
            'order_no' => (string) ($orderRow['order_no'] ?? ''),
            'idempotent' => true,
            'receipt' => is_array($receipt) ? $receipt : [],
        ];
    }

    /** @return array<string, mixed> */
    private function resolveIdempotentCheckout(int $companyId, string $key): array
    {
        $existing = $this->findCompletedOrderByIdempotencyKey($companyId, $key, false);
        if ($existing !== null) {
            return $this->buildIdempotentCheckoutResponse($existing, $companyId);
        }
        throw new \RuntimeException(__('pos_checkout_in_progress'));
    }

    private function isDuplicateIdempotencyKey(\PDOException $e): bool
    {
        if ((string) $e->getCode() === '23000') {
            return true;
        }
        $msg = strtolower($e->getMessage());
        return str_contains($msg, 'uq_pos_order_idempotency') || str_contains($msg, 'duplicate');
    }

}


