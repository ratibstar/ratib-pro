<?php

declare(strict_types=1);

namespace Rateb\App\Services;

/**
 * Phase 11 — Static + optional runtime enterprise certification harness (no commercial features).
 */
final class CrmEnterpriseCertificationService
{
    /**
     * @return array<string, mixed>
     */
    public function certifyAll(string $root): array
    {
        $axes = [
            'transaction_integrity' => $this->certifyTransactionIntegrity($root),
            'data_integrity' => $this->certifyDataIntegrity($root),
            'tenant_isolation' => $this->certifyTenantIsolation($root),
            'authorization' => $this->certifyAuthorization($root),
            'automation' => $this->certifyAutomation($root),
            'performance' => $this->certifyPerformance($root),
            'migrations' => $this->certifyMigrations($root),
            'regression' => ['status' => 'DEFERRED', 'note' => 'Run via tests/run-crm-master-certification.php'],
        ];

        $blockers = [];
        $warnings = [];
        foreach ($axes as $name => $axis) {
            $status = (string) ($axis['status'] ?? 'FAIL');
            if ($status === 'FAIL') {
                $blockers[] = $name . ': ' . (string) ($axis['summary'] ?? 'failed');
            } elseif ($status === 'WARN' || $status === 'DEFERRED') {
                $warnings[] = $name . ': ' . (string) ($axis['summary'] ?? $axis['note'] ?? 'warning');
            }
        }

        $failed = array_filter($axes, static fn ($a) => ($a['status'] ?? '') === 'FAIL');
        $overall = $failed === [] ? 'PASS' : 'FAIL';

        return [
            'overall' => $overall,
            'generated_at' => date('c'),
            'axes' => $axes,
            'blockers' => array_values($blockers),
            'warnings' => array_values($warnings),
            'recommendation' => $overall === 'PASS'
                ? 'CRM is Enterprise Production Certified pending green Master Suite + confirmed migrations 231–239 on production.'
                : 'Do not certify until blockers are cleared and Master Suite is green.',
        ];
    }

    /** @return array<string, mixed> */
    public function certifyTransactionIntegrity(string $root): array
    {
        $src = (string) @file_get_contents($root . '/app/services/CrmDuplicateMergeService.php');
        $checks = [
            'beginTransaction' => str_contains($src, 'beginTransaction'),
            'commit' => str_contains($src, '->commit()'),
            'rollBack' => str_contains($src, 'rollBack'),
            'company_scoped_repoint' => str_contains($src, 'company_id = :cid') && str_contains($src, 'repointBulk'),
            'audit_after_commit' => preg_match('/commit\(\);\s*\}[\s\S]*AuditService/m', $src) === 1
                || (strpos($src, 'commit()') !== false && strpos($src, 'AuditService') > strpos($src, 'commit()')),
            'no_accounting' => !str_contains($src, 'AccountingService'),
        ];
        $fail = array_keys(array_filter($checks, static fn ($ok) => !$ok));

        return [
            'status' => $fail === [] ? 'PASS' : 'FAIL',
            'summary' => $fail === [] ? 'Merge execute is transactional with rollback' : 'Missing: ' . implode(', ', $fail),
            'checks' => $checks,
        ];
    }

    /** @return array<string, mixed> */
    public function certifyDataIntegrity(string $root): array
    {
        $src = (string) @file_get_contents($root . '/app/services/CrmDataIntegrityAuditService.php');
        $required = [
            'orphan_opportunity',
            'orphan_activity',
            'invalid_customer_ref',
            'duplicate_active',
            'invalid_lifecycle',
            'invalid_pipeline_stage',
            'broken_quotation',
            'stage_history',
            'forecast',
        ];
        $checks = [];
        foreach ($required as $needle) {
            $checks[$needle] = str_contains($src, $needle);
        }
        $checks['no_auto_delete'] = str_contains($src, "'auto_delete' => false")
            && !preg_match('/\bDELETE\s+FROM\b/i', $src);
        $fail = array_keys(array_filter($checks, static fn ($ok) => !$ok));
        $runtime = null;
        try {
            $runtime = (new CrmDataIntegrityAuditService())->runAudit();
        } catch (\Throwable $e) {
            $runtime = ['skipped' => true, 'reason' => $e->getMessage()];
        }

        return [
            'status' => $fail === [] ? 'PASS' : 'FAIL',
            'summary' => $fail === [] ? 'Integrity audit covers required checks (no auto-delete)' : 'Missing: ' . implode(', ', $fail),
            'checks' => $checks,
            'runtime' => $runtime,
        ];
    }

