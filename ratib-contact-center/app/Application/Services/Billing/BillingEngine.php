<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Application\Services\Billing;

use Ratib\ContactCenter\App\Application\Services\RccAuditService;
use Ratib\ContactCenter\App\Core\Database;
use Ratib\ContactCenter\App\Core\Events\EventBus;
use Ratib\ContactCenter\App\Core\Events\EventType;

/** Orchestrates subscription billing cycles, invoice generation, and payment collection. */
final class BillingEngine
{
    public function __construct(
        private readonly SubscriptionService $subscriptions = new SubscriptionService(),
        private readonly InvoiceService $invoices = new InvoiceService(),
        private readonly UsageMeteringService $usage = new UsageMeteringService(),
        private readonly PaymentOrchestratorService $payments = new PaymentOrchestratorService(),
        private readonly RccAuditService $audit = new RccAuditService()
    ) {
    }

    /** @return array<string, mixed> */
    public function dashboard(int $tenantId): array
    {
        $sub = $this->subscriptions->activeSubscription($tenantId);
        $openInvoices = $this->invoices->list($tenantId, 'open');
        $usage = $this->usage->summary($tenantId);
        return [
            'subscription' => $sub,
            'open_invoices' => count($openInvoices),
            'open_invoices_total' => array_sum(array_map(static fn ($i) => (float) $i['total_amount'], $openInvoices)),
            'usage' => $usage,
        ];
    }

    public function runBillingCycle(int $tenantId, ?int $userId): array
    {
        $sub = $this->subscriptions->activeSubscription($tenantId);
        if (!$sub) {
            throw new \RuntimeException('No subscription to bill');
        }
        $stmt = Database::connection()->prepare('SELECT * FROM rcc_plans WHERE id = :id');
        $stmt->execute(['id' => $sub['plan_id']]);
        $plan = $stmt->fetch();
        if (!$plan) {
            throw new \RuntimeException('Plan missing');
        }
        $invoice = $this->invoices->create($tenantId, [
            'subscription_id' => $sub['id'],
            'subtotal' => $plan['price_amount'],
            'total_amount' => $plan['price_amount'],
            'currency' => $plan['currency'],
            'status' => 'open',
            'line_items' => [['description' => $plan['name'], 'amount' => $plan['price_amount']]],
        ], $userId);
        $this->audit->log($tenantId, 'billing.cycle.run', $userId, 'invoice', (int) $invoice['id']);
        EventBus::instance()->emit([
            'type' => EventType::BILLING_CYCLE_COMPLETED,
            'tenant_id' => $tenantId,
            'payload' => ['invoice_id' => $invoice['id']],
        ]);
        return $invoice;
    }

    /** @param array<string, mixed> $options */
    public function payInvoice(int $tenantId, int $invoiceId, string $gateway, array $options, ?int $userId): array
    {
        return $this->payments->initiatePayment($tenantId, $invoiceId, $gateway, $options, $userId);
    }
}
