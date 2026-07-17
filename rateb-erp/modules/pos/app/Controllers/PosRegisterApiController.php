<?php
declare(strict_types=1);

namespace Rateb\App\Pos\Controllers;

use Rateb\App\Core\Csrf;
use Rateb\App\Pos\Services\Bridge\PosBarcodeLookupBridgeService;
use Rateb\App\Pos\Services\Bridge\PosCustomerBridgeService;
use Rateb\App\Pos\Services\Bridge\PosInventoryBridgeService;
use Rateb\App\Pos\Services\PosDiscountGuardService;
use Rateb\App\Pos\Services\PosCashDrawerService;
use Rateb\App\Pos\Services\PosCheckoutService;
use Rateb\App\Pos\Services\PosContextService;
use Rateb\App\Pos\Services\PosHardwareManager;
use Rateb\App\Pos\Services\PosInventoryReservationService;
use Rateb\App\Pos\Services\PosPricingService;
use Rateb\App\Pos\Services\PosRegisterBootstrapService;
use Rateb\App\Pos\Services\PosRegisterCartService;
use Rateb\App\Pos\Services\PosReportService;
use Rateb\App\Pos\Services\PosRewardService;
use Rateb\App\Pos\Services\PosSellPriceService;
use Rateb\App\Pos\Services\PosSessionService;
use Rateb\App\Pos\Services\PosTaxSettingsService;

/** Register JSON API — cart, inventory lookup, reservations (no order completion). */
final class PosRegisterApiController extends PosBaseController
{
    public function registerBootstrap(): void
    {
        $this->bootstrapPos();
        $this->guardPosView('pos/register');
        $context = (new PosContextService())->snapshot();
        $catalog = (new PosRegisterBootstrapService())->catalogPayload($context);
        $this->json(['ok' => true] + $catalog);
    }

    public function sessionGet(): void
    {
        $this->bootstrapPos();
        $this->guardPosView('pos/register');
        $scope = $this->registerScope();
        (new PosInventoryReservationService())->expireStale();
        $context = new PosContextService();
        $context->syncRegisterFromOpenShift($scope['company_id'], $scope['user_id']);

        $session = new PosSessionService();
        $lines = (new PosRegisterCartService())->normalizeLines($session->getCartLines());

        $this->json([
            'ok' => true,
            'session' => $session->snapshot(),
            'context' => $context->snapshot(),
            'totals' => (new PosRegisterCartService())->totals(
                $lines,
                $scope['company_id'],
                $scope['branch_id'],
                $session->getCustomer()
            ),
        ]);
    }

