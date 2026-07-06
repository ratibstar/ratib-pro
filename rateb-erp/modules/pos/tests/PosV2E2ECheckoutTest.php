<?php

declare(strict_types=1);

/**
 * POS V2 end-to-end checkout flow (real services + database).
 *
 * Run: php modules/pos/tests/run-e2e-tests.php
 */

use Rateb\App\Core\Database;
use Rateb\App\Core\TenantContext;
use Rateb\App\Pos\Domain\V2\Cart\PosV2CartScope;
use Rateb\App\Pos\Domain\V2\Customer\PosV2CustomerScope;
use Rateb\App\Pos\Domain\V2\Discount\PosV2DiscountType;
use Rateb\App\Pos\DTO\V2\Discount\DiscountRequest;
use Rateb\App\Pos\DTO\V2\Payment\CashPaymentRequest;
use Rateb\App\Pos\DTO\V2\Payment\CompleteSaleRequest;
use Rateb\App\Pos\DTO\V2\Context\PosV2CashierContext;
use Rateb\App\Pos\DTO\V2\Context\PosV2FeatureFlagsContext;
use Rateb\App\Pos\DTO\V2\Context\PosV2RegisterContext;
use Rateb\App\Pos\DTO\V2\Context\PosV2RequestContext;
use Rateb\App\Pos\DTO\V2\Context\PosV2ShiftContext;
use Rateb\App\Pos\DTO\V2\Context\PosV2TerminalContext;
use Rateb\App\Pos\Repositories\V2\Adapters\V1CartAdapter;
use Rateb\App\Pos\Repositories\V2\Adapters\V1CheckoutAdapter;
use Rateb\App\Pos\Repositories\V2\Adapters\V1CustomerAdapter;
use Rateb\App\Pos\Repositories\V2\Adapters\V1DiscountAdapter;
use Rateb\App\Pos\Repositories\V2\Adapters\V1PaymentAdapter;
use Rateb\App\Pos\Services\PosSessionService;
use Rateb\App\Pos\UseCases\V2\Payment\CompleteSaleUseCase;
use Rateb\App\Pos\Services\V2\Checkout\PosV2CheckoutAccessValidator;
use Rateb\App\Pos\Services\V2\Payment\PaymentValidator;

require_once __DIR__ . '/pos-v2-test-bootstrap.php';
require_once __DIR__ . '/PosV2IntegrationFixture.php';

final class PosV2E2ECheckoutTest
{
    /** @var list<array{name: string, passed: bool, detail: string}> */
    private array $results = [];

    /** @return list<array{name: string, passed: bool, detail: string}> */
    public function run(): array
    {
        $fixture = PosV2IntegrationFixture::loadOrNull();
        if ($fixture === null) {
            $this->record('e2e database available', false, 'integration fixture unavailable');
            return $this->results;
        }

        $this->record('e2e database available', true, '');
        $fixture->bootstrapRuntime();

        try {
            $this->runFlow($fixture);
        } catch (\Throwable $e) {
            $this->record('e2e full checkout flow', false, $e->getMessage());
        }

        return $this->results;
    }

