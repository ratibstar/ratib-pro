<?php
declare(strict_types=1);

namespace RATEB\InfrastructureMarketplace\Commerce;

use RATEB\InfrastructureMarketplace\Audit\InfrastructureAuditLogger;
use RATEB\InfrastructureMarketplace\Catalog\CatalogRepository;
use RATEB\InfrastructureMarketplace\Domain\TenantContext;
use RATEB\InfrastructureMarketplace\Events\InfrastructureEventEmitter;
use RATEB\InfrastructureMarketplace\Execution\ExecutionSafetyLayer;
use RATEB\InfrastructureMarketplace\Ordering\OrderRepository;
use RATEB\InfrastructureMarketplace\Providers\ProviderExecutionBinder;
use RATEB\InfrastructureMarketplace\Provisioning\ProvisioningIntentFactory;
use RATEB\InfrastructureMarketplace\Provisioning\ProvisioningJob;
use RATEB\InfrastructureMarketplace\Provisioning\ProvisioningPayload;
use RATEB\InfrastructureMarketplace\Resources\ResourceIdentityManager;
use RATEB\InfrastructureMarketplace\Services\ProvisioningOrchestrator;
use RATEB\InfrastructureMarketplace\Services\ProviderRegistry;
use RATEB\InfrastructureMarketplace\Tenants\TenantResourceManager;

/**
 * PAYMENT_CONFIRMED-style orchestration: validate → map commerce → identity → ownership overlay → intent → enqueue → plan commerce hints.
 * Idempotent via audit marker commerce_activation_completed. Does not call provider adapters directly.
 */
final class CommerceActivationEngine
{
    public function __construct(
        private \PDO $pdo,
        private ProvisioningOrchestrator $orchestrator,
        private ProviderRegistry $providers,
        private InfrastructureAuditLogger $audit,
        private InfrastructureEventEmitter $events,
        private ?ExecutionSafetyLayer $safety = null
    ) {
        $this->safety = $safety ?? new ExecutionSafetyLayer($pdo, $audit);
    }

