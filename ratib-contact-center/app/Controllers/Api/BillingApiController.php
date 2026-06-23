<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Controllers\Api;

use Ratib\ContactCenter\App\Application\Services\Billing\BillingEngine;
use Ratib\ContactCenter\App\Application\Services\Billing\InvoiceService;
use Ratib\ContactCenter\App\Application\Services\Billing\LicenseService;
use Ratib\ContactCenter\App\Application\Services\Billing\PaymentOrchestratorService;
use Ratib\ContactCenter\App\Application\Services\Billing\SubscriptionService;
use Ratib\ContactCenter\App\Application\Services\Billing\UsageMeteringService;
use Ratib\ContactCenter\App\Application\Services\RealtimeOrchestrator;
use Ratib\ContactCenter\App\Application\Services\SaaS\ResellerService;
use Ratib\ContactCenter\App\Application\Services\SaaS\TenantProvisioningService;
use Ratib\ContactCenter\App\Application\Services\SaaS\WhiteLabelService;
use Ratib\ContactCenter\App\Core\Security\AuthContext;
use Ratib\ContactCenter\App\Core\TenantContext;

final class BillingApiController
{
    public function __construct(
        private readonly BillingEngine $billing = new BillingEngine(),
        private readonly SubscriptionService $subscriptions = new SubscriptionService(),
        private readonly InvoiceService $invoices = new InvoiceService(),
        private readonly PaymentOrchestratorService $payments = new PaymentOrchestratorService(),
        private readonly UsageMeteringService $usage = new UsageMeteringService(),
        private readonly LicenseService $licenses = new LicenseService(),
        private readonly WhiteLabelService $whiteLabel = new WhiteLabelService(),
        private readonly ResellerService $resellers = new ResellerService(),
        private readonly TenantProvisioningService $provisioning = new TenantProvisioningService()
    ) {
    }