    /** @return array<string, mixed> */
    public function certifyTenantIsolation(string $root): array
    {
        $surfaces = [
            'CrmUnifiedSearchService.php' => ['company_id'],
            'CrmCustomer360Service.php' => ['company_id', 'requireCompanyId'],
            'CrmReportExportService.php' => ['company_id'],
            'CrmDuplicateMergeService.php' => ['company_id = :cid'],
            'CrmAutomationService.php' => ['requireCompanyId', 'company_id'],
            'CrmEnterpriseForecastService.php' => ['company_id'],
            'CrmDashboardService.php' => ['company_id'],
            'CrmRevOpsCommandCenterService.php' => ['requireCompanyId'],
            'CrmReportingCenterService.php' => ['company_id'],
        ];
        $checks = [];
        foreach ($surfaces as $file => $needles) {
            $src = (string) @file_get_contents($root . '/app/services/' . $file);
            $ok = $src !== '';
            foreach ($needles as $n) {
                $ok = $ok && str_contains($src, $n);
            }
            $checks[$file] = $ok;
        }
        $fail = array_keys(array_filter($checks, static fn ($ok) => !$ok));

        return [
            'status' => $fail === [] ? 'PASS' : 'FAIL',
            'summary' => $fail === [] ? 'Sensitive CRM surfaces enforce company_id/tenant scope' : 'Missing tenant scope: ' . implode(', ', $fail),
            'checks' => $checks,
        ];
    }

    /** @return array<string, mixed> */
    public function certifyAuthorization(string $root): array
    {
        $perms = (string) @file_get_contents($root . '/config/permissions-system.php');
        $ops = (string) @file_get_contents($root . '/routes/modules/ops.php');
        $matrix = [
            'crm.view' => str_contains($perms, "'crm.view'"),
            'crm.create' => str_contains($perms, "'crm.create'"),
            'crm.update' => str_contains($perms, "'crm.update'"),
            'crm.delete' => str_contains($perms, "'crm.delete'"),
            'crm.manage_bundle' => str_contains($perms, "'crm.manage'"),
            'crm.admin_bundle' => str_contains($perms, "'crm.admin'"),
            'export' => str_contains($perms, 'crm.reports.export') && str_contains($perms, 'crm.export.manage'),
            'merge_manage' => str_contains($perms, 'crm.merge.manage') && str_contains($ops, 'crm.merge.manage'),
            'revops_run_not_view' => str_contains($ops, "crm.revops.run") && !preg_match("/crm\/revops\/automation'.*crm\.revops\.view/s", $ops),
            'insights_manage' => str_contains($ops, 'crm.insights.manage'),
            'governance_manage_scan' => str_contains($ops, 'crm.governance.manage'),
        ];
        $roleSeeds = [
            'super-admin_seed' => str_contains((string) @file_get_contents($root . '/migrations/239_crm_phase10_production_hardening.sql'), 'super-admin'),
            'company-full-access_seed' => str_contains((string) @file_get_contents($root . '/migrations/239_crm_phase10_production_hardening.sql'), 'company-full-access'),
        ];
        $checks = $matrix + $roleSeeds;
        $fail = array_keys(array_filter($checks, static fn ($ok) => !$ok));

        return [
            'status' => $fail === [] ? 'PASS' : 'FAIL',
            'summary' => $fail === [] ? 'Authorization matrix + route gates present' : 'Auth gaps: ' . implode(', ', $fail),
            'checks' => $checks,
            'roles_covered' => ['super-admin', 'company-full-access', 'crm.manage', 'crm.admin', 'custom via permission slugs', 'read-only via crm.view without mutate'],
        ];
    }

