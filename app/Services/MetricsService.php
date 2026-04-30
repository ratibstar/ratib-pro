<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\WorkflowMetrics;
use PDO;

final class MetricsService
{
    public function __construct(
        private readonly PDO $db,
        private readonly WorkflowMetrics $runtimeMetrics
    ) {
    }

    /** @return array<string, mixed> */
    public function getSystemHealth(): array
    {
        $runtime = $this->runtimeMetrics->snapshot();
        $workflowsTable = $this->tableExists('workflows');
        $workflowStatesTable = $this->tableExists('workflow_states');
        $eventsLogTable = $this->tableExists('events_log');

        $dbCounts = [
            'workflows_total' => $workflowsTable ? $this->count('SELECT COUNT(*) FROM workflows') : 0,
            'workflows_running' => $workflowsTable ? $this->count("SELECT COUNT(*) FROM workflows WHERE status = 'running'") : 0,
            'workflow_states_total' => $workflowStatesTable ? $this->count('SELECT COUNT(*) FROM workflow_states') : 0,
            'events_total' => $eventsLogTable ? $this->count('SELECT COUNT(*) FROM events_log') : 0,
        ];

        return [
            'status' => 'ok',
            'timestamp' => gmdate('c'),
            'runtime' => $runtime['global'] ?? [],
            'database' => $dbCounts,
        ];
    }

    /** @return array<string, mixed> */
    public function getWorkflowStats(): array
    {
        $runtime = $this->runtimeMetrics->snapshot();

        $byType = $this->queryAll(
            "SELECT name AS workflow_type,
                    COUNT(*) AS total,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) AS failed,
                    SUM(CASE WHEN status = 'running' THEN 1 ELSE 0 END) AS running
             FROM workflows
             GROUP BY name
             ORDER BY total DESC"
        );
        $byCountryRaw = $this->queryAll(
            "SELECT
                 CAST(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(context_json, '$.country_id')), JSON_UNQUOTE(JSON_EXTRACT(context_json, '$.control_country_id')), '0') AS UNSIGNED) AS country_id,
                 COUNT(*) AS total,
                 SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed,
                 SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) AS failed,
                 SUM(CASE WHEN status = 'running' THEN 1 ELSE 0 END) AS running
             FROM workflows
             GROUP BY country_id
             ORDER BY total DESC"
        );
        $byCountry = array_values(array_filter($byCountryRaw, static fn (array $r): bool => (int) ($r['country_id'] ?? 0) > 0));

        return [
            'timestamp' => gmdate('c'),
            'runtime_dimensions' => [
                'per_workflow_type' => $runtime['per_workflow_type'] ?? [],
                'per_country' => $runtime['per_country'] ?? [],
            ],
            'database_dimensions' => [
                'per_workflow_type' => $byType,
                'per_country' => $byCountry,
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function getFailureRates(): array
    {
        $overall = $this->queryOne(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) AS failed
             FROM workflows"
        );
        $total = (int) ($overall['total'] ?? 0);
        $failed = (int) ($overall['failed'] ?? 0);
        $overallRate = $total > 0 ? round(($failed / $total) * 100, 2) : 0.0;

        $byType = $this->queryAll(
            "SELECT
                name AS workflow_type,
                COUNT(*) AS total,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) AS failed,
                ROUND((SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) / NULLIF(COUNT(*), 0)) * 100, 2) AS failure_rate_pct
             FROM workflows
             GROUP BY name
             ORDER BY failure_rate_pct DESC, total DESC"
        );

        return [
            'timestamp' => gmdate('c'),
            'overall' => [
                'total' => $total,
                'failed' => $failed,
                'failure_rate_pct' => $overallRate,
            ],
            'by_workflow_type' => $byType,
        ];
    }

    /** @return array<string, mixed> */
    private function queryOne(string $sql): array
    {
        if (!$this->tableExists('workflows')) {
            return [];
        }
        $st = $this->db->query($sql);
        if (!$st) {
            return [];
        }
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : [];
    }

    /** @return list<array<string, mixed>> */
    private function queryAll(string $sql): array
    {
        if (!$this->tableExists('workflows')) {
            return [];
        }
        $st = $this->db->query($sql);
        if (!$st) {
            return [];
        }
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        return is_array($rows) ? $rows : [];
    }

    private function count(string $sql): int
    {
        $st = $this->db->query($sql);
        if (!$st) {
            return 0;
        }
        return (int) ($st->fetchColumn() ?: 0);
    }

    private function tableExists(string $table): bool
    {
        $st = $this->db->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table');
        $st->execute([':table' => $table]);
        return ((int) $st->fetchColumn()) > 0;
    }
}
