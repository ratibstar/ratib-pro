<?php
declare(strict_types=1);

namespace RATEB\InfrastructureMarketplace\Observability;

/**
 * Lightweight DB-backed throttling to avoid provider event spam and retry-storm logging.
 */
final class ProviderFailureThrottle
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function allowEventLog(string $providerCode, string $eventName, string $status): bool
    {
        if (!$this->eventsTableReady()) {
            return true;
        }
        $providerCode = strtolower(trim($providerCode));
        $eventName = strtolower(trim($eventName));
        $status = strtolower(trim($status));

        if ($eventName === 'health_check') {
            $limit = $this->envInt('RATEB_INFRA_HEALTH_LOG_MAX_PER_MINUTE', 12);
            return $this->recentCount($providerCode, $eventName, 1) < $limit;
        }

        if (in_array($status, ['failed', 'error', 'retry', 'degraded'], true)) {
            $limit = $this->envInt('RATEB_INFRA_FAILURE_LOG_MAX_PER_MINUTE', 30);
            return $this->recentCount($providerCode, $eventName, 1) < $limit;
        }

        $limit = $this->envInt('RATEB_INFRA_EVENT_LOG_MAX_PER_MINUTE', 90);
        return $this->recentCount($providerCode, $eventName, 1) < $limit;
    }

    public function allowWorkerFailureLog(string $jobPublicId): bool
    {
        if (!$this->eventsTableReady() || trim($jobPublicId) === '') {
            return true;
        }
        $windowSec = $this->envInt('RATEB_INFRA_WORKER_FAILURE_LOG_WINDOW_SEC', 300);
        $maxInWindow = $this->envInt('RATEB_INFRA_WORKER_FAILURE_LOG_MAX', 5);
        $sql = 'SELECT COUNT(*) FROM rateb_infra_provider_events
                WHERE provider_code = :provider_code
                  AND event_name IN ("retries","failures")
                  AND created_at >= DATE_SUB(NOW(), INTERVAL :window_sec SECOND)
                  AND payload_json LIKE :job_needle';
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':provider_code', 'provisioning_queue');
        $stmt->bindValue(':window_sec', max(60, $windowSec), \PDO::PARAM_INT);
        $stmt->bindValue(':job_needle', '%"public_id":"' . str_replace(['%', '_'], ['\\%', '\\_'], $jobPublicId) . '"%');
        $stmt->execute();

        return (int) ($stmt->fetchColumn() ?: 0) < $maxInWindow;
    }

    private function recentCount(string $providerCode, string $eventName, int $minutes): int
    {
        $sql = 'SELECT COUNT(*) FROM rateb_infra_provider_events
                WHERE provider_code = :provider_code
                  AND event_name = :event_name
                  AND created_at >= DATE_SUB(NOW(), INTERVAL :minutes MINUTE)';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'provider_code' => $providerCode,
            'event_name' => $eventName,
            'minutes' => max(1, $minutes),
        ]);

        return (int) ($stmt->fetchColumn() ?: 0);
    }

    private function eventsTableReady(): bool
    {
        static $ready = null;
        if ($ready !== null) {
            return $ready;
        }
        try {
            $stmt = $this->pdo->query("SHOW TABLES LIKE 'rateb_infra_provider_events'");
            $ready = $stmt instanceof \PDOStatement && $stmt->fetchColumn() !== false;
        } catch (\Throwable $e) {
            $ready = false;
        }

        return $ready;
    }

    private function envInt(string $key, int $default): int
    {
        $raw = getenv($key);
        if ($raw === false || trim((string) $raw) === '') {
            return $default;
        }
        $n = (int) $raw;

        return $n > 0 ? $n : $default;
    }
}
