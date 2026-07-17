<?php
declare(strict_types=1);

namespace Rateb\App\Pos\Controllers;

use Rateb\App\Core\Csrf;
use Rateb\App\Pos\Services\PosExchangeService;
use Rateb\App\Pos\Services\PosOrderQueryService;
use Rateb\App\Pos\Services\PosQuoteService;
use Rateb\App\Pos\Services\PosReturnService;
use Rateb\App\Pos\Services\PosSessionService;
use Rateb\App\Pos\Services\PosSuspendService;
use Rateb\App\Pos\Services\PosCheckoutService;
use Rateb\App\Pos\Services\Bridge\PosCustomerBridgeService;
use Rateb\App\Pos\Support\PosBranchScope;

/** Register APIs — suspend, quotes, returns, exchanges (Phase 6). */
final class PosOrderOpsApiController extends PosBaseController
{
    public function suspend(): void
    {
        $this->bootstrapPos();
        $this->guardPosView('pos/register');
        $this->requireCsrf();
        $scope = $this->registerScope();
        $payload = $this->inputData();
        $lines = $this->decodeLines($payload);
        $customer = $this->resolveCustomer($payload);
        try {
            $result = (new PosSuspendService())->suspend(
                $lines,
                $scope,
                $customer,
                trim((string) ($payload['notes'] ?? ''))
            );
            (new PosSessionService())->setCartLines([]);
            (new PosSessionService())->setCustomer(null);
            $this->json($result);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function suspendedList(): void
    {
        $this->bootstrapPos();
        $this->guardPosView('pos/register');
        $scope = $this->registerScope();
        $items = (new PosSuspendService())->listSuspended($scope['company_id'], $scope['branch_id']);
        $this->json(['ok' => true, 'items' => $items]);
    }

    public function resumeSuspended(array $params): void
    {
        $this->bootstrapPos();
        $this->guardPosView('pos/register');
        $this->requireCsrf();
        $scope = $this->registerScope();
        $id = (int) ($params['id'] ?? 0);
        try {
            $result = (new PosSuspendService())->resume(
                $id,
                $scope['company_id'],
                $scope['branch_id'],
                $scope['user_id']
            );
            $session = new PosSessionService();
            $session->setCartLines($result['lines'] ?? []);
            $session->setCustomer($result['customer'] ?? null);
            $this->json($result);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function saveQuote(): void
    {
        $this->bootstrapPos();
        $this->guardPosView('pos/register');
        $this->requireCsrf();
        $scope = $this->registerScope();
        $payload = $this->inputData();
        try {
            $result = (new PosQuoteService())->save(
                $this->decodeLines($payload),
                $scope,
                $this->resolveCustomer($payload),
                (int) ($payload['valid_days'] ?? 30),
                trim((string) ($payload['notes'] ?? ''))
            );
            $this->json($result);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function quotesList(): void
    {
        $this->bootstrapPos();
        $this->guardPosView('pos/register');
        $scope = $this->registerScope();
        $items = (new PosQuoteService())->listQuotes($scope['company_id'], $scope['branch_id']);
        $this->json(['ok' => true, 'items' => $items]);
    }

    public function convertQuote(array $params): void
    {
        $this->bootstrapPos();
        $this->guardPosPermission('pos.sale.complete', 'pos/register');
        $this->requireCsrf();
        $scope = $this->registerScope();
        $this->ensureShift($scope);
        $payload = $this->inputData();
        try {
            $result = (new PosQuoteService())->convert(
                (int) ($params['id'] ?? 0),
                $this->decodePayments($payload),
                $scope,
                $this->decodeJsonField($payload, 'invoice_discount'),
                !empty($payload['gift_receipt'])
            );
            (new PosSessionService())->setCartLines([]);
            $this->json($result);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function resumeQuote(array $params): void
    {
        $this->bootstrapPos();
        $this->guardPosView('pos/register');
        $this->requireCsrf();
        $scope = $this->registerScope();
        $id = (int) ($params['id'] ?? 0);
        try {
            $result = (new PosQuoteService())->resume(
                $id,
                $scope['company_id'],
                $scope['branch_id'],
                $scope['user_id']
            );
            $session = new PosSessionService();
            $session->setCartLines($result['lines'] ?? []);
            $session->setCustomer($result['customer'] ?? null);
            $this->json($result);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function returnableLines(array $params): void
    {
        $this->bootstrapPos();
        $this->guardPosPermission('pos.returns.manage', 'pos/returns');
        $scope = $this->registerScope();
        $branchId = PosBranchScope::registerBranchId($scope['branch_id']);
        $orderId = (int) ($params['id'] ?? 0);
        $lines = (new PosOrderQueryService())->returnableLines($orderId, $scope['company_id'], $branchId);
        $this->json(['ok' => true, 'lines' => $lines]);
    }

    public function searchOrdersForReturn(): void
    {
        $this->bootstrapPos();
        $this->guardPosPermission('pos.returns.manage', 'pos/returns');
        $scope = $this->registerScope();
        $branchId = PosBranchScope::registerBranchId($scope['branch_id']);
        $q = trim((string) ($_GET['q'] ?? ''));
        $items = (new PosOrderQueryService())->searchReturnableOrders(
            $scope['company_id'],
            $branchId,
            $q,
            25
        );
        $this->json(['ok' => true, 'items' => $items]);
    }

    public function processReturn(): void
    {
        $this->bootstrapPos();
        $this->guardPosPermission('pos.returns.manage', 'pos/returns');
        $this->requireCsrf();
        $scope = $this->registerScope();
        $this->ensureShift($scope);
        $payload = $this->inputData();
        try {
            $result = (new PosReturnService())->process(
                (int) ($payload['original_order_id'] ?? 0),
                $this->decodeJsonField($payload, 'return_lines'),
                $this->decodePayments($payload, 'refunds'),
                $scope,
                $this->resolveCustomer($payload)
            );
            $this->json($result);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function processExchange(): void
    {
        $this->bootstrapPos();
        $this->guardPosPermission('pos.returns.manage', 'pos/returns');
        $this->requireCsrf();
        $scope = $this->registerScope();
        $this->ensureShift($scope);
        $payload = $this->inputData();
        try {
            $scope['coupon_code'] = trim((string) ($payload['coupon_code'] ?? ''));
            $scope['points_redeem'] = (float) ($payload['points_redeem'] ?? 0);
            $result = (new PosExchangeService())->processExchange(
                (int) ($payload['original_order_id'] ?? 0),
                $this->decodeJsonField($payload, 'return_lines'),
                $this->decodeLines($payload, 'sale_lines'),
                $this->decodePayments($payload, 'payments'),
                $this->decodePayments($payload, 'refunds'),
                $scope,
                $this->resolveCustomer($payload),
                $this->decodeJsonField($payload, 'invoice_discount')
            );
            (new PosSessionService())->setCartLines([]);
            $this->json($result);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
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

    /** @param array<string, mixed> $scope */
    private function ensureShift(array $scope): void
    {
        if ((int) ($scope['shift_id'] ?? 0) < 1) {
            throw new \RuntimeException(__('pos_no_shift_warning'));
        }
    }

    private function requireCsrf(): void
    {
        if (!Csrf::validate($_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
            $this->json(['ok' => false, 'error' => __('invalid_request')], 419);
            exit;
        }
    }

    /** @param array<string, mixed> $payload @return array<int, array<string, mixed>> */
    private function decodeLines(array $payload, string $key = 'lines'): array
    {
        if (!isset($payload[$key])) {
            return [];
        }
        if (is_string($payload[$key])) {
            $decoded = json_decode($payload[$key], true);
            return is_array($decoded) ? $decoded : [];
        }
        return is_array($payload[$key]) ? $payload[$key] : [];
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
    private function decodePayments(array $payload, string $key = 'payments'): array
    {
        if (!isset($payload[$key])) {
            return [];
        }
        if (is_string($payload[$key])) {
            $decoded = json_decode($payload[$key], true);
            return is_array($decoded) ? $decoded : [];
        }
        return is_array($payload[$key]) ? $payload[$key] : [];
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
}