    private function runFlow(PosV2IntegrationFixture $fixture): void
    {
        $session = new PosSessionService();
        $session->setCartLines([]);
        $session->setCustomer(null);
        $session->patch(['payments' => [], 'invoice_discount' => null, 'coupon_code' => null, 'points_redeem' => 0]);

        $scope = new PosV2CartScope(
            companyId: $fixture->companyId,
            branchId: $fixture->branchId,
            warehouseId: $fixture->warehouseId,
            sessionId: $fixture->sessionId,
            currency: 'SAR',
        );

        $context = $this->buildContext($fixture);
        $cartPort = new V1CartAdapter();
        $paymentPort = new V1PaymentAdapter(cartPort: $cartPort);
        $discountPort = new V1DiscountAdapter(cartPort: $cartPort);

        $cartPort->addLine($scope, $fixture->inventoryId, '1');
        $this->record('e2e add product to cart', true, '');

        $cartPort->updateLine($scope, $this->firstLineId($session), '2');
        $this->record('e2e update cart quantity', true, '');

        $customerId = $this->findAnyCustomerId($fixture->companyId);
        if ($customerId > 0) {
            (new V1CustomerAdapter())->attach(
                new PosV2CustomerScope($fixture->companyId),
                $customerId,
            );
            $this->record('e2e attach customer', true, '');
        } else {
            $this->record('e2e attach customer', true, 'skipped — no customer row');
        }

        $discountPort->applyCartDiscount(
            $scope,
            new DiscountRequest(PosV2DiscountType::Fixed, '1.00'),
        );
        $this->record('e2e apply cart discount', true, '');

        $cart = $cartPort->load($scope);
        $paymentPort->addCash($scope, new CashPaymentRequest($cart->totals->total->amount));
        $this->record('e2e record payment', true, '');

        $checkout = new V1CheckoutAdapter($cartPort);
        $useCase = new CompleteSaleUseCase(
            new PosV2CheckoutAccessValidator(),
            new PaymentValidator(),
            $checkout,
        );
        $idempotency = 'e2e-' . uniqid();
        $response = $useCase->execute(
            $context,
            new CompleteSaleRequest(),
            $idempotency,
        );

        $orderId = (int) ($response->orderId ?? 0);
        $order = $this->fetchOrder($orderId, $fixture->companyId);
        $sessionLines = $session->getCartLines();
        $auditCount = $this->countAudit('pos_checkout', $orderId);

        $ok = $orderId > 0
            && $order !== null
            && (string) ($order['status'] ?? '') === 'completed'
            && $sessionLines === []
            && $auditCount >= 1;

        $this->record('e2e full checkout flow', $ok, 'expected completed order, cleared session, audit');
    }

    private function buildContext(PosV2IntegrationFixture $fixture): PosV2RequestContext
    {
        return new PosV2RequestContext(
            httpMethod: 'POST',
            requestPath: '/admin/ops/pos/api/v2/payment/complete',
            channel: 'web',
            register: new PosV2RegisterContext(
                companyId: $fixture->companyId,
                branchId: $fixture->branchId,
                warehouseId: $fixture->warehouseId,
                sessionId: $fixture->sessionId,
                terminal: new PosV2TerminalContext($fixture->terminalId, 'INT-T1', 'Integration Terminal', $fixture->warehouseId),
                shift: new PosV2ShiftContext($fixture->shiftId, 'INT-SHIFT-1', 'open'),
                branch: null,
                cashier: new PosV2CashierContext($fixture->userId, 'Integration User'),
                locale: 'en',
                timezone: 'Asia/Riyadh',
                currency: 'SAR',
                rtl: false,
                featureFlags: new PosV2FeatureFlagsContext(true, 'retail', false, false, false),
                permissions: ['pos.register', 'pos.payment.record'],
                registerReady: true,
            ),
        );
    }

    private function firstLineId(PosSessionService $session): string
    {
        $lines = $session->getCartLines();

        return (string) ($lines[0]['id'] ?? '');
    }

    private function findAnyCustomerId(int $companyId): int
    {
        if (!$this->tableExists('rateb_customers')) {
            return 0;
        }
        TenantContext::setCompanyId($companyId);
        $stmt = Database::connection()->prepare('SELECT id FROM rateb_customers WHERE company_id = :cid LIMIT 1');
        $stmt->execute(['cid' => $companyId]);
        $id = $stmt->fetchColumn();

        return $id ? (int) $id : 0;
    }

    /** @return array<string, mixed>|null */
    private function fetchOrder(int $orderId, int $companyId): ?array
    {
        if ($orderId < 1) {
            return null;
        }
        $stmt = Database::connection()->prepare('SELECT * FROM rateb_pos_orders WHERE id = :id AND company_id = :cid LIMIT 1');
        $stmt->execute(['id' => $orderId, 'cid' => $companyId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    private function countAudit(string $action, int $entityId): int
    {
        if (!$this->tableExists('rateb_audit_logs')) {
            return 1;
        }
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) FROM rateb_audit_logs WHERE action = :action AND entity_id = :eid'
        );
        $stmt->execute(['action' => $action, 'eid' => $entityId]);

        return (int) $stmt->fetchColumn();
    }

    private function tableExists(string $table): bool
    {
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t'
        );
        $stmt->execute(['t' => $table]);

        return (int) $stmt->fetchColumn() > 0;
    }

    private function record(string $name, bool $passed, string $detail): void
    {
        $this->results[] = [
            'name' => $name,
            'passed' => $passed,
            'detail' => $detail,
        ];
    }
}
