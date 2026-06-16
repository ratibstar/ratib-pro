<?php
declare(strict_types=1);

namespace RATEB\InfrastructureMarketplace\Ordering;

use RATEB\InfrastructureMarketplace\Audit\InfrastructureAuditLogger;
use RATEB\InfrastructureMarketplace\Catalog\CatalogRepository;
use RATEB\InfrastructureMarketplace\Config\ModuleConfig;
use RATEB\InfrastructureMarketplace\Domain\TenantContext;
use RATEB\InfrastructureMarketplace\Events\InfrastructureEventEmitter;
use RATEB\InfrastructureMarketplace\Provisioning\ProvisioningJob;
use RATEB\InfrastructureMarketplace\Provisioning\ProvisioningPayload;
use RATEB\InfrastructureMarketplace\Provisioning\Execution\OperationalSafetyGuard;
use RATEB\InfrastructureMarketplace\Services\ProviderRegistry;
use RATEB\InfrastructureMarketplace\Services\ProvisioningOrchestrator;

final class InfrastructureOrderService
{
    private \PDO $pdo;
    private ProvisioningOrchestrator $orchestrator;
    private ProviderRegistry $providers;
    private InfrastructureEventEmitter $events;
    private InfrastructureAuditLogger $audit;

    public function __construct(\PDO $pdo, ProvisioningOrchestrator $orchestrator, ProviderRegistry $providers, InfrastructureEventEmitter $events, InfrastructureAuditLogger $audit) {
        $this->pdo = $pdo;
        $this->orchestrator = $orchestrator;
        $this->providers = $providers;
        $this->events = $events;
        $this->audit = $audit;
    }


    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function place(array $input): array
    {
        $tenant = new TenantContext(
            isset($input['tenant_id']) ? (int) $input['tenant_id'] : null,
            isset($input['agency_id']) ? (int) $input['agency_id'] : null
        );
        $sku = (string) ($input['sku'] ?? '');
        $idempotencyKey = (string) ($input['idempotency_key'] ?? '');
        if ($sku === '' || $idempotencyKey === '') {
            throw new \InvalidArgumentException('sku and idempotency_key are required');
        }
        $allowlist = ModuleConfig::rolloutTenantAllowlist();
        if ($allowlist !== [] && $tenant->tenantId() !== null && !in_array($tenant->tenantId(), $allowlist, true)) {
            throw new \RuntimeException('Tenant rollout guard is active');
        }

        $orders = new OrderRepository($this->pdo);
        $existing = $orders->findByIdempotency($idempotencyKey);
        if ($existing !== null) {
            return ['ok' => true, 'idempotent' => true, 'order' => $existing];
        }
        $guard = new OperationalSafetyGuard($this->pdo);
        $guard->assertNoQueueStorm(ModuleConfig::queuePressureThreshold());
        $guard->assertIdempotencyUnused($idempotencyKey);

        $catalog = new CatalogRepository($this->pdo);
        $visible = $catalog->listVisibleForTenant($tenant->tenantId());
        $found = null;
        foreach ($visible as $row) {
            if ((string) ($row['sku'] ?? '') === $sku) {
                $found = $row;
                break;
            }
        }
        if ($found === null) {
            throw new \RuntimeException('SKU not visible for tenant');
        }

        $serviceType = (string) (($found['service_type'] ?? '') ?: ((is_array(json_decode((string) ($found['metadata_json'] ?? '{}'), true)) ? (json_decode((string) ($found['metadata_json'] ?? '{}'), true)['service_type'] ?? '') : '')));
        if (!$this->providerCompatible($serviceType)) {
            throw new \RuntimeException('No active provider compatible with sku type');
        }

        $publicId = $this->newPublicId();
        $orderId = $orders->create([
            'public_id' => $publicId,
            'tenant_id' => $tenant->tenantId(),
            'agency_id' => $tenant->agencyId(),
            'sku' => $sku,
            'status' => 'PENDING',
            'idempotency_key' => $idempotencyKey,
            'currency' => strtoupper((string) ($input['currency'] ?? 'USD')),
            'amount' => (float) ($input['amount'] ?? 0),
            'payload_json' => json_encode($input, JSON_UNESCAPED_SLASHES),
        ]);

        $steps = ['hosting'];
        if ($serviceType === 'domain') {
            $steps = ['registrar', 'dns', 'ssl', 'hosting'];
        } elseif ($serviceType === 'ssl') {
            $steps = ['ssl'];
        } elseif ($serviceType === 'dns') {
            $steps = ['dns'];
        }

        $job = new ProvisioningJob(
            $tenant,
            $steps,
            ['hosting' => new ProvisioningPayload('create_account', $input)],
            $publicId
        );
        $jobPublicId = $this->orchestrator->submit($job);
        $orders->markQueued($orderId, $jobPublicId);

        $this->events->structuredLog('info', 'Infrastructure order queued', ['order_public_id' => $publicId]);
        $this->audit->appendImmutable('infra_order_created', [
            'actor' => (string) ($input['actor'] ?? 'system'),
            'tenant_id' => $tenant->tenantId(),
            'agency_id' => $tenant->agencyId(),
            'order_public_id' => $publicId,
            'provisioning_job_public_id' => $jobPublicId,
        ]);

        return [
            'ok' => true,
            'order_public_id' => $publicId,
            'provisioning_job_public_id' => $jobPublicId,
            'status' => 'QUEUED',
        ];
    }

    private function providerCompatible(string $serviceType): bool
    {
        $t = strtolower($serviceType);
        if ($t === 'domain') {
            return $this->providers->registrar() !== null;
        }
        if ($t === 'dns') {
            return $this->providers->dns() !== null;
        }
        if ($t === 'ssl') {
            return $this->providers->ssl() !== null;
        }
        return $this->providers->hosting() !== null;
    }

    private function newPublicId(): string
    {
        $hex = bin2hex(random_bytes(16));
        return sprintf('%s-%s-%s-%s-%s', substr($hex, 0, 8), substr($hex, 8, 4), substr($hex, 12, 4), substr($hex, 16, 4), substr($hex, 20, 12));
    }
}

