<?php
/**
 * Single entry to enrich snapshots and action payloads with governance contracts.
 */
declare(strict_types=1);

final class Ratib_ClientDashboard_GovernanceFacade
{
    /**
     * @param array<string, mixed> $base
     * @return array<string, mixed>
     */
    public static function mergeSnapshot(
        array $base,
        Ratib_ClientDashboard_AdapterContext $ctx,
        Ratib_ClientDashboard_ObservabilityHub $obs,
        array $orders,
        array $services,
        array $billingSync,
        array $subscription,
        array $infra,
        array $notifications,
        array $domainRows
    ): array {
        require_once dirname(__DIR__) . '/Tenant/TenantScope.php';
        require_once dirname(__DIR__) . '/State/UnifiedStateEngine.php';
        require_once dirname(__DIR__) . '/Consistency/ConsistencyValidator.php';
        require_once dirname(__DIR__) . '/Policy/PolicyEngine.php';
        require_once dirname(__DIR__) . '/Recovery/RecoveryLayer.php';
        require_once dirname(__DIR__) . '/Async/AsyncCoordinationLayer.php';

        $tenant = Ratib_ClientDashboard_TenantScope::fromSession();
        $stateEngine = new Ratib_ClientDashboard_UnifiedStateEngine();
        $unified = $stateEngine->compile($orders, $services, $billingSync, $subscription, $infra, $notifications);

        $validator = new Ratib_ClientDashboard_ConsistencyValidator();
        $warnings = $validator->validate($unified, $services, $domainRows);

        $policy = new Ratib_ClientDashboard_PolicyEngine();

        $asyncPreview = Ratib_ClientDashboard_AsyncCoordinationLayer::tail($ctx->conn, $tenant->userId, 5);

        $recovery = new Ratib_ClientDashboard_RecoveryLayer();
        $base['governance'] = [
            'tenant_scope' => $tenant->toMeta(),
            'unified_state' => $unified,
            'consistency' => [
                'warnings' => $warnings,
                'blocking' => false,
            ],
            'policy' => $policy->publicSnapshot(),
            'lifecycle' => [
                'coordinator' => 'ServiceLifecycleCoordinator',
                'note' => 'All mutations should pass coordinator + policy before runtime.',
            ],
            'async' => [
                'recent_jobs' => $asyncPreview,
                'provider' => 'optional_db',
            ],
        ];

        $base['recovery'] = $recovery->hintsFromSnapshot($base);

        $base['observability'] = array_merge(
            (array) ($base['observability'] ?? []),
            [
                'governance_correlation' => $obs->correlationId(),
            ]
        );

        return $base;
    }

    /**
     * @param array<string, mixed> $result
     * @return array<string, mixed>
     */
    public static function augmentActionResponse(
        array $result,
        string $correlationId,
        string $traceId,
        ?array $asyncEnvelope,
        Ratib_ClientDashboard_ServiceLifecycleCoordinator $lifecycle,
        string $verb
    ): array {
        $lc = $lifecycle->mapActionToLifecycle($verb, null);

        return array_merge($result, [
            'governance' => [
                'correlation_id' => $correlationId,
                'trace_id' => $traceId,
                'lifecycle' => $lc,
                'async' => $asyncEnvelope,
            ],
        ]);
    }
}
