<?php
declare(strict_types=1);

namespace RATEB\InfrastructureMarketplace\Providers\Health;

use RATEB\InfrastructureMarketplace\Diagnostics\ProviderDiagnosticsService;
use RATEB\InfrastructureMarketplace\Observability\ProviderEventLogger;

final class ProviderHealthMonitor
{
    private \PDO $pdo;
    private ProviderDiagnosticsService $diagnostics;
    private ProviderEventLogger $events;

    public function __construct(\PDO $pdo, ?ProviderDiagnosticsService $diagnostics = null, ?ProviderEventLogger $events = null)
    {
        $this->pdo = $pdo;
        $this->diagnostics = $diagnostics ?? new ProviderDiagnosticsService($pdo);
        $this->events = $events ?? new ProviderEventLogger($pdo);
    }

    /**
     * @return array<string, mixed>
     */
    public function run(?int $tenantId = null, ?int $agencyId = null): array
    {
        $checks = (array) ($this->diagnostics->verify()['checks'] ?? []);
        $rows = [];
        $now = gmdate('c');
        foreach ($checks as $check) {
            if (!is_array($check)) {
                continue;
            }
            [$providerType, $providerCode] = $this->mapCheck((string) ($check['name'] ?? ''));
            if ($providerCode === '') {
                continue;
            }
            $status = strtoupper((string) ($check['status'] ?? 'WARN'));
            $msg = (string) ($check['message'] ?? '');
            $requestId = (string) ($check['request_id'] ?? bin2hex(random_bytes(8)));
            $durationMs = isset($check['duration_ms']) ? (int) $check['duration_ms'] : null;
            $reachable = $status === 'PASS' || isset($check['http_status']);
            $authValid = $status === 'PASS'
                || !in_array($msg, ['credentials_missing', 'invalid_credentials'], true);
            $failureCount = $this->events->failuresLastMinutes($providerCode, 60, $tenantId, $agencyId);
            $row = [
                'provider_type' => $providerType,
                'provider_code' => $providerCode,
                'request_id' => $requestId,
                'status' => $status,
                'message' => $msg,
                'auth_valid' => $authValid,
                'api_reachable' => $reachable,
                'latency_ms' => $durationMs,
                'http_status' => $check['http_status'] ?? null,
                'failures_last_hour' => $failureCount,
                'checked_at' => $now,
            ];
            $rows[] = $row;
            $this->events->log($providerType, $providerCode, 'health_check', [
                'request_id' => $requestId,
                'operation_name' => 'provider_health_monitor',
                'status' => $status === 'PASS' ? 'success' : 'degraded',
                'duration_ms' => $durationMs,
                'tenant_id' => $tenantId,
                'agency_id' => $agencyId,
                'payload' => $row,
                'error_message' => $status === 'PASS' ? null : $msg,
            ]);
        }

        return [
            'ok' => true,
            'checked_at' => $now,
            'providers' => $rows,
        ];
    }

    /**
     * @return array{0:string,1:string}
     */
    private function mapCheck(string $checkName): array
    {
        switch ($checkName) {
            case 'cpanel_connectivity':
                return ['hosting', 'cpanel_whm'];
            case 'cloudflare_connectivity':
                return ['dns', 'cloudflare_dns'];
            case 'namecheap_reachability':
                return ['registrar', 'namecheap'];
            case 'acme_reachability':
                return ['ssl', 'letsencrypt_ssl'];
            default:
                return ['', ''];
        }
    }
}
