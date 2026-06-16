<?php
declare(strict_types=1);

final class RATEB_ClientDashboard_ServiceHealthLayer
{
    /**
     * @param list<array<string, mixed>> $services
     * @param array<string, mixed> $infra
     * @return array<string, mixed>
     */
    public function summarize(array $services, array $infra): array
    {
        $degraded = 0;
        foreach ($services as $s) {
            $h = strtolower((string) ($s['health_state'] ?? ''));
            if ($h !== '' && $h !== 'healthy' && $h !== 'ok') {
                ++$degraded;
            }
        }

        $global = 'healthy';
        if (($infra['incident_level'] ?? '') === 'warning') {
            $global = 'degraded';
        }
        if ($degraded > 0) {
            $global = $global === 'healthy' ? 'mixed' : $global;
        }

        return [
            'global' => $global,
            'degraded_service_count' => $degraded,
            'infra_reachable' => !empty($infra['reachable']),
        ];
    }
}
