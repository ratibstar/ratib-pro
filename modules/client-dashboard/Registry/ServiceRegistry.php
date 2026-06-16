<?php
declare(strict_types=1);

final class RATEB_ClientDashboard_ServiceRegistry
{
    /**
     * @param list<array<string, mixed>> $hostingRows
     * @param list<array<string, mixed>> $domainRows
     * @return list<array<string, mixed>>
     */
    public function merge(array $hostingRows, array $domainRows): array
    {
        $services = [];

        foreach ($hostingRows as $h) {
            $services[] = $this->normalizeHostingRow($h);
        }

        foreach ($domainRows as $d) {
            $services[] = [
                'service_id' => (string) ($d['service_id'] ?? ''),
                'type' => 'domain',
                'provider' => (string) ($d['registrar'] ?? 'registry'),
                'status' => 'active',
                'billing_state' => !empty($d['auto_renew']) ? 'auto_renew' : 'manual',
                'health_state' => (string) ($d['health_state'] ?? 'unknown'),
                'renewal_state' => $this->renewalFromDate($d['expires_at'] ?? ''),
                'quick_actions' => ['renew', 'dns', 'transfer_lock'],
                'infrastructure_binding' => 'dns:zone/' . ($d['fqdn'] ?? ''),
            ];
        }

        $hasEmail = false;
        $hasSsl = false;
        foreach ($services as $s) {
            $t = strtolower((string) ($s['type'] ?? ''));
            if ($t === 'email') {
                $hasEmail = true;
            }
            if ($t === 'ssl') {
                $hasSsl = true;
            }
        }
        foreach ($this->syntheticEdgeServices() as $edge) {
            $t = strtolower((string) ($edge['type'] ?? ''));
            if ($t === 'email' && $hasEmail) {
                continue;
            }
            if ($t === 'ssl' && $hasSsl) {
                continue;
            }
            $services[] = $edge;
        }

        return $services;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function syntheticEdgeServices(): array
    {
        return [
            [
                'service_id' => 'email:bundle:1',
                'type' => 'email',
                'provider' => 'rateb-mail',
                'status' => 'active',
                'billing_state' => 'paid',
                'health_state' => 'healthy',
                'renewal_state' => 'bundled',
                'quick_actions' => ['suspend', 'upgrade'],
                'infrastructure_binding' => 'mx:cluster-primary',
            ],
            [
                'service_id' => 'ssl:edge:1',
                'type' => 'ssl',
                'provider' => 'acme-proxy',
                'status' => 'active',
                'billing_state' => 'paid',
                'health_state' => 'healthy',
                'renewal_state' => 'auto',
                'quick_actions' => ['renew', 'upgrade'],
                'infrastructure_binding' => 'cert:wildcard',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $h
     * @return array<string, mixed>
     */
    private function normalizeHostingRow(array $h): array
    {
        return [
            'service_id' => (string) ($h['service_id'] ?? ''),
            'type' => (string) ($h['type'] ?? ''),
            'provider' => (string) ($h['provider'] ?? ''),
            'status' => (string) ($h['status'] ?? ''),
            'billing_state' => (string) ($h['billing_state'] ?? ''),
            'health_state' => (string) ($h['health_state'] ?? ''),
            'renewal_state' => (string) ($h['renewal_state'] ?? ''),
            'quick_actions' => is_array($h['quick_actions'] ?? null) ? $h['quick_actions'] : [],
            'infrastructure_binding' => (string) ($h['infrastructure_binding'] ?? ''),
        ];
    }

    private function renewalFromDate(string $expiresAt): string
    {
        if ($expiresAt === '') {
            return 'unknown';
        }
        $ts = strtotime($expiresAt . ' UTC');
        if ($ts === false) {
            return 'unknown';
        }
        $days = (int) floor(($ts - time()) / 86400);
        if ($days < 0) {
            return 'expired';
        }
        if ($days <= 14) {
            return 'critical';
        }
        if ($days <= 45) {
            return 'soon';
        }

        return 'healthy';
    }
}
