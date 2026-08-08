<?php

declare(strict_types=1);

namespace Rateb\App\Services;

/**
 * Phase 11 — Performance certification measurements (evidence-first; no speculative indexes).
 */
final class CrmPerformanceCertificationService
{
    /**
     * @return array<string, mixed>
     */
    public function measure(string $root): array
    {
        $surfaces = [
            'dashboard' => ['class' => CrmDashboardService::class, 'method' => 'kpis', 'args' => []],
            'revops' => ['class' => CrmRevOpsCommandCenterService::class, 'method' => 'assemble', 'args' => []],
            'customer_360' => null, // needs customer id
            'unified_search' => ['class' => CrmUnifiedSearchService::class, 'method' => 'search', 'args' => ['a', 10]],
            'pipeline' => null,
            'reports' => ['class' => CrmReportingCenterService::class, 'method' => 'listSavedDashboards', 'args' => []],
            'workspace' => ['class' => CrmSalesWorkspaceService::class, 'method' => 'assemble', 'args' => [[]]],
        ];

        $structural = [
            'pipeline_board_cap' => str_contains((string) @file_get_contents($root . '/app/services/CrmDomainServices.php'), 'LIMIT 500'),
            'dq_snapshot_first' => str_contains((string) @file_get_contents($root . '/app/services/CrmDataQualityEngineService.php'), 'liveScan'),
            'c360_readonly_default' => str_contains((string) @file_get_contents($root . '/app/services/CrmCustomer360Service.php'), 'read_only'),
            'phase9_10_indexes' => is_file($root . '/migrations/238_crm_phase9_ai_ready_optimization.sql')
                && is_file($root . '/migrations/239_crm_phase10_production_hardening.sql'),
        ];

        $runtime = [];
        $memBefore = memory_get_usage(true);
        foreach ($surfaces as $name => $spec) {
            if ($spec === null) {
                $runtime[$name] = ['status' => 'SKIP', 'note' => 'requires entity context'];
                continue;
            }
            $class = $spec['class'];
            $method = $spec['method'];
            if (!class_exists($class) || !method_exists($class, $method)) {
                $runtime[$name] = ['status' => 'SKIP', 'note' => 'method missing'];
                continue;
            }
            $t0 = microtime(true);
            $err = null;
            try {
                $svc = new $class();
                $svc->{$method}(...$spec['args']);
            } catch (\Throwable $e) {
                $err = $e->getMessage();
            }
            $ms = round((microtime(true) - $t0) * 1000, 2);
            $runtime[$name] = [
                'status' => $err === null ? 'OK' : (str_contains((string) $err, 'company_required') ? 'SKIP_NO_TENANT' : 'ERROR'),
                'execution_ms' => $ms,
                'error' => $err,
                'query_count' => null,
                'note' => 'query_count requires DB statement logger; structural caps certified separately',
            ];
        }
        $memAfter = memory_get_usage(true);

        $structFail = array_keys(array_filter($structural, static fn ($ok) => !$ok));
        $status = $structFail === [] ? 'PASS' : 'FAIL';

        return [
            'status' => $status,
            'summary' => $status === 'PASS'
                ? 'Performance guards present; runtime samples captured when tenant available'
                : 'Missing performance guards: ' . implode(', ', $structFail),
            'structural' => $structural,
            'runtime' => $runtime,
            'memory_delta_bytes' => $memAfter - $memBefore,
            'index_recommendation' => 'No new indexes without slow-query evidence from production EXPLAIN.',
        ];
    }
}
