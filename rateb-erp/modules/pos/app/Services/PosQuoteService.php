<?php
declare(strict_types=1);

namespace Rateb\App\Pos\Services;

use Rateb\App\Core\Database;
use Rateb\App\Core\TenantContext;
use Rateb\App\Pos\Models\PosOrder;
use Rateb\App\Pos\Models\PosOrderLine;
use Rateb\App\Pos\Services\Bridge\PosAuditBridgeService;
use Rateb\App\Pos\Support\PosDocumentCodes;

/** Quote lifecycle — save, list, convert (no stock until convert). */
final class PosQuoteService
{
    public function __construct(
        private PosRegisterCartService $cart = new PosRegisterCartService(),
        private PosSellPriceService $sellPrices = new PosSellPriceService(),
        private PosPricingService $pricing = new PosPricingService(),
        private PosCheckoutService $checkout = new PosCheckoutService(),
        private PosAuditBridgeService $audit = new PosAuditBridgeService(),
    ) {
    }

    /**
     * @param array<int, array<string, mixed>> $lines
     * @param array<string, mixed> $scope
     */
    public function save(
        array $lines,
        array $scope,
        ?array $customer = null,
        int $validDays = 30,
        string $notes = ''
    ): array {
        $companyId = (int) ($scope['company_id'] ?? 0);
        $branchId = (int) ($scope['branch_id'] ?? 0);
        $userId = (int) ($scope['user_id'] ?? 0);
        if ($companyId < 1 || $branchId < 1 || $userId < 1) {
            throw new \RuntimeException(__('invalid_request'));
        }
        $normalized = $this->cart->normalizeLines($lines);
        if ($normalized === []) {
            throw new \RuntimeException(__('pos_cart_empty'));
        }
        TenantContext::setCompanyId($companyId);
        $priced = $this->sellPrices->applyToLines($normalized, $companyId, $branchId, $customer);
        $totals = $this->pricing->calculate($priced);
        $expires = date('Y-m-d H:i:s', time() + max(1, $validDays) * 86400);

        $db = Database::connection();
        $db->beginTransaction();
        try {
            $orderNo = (new PosOrder())->generateDocumentCode(PosDocumentCodes::QUOTE, 'order_no');
            $orderId = (new PosOrder())->create([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'warehouse_id' => (int) ($scope['warehouse_id'] ?? 0) ?: null,
                'terminal_id' => (int) ($scope['terminal_id'] ?? 0) ?: null,
                'order_no' => $orderNo,
                'order_type' => 'quote',
                'status' => 'draft',
                'customer_id' => !empty($customer['id']) ? (int) $customer['id'] : null,
                'created_by' => $userId,
                'notes' => $notes !== '' ? $notes : null,
                'quote_expires_at' => $expires,
                'suspended_payload' => json_encode(['lines' => $priced, 'customer' => $customer], JSON_UNESCAPED_UNICODE),
                'subtotal' => $totals['subtotal'],
                'discount_total' => $totals['discount_total'],
                'tax' => $totals['tax'],
                'total' => $totals['total'],
            ]);

            $lineNo = 1;
            foreach ($priced as $line) {
                (new PosOrderLine())->create([
                    'order_id' => $orderId,
                    'company_id' => $companyId,
                    'inventory_id' => (int) ($line['product_id'] ?? 0),
                    'line_no' => $lineNo++,
                    'line_kind' => 'sale',
                    'description' => trim((string) ($line['item_name'] ?? '')),
                    'quantity' => (float) ($line['quantity'] ?? 0),
                    'unit_price' => (float) ($line['unit_price'] ?? 0),
                    'line_total' => (float) ($line['line_total'] ?? 0),
                ]);
            }

            $db->commit();
            $this->audit->log('pos_quote_save', 'pos_order', $orderId, [
                'order_no' => $orderNo,
                'expires' => $expires,
                'company_id' => $companyId,
            ]);

            return ['ok' => true, 'order_id' => $orderId, 'order_no' => $orderNo, 'expires_at' => $expires, 'totals' => $totals];
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /** @return array<int, array<string, mixed>> */
    public function listQuotes(int $companyId, int $branchId, int $limit = 50): array
    {
        if ($companyId < 1 || $branchId < 1) {
            return [];
        }
        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT id, order_no, total, customer_id, quote_expires_at, created_at
             FROM rateb_pos_orders
             WHERE company_id = :cid AND branch_id = :bid AND order_type = :ot AND status = :st
             ORDER BY id DESC LIMIT ' . max(1, min(100, $limit))
        );
        $stmt->execute(['cid' => $companyId, 'bid' => $branchId, 'ot' => 'quote', 'st' => 'draft']);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @param array<int, array<string, mixed>> $payments
     * @param array<string, mixed> $scope
     */
    public function convert(
        int $quoteId,
        array $payments,
        array $scope,
        array $invoiceDiscount = [],
        bool $giftReceipt = false
    ): array {
        $companyId = (int) ($scope['company_id'] ?? 0);
        $branchId = (int) ($scope['branch_id'] ?? 0);
        if ($companyId < 1 || $branchId < 1 || $quoteId < 1) {
            throw new \RuntimeException(__('invalid_request'));
        }

        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT * FROM rateb_pos_orders WHERE id = :id AND company_id = :cid AND branch_id = :bid
             AND order_type = :ot AND status = :st LIMIT 1'
        );
        $stmt->execute(['id' => $quoteId, 'cid' => $companyId, 'bid' => $branchId, 'ot' => 'quote', 'st' => 'draft']);
        $quote = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$quote) {
            throw new \RuntimeException(__('no_records'));
        }
        $expires = (string) ($quote['quote_expires_at'] ?? '');
        if ($expires !== '' && strtotime($expires) < time()) {
            throw new \RuntimeException(__('pos_quote_expired'));
        }

        $payload = json_decode((string) ($quote['suspended_payload'] ?? ''), true);
        $lines = is_array($payload['lines'] ?? null) ? $payload['lines'] : [];
        $customer = is_array($payload['customer'] ?? null) ? $payload['customer'] : null;
        if ($lines === []) {
            throw new \RuntimeException(__('invalid_request'));
        }

        $scope['gift_receipt'] = $giftReceipt;
        $result = $this->checkout->complete($lines, $payments, $invoiceDiscount, $scope, $customer, 0.15, $giftReceipt);

        (new PosOrder())->update($quoteId, [
            'status' => 'void',
            'linked_order_id' => (int) ($result['order_id'] ?? 0),
            'notes' => 'Converted to ' . ($result['order_no'] ?? ''),
        ]);

        $this->audit->log('pos_quote_convert', 'pos_order', $quoteId, [
            'linked_order_id' => (int) ($result['order_id'] ?? 0),
            'company_id' => $companyId,
        ]);

        return $result;
    }
}
