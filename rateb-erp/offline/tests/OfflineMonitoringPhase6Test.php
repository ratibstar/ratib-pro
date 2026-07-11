<?php

declare(strict_types=1);

/**
 * Phase 6 — Enterprise Offline Monitoring & Operations tests.
 *
 * Run: php offline/tests/run-offline-monitoring-tests.php
 */

use Rateb\App\Offline\Services\OfflineFeatureFlagService;
use Rateb\App\Offline\Services\OfflineMonitoringService;

final class OfflineMonitoringPhase6Test
{
    /** @var list<array{name: string, passed: bool, detail: string}> */
    private array $results = [];

    /** @return list<array{name: string, passed: bool, detail: string}> */
    public function run(): array
    {
        $this->clearEnv();

        $this->testFlagDefaultOff();
        $this->testMonitoringIndependentOfMaster();
        $this->testFeatureFlagsConfigHasMonitoring();
        $this->testSnapshotShape();
        $this->testQueueHealthShape();
        $this->testAlertsThresholds();
        $this->testPerformanceSuccessRate();
        $this->testProductionReadinessScoring();
        $this->testMonitoringApiRoutesReadOnly();
        $this->testWebRoutesRegistered();
        $this->testViewsPresent();
        $this->testControllersReadOnly();
        $this->testNoNewMigrations();
        $this->testExistingOfflineApiUntouched();
        $this->testServiceHasNoWriteMethods();
        $this->testDashboardCoversTwelveAreas();
        $this->testApiWiredInRoutes();
        $this->testCompanyWebWired();

        $this->clearEnv();

        return $this->results;
    }

    private function clearEnv(): void
    {
        putenv('RATEB_OFFLINE_ENABLED');
        putenv('RATEB_OFFLINE_MONITORING');
        unset($_ENV['RATEB_OFFLINE_ENABLED'], $_ENV['RATEB_OFFLINE_MONITORING']);
        unset($_SERVER['RATEB_OFFLINE_ENABLED'], $_SERVER['RATEB_OFFLINE_MONITORING']);
    }

    private function testFlagDefaultOff(): void
    {
        $this->clearEnv();
        $flags = new OfflineFeatureFlagService();
        $this->assert(
            'monitoring flag default OFF',
            !$flags->isMonitoringEnabled() && !$flags->enabled('offline.monitoring'),
            'offline.monitoring must default false'
        );
    }

    private function testMonitoringIndependentOfMaster(): void
    {
        $this->clearEnv();
        putenv('RATEB_OFFLINE_MONITORING=1');
        $_ENV['RATEB_OFFLINE_MONITORING'] = '1';
        $flags = new OfflineFeatureFlagService();
        $this->assert(
            'monitoring ON without master',
            $flags->isMonitoringEnabled() && !$flags->isMasterEnabled(),
            'ops visibility must not require offline.enabled'
        );
        $this->clearEnv();
    }

    private function testFeatureFlagsConfigHasMonitoring(): void
    {
        $cfg = require dirname(__DIR__) . '/config/feature-flags.php';
        $this->assert(
            'feature-flags.php has offline.monitoring',
            isset($cfg['defaults']['offline.monitoring'])
                && $cfg['defaults']['offline.monitoring'] === false
                && ($cfg['env']['offline.monitoring'] ?? '') === 'RATEB_OFFLINE_MONITORING',
            'config defaults + env mapping'
        );
    }

    private function testSnapshotShape(): void
    {
        $snap = (new OfflineMonitoringService())->snapshot(0);
        $keys = [
            'monitoring_enabled', 'master_enabled', 'flags', 'company_id', 'migration_required',
            'generated_at', 'queue_health', 'devices', 'sync_metrics', 'conflicts', 'retries',
            'replay_history', 'audit_logs', 'background_worker', 'alerts', 'performance',
            'production_readiness',
        ];
        $missing = [];
        foreach ($keys as $k) {
            if (!array_key_exists($k, $snap)) {
                $missing[] = $k;
            }
        }
        $this->assert('snapshot has all ops sections', $missing === [], $missing === [] ? 'ok' : implode(',', $missing));
    }

    private function testQueueHealthShape(): void
    {
        $qh = (new OfflineMonitoringService())->queueHealth(0);
        $this->assert(
            'queue health keys',
            isset($qh['pending'], $qh['failed'], $qh['depth'], $qh['healthy']),
            'pending/failed/depth/healthy'
        );
    }

    private function testAlertsThresholds(): void
    {
        $svc = new OfflineMonitoringService();
        $alerts = $svc->alerts(
            1,
            ['failed' => 25, 'depth' => 10, 'pending' => 0, 'migration_required' => false],
            ['stale_active' => 0],
            ['open' => 0],
            ['high_retry_count' => 0]
        );
        $codes = array_column($alerts['items'] ?? [], 'code');
        $this->assert(
            'alert QUEUE_FAILED_HIGH at 25',
            in_array('QUEUE_FAILED_HIGH', $codes, true),
            implode(',', $codes)
        );
    }

    private function testPerformanceSuccessRate(): void
    {
        $perf = (new OfflineMonitoringService())->performanceMetrics(1, [
            'synced_24h' => 90,
            'failed_24h' => 10,
            'avg_retry' => 0.5,
        ]);
        $this->assert(
            'success rate 90%',
            ($perf['success_rate_24h_pct'] ?? 0) === 90.0,
            (string) ($perf['success_rate_24h_pct'] ?? 'n/a')
        );
    }

