<?php
/**
 * Non-fatal consistency checks — warnings only for UX / ops.
 */
declare(strict_types=1);

final class RATEB_ClientDashboard_ConsistencyValidator
{
    /**
     * @param array<string, mixed> $unifiedState
     * @param list<array<string, mixed>> $services
     * @param list<array<string, mixed>> $domainRows
     * @return list<array{code: string, severity: string, message: string}>
     */
    public function validate(array $unifiedState, array $services, array $domainRows): array
    {
        $warnings = [];

        $failedPay = (int) (($unifiedState['billing'] ?? [])['failed_payment_orders'] ?? 0);
        $activeSvc = (int) (($unifiedState['services'] ?? [])['active'] ?? 0);
        if ($failedPay > 0 && $activeSvc > 0) {
            $warnings[] = [
                'code' => 'active_service_with_failed_payments',
                'severity' => 'high',
                'message' => 'Active services exist while failed payment orders detected.',
            ];
        }

        $subHealthy = (($unifiedState['subscription'] ?? [])['status'] ?? '') === 'healthy';
        $totalSvc = (int) (($unifiedState['services'] ?? [])['total'] ?? 0);
        if ($subHealthy && $totalSvc === 0) {
            $warnings[] = [
                'code' => 'healthy_subscription_zero_services',
                'severity' => 'medium',
                'message' => 'Subscription reports healthy but no services in registry.',
            ];
        }

        foreach ($domainRows as $d) {
            $health = strtolower((string) ($d['health_state'] ?? ''));
            $exp = $d['expires_at'] ?? '';
            if ($exp === '') {
                continue;
            }
            $ts = strtotime((string) $exp . ' UTC');
            if ($ts !== false && $ts < time() && $health === 'ok') {
                $warnings[] = [
                    'code' => 'expired_domain_marked_healthy',
                    'severity' => 'high',
                    'message' => 'Domain ' . ($d['fqdn'] ?? '') . ' expired but health_state is ok.',
                ];
            }
        }

        foreach ($services as $s) {
            $bind = (string) ($s['infrastructure_binding'] ?? '');
            $st = strtolower((string) ($s['status'] ?? ''));
            if ($st === 'active' && $bind === '') {
                $warnings[] = [
                    'code' => 'orphan_binding_risk',
                    'severity' => 'low',
                    'message' => 'Active service ' . ($s['service_id'] ?? '') . ' has empty infrastructure binding.',
                ];
            }
        }

        return $warnings;
    }
}
