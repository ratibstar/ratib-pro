<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use Throwable;

final class SystemHealth
{
    public function __construct(private readonly PDO $db)
    {
    }

    /** @return array<string, mixed> */
    public function snapshot(): array
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'workflows' => $this->checkWorkflows(),
            'webhooks' => $this->checkWebhooks(),
            'metrics' => $this->checkMetrics(),
        ];
        $status = 'ok';
        foreach ($checks as $c) {
            if (($c['status'] ?? 'ok') === 'down') {
                $status = 'down';
                break;
            }
            if (($c['status'] ?? 'ok') === 'degraded') {
                $status = 'degraded';
            }
        }

        return [
            'status' => $status,
            'timestamp' => gmdate('c'),
            'checks' => $checks,
        ];
    }

    /** @return array<string, mixed> */
    private function checkDatabase(): array
    {
        try {
            $st = $this->db->query('SELECT 1');
            $ok = $st !== false && ((int) $st->fetchColumn()) === 1;
            return ['status' => $ok ? 'ok' : 'down', 'message' => $ok ? 'connected' : 'query failed'];
        } catch (Throwable $e) {
            return ['status' => 'down', 'message' => $e->getMessage()];
        }
    }

    /** @return array<string, mixed> */
    private function checkWorkflows(): array
    {
        if (!$this->tableExists('workflows')) {
            return ['status' => 'degraded', 'message' => 'workflows table not found', 'active' => 0];
        }
        try {
            $active = (int) $this->count("SELECT COUNT(*) FROM workflows WHERE status = 'running'");
            $threshold = (int) (getenv('HEALTH_ACTIVE_WORKFLOWS_WARN') ?: 200);
            $status = $active > $threshold ? 'degraded' : 'ok';
            return ['status' => $status, 'active' => $active, 'threshold' => $threshold];
        } catch (Throwable $e) {
            return ['status' => 'degraded', 'message' => $e->getMessage(), 'active' => 0];
        }
    }

    /** @return array<string, mixed> */
    private function checkWebhooks(): array
    {
        if (!$this->tableExists('webhook_deliveries')) {
            return ['status' => 'degraded', 'message' => 'webhook_deliveries table not found', 'backlog' => 0];
        }
        try {
            $backlog = (int) $this->count("SELECT COUNT(*) FROM webhook_deliveries WHERE status IN ('pending','retry')");
            $threshold = (int) (getenv('HEALTH_WEBHOOK_BACKLOG_WARN') ?: 500);
            $status = $backlog > $threshold ? 'degraded' : 'ok';
            return ['status' => $status, 'backlog' => $backlog, 'threshold' => $threshold];
        } catch (Throwable $e) {
            return ['status' => 'degraded', 'message' => $e->getMessage(), 'backlog' => 0];
        }
    }

    /** @return array<string, mixed> */
    private function checkMetrics(): array
    {
        if (!$this->tableExists('workflows')) {
            return ['status' => 'degraded', 'message' => 'workflows table not found', 'failure_rate_pct' => 0.0];
        }
        try {
            $total = (int) $this->count('SELECT COUNT(*) FROM workflows');
            $failed = (int) $this->count("SELECT COUNT(*) FROM workflows WHERE status = 'failed'");
            $rate = $total > 0 ? round(($failed / $total) * 100, 2) : 0.0;
            $threshold = (float) (getenv('HEALTH_FAILURE_RATE_WARN_PCT') ?: 20);
            $status = $rate > $threshold ? 'degraded' : 'ok';
            return ['status' => $status, 'failure_rate_pct' => $rate, 'threshold_pct' => $threshold];
        } catch (Throwable $e) {
            return ['status' => 'degraded', 'message' => $e->getMessage(), 'failure_rate_pct' => 0.0];
        }
    }

    private function tableExists(string $table): bool
    {
        $st = $this->db->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table');
        $st->execute([':table' => $table]);
        return ((int) $st->fetchColumn()) > 0;
    }

    private function count(string $sql): int
    {
        $st = $this->db->query($sql);
        if (!$st) {
            return 0;
        }
        return (int) ($st->fetchColumn() ?: 0);
    }
}
