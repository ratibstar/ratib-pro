<?php
declare(strict_types=1);

namespace RATEB\InfrastructureMarketplace\Lifecycle;

use RATEB\InfrastructureMarketplace\Audit\InfrastructureAuditLogger;
use RATEB\InfrastructureMarketplace\Domain\TenantContext;
use RATEB\InfrastructureMarketplace\Provisioning\ProvisioningJob;
use RATEB\InfrastructureMarketplace\Provisioning\ProvisioningPayload;
use RATEB\InfrastructureMarketplace\Services\ProvisioningOrchestrator;

final class InfrastructureServiceLifecycleManager
{
    private ProvisioningOrchestrator $orchestrator;
    private InfrastructureAuditLogger $audit;

    public function __construct(ProvisioningOrchestrator $orchestrator, InfrastructureAuditLogger $audit) {
        $this->orchestrator = $orchestrator;
        $this->audit = $audit;
    }


    /**
     * @param array<string, mixed> $service
     * @return array<string, mixed>
     */
    public function dispatchAction(string $action, TenantContext $tenant, array $service, string $actor): array
    {
        $allowed = ['suspend', 'unsuspend', 'terminate', 'retry_provisioning', 'reconcile', 'renewal_prepare', 'expiration_prepare'];
        if (!in_array($action, $allowed, true)) {
            throw new \InvalidArgumentException('Unsupported lifecycle action');
        }

        $payload = new ProvisioningPayload($action, [
            'service_public_id' => (string) ($service['public_id'] ?? ''),
            'resource_reference' => (string) ($service['resource_reference'] ?? ''),
            'service_type' => (string) ($service['service_type'] ?? ''),
        ]);
        $steps = ['hosting'];
        $type = strtolower((string) ($service['service_type'] ?? 'hosting'));
        if ($type === 'domain') {
            $steps = ['registrar', 'dns', 'ssl', 'hosting'];
        } elseif ($type === 'ssl') {
            $steps = ['ssl'];
        } elseif ($type === 'dns') {
            $steps = ['dns'];
        }

        $job = new ProvisioningJob($tenant, $steps, ['hosting' => $payload], (string) ($service['public_id'] ?? ''));
        $jobPublicId = $this->orchestrator->submit($job);

        $this->audit->appendImmutable('lifecycle_action_dispatched', [
            'actor' => $actor,
            'tenant_id' => $tenant->tenantId(),
            'service_public_id' => (string) ($service['public_id'] ?? ''),
            'action' => $action,
            'job_public_id' => $jobPublicId,
        ]);

        return ['ok' => true, 'job_public_id' => $jobPublicId, 'action' => $action];
    }
}