    public function sessionSave(): void
    {
        $this->bootstrapPos();
        $this->guardPosView('pos/register');
        if (!Csrf::validate($_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
            $this->json(['ok' => false, 'error' => __('invalid_request')], 419);
            return;
        }

        $scope = $this->registerScope();
        $payload = $this->inputData();
        $lines = $this->decodeLines($payload);

        $session = new PosSessionService();
        $this->ensureDbSession($session, $scope);
        $scope = $this->registerScope();

        $inventory = new PosInventoryBridgeService();
        $validated = $inventory->validateAndSyncCart(
            $lines,
            $scope['company_id'],
            $scope['branch_id'],
            $scope['warehouse_id'],
            $scope['session_id']
        );

        $cart = new PosRegisterCartService();
        // Soft-fail inventory: keep cashier cart (demo/catalog/offline) instead of hard 422.
        if (!$validated['ok']) {
            $normalized = $cart->normalizeLines($lines);
            $customer = $this->resolveCustomer($payload);
            $session->setCartLines($normalized);
            $session->setCustomer($customer);
            $this->json([
                'ok' => true,
                'warning' => 'inventory_sync_skipped',
                'errors' => $validated['errors'] ?? [],
                'session' => $session->snapshot(),
                'lines' => $normalized,
                'totals' => $cart->totals($normalized),
            ]);
            return;
        }

        $normalized = $cart->normalizeLines($validated['lines'] ?? []);

        $customer = $this->resolveCustomer($payload);
        $session->setCartLines($normalized);
        $session->setCustomer($customer);

        $this->json([
            'ok' => true,
            'session' => $session->snapshot(),
            'lines' => $normalized,
            'totals' => $cart->totals($normalized),
        ]);
    }

    public function cartAdd(): void
    {
        $this->bootstrapPos();
        $this->guardPosView('pos/register');
        if (!Csrf::validate($_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
            $this->json(['ok' => false, 'error' => __('invalid_request')], 419);
            return;
        }

        $scope = $this->registerScope();
        $payload = $this->inputData();
        $productId = (int) ($payload['product_id'] ?? 0);
        $qty = (float) ($payload['quantity'] ?? 1);
        $serialNo = trim((string) ($payload['serial_no'] ?? ''));

        $session = new PosSessionService();
        $this->ensureDbSession($session, $scope);
        $scope = $this->registerScope();

        $inventory = new PosInventoryBridgeService();
        $product = $inventory->getProduct(
            $productId,
            $scope['company_id'],
            $scope['warehouse_id'],
            $scope['branch_id'],
            $scope['session_id']
        );
        if ($product === null) {
            // Demo/catalog seed (990001+) or client-supplied snapshot — add without inventory row.
            $product = $this->resolveCatalogProductFallback($productId, $payload);
            if ($product === null) {
                $this->json([
                    'ok' => false,
                    'error' => __('pos_product_not_found'),
                    'fallback_local' => true,
                ], 404);
                return;
            }
            $cart = new PosRegisterCartService();
            $currentLines = $session->getCartLines();
            $result = $this->addCatalogProductLocal($cart, $currentLines, $product, $qty, $serialNo);
            if (!$result['ok']) {
                $this->json(['ok' => false, 'error' => (string) ($result['error'] ?? __('invalid_request'))], 422);
                return;
            }
            $lines = $result['lines'] ?? [];
            $session->setCartLines($lines);
            $this->json([
                'ok' => true,
                'demo' => true,
                'line' => $result['line'] ?? null,
                'lines' => $lines,
                'totals' => $cart->totals($lines),
            ]);
            return;
        }

        $cart = new PosRegisterCartService();
        $currentLines = $session->getCartLines();
        $result = $cart->addProduct(
            $currentLines,
            $product,
            $qty,
            $scope['company_id'],
            $scope['warehouse_id'],
            $scope['branch_id'],
            $scope['session_id'],
            $serialNo !== '' ? $serialNo : null
        );

        if (!$result['ok']) {
            $this->json(['ok' => false, 'error' => (string) ($result['error'] ?? __('invalid_request'))], 422);
            return;
        }

        $lines = $result['lines'] ?? [];
        $inventory->validateAndSyncCart(
            $lines,
            $scope['company_id'],
            $scope['branch_id'],
            $scope['warehouse_id'],
            $scope['session_id']
        );
        $session->setCartLines($lines);

        $this->json([
            'ok' => true,
            'line' => $result['line'] ?? null,
            'lines' => $lines,
            'totals' => $cart->totals($lines),
        ]);
    }

    public function cartUpdateLine(): void
    {
        $this->bootstrapPos();
        $this->guardPosView('pos/register');
        if (!Csrf::validate($_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
            $this->json(['ok' => false, 'error' => __('invalid_request')], 419);
            return;
        }

        $scope = $this->registerScope();
        $payload = $this->inputData();
        $lineId = trim((string) ($payload['line_id'] ?? ''));
        $qty = (float) ($payload['quantity'] ?? 0);
        $serialNo = trim((string) ($payload['serial_no'] ?? ''));
        $discountType = trim((string) ($payload['discount_type'] ?? ''));
        $discountValue = (float) ($payload['discount_value'] ?? 0);
        $lineNotes = trim((string) ($payload['notes'] ?? ''));
        $qtyProvided = array_key_exists('quantity', $payload);

        if ($lineId === '') {
            $this->json(['ok' => false, 'error' => __('invalid_request')], 400);
            return;
        }

        $session = new PosSessionService();
        $this->ensureDbSession($session, $scope);
        $scope = $this->registerScope();

        $lines = $session->getCartLines();
        $inventory = new PosInventoryBridgeService();
        $found = false;
        $newLines = [];

        foreach ($lines as $line) {
            if ((string) ($line['id'] ?? '') !== $lineId) {
                $newLines[] = $line;
                continue;
            }
            $found = true;
            if ($qtyProvided && $qty <= 0) {
                continue;
            }
            if (!$qtyProvided) {
                $qty = (float) ($line['quantity'] ?? 0);
            }
            $invId = (int) ($line['product_id'] ?? 0);
            $effectiveSerial = $serialNo !== '' ? $serialNo : trim((string) ($line['serial_no'] ?? ''));
            $check = $inventory->validateCartLine(
                $invId,
                $qty,
                $scope['company_id'],
                $scope['warehouse_id'],
                $scope['branch_id'],
                $scope['session_id'],
                $newLines,
                $lineId,
                $effectiveSerial !== '' ? $effectiveSerial : null
            );
            if (!$check['ok']) {
                $this->json(['ok' => false, 'error' => (string) ($check['error'] ?? __('invalid_request'))], 422);
                return;
            }
            $line['quantity'] = $qty;
            $line['serial_no'] = $effectiveSerial !== '' ? $effectiveSerial : null;
            $line['available_qty'] = (float) ($check['available'] ?? 0);
            $line['batch_preview'] = $check['batch_preview'] ?? [];
            if ($discountType !== '' || array_key_exists('discount_value', $payload)) {
                $line['discount_amount'] = 0;
                $line['discount_percent'] = 0;
                if ($discountValue > 0) {
                    if ($discountType === 'percent') {
                        $line['discount_percent'] = min(100, $discountValue);
                    } else {
                        $line['discount_amount'] = $discountValue;
                    }
                }
            }
            if (array_key_exists('notes', $payload)) {
                $line['notes'] = $lineNotes !== '' ? $lineNotes : null;
            }
            $gross = round($qty * (float) ($line['unit_price'] ?? 0), 2);
            $disc = (float) ($line['discount_amount'] ?? 0);
            if ($disc <= 0 && (float) ($line['discount_percent'] ?? 0) > 0) {
                $disc = round($gross * ((float) $line['discount_percent'] / 100), 2);
            }
            $line['line_total'] = max(0, round($gross - min($gross, $disc), 2));
            $newLines[] = $line;
        }

        if (!$found) {
            $this->json(['ok' => false, 'error' => __('no_records')], 404);
            return;
        }

        $lines = $newLines;
        $cart = new PosRegisterCartService();
        $normalized = $cart->normalizeLines($lines);
        $inventory->validateAndSyncCart(
            $normalized,
            $scope['company_id'],
            $scope['branch_id'],
            $scope['warehouse_id'],
            $scope['session_id']
        );
        $session->setCartLines($normalized);

        $this->json(['ok' => true, 'lines' => $normalized, 'totals' => $cart->totals($normalized)]);
    }

    public function searchCustomers(): void
    {
        $this->bootstrapPos();
        $this->guardPosView('pos/register');
        $q = trim((string) ($_GET['q'] ?? ''));
        $items = (new PosCustomerBridgeService())->search($q, 20);
        $this->json(['ok' => true, 'items' => $items]);
    }

    public function searchProducts(): void
    {
        $this->bootstrapPos();
        $this->guardPosView('pos/register');
        $scope = $this->registerScope();
        $q = trim((string) ($_GET['q'] ?? ''));
        $items = (new PosInventoryBridgeService())->searchProducts(
            $q,
            $scope['company_id'],
            $scope['warehouse_id'],
            $scope['branch_id'],
            $scope['session_id'],
            24
        );
        $this->json(['ok' => true, 'items' => $items]);
    }

    public function lookupBarcode(): void
    {
        $this->bootstrapPos();
        $this->guardPosView('pos/register');
        $scope = $this->registerScope();
        $code = trim((string) ($_GET['code'] ?? $_GET['barcode'] ?? ''));
        if ($code === '') {
            $this->json(['ok' => false, 'error' => __('invalid_request')], 400);
            return;
        }
        $item = (new PosBarcodeLookupBridgeService())->lookupInventoryBarcode(
            $code,
            $scope['company_id'],
            $scope['warehouse_id'],
            $scope['branch_id'],
            $scope['session_id']
        );
        if ($item === null) {
            $this->json(['ok' => false, 'error' => __('pos_product_not_found')], 404);
            return;
        }
        $this->json(['ok' => true, 'item' => $item]);
    }

    public function productDetail(): void
    {
        $this->bootstrapPos();
        $this->guardPosView('pos/register');
        $scope = $this->registerScope();
        $id = (int) ($_GET['id'] ?? 0);
        $item = (new PosInventoryBridgeService())->getProduct(
            $id,
            $scope['company_id'],
            $scope['warehouse_id'],
            $scope['branch_id'],
            $scope['session_id']
        );
        if ($item === null) {
            $this->json(['ok' => false, 'error' => __('pos_product_not_found')], 404);
            return;
        }
        $this->json(['ok' => true, 'item' => $item]);
    }

    public function productAvailability(): void
    {
        $this->bootstrapPos();
        $this->guardPosView('pos/register');
        $scope = $this->registerScope();
        $id = (int) ($_GET['id'] ?? 0);
        $result = (new PosInventoryBridgeService())->availabilitySnapshot(
            $id,
            $scope['company_id'],
            $scope['warehouse_id'],
            $scope['branch_id'],
            $scope['session_id']
        );
        $this->json($result, ($result['ok'] ?? false) ? 200 : 404);
    }

    public function productFefoPreview(): void
    {
        $this->bootstrapPos();
        $this->guardPosView('pos/register');
        $id = (int) ($_GET['id'] ?? 0);
        $qty = (float) ($_GET['qty'] ?? 1);
        $preview = (new PosInventoryBridgeService())->previewFefoAllocation(
            $id,
            $qty,
            $scope['company_id']
        );
        $this->json(['ok' => true, 'preview' => $preview]);
    }

    public function productSerials(): void
    {
        $this->bootstrapPos();
        $this->guardPosView('pos/register');
        $scope = $this->registerScope();
        $id = (int) ($_GET['id'] ?? 0);
        $items = (new PosInventoryBridgeService())->listAvailableSerials(
            $id,
            $scope['company_id'],
            $scope['warehouse_id'],
            $scope['branch_id'],
            100
        );
        $this->json(['ok' => true, 'items' => $items, 'read_only' => true]);
    }

    public function pricingPreview(): void
    {
        $this->bootstrapPos();
        $this->guardPosView('pos/register');
        $scope = $this->registerScope();
        $payload = $this->inputData();
        $lines = $this->decodeLines($payload);
        $cart = new PosRegisterCartService();
        $normalized = $cart->normalizeLines($lines);
        $invoiceDiscount = $this->decodeJsonField($payload, 'invoice_discount');
        $taxRate = (new PosTaxSettingsService())->resolveRate(
            (int) $scope['company_id'],
            (int) $scope['branch_id']
        );
        try {
            (new PosDiscountGuardService())->assertManualDiscountAllowed($normalized, $invoiceDiscount);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 403);
            return;
        }
        $customer = $this->resolveCustomer($payload) ?? (new PosSessionService())->getCustomer();
        $normalized = (new PosSellPriceService())->applyToLines(
            $normalized,
            $scope['company_id'],
            $scope['branch_id'],
            $customer
        );
        $pricing = (new PosPricingService())->calculate($normalized, $invoiceDiscount, $taxRate);
        $this->json(['ok' => true, 'pricing' => $pricing, 'totals' => $pricing]);
    }

    public function checkout(): void
    {
        $this->bootstrapPos();
        $this->guardPosPermission('pos.sale.complete', 'pos/register');
        if (!Csrf::validate($_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
            $this->json(['ok' => false, 'error' => __('invalid_request')], 419);
            return;
        }

        $scope = $this->registerScope();
        $session = new PosSessionService();
        $this->ensureDbSession($session, $scope);
        $scope = $this->registerScope();

        if ((int) ($scope['shift_id'] ?? 0) < 1) {
            $this->json(['ok' => false, 'error' => __('pos_no_shift_warning')], 422);
            return;
        }

        $payload = $this->inputData();
        $lines = $this->decodeLines($payload);
        $payments = $this->decodePayments($payload);
        $invoiceDiscount = $this->decodeJsonField($payload, 'invoice_discount');
        $taxRate = (new PosTaxSettingsService())->resolveRate(
            (int) $scope['company_id'],
            (int) $scope['branch_id']
        );
        $customer = $this->resolveCustomer($payload) ?? $session->getCustomer();
        $scope['coupon_code'] = trim((string) ($payload['coupon_code'] ?? ''));
        $scope['points_redeem'] = (float) ($payload['points_redeem'] ?? 0);
        $scope['idempotency_key'] = trim((string) ($payload['idempotency_key'] ?? ''));

        try {
            (new PosDiscountGuardService())->assertManualDiscountAllowed($lines, $invoiceDiscount);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 403);
            return;
        }

        try {
            $result = (new PosCheckoutService())->complete(
                $lines,
                $payments,
                $invoiceDiscount,
                $scope,
                $customer,
                $taxRate,
                !empty($payload['gift_receipt'])
            );
            $session->setCartLines([]);
            $session->setCustomer(null);
            $this->json($result);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function validateCoupon(): void
    {
        $this->bootstrapPos();
        $this->guardPosView('pos/register');
        $scope = $this->registerScope();
        $payload = $this->inputData();
        $code = trim((string) ($payload['coupon_code'] ?? ''));
        $subtotal = (float) ($payload['subtotal'] ?? 0);
        $result = (new PosRewardService())->previewCoupon($code, $scope['company_id'], $subtotal);
        $this->json($result['ok'] ? ['ok' => true, 'discount' => $result['discount'] ?? 0] : $result, $result['ok'] ? 200 : 422);
    }

    public function validateGiftCard(): void
    {
        $this->bootstrapPos();
        $this->guardPosView('pos/register');
        $scope = $this->registerScope();
        $payload = $this->inputData();
        $code = trim((string) ($payload['gift_card_code'] ?? ''));
        $amount = (float) ($payload['amount'] ?? 0);
        $result = (new PosRewardService())->validateGiftCard($code, $scope['company_id'], $amount);
        $this->json($result['ok'] ? ['ok' => true, 'balance' => $result['balance'] ?? 0] : $result, $result['ok'] ? 200 : 422);
    }

    public function loyaltyBalance(): void
    {
        $this->bootstrapPos();
        $this->guardPosView('pos/register');
        $scope = $this->registerScope();
        $customerId = (int) ($_GET['customer_id'] ?? 0);
        if ($customerId < 1) {
            $this->json(['ok' => false, 'error' => __('invalid_request')], 422);
            return;
        }
        $balance = (new PosRewardService())->loyaltyBalance($scope['company_id'], $customerId);
        $this->json(['ok' => true, 'points_balance' => $balance, 'balance' => $balance, 'points' => $balance]);
    }

    public function createCustomer(): void
    {
        $this->bootstrapPos();
        $this->guardPosView('pos/register');
        if (!Csrf::validate($_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
            $this->json(['ok' => false, 'error' => __('invalid_request')], 419);
            return;
        }
        $scope = $this->registerScope();
        $payload = $this->inputData();
        $name = trim((string) ($payload['name'] ?? ''));
        $phone = trim((string) ($payload['phone'] ?? ''));
        try {
            $customer = (new PosCustomerBridgeService())->quickCreate(
                $name,
                $phone !== '' ? $phone : null,
                $scope['branch_id']
            );
            (new PosSessionService())->setCustomer($customer);
            $this->json(['ok' => true, 'customer' => $customer]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function drawerEvent(): void
    {
        $this->bootstrapPos();
        $this->guardPosView('pos/register');
        if (!Csrf::validate($_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
            $this->json(['ok' => false, 'error' => __('invalid_request')], 419);
            return;
        }
        $scope = $this->registerScope();
        $shiftId = (int) ($scope['shift_id'] ?? 0);
        if ($shiftId < 1) {
            $this->json(['ok' => false, 'error' => __('pos_no_shift_warning')], 422);
            return;
        }
        $drawer = (new PosCashDrawerService())->findOpenByShift($shiftId, $scope['company_id']);
        if (!$drawer) {
            $this->json(['ok' => false, 'error' => __('pos_drawer_not_found')], 422);
            return;
        }
        $payload = $this->inputData();
        $eventType = trim((string) ($payload['event_type'] ?? ''));
        $amount = (float) ($payload['amount'] ?? 0);
        $notes = trim((string) ($payload['notes'] ?? ''));
        try {
            (new PosCashDrawerService())->recordManualEvent(
                (int) ($drawer['id'] ?? 0),
                $scope['company_id'],
                $scope['user_id'],
                $eventType,
                $amount,
                $notes
            );
            if ($eventType === 'no_sale') {
                (new PosHardwareManager())->cashDrawer()->open();
            }
            $this->json(['ok' => true]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function openDrawer(): void
    {
        $this->bootstrapPos();
        $this->guardPosView('pos/register');
        if (!Csrf::validate($_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
            $this->json(['ok' => false, 'error' => __('invalid_request')], 419);
            return;
        }
        try {
            (new PosHardwareManager())->cashDrawer()->open();
            $this->json(['ok' => true]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function xReport(): void
    {
        $this->bootstrapPos();
        $this->guardPosView('pos/register');
        $scope = $this->registerScope();
        $shiftId = (int) ($scope['shift_id'] ?? 0);
        if ($shiftId < 1) {
            $this->json(['ok' => false, 'error' => __('pos_no_shift_warning')], 422);
            return;
        }
        try {
            $report = (new PosReportService())->buildXReport($shiftId, $scope['company_id']);
            $this->json(['ok' => true, 'report' => $report]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function lastReceipt(): void
    {
        $this->bootstrapPos();
        $this->guardPosView('pos/register');
        $scope = $this->registerScope();
        $shiftId = (int) ($scope['shift_id'] ?? 0);
        if ($shiftId < 1) {
            $this->json(['ok' => false, 'error' => __('pos_no_shift_warning')], 422);
            return;
        }
        $db = \Rateb\App\Core\Database::connection();
        $stmt = $db->prepare(
            'SELECT id, order_no, receipt_json FROM rateb_pos_orders
             WHERE company_id = :cid AND shift_id = :sid AND status = :st AND order_type = :ot
             ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([
            'cid' => $scope['company_id'],
            'sid' => $shiftId,
            'st' => 'completed',
            'ot' => 'sale',
        ]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            $this->json(['ok' => false, 'error' => __('no_records')], 404);
            return;
        }
        $receipt = json_decode((string) ($row['receipt_json'] ?? ''), true);
        $this->json([
            'ok' => true,
            'order_id' => (int) ($row['id'] ?? 0),
            'order_no' => (string) ($row['order_no'] ?? ''),
            'receipt' => is_array($receipt) ? $receipt : [],
        ]);
    }

    /** @return array{company_id: int, user_id: int, branch_id: int, warehouse_id: ?int, session_id: ?int, terminal_id: ?int, shift_id: ?int} */
    private function registerScope(): array
    {
        $session = new PosSessionService();
        $snap = $session->snapshot();
        return [
            'company_id' => $this->companyId(),
            'user_id' => $this->userId(),
            'branch_id' => (int) ($snap['branch_id'] ?? 0),
            'warehouse_id' => (int) ($snap['warehouse_id'] ?? 0) ?: null,
            'session_id' => (int) ($snap['db_session_id'] ?? 0) ?: null,
            'terminal_id' => (int) ($snap['terminal_id'] ?? 0) ?: null,
            'shift_id' => (int) ($snap['shift_id'] ?? 0) ?: null,
        ];
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    private function decodeJsonField(array $payload, string $key): array
    {
        if (!isset($payload[$key])) {
            return [];
        }
        if (is_array($payload[$key])) {
            return $payload[$key];
        }
        if (is_string($payload[$key])) {
            $decoded = json_decode($payload[$key], true);
            return is_array($decoded) ? $decoded : [];
        }
        return [];
    }

    /** @param array<string, mixed> $payload @return array<int, array<string, mixed>> */
    private function decodePayments(array $payload): array
    {
        if (isset($payload['payments']) && is_string($payload['payments'])) {
            $decoded = json_decode($payload['payments'], true);
            return is_array($decoded) ? $decoded : [];
        }
        if (isset($payload['payments']) && is_array($payload['payments'])) {
            return $payload['payments'];
        }
        $method = trim((string) ($payload['payment_method'] ?? 'cash'));
        $amount = (float) ($payload['payment_amount'] ?? 0);
        if ($amount > 0) {
            return [['method' => $method, 'amount' => $amount, 'reference_no' => (string) ($payload['reference_no'] ?? '')]];
        }
        return [];
    }

    /** @param array<string, mixed> $scope */
    private function ensureDbSession(PosSessionService $session, array $scope): void
    {
        if ((int) ($session->snapshot()['db_session_id'] ?? 0) > 0) {
            return;
        }
        $terminalId = (int) ($session->current()['terminal_id'] ?? 0);
        $shiftId = (int) ($session->current()['shift_id'] ?? 0);
        $branchId = (int) ($session->current()['branch_id'] ?? 0);
        $warehouseId = (int) ($session->current()['warehouse_id'] ?? 0);
        if ($terminalId > 0 && $shiftId > 0 && $branchId > 0) {
            $session->bindRegisterContext(
                $scope['company_id'],
                $scope['user_id'],
                $terminalId,
                $shiftId,
                $branchId,
                $warehouseId > 0 ? $warehouseId : null
            );
        }
    }

    /** @param array<string, mixed> $payload @return array<int, array<string, mixed>> */
    private function decodeLines(array $payload): array
    {
        if (isset($payload['lines']) && is_string($payload['lines'])) {
            $decoded = json_decode($payload['lines'], true);
            return is_array($decoded) ? $decoded : [];
        }
        if (isset($payload['cart']) && is_string($payload['cart'])) {
            $decoded = json_decode($payload['cart'], true);
            return is_array($decoded['lines'] ?? null) ? $decoded['lines'] : [];
        }
        return [];
    }

    /** @param array<string, mixed> $payload @return array<string, mixed>|null */
    private function resolveCustomer(array $payload): ?array
    {
        if (isset($payload['customer']) && is_string($payload['customer'])) {
            $decoded = json_decode($payload['customer'], true);
            if (is_array($decoded) && !empty($decoded['id'])) {
                return (new PosCustomerBridgeService())->findById((int) $decoded['id']);
            }
        }
        if (isset($payload['customer_id'])) {
            $cid = (int) $payload['customer_id'];
            return $cid > 0 ? (new PosCustomerBridgeService())->findById($cid) : null;
        }
        return null;
    }

    /**
     * Resolve demo/catalog seed products that are not inventory rows (ids 990001+).
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>|null
     */
    private function resolveCatalogProductFallback(int $productId, array $payload): ?array
    {
        $demos = [
            990001 => ['item_code' => 'DEMO-ESP', 'item_name' => 'Espresso (Demo)', 'unit_price' => 12.00],
            990002 => ['item_code' => 'DEMO-LAT', 'item_name' => 'Latte (Demo)', 'unit_price' => 16.00],
            990003 => ['item_code' => 'DEMO-CRO', 'item_name' => 'Croissant (Demo)', 'unit_price' => 9.50],
            990004 => ['item_code' => 'DEMO-SAN', 'item_name' => 'Chicken Sandwich (Demo)', 'unit_price' => 22.00],
            990005 => ['item_code' => 'DEMO-WAT', 'item_name' => 'Water 330ml (Demo)', 'unit_price' => 3.00],
        ];
        if (isset($demos[$productId])) {
            $d = $demos[$productId];
            return [
                'id' => $productId,
                'item_code' => $d['item_code'],
                'item_name' => $d['item_name'],
                'unit_price' => $d['unit_price'],
                'demo' => true,
                'availability' => ['can_add' => true, 'available' => 999.0, 'on_hand' => 999.0],
            ];
        }

        $name = trim((string) ($payload['item_name'] ?? $payload['name'] ?? ''));
        $price = (float) ($payload['unit_price'] ?? $payload['price'] ?? 0);
        $code = trim((string) ($payload['item_code'] ?? $payload['sku'] ?? ''));
        if ($productId >= 990000 && ($name !== '' || $price > 0)) {
            return [
                'id' => $productId,
                'item_code' => $code !== '' ? $code : ('DEMO-' . $productId),
                'item_name' => $name !== '' ? $name : ('Item #' . $productId),
                'unit_price' => max(0, $price),
                'demo' => true,
                'availability' => ['can_add' => true, 'available' => 999.0, 'on_hand' => 999.0],
            ];
        }

        return null;
    }

    /**
     * Add a catalog/demo product without inventory reservation checks.
     *
     * @param array<int, array<string, mixed>> $lines
     * @param array<string, mixed> $product
     * @return array{ok: bool, lines?: array<int, array<string, mixed>>, line?: array<string, mixed>, error?: string}
     */
    private function addCatalogProductLocal(
        PosRegisterCartService $cart,
        array $lines,
        array $product,
        float $qty,
        string $serialNo
    ): array {
        $productId = (int) ($product['id'] ?? 0);
        $qty = max(0.001, $qty);
        $unitPrice = max(0, round((float) ($product['unit_price'] ?? 0), 2));
        $mergeSerial = $serialNo === '' && empty($product['requires_serial']);

        if ($mergeSerial) {
            foreach ($lines as &$line) {
                if ((int) ($line['product_id'] ?? 0) === $productId && empty($line['serial_no'])) {
                    $line['quantity'] = round((float) ($line['quantity'] ?? 0) + $qty, 3);
                    $line['unit_price'] = $unitPrice > 0 ? $unitPrice : (float) ($line['unit_price'] ?? 0);
                    $line['line_total'] = round((float) $line['quantity'] * (float) $line['unit_price'], 2);
                    $updated = $line;
                    unset($line);
                    return ['ok' => true, 'lines' => $cart->normalizeLines($lines), 'line' => $updated];
                }
            }
            unset($line);
        }

        $newLine = [
            'id' => bin2hex(random_bytes(8)),
            'product_id' => $productId,
            'item_code' => (string) ($product['item_code'] ?? ''),
            'item_name' => (string) ($product['item_name'] ?? ''),
            'barcode' => (string) ($product['barcode'] ?? ''),
            'quantity' => $qty,
            'unit_price' => $unitPrice,
            'line_total' => round($qty * $unitPrice, 2),
            'serial_no' => $serialNo !== '' ? $serialNo : null,
            'available_qty' => 999.0,
            'requires_serial' => false,
            'has_batches' => false,
        ];
        $lines[] = $newLine;

        return ['ok' => true, 'lines' => $cart->normalizeLines($lines), 'line' => $newLine];
    }
}
