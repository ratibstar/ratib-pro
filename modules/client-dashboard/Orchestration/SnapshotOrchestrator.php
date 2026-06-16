<?php
/**
 * Composes production client snapshot: adapters + engines + legacy widgets.
 */
declare(strict_types=1);

final class RATEB_ClientDashboard_SnapshotOrchestrator
{
    /**
     * @param mysqli|null $conn
     * @return array<string, mixed>
     */
    public static function build(?mysqli $conn): array
    {
        require_once dirname(__DIR__) . '/Data/SnapshotBuilder.php';
        require_once dirname(__DIR__) . '/Data/FallbackPayloads.php';
        require_once dirname(__DIR__) . '/Observability/ObservabilityHub.php';
        require_once dirname(__DIR__) . '/Adapters/AdapterContext.php';
        require_once dirname(__DIR__) . '/Adapters/OrdersAdapter.php';
        require_once dirname(__DIR__) . '/Adapters/BillingAdapter.php';
        require_once dirname(__DIR__) . '/Adapters/DomainsAdapter.php';
        require_once dirname(__DIR__) . '/Adapters/HostingAdapter.php';
        require_once dirname(__DIR__) . '/Adapters/InfrastructureAdapter.php';
        require_once dirname(__DIR__) . '/Registry/ServiceRegistry.php';
        require_once dirname(__DIR__) . '/Activity/ActivityStreamBuilder.php';
        require_once dirname(__DIR__) . '/Notifications/NotificationEngine.php';
        require_once dirname(__DIR__) . '/Billing/BillingSyncService.php';
        require_once dirname(__DIR__) . '/Subscription/SubscriptionStateEngine.php';
        require_once dirname(__DIR__) . '/Health/ServiceHealthLayer.php';
        require_once dirname(__DIR__) . '/Security/ClientSecuritySnapshotBuilder.php';
        require_once dirname(__DIR__) . '/Governance/GovernanceFacade.php';

        $obs = new RATEB_ClientDashboard_ObservabilityHub();
        $obs->setCorrelationId(bin2hex(random_bytes(8)));
        $ctx = RATEB_ClientDashboard_AdapterContext::fromSession($conn, $obs);

        $base = RATEB_ClientDashboard_SnapshotBuilder::build($conn);
        $widgets = $base['widgets'];

        $t0 = microtime(true);
        $ordersAdapter = new RATEB_ClientDashboard_OrdersAdapter();
        $orders = $ordersAdapter->fetchNormalized($ctx);
        $obs->recordAdapterTiming('orders', (microtime(true) - $t0) * 1000.0);

        $t0 = microtime(true);
        $billingAdapter = new RATEB_ClientDashboard_BillingAdapter();
        $billingRaw = $billingAdapter->fetchNormalized($ctx);
        $obs->recordAdapterTiming('billing', (microtime(true) - $t0) * 1000.0);

        $t0 = microtime(true);
        $domainsAdapter = new RATEB_ClientDashboard_DomainsAdapter();
        $domainPack = $domainsAdapter->fetchNormalized($ctx);
        $obs->recordAdapterTiming('domains', (microtime(true) - $t0) * 1000.0);

        $t0 = microtime(true);
        $hostingAdapter = new RATEB_ClientDashboard_HostingAdapter();
        $hostingRows = $hostingAdapter->fetchNormalized($ctx);
        $obs->recordAdapterTiming('hosting', (microtime(true) - $t0) * 1000.0);

        $t0 = microtime(true);
        $infraAdapter = new RATEB_ClientDashboard_InfrastructureAdapter();
        $infra = $infraAdapter->fetchAwareness($ctx);
        $obs->recordAdapterTiming('infrastructure', (microtime(true) - $t0) * 1000.0);

        $registry = new RATEB_ClientDashboard_ServiceRegistry();
        $services = $registry->merge($hostingRows, $domainPack['domains'] ?? []);

        $billingSync = (new RATEB_ClientDashboard_BillingSyncService())->synthesize($billingRaw, $orders);

        $subscription = (new RATEB_ClientDashboard_SubscriptionStateEngine())->evaluate($billingSync, $services);

        $health = (new RATEB_ClientDashboard_ServiceHealthLayer())->summarize($services, $infra);

        $activity = (new RATEB_ClientDashboard_ActivityStreamBuilder())->build($ctx, $orders);

        $notifications = (new RATEB_ClientDashboard_NotificationEngine())->build(
            $ctx,
            $billingRaw,
            $orders,
            $domainPack['expiry_alerts'] ?? [],
            $infra
        );

        $security = (new RATEB_ClientDashboard_ClientSecuritySnapshotBuilder())->build($ctx);

        /* Enrich legacy widgets (backward compatible) */
        $widgets['active_services_count'] = count($services);
        $widgets['recent_orders'] = array_slice($orders, 0, 5);
        if ($billingRaw['invoice_count'] !== null) {
            $widgets['billing_summary']['currency'] = (string) ($billingRaw['currency'] ?? 'SAR');
            $widgets['billing_summary']['invoice_count'] = $billingRaw['invoice_count'];
        }
        $widgets['domain_expiry_alerts'] = $domainPack['expiry_alerts'] ?? [];
        $widgets['subscription_health'] = [
            'status' => (string) ($subscription['status'] ?? 'unknown'),
            'label' => (string) ($subscription['label'] ?? ''),
        ];
        $widgets['infra_status'] = [
            'region' => 'global',
            'control_plane' => (string) ($infra['control_plane'] ?? 'unknown'),
            'last_incident' => ($infra['incident_level'] ?? 'none') !== 'none' ? 'advisory' : null,
            'reachable' => !empty($infra['reachable']),
        ];
        $widgets['security_alerts'] = array_slice($notifications['items'] ?? [], 0, 4);

        $feedFromActivity = [];
        foreach (array_slice($activity, 0, 6) as $ev) {
            $feedFromActivity[] = [
                'title' => (string) ($ev['title'] ?? ''),
                'at' => (string) ($ev['timestamp'] ?? ''),
            ];
        }
        if (!empty($feedFromActivity)) {
            $widgets['activity_feed'] = $feedFromActivity;
        }

        $base['widgets'] = $widgets;
        $base['source'] = self::resolveSource((string) ($base['source'] ?? 'fallback'), $obs);
        $base['service_registry'] = ['services' => $services, 'count' => count($services)];
        $base['activity'] = ['stream' => $activity, 'filters_supported' => ['severity', 'source', 'range']];
        $base['notifications'] = $notifications;
        $base['security'] = $security;
        $base['subscription'] = $subscription;
        $base['billing'] = ['adapter' => $billingRaw, 'sync' => $billingSync];
        $base['health'] = $health;
        $base['infrastructure'] = $infra;
        $base['observability'] = $obs->snapshotSlice();

        try {
            return RATEB_ClientDashboard_GovernanceFacade::mergeSnapshot(
                $base,
                $ctx,
                $obs,
                $orders,
                $services,
                $billingSync,
                $subscription,
                $infra,
                $notifications,
                $domainPack['domains'] ?? []
            );
        } catch (\Throwable $e) {
            $obs->markDegraded('governance_merge', true);
            $base['observability'] = $obs->snapshotSlice();
            $base['governance'] = [
                'error' => 'governance_degraded',
                'message' => 'Governance layer skipped safely.',
            ];

            return $base;
        }
    }

    private static function resolveSource(string $legacy, RATEB_ClientDashboard_ObservabilityHub $obs): string
    {
        $slice = $obs->snapshotSlice();
        if (!empty($slice['degraded_flags'])) {
            return $legacy === 'fallback' ? 'degraded' : $legacy . '+degraded';
        }

        return $legacy;
    }
}
