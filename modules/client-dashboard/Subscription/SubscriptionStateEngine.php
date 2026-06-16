<?php
declare(strict_types=1);

final class RATEB_ClientDashboard_SubscriptionStateEngine
{
    /**
     * @param array<string, mixed> $billingSync
     * @param list<array<string, mixed>> $services
     * @return array<string, mixed>
     */
    public function evaluate(array $billingSync, array $services): array
    {
        $suspended = 0;
        $active = 0;
        foreach ($services as $s) {
            $st = strtolower((string) ($s['status'] ?? ''));
            if ($st === 'suspended') {
                ++$suspended;
            }
            if ($st === 'active') {
                ++$active;
            }
        }

        $risk = (int) ($billingSync['failed_payment_orders'] ?? 0) > 0 || $suspended > 0;

        return [
            'status' => $risk ? 'at_risk' : 'healthy',
            'label' => $risk ? 'Renewals or payments need attention' : 'Subscriptions nominal',
            'active_services' => $active,
            'suspended_services' => $suspended,
        ];
    }
}