    public function handle(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        try {
            RealtimeOrchestrator::boot();
            $action = (string) ($_GET['action'] ?? '');
            $input = array_merge($this->parseJsonBody(), $_GET);
            echo json_encode($this->handleAction($action, $input), JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
    }

    /** @return array<string, mixed> */
    public function handleAction(string $action, array $input): array
    {
        AuthContext::requirePermission('rcc.billing.view');
        $tenantId = $this->resolveTenantId($input);
        $userId = AuthContext::userId();
        TenantContext::set($tenantId);

        return match ($action) {
            'dashboard' => $this->ok($this->billing->dashboard($tenantId)),
            'plans_list' => $this->ok(['plans' => $this->subscriptions->listPlans()]),
            'subscription_get' => $this->ok(['subscription' => $this->subscriptions->activeSubscription($tenantId)]),
            'subscription_subscribe' => $this->runPerm('rcc.billing.subscriptions', fn () => $this->ok([
                'subscription' => $this->subscriptions->subscribe($tenantId, (int) ($input['plan_id'] ?? 0), $userId),
            ])),
            'subscription_cancel' => $this->runPerm('rcc.billing.subscriptions', fn () => $this->ok([
                'subscription' => $this->subscriptions->cancel($tenantId, $userId, !empty($input['at_period_end'])),
            ])),
            'invoices_list' => $this->ok(['invoices' => $this->invoices->list($tenantId, $input['status'] ?? null)]),
            'invoice_create' => $this->runPerm('rcc.billing.invoices', fn () => $this->ok([
                'invoice' => $this->invoices->create($tenantId, $input, $userId),
            ])),
            'billing_cycle_run' => $this->runPerm('rcc.billing.manage', fn () => $this->ok([
                'invoice' => $this->billing->runBillingCycle($tenantId, $userId),
            ])),
            'gateways_list' => $this->ok(['gateways' => $this->payments->listGateways($tenantId)]),
            'payment_initiate' => $this->runPerm('rcc.billing.payments', fn () => $this->ok(
                $this->billing->payInvoice($tenantId, (int) ($input['invoice_id'] ?? 0), (string) ($input['gateway'] ?? ''), $input, $userId)
            )),
            'payment_confirm' => $this->runPerm('rcc.billing.payments', fn () => $this->ok(
                $this->payments->confirmPayment($tenantId, (int) ($input['payment_id'] ?? 0), $userId)
            )),
            'usage_summary' => $this->ok(['usage' => $this->usage->summary($tenantId, $input['from'] ?? null, $input['to'] ?? null)]),
            'licenses_list' => $this->gate('rcc.license.view', fn () => $this->ok(['licenses' => $this->licenses->list($tenantId)])),
            'license_issue' => $this->runPerm('rcc.license.manage', fn () => $this->ok([
                'license' => $this->licenses->issue($tenantId, (int) ($input['seats'] ?? 5), isset($input['plan_id']) ? (int) $input['plan_id'] : null, $userId),
            ])),
            'whitelabel_branding_get' => $this->gate('rcc.whitelabel.manage', fn () => $this->ok(['branding' => $this->whiteLabel->branding($tenantId)])),
            'whitelabel_branding_save' => $this->runPerm('rcc.whitelabel.manage', fn () => $this->ok([
                'branding' => $this->whiteLabel->saveBranding($tenantId, $input, $userId),
            ])),
            'whitelabel_domains' => $this->gate('rcc.whitelabel.manage', fn () => $this->ok(['domains' => $this->whiteLabel->listDomains($tenantId)])),
            'whitelabel_domain_add' => $this->runPerm('rcc.whitelabel.manage', fn () => $this->ok(
                $this->whiteLabel->addDomain($tenantId, (string) ($input['domain'] ?? ''), $userId)
            )),
            'reseller_get' => $this->gate('rcc.reseller.view', fn () => $this->ok(['reseller' => $this->resellers->findByTenant($tenantId)])),
            'reseller_register' => $this->runPerm('rcc.reseller.manage', fn () => $this->ok([
                'reseller' => $this->resellers->register($tenantId, $input, $userId),
            ])),
            'reseller_commissions' => $this->gate('rcc.reseller.view', function () use ($tenantId, $input) {
                $r = $this->resellers->findByTenant($tenantId);
                return $this->ok(['commissions' => $r ? $this->resellers->commissions((int) $r['id'], $input['month'] ?? null) : []]);
            }),
            'tenant_provision' => $this->runPerm('rcc.provisioning.manage', fn () => $this->ok([
                'tenant' => $this->provisioning->provision($input, $userId),
            ])),
            default => ['ok' => false, 'error' => 'Unknown action: ' . $action],
        };
    }

    /** @param array<string, mixed> $input */
    private function resolveTenantId(array $input): int
    {
        if (AuthContext::can('rcc.tenants.manage') && isset($input['tenant_id']) && (int) $input['tenant_id'] > 0) {
            return (int) $input['tenant_id'];
        }
        return AuthContext::tenantId();
    }

    /** @return array<string, mixed> */
    private function ok(mixed $data): array
    {
        return is_array($data) && isset($data['ok']) ? $data : ['ok' => true] + (is_array($data) ? $data : ['data' => $data]);
    }

    private function gate(string $perm, callable $fn): array
    {
        AuthContext::requirePermission($perm);
        return $fn();
    }

    private function requirePerm(string $perm): ?array
    {
        if (!AuthContext::can($perm)) {
            return ['ok' => false, 'error' => 'Permission denied: ' . $perm];
        }
        return null;
    }

    private function runPerm(string $perm, callable $fn): array
    {
        $deny = $this->requirePerm($perm);
        return $deny ?? $fn();
    }

    /** @return array<string, mixed> */
    private function parseJsonBody(): array
    {
        $raw = file_get_contents('php://input');
        if ($raw === false || trim($raw) === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }
}
