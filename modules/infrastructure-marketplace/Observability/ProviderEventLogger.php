<?php
declare(strict_types=1);

namespace Ratib\InfrastructureMarketplace\Observability;

final class ProviderEventLogger
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * @param array<string, mixed> $context
     */
    public function log(string $providerType, string $providerCode, string $eventName, array $context = []): void
    {
        $sql = 'INSERT INTO ratib_infra_provider_events
                (provider_type, provider_code, event_name, request_id, operation_name, status, duration_ms, retry_count, tenant_id, agency_id, error_message, payload_json, created_at)
                VALUES
                (:provider_type, :provider_code, :event_name, :request_id, :operation_name, :status, :duration_ms, :retry_count, :tenant_id, :agency_id, :error_message, :payload_json, NOW())';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'provider_type' => substr(strtolower(trim($providerType)), 0, 32),
            'provider_code' => substr(strtolower(trim($providerCode)), 0, 64),
            'event_name' => substr(strtolower(trim($eventName)), 0, 64),
            'request_id' => substr((string) ($context['request_id'] ?? ''), 0, 80),
            'operation_name' => substr((string) ($context['operation_name'] ?? ''), 0, 80),
            'status' => substr((string) ($context['status'] ?? 'unknown'), 0, 32),
            'duration_ms' => isset($context['duration_ms']) ? (int) $context['duration_ms'] : null,
            'retry_count' => isset($context['retry_count']) ? (int) $context['retry_count'] : null,
            'tenant_id' => isset($context['tenant_id']) ? (int) $context['tenant_id'] : null,
            'agency_id' => isset($context['agency_id']) ? (int) $context['agency_id'] : null,
            'error_message' => isset($context['error_message']) ? substr((string) $context['error_message'], 0, 500) : null,
            'payload_json' => json_encode($context['payload'] ?? [], JSON_UNESCAPED_SLASHES),
        ]);
    }

    public function failuresLastMinutes(string $providerCode, int $minutes = 60, ?int $tenantId = null, ?int $agencyId = null): int
    {
        $sql = 'SELECT COUNT(*) FROM ratib_infra_provider_events
                WHERE provider_code = :provider_code
                  AND created_at >= DATE_SUB(NOW(), INTERVAL :minutes MINUTE)
                  AND status IN ("failed","error","retry","degraded")
                  AND (tenant_id IS NULL OR tenant_id <=> :tenant_id)
                  AND (agency_id IS NULL OR agency_id <=> :agency_id)';
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':provider_code', strtolower(trim($providerCode)));
        $stmt->bindValue(':minutes', max(1, $minutes), \PDO::PARAM_INT);
        if ($tenantId === null) {
            $stmt->bindValue(':tenant_id', null, \PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':tenant_id', $tenantId, \PDO::PARAM_INT);
        }
        if ($agencyId === null) {
            $stmt->bindValue(':agency_id', null, \PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':agency_id', $agencyId, \PDO::PARAM_INT);
        }
        $stmt->execute();

        return (int) ($stmt->fetchColumn() ?: 0);
    }
}