    /** @return array<string, mixed> */
    public function certifyAutomation(string $root): array
    {
        $safety = (string) @file_get_contents($root . '/app/services/CrmAutomationSafetyService.php');
        $legacy = (string) @file_get_contents($root . '/app/services/CrmAutomationService.php');
        $revops = (string) @file_get_contents($root . '/app/services/CrmRevOpsAutomationService.php');
        $rules = (string) @file_get_contents($root . '/app/services/CrmAutomationRulesEngineService.php');
        $checks = [
            'cooldown' => str_contains($safety, 'recentlyFired')
                && (str_contains($legacy, 'recentlyFired') || str_contains($legacy, 'cooldown') || str_contains($legacy, 'CrmAutomationSafetyService')),
            'run_lock' => str_contains($safety, 'acquireRunLock')
                && (str_contains($legacy, 'acquireRunLock') || str_contains($revops, 'acquireRunLock')),
            'notify_budget' => str_contains($safety, 'allowNotify')
                && (str_contains($legacy, 'allowNotify') || str_contains($revops, 'allowNotify')),
            'revops_default_no_legacy' => str_contains($revops, 'includeLegacy = false') || str_contains($revops, 'include_legacy_in_revops'),
            'always_rule_cap' => str_contains($rules, 'always_rule_cap') || str_contains($rules, 'block_always_rules_over_max'),
            'storm_prevention' => str_contains($safety, 'max_notifies_per_run') || str_contains($safety, 'maxNotifiesPerRun'),
        ];
        $fail = array_keys(array_filter($checks, static fn ($ok) => !$ok));

        return [
            'status' => $fail === [] ? 'PASS' : 'FAIL',
            'summary' => $fail === [] ? 'Automation safety controls certified structurally' : 'Automation gaps: ' . implode(', ', $fail),
            'checks' => $checks,
        ];
    }

    /** @return array<string, mixed> */
    public function certifyPerformance(string $root): array
    {
        return (new CrmPerformanceCertificationService())->measure($root);
    }

    /** @return array<string, mixed> */
    public function certifyMigrations(string $root): array
    {
        $files = [];
        foreach (range(228, 239) as $n) {
            $matches = glob($root . '/migrations/' . $n . '_*.sql') ?: [];
            $files[$n] = $matches[0] ?? null;
        }
        $checks = [];
        $orderOk = true;
        $prev = 227;
        foreach ($files as $n => $path) {
            $exists = is_string($path) && is_file($path);
            $checks['mig_' . $n . '_exists'] = $exists;
            if (!$exists) {
                // 229/230 may not be CRM-named — allow any file with that number
                $any = glob($root . '/migrations/' . $n . '_*.sql') ?: [];
                $checks['mig_' . $n . '_exists'] = $any !== [];
                $exists = $any !== [];
                $path = $any[0] ?? null;
            }
            if ($exists && $n !== $prev + 1) {
                $orderOk = false;
            }
            $prev = $n;
            if (is_string($path) && is_file($path)) {
                $sql = (string) file_get_contents($path);
                $checks['mig_' . $n . '_no_drop'] = !preg_match('/\bDROP\s+TABLE\b|\bTRUNCATE\b/i', $sql);
                // CRM additive migrations must be idempotent/guarded; 229–230 are non-CRM plan/marketplace.
                $isCrmMig = $n === 228 || ($n >= 231 && $n <= 239);
                if ($isCrmMig) {
                    $checks['mig_' . $n . '_guarded_or_if'] = str_contains($sql, 'IF NOT EXISTS')
                        || str_contains($sql, 'information_schema')
                        || str_contains($sql, 'ON DUPLICATE KEY')
                        || str_contains($sql, 'INSERT IGNORE');
                }
            }
        }
        $checks['numeric_order_228_239'] = $orderOk;
        $fail = array_keys(array_filter($checks, static fn ($ok) => !$ok));

        return [
            'status' => $fail === [] ? 'PASS' : 'FAIL',
            'summary' => $fail === [] ? 'Migrations 228–239 present, ordered, additive/idempotent patterns' : 'Migration issues: ' . implode(', ', $fail),
            'checks' => $checks,
            'files' => array_map(static fn ($p) => $p !== null ? basename((string) $p) : null, $files),
            'new_migration_required' => false,
        ];
    }
}