    /**
     * @return array<string, mixed> ok, warnings, intent, provisioning_job_public_id, skipped_enqueue, rolled_back, idempotent?
     */
    public function activateAfterPaymentConfirmed(
        string $orderPublicId,
        TenantContext $tenant,
        string $actor,
        ?string $correlationId = null,
        ?string $traceId = null
    ): array {
        $correlationId = $correlationId ?? $orderPublicId;
        $orders = new OrderRepository($this->pdo);
        $order = $orders->findByPublicId($orderPublicId);
        if ($order === null) {
            return ['ok' => false, 'warnings' => ['order_not_found'], 'rolled_back' => false];
        }
        $assess = $this->safety->assessOrderActivation($order, $tenant, $orderPublicId);
        if ($assess['blockers'] !== []) {
            return [
                'ok' => false,
                'warnings' => array_merge($assess['warnings'], $assess['blockers']),
                'rolled_back' => false,
            ];
        }
        if ($assess['replay_detected']) {
            return [
                'ok' => true,
                'idempotent' => true,
                'skipped_enqueue' => true,
                'warnings' => $assess['warnings'],
                'rolled_back' => false,
            ];
        }

        $mapper = new OrderCommerceMapper(
            $this->pdo,
            new CatalogRepository($this->pdo),
            new PlanRepository($this->pdo),
            new ProductRepository($this->pdo)
        );
        $plan = $mapper->mapOrderToPlan($order, $tenant);
        $product = $mapper->mapOrderToProduct($order, $plan, $tenant);
        $warnings = array_merge(
            $assess['warnings'],
            $mapper->validateCommerceBinding($order, $plan, $product),
            $mapper->detectLegacyMappings($order, $plan)
        );
        $intentMeta = $mapper->mapOrderToProvisioningIntent($order, $plan, $product);
        /** @var list<string> $caps */
        $caps = isset($intentMeta['requested_capabilities']) && is_array($intentMeta['requested_capabilities'])
            ? $intentMeta['requested_capabilities']
            : [];
        $binder = new ProviderExecutionBinder($this->pdo, $this->providers);
        $bind = $binder->resolveBindingsForCapabilities($caps, $tenant);
        $warnings = array_merge($warnings, $bind['warnings']);

        $resourceId = ResourceIdentityManager::newResourcePublicId();
        $graphNode = ResourceIdentityManager::graphNodeId($resourceId);
        $intent = ProvisioningIntentFactory::fromOrderResolution(
            $order,
            $plan,
            $product,
            $resourceId,
            $correlationId,
            $traceId,
            'VALIDATING',
            $bind['target'],
            $caps,
            ['provisioning_trace' => ['phase' => 'commerce_activation_start', 'trace_id' => $traceId]]
        );
        $warnings = array_merge($warnings, $this->safety->detectDuplicateProvisioningIntent($orderPublicId, $intent->intentId()));

        $this->safety->logSafetyScan($orderPublicId, [
            'intent_id' => $intent->intentId(),
            'correlation_id' => $correlationId,
            'trace_id' => $traceId,
            'tenant_id' => $tenant->tenantId(),
        ], $actor, $this->audit);

        $this->audit->appendImmutable('commerce_activation_intent', [
            'actor' => $actor,
            'tenant_id' => $tenant->tenantId(),
            'order_public_id' => $orderPublicId,
            'intent_id' => $intent->intentId(),
            'intent' => $intent->toArray(),
            'correlation_id' => $correlationId,
            'trace_id' => $traceId,
        ]);

        $tId = isset($order['tenant_id']) && $order['tenant_id'] !== null ? (int) $order['tenant_id'] : ($tenant->tenantId() ?? 0);
        if ($tId === 0) {
            $warnings[] = 'No tenant_id on order or TenantContext; tenant_resource assign skipped.';
        } else {
            try {
                $trm = new TenantResourceManager($this->pdo, $this->audit);
                $trm->assign(
                    $tId,
                    [
                        'agency_id' => $order['agency_id'] ?? $tenant->agencyId(),
                        'resource_type' => 'infra_resource',
                        'resource_id' => $resourceId,
                        'commerce_product_id' => is_array($product) ? ($product['id'] ?? null) : null,
                        'commerce_plan_id' => is_array($plan) ? ($plan['id'] ?? null) : null,
                        'ownership_state' => 'OWNED',
                        'linked_graph_node' => $graphNode,
                        'metadata_json' => [
                            'order_public_id' => $orderPublicId,
                            'intent_id' => $intent->intentId(),
                            'trace_id' => $traceId,
                        ],
                    ],
                    $actor,
                    $correlationId
                );
            } catch (\Throwable $e) {
                $warnings[] = 'tenant_resource_assign skipped: ' . $e->getMessage();
            }
        }

        $existingJob = trim((string) ($order['provisioning_job_public_id'] ?? ''));
        if ($existingJob !== '') {
            $warnings[] = 'Order already linked to provisioning_job_public_id; skipping duplicate enqueue.';
            $this->audit->appendImmutable('commerce_activation_completed', [
                'actor' => $actor,
                'tenant_id' => $tenant->tenantId(),
                'order_public_id' => $orderPublicId,
                'intent_id' => $intent->intentId(),
                'provisioning_job_public_id' => $existingJob,
                'skipped_enqueue' => true,
                'correlation_id' => $correlationId,
                'trace_id' => $traceId,
                'resource_public_id' => $resourceId,
            ]);

            return [
                'ok' => true,
                'warnings' => $warnings,
                'resource_public_id' => $resourceId,
                'intent' => $intent->toArray(),
                'provisioning_job_public_id' => $existingJob,
                'skipped_enqueue' => true,
                'rolled_back' => false,
            ];
        }

        $sku = (string) ($order['sku'] ?? '');
        $catalog = new CatalogRepository($this->pdo);
        $visible = $catalog->listVisibleForTenant($tenant->tenantId());
        $found = null;
        foreach ($visible as $row) {
            if ((string) ($row['sku'] ?? '') === $sku) {
                $found = $row;
                break;
            }
        }
        $serviceType = 'hosting';
        if (is_array($found)) {
            $serviceType = (string) (($found['service_type'] ?? '') ?: '');
            if ($serviceType === '') {
                $mj = json_decode((string) ($found['metadata_json'] ?? '{}'), true);
                if (is_array($mj) && isset($mj['service_type'])) {
                    $serviceType = (string) $mj['service_type'];
                }
            }
        }
        if ($serviceType === '') {
            $serviceType = 'hosting';
        }

        $steps = ['hosting'];
        if ($serviceType === 'domain') {
            $steps = ['registrar', 'dns', 'ssl', 'hosting'];
        } elseif ($serviceType === 'ssl') {
            $steps = ['ssl'];
        } elseif ($serviceType === 'dns') {
            $steps = ['dns'];
        }

        $inputPayload = json_decode((string) ($order['payload_json'] ?? '{}'), true);
        if (!is_array($inputPayload)) {
            $inputPayload = [];
        }
        $jobTenant = new TenantContext(
            isset($order['tenant_id']) ? (int) $order['tenant_id'] : $tenant->tenantId(),
            isset($order['agency_id']) ? (int) $order['agency_id'] : $tenant->agencyId()
        );
        $job = new ProvisioningJob(
            $jobTenant,
            $steps,
            ['hosting' => new ProvisioningPayload('create_account', $inputPayload)],
            $correlationId
        );
        $jobPublicId = $this->orchestrator->submit($job);
        $orders->markQueued((int) $order['id'], $jobPublicId);

        if (is_array($plan)) {
            $cs = strtoupper(trim((string) ($plan['commerce_state'] ?? '')));
            if ($cs === 'PENDING_ACTIVATION') {
                $lifecycle = new ProductLifecycleManager($this->pdo, $this->audit);
                $lifecycle->transitionPlanCommerceState(
                    (int) $plan['id'],
                    'PENDING_ACTIVATION',
                    'ACTIVE',
                    $actor,
                    $correlationId,
                    $traceId
                );
            }
        }

        $this->events->structuredLog('info', 'Commerce activation enqueued', [
            'order_public_id' => $orderPublicId,
            'provisioning_job_public_id' => $jobPublicId,
            'intent_id' => $intent->intentId(),
            'trace_id' => $traceId,
        ]);
        $this->audit->appendImmutable('commerce_activation_completed', [
            'actor' => $actor,
            'tenant_id' => $tenant->tenantId(),
            'order_public_id' => $orderPublicId,
            'intent_id' => $intent->intentId(),
            'provisioning_job_public_id' => $jobPublicId,
            'correlation_id' => $correlationId,
            'trace_id' => $traceId,
            'resource_public_id' => $resourceId,
        ]);

        return [
            'ok' => true,
            'warnings' => $warnings,
            'resource_public_id' => $resourceId,
            'intent' => $intent->toArray(),
            'provisioning_job_public_id' => $jobPublicId,
            'skipped_enqueue' => false,
            'rolled_back' => false,
        ];
    }
}
