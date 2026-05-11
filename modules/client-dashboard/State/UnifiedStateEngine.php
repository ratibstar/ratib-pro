<?php
/**
 * Central normalized view across domains (validation hints, not enforcement here).
 */
declare(strict_types=1);

final class Ratib_ClientDashboard_UnifiedStateEngine
{
    /**
     * @param list<array<string, mixed>> $orders
     * @param list<array<string, mixed>> $services
     * @param array<string, mixed> $billingSync
     * @param array<string, mixed> $subscription
     * @param array<string, mixed> $infra
     * @param array<string, mixed> $notifications
     * @return array<string, mixed>
     */
    public function compile(
        array $orders,
        array $services,
        array $billingSync,
        array $subscription,
        array $infra,
        array $notifications
    ): array {
        $orderAgg = $this->aggregateOrders($orders);
        $svcAgg = $this->aggregateServices($services);

        return [
            'version' => 1,
            'orders' => $orderAgg,
            'services' => $svcAgg,
            'billing' => [
                'recurring_state' => (string) ($billingSync['recurring_state'] ?? 'unknown'),
                'failed_payment_orders' => (int) ($billingSync['failed_payment_orders'] ?? 0),
            ],
            'subscription' => [
                'status' => (string) ($subscription['status'] ?? 'unknown'),
                'active_services' => (int) ($subscription['active_services'] ?? 0),
                'suspended_services' => (int) ($subscription['suspended_services'] ?? 0),
            ],
            'infrastructure' => [
                'reachable' => !empty($infra['reachable']),
                'incident_level' => (string) ($infra['incident_level'] ?? 'none'),
            ],
            'notifications' => [
                'unread' => (int) ($notifications['unread_count'] ?? 0),
            ],
            'integrity_hint' => $this->integrityHint($orderAgg, $svcAgg, $billingSync, $subscription),
        ];
    }

    /**
     * @param array<string, int> $orderAgg
     * @param array<string, int> $svcAgg
     * @param array<string, mixed> $billingSync
     * @param array<string, mixed> $subscription
     */
    private function integrityHint(array $orderAgg, array $svcAgg, array $billingSync, array $subscription): string
    {
        if ((int) ($billingSync['failed_payment_orders'] ?? 0) > 0 && ($svcAgg['active'] ?? 0) > 0) {
            return 'review_billing_service_alignment';
        }
        if (($subscription['status'] ?? '') === 'healthy' && ($svcAgg['total'] ?? 0) === 0) {
            return 'subscription_without_registered_service';
        }

        return 'nominal';
    }

    /**
     * @param list<array<string, mixed>> $orders
     * @return array<string, int>
     */
    private function aggregateOrders(array $orders): array
    {
        $agg = ['total' => 0, 'pending' => 0, 'active' => 0, 'failed' => 0];
        foreach ($orders as $o) {
            ++$agg['total'];
            $st = strtolower((string) ($o['status'] ?? ''));
            if ($st === 'pending' || $st === 'processing') {
                ++$agg['pending'];
            }
            if ($st === 'active') {
                ++$agg['active'];
            }
            if ($st === 'failed' || ($o['payment_status'] ?? '') === 'failed') {
                ++$agg['failed'];
            }
        }

        return $agg;
    }

    /**
     * @param list<array<string, mixed>> $services
     * @return array<string, int>
     */
    private function aggregateServices(array $services): array
    {
        $agg = ['total' => count($services), 'active' => 0, 'suspended' => 0];
        foreach ($services as $s) {
            $st = strtolower((string) ($s['status'] ?? ''));
            if ($st === 'active') {
                ++$agg['active'];
            }
            if ($st === 'suspended') {
                ++$agg['suspended'];
            }
        }

        return $agg;
    }
}
