<?php
declare(strict_types=1);

namespace Rateb\App\Core;

/**
 * Phase D — Continuous health monitor (orchestrates diagnostics + sync metrics).
 */
final class BranchHealthMonitor
{
    /**
     * Single health snapshot with score 0–100.
     *
     * @return array{ok:bool,score:int,grade:string,metrics:array<string,mixed>,ts:string}
     */
    public function snapshot(): array
    {
        $metrics = [
            'service_running' => $this->serviceRunning(),
            'internet' => false,
            'sync_latency_ms' => null,
            'pending_outbox' => 0,
            'sqlite_integrity' => false,
            'disk_usage_pct' => null,
            'memory_bytes' => memory_get_usage(true),
            'queue_growth' => 0,
            'retry_failures' => 0,
            'conflict_growth' => 0,
        ];

        $diag = (new BranchDiagnostics())->run();
        foreach ($diag['checks'] as $c) {
            if ($c['id'] === 'internet') {
                $metrics['internet'] = $c['ok'];
            }
        }

        if (HybridRuntime::shouldUseSqlite() && is_file(HybridRuntime::sqlitePath())) {
            try {
                $pdo = Database::connection();
                $t0 = microtime(true);
                $status = (new HybridSyncEngine())->status($pdo);
                $metrics['sync_latency_ms'] = (int) round((microtime(true) - $t0) * 1000);
                $metrics['pending_outbox'] = (int) ($status['outbox']['pending'] ?? 0);
                $metrics['retry_failures'] = (int) ($status['outbox']['failed'] ?? 0);
                $metrics['conflict_growth'] = (int) ($status['outbox']['conflict'] ?? 0);
                $metrics['queue_growth'] = $metrics['pending_outbox'] + $metrics['retry_failures'];
                $integrity = (string) $pdo->query('PRAGMA integrity_check')->fetchColumn();
                $metrics['sqlite_integrity'] = strtoupper($integrity) === 'OK';
            } catch (\Throwable $e) {
                $metrics['sqlite_integrity'] = false;
            }
        }

        $root = BranchAppliancePaths::root();
        $free = @disk_free_space($root);
        $total = @disk_total_space($root);
        if (is_float($free) && is_float($total) && $total > 0) {
            $metrics['disk_usage_pct'] = round((1 - ($free / $total)) * 100, 1);
        }

        $score = 100;
        if (!$metrics['sqlite_integrity']) {
            $score -= 40;
        }
        if (!$metrics['internet'] && HybridSyncConfig::sinkMode() === 'mysql') {
            $score -= 10;
        }
        if ($metrics['pending_outbox'] > 100) {
            $score -= 15;
        } elseif ($metrics['pending_outbox'] > 20) {
            $score -= 5;
        }
        if ($metrics['retry_failures'] > 10) {
            $score -= 10;
        }
        if ($metrics['conflict_growth'] > 0) {
            $score -= min(20, 5 * (int) $metrics['conflict_growth']);
        }
        if ($metrics['disk_usage_pct'] !== null && $metrics['disk_usage_pct'] > 90) {
            $score -= 20;
        } elseif ($metrics['disk_usage_pct'] !== null && $metrics['disk_usage_pct'] > 80) {
            $score -= 10;
        }
        $score = max(0, min(100, $score));
        $grade = $score >= 85 ? 'green' : ($score >= 60 ? 'amber' : 'red');

        $result = [
            'ok' => $score >= 60,
            'score' => $score,
            'grade' => $grade,
            'metrics' => $metrics,
            'ts' => gmdate('c'),
        ];
        BranchAppliancePaths::ensureLayout();
        $line = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        @file_put_contents($root . '/health/health.jsonl', $line, FILE_APPEND);
        @file_put_contents($root . '/health/last-health.json', json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return $result;
    }

    /**
     * Continuous loop (for service / tests).
     *
     * @param array{max_cycles?:int,interval_sec?:int} $options
     */
    public function run(array $options = []): int
    {
        $max = (int) ($options['max_cycles'] ?? 0);
        $interval = max(1, (int) ($options['interval_sec'] ?? 30));
        $cycle = 0;
        while (true) {
            $this->snapshot();
            $cycle++;
            if ($max > 0 && $cycle >= $max) {
                break;
            }
            sleep($interval);
        }

        return 0;
    }

    private function serviceRunning(): bool
    {
        $lock = BranchAppliancePaths::root() . '/hybrid-sync.daemon.lock';
        if (!is_file($lock)) {
            return false;
        }
        $fh = @fopen($lock, 'c+');
        if ($fh === false) {
            return false;
        }
        $busy = !@flock($fh, LOCK_EX | LOCK_NB);
        if (!$busy) {
            @flock($fh, LOCK_UN);
        }
        fclose($fh);

        return $busy;
    }
}
