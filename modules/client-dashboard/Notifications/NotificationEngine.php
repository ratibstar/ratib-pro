<?php
declare(strict_types=1);

final class RATEB_ClientDashboard_NotificationEngine
{
    /**
     * Standalone API entry (reuses adapters; partial-failure safe).
     *
     * @return array{items: list<array<string, mixed>>, unread_count: int, grouped: array<string, list<array<string, mixed>>>}
     */
    public static function buildForHttp(?mysqli $conn): array
    {
        require_once dirname(__DIR__) . '/Observability/ObservabilityHub.php';
        require_once dirname(__DIR__) . '/Adapters/AdapterContext.php';
        require_once dirname(__DIR__) . '/Adapters/OrdersAdapter.php';
        require_once dirname(__DIR__) . '/Adapters/BillingAdapter.php';
        require_once dirname(__DIR__) . '/Adapters/DomainsAdapter.php';
        require_once dirname(__DIR__) . '/Adapters/InfrastructureAdapter.php';

        $obs = new RATEB_ClientDashboard_ObservabilityHub();
        $ctx = RATEB_ClientDashboard_AdapterContext::fromSession($conn, $obs);
        $billing = (new RATEB_ClientDashboard_BillingAdapter())->fetchNormalized($ctx);
        $orders = (new RATEB_ClientDashboard_OrdersAdapter())->fetchNormalized($ctx);
        $domainPack = (new RATEB_ClientDashboard_DomainsAdapter())->fetchNormalized($ctx);
        $infra = (new RATEB_ClientDashboard_InfrastructureAdapter())->fetchAwareness($ctx);

        return (new self())->build(
            $ctx,
            $billing,
            $orders,
            $domainPack['expiry_alerts'] ?? [],
            $infra
        );
    }

    /**
     * @param array<string, mixed> $billing
     * @param list<array<string, mixed>> $orders
     * @param list<array<string, mixed>> $domainAlerts
     * @param array<string, mixed> $infra
     * @return array{items: list<array<string, mixed>>, unread_count: int, grouped: array<string, list<array<string, mixed>>>}
     */
    public function build(
        RATEB_ClientDashboard_AdapterContext $ctx,
        array $billing,
        array $orders,
        array $domainAlerts,
        array $infra
    ): array {
        $items = [];

        $stored = $this->loadStored($ctx);
        foreach ($stored as $row) {
            $items[] = $row;
        }

        foreach ($domainAlerts as $a) {
            $items[] = [
                'id' => 'syn:dom:' . sha1((string) json_encode($a)),
                'kind' => 'domain_expiry',
                'severity' => (string) ($a['severity'] ?? 'medium'),
                'title' => 'Domain expiring · ' . ($a['fqdn'] ?? ''),
                'body' => 'Days left: ' . (string) ($a['days_left'] ?? ''),
                'unread' => true,
                'created_at' => gmdate('c'),
            ];
        }

        foreach ($orders as $o) {
            if (($o['payment_status'] ?? '') === 'failed') {
                $items[] = [
                    'id' => 'syn:pay:' . ($o['id'] ?? ''),
                    'kind' => 'failed_payment',
                    'severity' => 'high',
                    'title' => 'Payment failed · ' . ($o['id'] ?? ''),
                    'body' => (string) ($o['product'] ?? ''),
                    'unread' => true,
                    'created_at' => gmdate('c'),
                ];
            }
        }

        if (($infra['incident_level'] ?? '') === 'warning') {
            $items[] = [
                'id' => 'syn:infra:' . gmdate('YmdH'),
                'kind' => 'infrastructure',
                'severity' => 'medium',
                'title' => 'Provider capacity advisory',
                'body' => 'Infrastructure probe reported degraded provider bindings.',
                'unread' => true,
                'created_at' => gmdate('c'),
            ];
        }

        $inv = $billing['invoice_count'] ?? null;
        if (is_int($inv) && $inv > 80) {
            $items[] = [
                'id' => 'syn:bill:volume',
                'kind' => 'invoice_due',
                'severity' => 'low',
                'title' => 'High invoice volume detected',
                'body' => 'Review billing workspace for reconciliation.',
                'unread' => false,
                'created_at' => gmdate('c'),
            ];
        }

        $unread = 0;
        foreach ($items as $it) {
            if (!empty($it['unread'])) {
                ++$unread;
            }
        }

        $grouped = [];
        foreach ($items as $it) {
            $k = (string) ($it['kind'] ?? 'general');
            if (!isset($grouped[$k])) {
                $grouped[$k] = [];
            }
            $grouped[$k][] = $it;
        }

        return [
            'items' => array_slice($items, 0, 40),
            'unread_count' => $unread,
            'grouped' => $grouped,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function loadStored(RATEB_ClientDashboard_AdapterContext $ctx): array
    {
        $conn = $ctx->conn;
        if (!$conn instanceof mysqli) {
            return [];
        }
        try {
            $chk = @$conn->query("SHOW TABLES LIKE 'rateb_client_hub_notifications'");
            if (!$chk || $chk->num_rows === 0) {
                return [];
            }
            $uid = $ctx->userId;
            $stmt = @$conn->prepare('SELECT id, kind, severity, title, body, read_at, created_at FROM rateb_client_hub_notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 50');
            if (!$stmt) {
                return [];
            }
            $stmt->bind_param('i', $uid);
            $stmt->execute();
            $res = $stmt->get_result();
            $out = [];
            while ($row = $res->fetch_assoc()) {
                $out[] = [
                    'id' => 'db:' . (string) ($row['id'] ?? ''),
                    'kind' => (string) ($row['kind'] ?? ''),
                    'severity' => (string) ($row['severity'] ?? 'info'),
                    'title' => (string) ($row['title'] ?? ''),
                    'body' => (string) ($row['body'] ?? ''),
                    'unread' => empty($row['read_at']),
                    'created_at' => gmdate('c', strtotime((string) ($row['created_at'] ?? 'now')) ?: time()),
                ];
            }
            $stmt->close();

            return $out;
        } catch (Throwable $e) {
            return [];
        }
    }
}