    private function testProductionReadinessScoring(): void
    {
        $svc = new OfflineMonitoringService();
        $ready = $svc->productionReadiness(
            1,
            ['migration_required' => false, 'healthy' => true],
            ['migration_required' => false],
            ['open' => 0]
        );
        $this->assert(
            'readiness has score + verdict',
            isset($ready['score'], $ready['verdict'], $ready['checks']) && is_array($ready['checks']),
            json_encode($ready)
        );
    }

    private function testMonitoringApiRoutesReadOnly(): void
    {
        $file = dirname(__DIR__) . '/server/routes/offline-monitoring-api.php';
        $src = is_file($file) ? (string) file_get_contents($file) : '';
        $hasGet = str_contains($src, "get('/api/v1/offline/monitoring'");
        $hasPost = (bool) preg_match('/\$router->post\s*\(/', $src);
        $this->assert(
            'monitoring API GET-only',
            $hasGet && !$hasPost,
            $hasPost ? 'POST found' : 'GET routes present'
        );
    }

    private function testWebRoutesRegistered(): void
    {
        $file = dirname(__DIR__) . '/server/routes/offline-web.php';
        $src = is_file($file) ? (string) file_get_contents($file) : '';
        $this->assert(
            'web ops routes + pos.sync.manage',
            str_contains($src, "offline/ops")
                && str_contains($src, "offline/monitoring")
                && str_contains($src, 'pos.sync.manage'),
            'ops + monitoring + mw'
        );
    }

    private function testViewsPresent(): void
    {
        $root = dirname(__DIR__, 2);
        $ok = is_file($root . '/views/offline/ops/index.php')
            && is_file($root . '/views/offline/ops/disabled.php')
            && is_file($root . '/views/offline/ops/forbidden.php');
        $this->assert('ops views present', $ok, 'index/disabled/forbidden');
    }

    private function testControllersReadOnly(): void
    {
        $api = (string) file_get_contents(dirname(__DIR__) . '/server/Controllers/OfflineMonitoringApiController.php');
        $web = (string) file_get_contents(dirname(__DIR__) . '/server/Controllers/OfflineOpsDashboardController.php');
        $writes = (bool) preg_match('/\b(INSERT|UPDATE|DELETE|enqueue|processQueue|resolveConflict)\b/i', $api . $web);
        $this->assert('controllers have no write/process calls', !$writes, $writes ? 'write pattern found' : 'read-only');
    }

    private function testNoNewMigrations(): void
    {
        $mig = glob(dirname(__DIR__) . '/migrations/*.sql') ?: [];
        $phase6 = array_filter($mig, static fn ($f) => str_contains(strtolower(basename((string) $f)), 'monitor'));
        $this->assert('no monitoring migrations', $phase6 === [], 'additive tables not required');
    }

    private function testExistingOfflineApiUntouched(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__) . '/server/routes/offline-api.php');
        $required = [
            "/api/v1/offline/status",
            "/api/v1/offline/push",
            "/api/v1/offline/process",
            "/api/v1/offline/conflicts",
            "/api/v1/offline/delta/{entity}",
        ];
        $missing = [];
        foreach ($required as $r) {
            if (!str_contains($src, $r)) {
                $missing[] = $r;
            }
        }
        $this->assert('legacy offline API routes intact', $missing === [], $missing === [] ? 'ok' : implode(',', $missing));
    }

    private function testServiceHasNoWriteMethods(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__) . '/server/Services/OfflineMonitoringService.php');
        $bad = (bool) preg_match('/\b(INSERT INTO|UPDATE |DELETE FROM|->enqueue\(|->process\()/i', $src);
        $this->assert('OfflineMonitoringService read-only SQL', !$bad, $bad ? 'mutating SQL found' : 'SELECT aggregates only');
    }

    private function testDashboardCoversTwelveAreas(): void
    {
        $view = (string) file_get_contents(dirname(__DIR__, 2) . '/views/offline/ops/index.php');
        $areas = [
            'Alerting',
            'Queue',
            'Device',
            'Synchronization',
            'Conflict',
            'Retry',
            'Replay',
            'Audit',
            'Background',
            'Performance',
            'Production',
            'Feature flags',
        ];
        $missing = [];
        foreach ($areas as $a) {
            if (!str_contains($view, $a)) {
                $missing[] = $a;
            }
        }
        $this->assert('dashboard covers 12 ops areas', $missing === [], $missing === [] ? 'ok' : implode(',', $missing));
    }

    private function testApiWiredInRoutes(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 2) . '/routes/api.php');
        $this->assert(
            'api.php requires offline-monitoring-api',
            str_contains($src, 'offline-monitoring-api.php'),
            'wire monitoring API'
        );
    }

    private function testCompanyWebWired(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 2) . '/routes/company.php');
        $this->assert(
            'company.php requires offline-web',
            str_contains($src, 'offline-web.php'),
            'wire ops dashboard'
        );
    }

    private function assert(string $name, bool $passed, string $detail): void
    {
        $this->results[] = ['name' => $name, 'passed' => $passed, 'detail' => $detail];
        echo ($passed ? '[PASS] ' : '[FAIL] ') . $name . ($detail !== '' ? " — {$detail}" : '') . PHP_EOL;
    }
}
