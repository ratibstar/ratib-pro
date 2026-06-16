<?php
/**
 * Baseline payloads for APIs when integrations are absent or degraded.
 */
declare(strict_types=1);

final class RATEB_ClientDashboard_FallbackPayloads
{
    /**
     * @return array<string, mixed>
     */
    public static function homeSnapshotEnvelope(): array
    {
        return [
            'ok' => true,
            'generated_at' => gmdate('c'),
            'source' => 'fallback',
            'widgets' => self::fallbackWidgets(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function fallbackWidgets(): array
    {
        return [
            'active_services_count' => 0,
            'recent_orders' => [],
            'billing_summary' => [
                'currency' => 'SAR',
                'balance_due' => null,
                'next_invoice_date' => null,
                'auto_pay' => null,
            ],
            'infra_status' => [
                'region' => '—',
                'control_plane' => 'unknown',
                'last_incident' => null,
            ],
            'security_alerts' => [],
            'subscription_health' => ['status' => 'unknown', 'label' => 'No subscription data'],
            'domain_expiry_alerts' => [],
            'usage' => ['cpu_pct' => null, 'traffic_tb' => null, 'projects' => null],
            'activity_feed' => [],
            'quick_actions' => [
                ['id' => 'open_ticket', 'label' => 'Open ticket'],
                ['id' => 'retry_payment', 'label' => 'Retry payment'],
                ['id' => 'renew', 'label' => 'Renew service'],
                ['id' => 'upgrade', 'label' => 'Upgrade plan'],
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function demoOrdersRows(): array
    {
        return [
            [
                'id' => 'ORD-100042',
                'product' => 'Business Hosting · Gold',
                'status' => 'active',
                'payment_status' => 'paid',
                'created_at' => gmdate('Y-m-d', strtotime('-14 days')),
                'renewal_at' => gmdate('Y-m-d', strtotime('+50 days')),
            ],
            [
                'id' => 'ORD-100041',
                'product' => 'Domain · example.sa',
                'status' => 'processing',
                'payment_status' => 'pending',
                'created_at' => gmdate('Y-m-d', strtotime('-2 days')),
                'renewal_at' => gmdate('Y-m-d', strtotime('+360 days')),
            ],
            [
                'id' => 'ORD-100039',
                'product' => 'Cloud VPS · CX21',
                'status' => 'suspended',
                'payment_status' => 'failed',
                'created_at' => gmdate('Y-m-d', strtotime('-180 days')),
                'renewal_at' => gmdate('Y-m-d', strtotime('+8 days')),
            ],
        ];
    }
}
