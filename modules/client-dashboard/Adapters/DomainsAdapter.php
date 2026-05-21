<?php
declare(strict_types=1);

final class Ratib_ClientDashboard_DomainsAdapter
{
    /**
     * @return array{domains: list<array<string, mixed>>, expiry_alerts: list<array<string, mixed>>}
     */
    public function fetchNormalized(Ratib_ClientDashboard_AdapterContext $ctx): array
    {
        $domains = [];
        $alerts = [];

        try {
            $conn = $ctx->conn;
            if ($conn instanceof mysqli) {
                $custom = $this->tryClientDomains($conn);
                if ($custom !== null) {
                    $ctx->obs->recordAdapter('domains', true, null, ['rows' => count($custom)]);

                    return ['domains' => $custom, 'expiry_alerts' => $this->alertsFromDomains($custom)];
                }
            }
        } catch (Throwable $e) {
            $ctx->obs->recordAdapter('domains', false, $e->getMessage());
        }

        $ctx->obs->recordAdapter('domains', true, 'placeholder', ['fallback' => true]);
        $demo = [
            [
                'service_id' => 'dom:demo:1',
                'fqdn' => 'example.sa',
                'registrar' => 'RATEB',
                'expires_at' => gmdate('Y-m-d', strtotime('+300 days')),
                'auto_renew' => true,
                'transfer_lock' => true,
                'health_state' => 'ok',
            ],
        ];

        return ['domains' => $demo, 'expiry_alerts' => $this->alertsFromDomains($demo)];
    }

    /**
     * @return list<array<string, mixed>>|null
     */
    private function tryClientDomains(mysqli $conn): ?array
    {
        $chk = @$conn->query("SHOW TABLES LIKE 'ratib_client_domains'");
        if (!$chk || $chk->num_rows === 0) {
            return null;
        }
        $r = @$conn->query('SELECT service_id, fqdn, registrar, expires_at, auto_renew, transfer_lock, health_state FROM ratib_client_domains ORDER BY expires_at ASC LIMIT 100');
        if (!$r) {
            return null;
        }
        $out = [];
        while ($row = $r->fetch_assoc()) {
            $out[] = [
                'service_id' => (string) ($row['service_id'] ?? ''),
                'fqdn' => (string) ($row['fqdn'] ?? ''),
                'registrar' => (string) ($row['registrar'] ?? ''),
                'expires_at' => (string) ($row['expires_at'] ?? ''),
                'auto_renew' => !empty($row['auto_renew']),
                'transfer_lock' => !empty($row['transfer_lock']),
                'health_state' => (string) ($row['health_state'] ?? 'unknown'),
            ];
        }

        return $out;
    }

    /**
     * @param list<array<string, mixed>> $domains
     * @return list<array<string, mixed>>
     */
    private function alertsFromDomains(array $domains): array
    {
        $alerts = [];
        foreach ($domains as $d) {
            $exp = $d['expires_at'] ?? '';
            if ($exp === '') {
                continue;
            }
            $ts = strtotime($exp . ' UTC');
            if ($ts === false) {
                continue;
            }
            $days = (int) floor(($ts - time()) / 86400);
            if ($days <= 30 && $days >= 0) {
                $alerts[] = [
                    'severity' => $days <= 7 ? 'high' : 'medium',
                    'fqdn' => $d['fqdn'] ?? '',
                    'days_left' => $days,
                ];
            }
        }

        return $alerts;
    }
}
