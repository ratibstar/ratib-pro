<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Application\Services\DisasterRecovery;

use Ratib\ContactCenter\App\Core\Database;
use Ratib\ContactCenter\App\Core\Events\EventBus;
use Ratib\ContactCenter\App\Core\Events\EventType;

final class MonitoringService
{
    /** @return list<array<string, mixed>> */
    public function listMonitors(?int $tenantId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM rcc_monitors WHERE (tenant_id IS NULL OR tenant_id = :tid) AND is_enabled = 1 ORDER BY monitor_type'
        );
        $stmt->execute(['tid' => $tenantId ?? 0]);
        return $stmt->fetchAll() ?: [];
    }

    public function runCheck(int $monitorId): array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM rcc_monitors WHERE id = :id');
        $stmt->execute(['id' => $monitorId]);
        $monitor = $stmt->fetch();
        if (!$monitor) {
            throw new \InvalidArgumentException('Monitor not found');
        }
        $result = match ((string) $monitor['monitor_type']) {
            'database' => $this->checkDatabase(),
            'pbx', 'sip' => $this->checkTcp((string) $monitor['target']),
            'uptime' => $this->checkUptime((string) $monitor['target']),
            'queue' => $this->checkQueue(),
            default => ['status' => 'degraded', 'response_ms' => 0, 'message' => 'Unknown monitor type'],
        };
        Database::connection()->prepare(
            'INSERT INTO rcc_monitor_checks (monitor_id, status, response_ms, message) VALUES (:mid, :st, :ms, :msg)'
        )->execute([
            'mid' => $monitorId,
            'st' => $result['status'],
            'ms' => $result['response_ms'],
            'msg' => $result['message'] ?? null,
        ]);
        if ($result['status'] === 'down') {
            EventBus::instance()->emit([
                'type' => EventType::MONITOR_ALERT,
                'tenant_id' => (int) ($monitor['tenant_id'] ?? 0),
                'payload' => ['monitor_id' => $monitorId, 'message' => $result['message'] ?? ''],
            ]);
        }
        return $result;
    }

    public function runAll(?int $tenantId): array
    {
        $results = [];
        foreach ($this->listMonitors($tenantId) as $m) {
            $results[] = ['monitor_id' => $m['id'], 'name' => $m['name']] + $this->runCheck((int) $m['id']);
        }
        return $results;
    }

    /** @return array{status:string,response_ms:int,message:string} */
    private function checkDatabase(): array
    {
        $start = microtime(true);
        try {
            Database::connection()->query('SELECT 1');
            $ms = (int) round((microtime(true) - $start) * 1000);
            return ['status' => 'up', 'response_ms' => $ms, 'message' => 'MySQL OK'];
        } catch (\Throwable $e) {
            return ['status' => 'down', 'response_ms' => 0, 'message' => $e->getMessage()];
        }
    }

    /** @return array{status:string,response_ms:int,message:string} */
    private function checkTcp(string $target): array
    {
        $host = getenv('RCC_AMI_HOST') ?: '127.0.0.1';
        $port = (int) (getenv('RCC_AMI_PORT') ?: 5038);
        if (str_contains($target, ':')) {
            [$proto, $portStr] = explode(':', $target, 2);
            if ($proto === 'ami') {
                $port = (int) $portStr;
            } elseif ($proto === 'sip') {
                $port = (int) $portStr;
            }
        }
        $start = microtime(true);
        $fp = @fsockopen($host, $port, $errno, $errstr, 2);
        $ms = (int) round((microtime(true) - $start) * 1000);
        if (is_resource($fp)) {
            fclose($fp);
            return ['status' => 'up', 'response_ms' => $ms, 'message' => "{$host}:{$port} reachable"];
        }
        return ['status' => 'down', 'response_ms' => $ms, 'message' => $errstr ?: 'Connection failed'];
    }

    /** @return array{status:string,response_ms:int,message:string} */
    private function checkUptime(string $target): array
    {
        $base = getenv('RCC_PUBLIC_BASE_URL') ?: '';
        $url = str_starts_with($target, 'http') ? $target : rtrim($base, '/') . '/api/v1/health.php';
        $start = microtime(true);
        $ctx = stream_context_create(['http' => ['timeout' => 5]]);
        $body = @file_get_contents($url, false, $ctx);
        $ms = (int) round((microtime(true) - $start) * 1000);
        if ($body !== false) {
            return ['status' => 'up', 'response_ms' => $ms, 'message' => 'Health endpoint OK'];
        }
        return ['status' => 'down', 'response_ms' => $ms, 'message' => 'Health check failed'];
    }

    /** @return array{status:string,response_ms:int,message:string} */
    private function checkQueue(): array
    {
        try {
            $stmt = Database::connection()->query("SELECT COUNT(*) FROM rcc_queues WHERE status = 'active'");
            $n = (int) $stmt->fetchColumn();
            return ['status' => $n > 0 ? 'up' : 'degraded', 'response_ms' => 0, 'message' => $n . ' active queues'];
        } catch (\Throwable $e) {
            return ['status' => 'down', 'response_ms' => 0, 'message' => $e->getMessage()];
        }
    }
}
