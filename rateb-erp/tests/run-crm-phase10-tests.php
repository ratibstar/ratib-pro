<?php
declare(strict_types=1);

/**
 * CRM Phase 10 — Production hardening validation suite.
 * Runs structural checks + Phase 2–9 regressions + route audit.
 *
 * Run: php rateb-erp/tests/run-crm-phase10-tests.php
 */

$root = dirname(__DIR__);
require_once $root . '/app/Core/Bootstrap.php';
\Rateb\App\Core\Bootstrap::init($root);

use Rateb\App\Services\CrmAutomationSafetyService;
use Rateb\App\Services\CrmAutomationService;
use Rateb\App\Services\CrmDataQualityEngineService;
use Rateb\App\Services\CrmGovernanceService;
use Rateb\App\Services\CrmObservability;
use Rateb\App\Services\CrmQuotationService;
use Rateb\App\Services\CrmRevOpsAutomationService;

$passed = 0;
$failed = 0;

function c10_assert(bool $cond, string $label): void
{
    global $passed, $failed;
    if ($cond) {
        ++$passed;
        echo "PASS: {$label}\n";
    } else {
        ++$failed;
        echo "FAIL: {$label}\n";
    }
}

echo "=== Phase 10 hardening assertions ===\n";

c10_assert(class_exists(CrmAutomationSafetyService::class), 'CrmAutomationSafetyService');
c10_assert(class_exists(CrmObservability::class), 'CrmObservability');
c10_assert(method_exists(CrmAutomationSafetyService::class, 'recentlyFired'), 'cooldown API');
c10_assert(method_exists(CrmAutomationSafetyService::class, 'acquireRunLock'), 'run lock API');
c10_assert(method_exists(CrmAutomationSafetyService::class, 'allowNotify'), 'notify budget API');
c10_assert(method_exists(CrmDataQualityEngineService::class, 'dashboard'), 'DQ dashboard');
c10_assert(method_exists(CrmGovernanceService::class, 'healthDashboard'), 'gov health');
c10_assert(method_exists(CrmRevOpsAutomationService::class, 'runAll'), 'revops runAll');

$dqSrc = (string) file_get_contents($root . '/app/services/CrmDataQualityEngineService.php');
c10_assert(str_contains($dqSrc, 'liveScan') && str_contains($dqSrc, 'snapshot'), 'DQ snapshot-first dashboard');

$govSrc = (string) file_get_contents($root . '/app/services/CrmGovernanceService.php');
c10_assert(str_contains($govSrc, 'issue_counts') || str_contains($govSrc, 'liveScan'), 'gov health no forced scan');
c10_assert(str_contains($govSrc, 'require_permission') && str_contains($govSrc, 'rateb_can'), 'export policy checks permission');

$autoSrc = (string) file_get_contents($root . '/app/services/CrmAutomationService.php');
c10_assert(str_contains($autoSrc, 'allowNotify') && str_contains($autoSrc, 'acquireRunLock'), 'automation safety wired');

$revSrc = (string) file_get_contents($root . '/app/services/CrmRevOpsAutomationService.php');
c10_assert(str_contains($revSrc, 'include_legacy_in_revops'), 'revops legacy opt-in');
c10_assert(str_contains($revSrc, 'runAll(bool $includeLegacy = false)') || str_contains($revSrc, 'includeLegacy = false'), 'revops default no legacy');

$c360 = (string) file_get_contents($root . '/app/services/CrmCustomer360Service.php');
c10_assert(str_contains($c360, 'refresh') && str_contains($c360, 'read_only'), '360 read-only default');

$pipe = (string) file_get_contents($root . '/app/services/CrmDomainServices.php');
c10_assert(str_contains($pipe, 'LIMIT 500'), 'pipeline board capped');

$ops = (string) file_get_contents($root . '/routes/modules/ops.php');
c10_assert(str_contains($ops, 'crm.revops.run'), 'revops.run route permission');
c10_assert(str_contains($ops, 'crm.insights.manage'), 'insights.manage dismiss permission');

$ctrl = (string) file_get_contents($root . '/app/controllers/Company/CrmControllers.php');
c10_assert(str_contains($ctrl, 'crm.revops.run'), 'controller revops.run gate');
c10_assert(str_contains($ctrl, 'crm.insights.manage'), 'controller insights.manage gate');
c10_assert(str_contains($ctrl, "assemble(\$persist)"), 'insights persist opt-in');

$rules = (string) file_get_contents($root . '/app/services/CrmAutomationRulesEngineService.php');
c10_assert(str_contains($rules, 'always_rule_cap') || str_contains($rules, 'block_always_rules_over_max'), 'always-rule cap');
c10_assert(str_contains($rules, 'cooldown'), 'rules engine cooldown');

$report = (string) file_get_contents($root . '/app/services/CrmReportingCenterService.php');
c10_assert(str_contains($report, 'validateExportPolicy'), 'scheduled export policy check');

$blocked = false;
try {
    (new CrmQuotationService())->convertToInvoice(1);
} catch (\RuntimeException $e) {
    $blocked = str_contains($e->getMessage(), 'disabled');
}
c10_assert($blocked, 'quote→invoice still disabled');

$mig = $root . '/migrations/239_crm_phase10_production_hardening.sql';
c10_assert(is_file($mig), 'migration 239 exists');
$sql = (string) file_get_contents($mig);
c10_assert(str_contains($sql, 'idx_crm_quote_status_valid'), 'quote index');
c10_assert(str_contains($sql, 'idx_crm_alog_cooldown'), 'automation log cooldown index');
c10_assert(str_contains($sql, 'crm.revops.run'), 'revops.run perm seed');
c10_assert(str_contains($sql, 'crm.insights.manage'), 'insights.manage perm seed');
c10_assert(str_contains($sql, 'automation_safety'), 'automation_safety setting');
c10_assert(!preg_match('/\bDROP\s+TABLE\b|\bTRUNCATE\b/i', $sql), 'no DROP/TRUNCATE');
c10_assert(!str_contains($sql, 'AccountingService'), 'no AccountingService');

$perms = (string) file_get_contents($root . '/config/permissions-system.php');
c10_assert(str_contains($perms, 'crm.revops.run'), 'permissions-system revops.run');
c10_assert(str_contains($perms, 'crm.insights.manage'), 'permissions-system insights.manage');

foreach ([
    'CRM-PHASE10-ARCHITECTURE-MAP.md',
    'CRM-PHASE10-MIGRATION-MAP.md',
    'CRM-PHASE10-PERMISSIONS-MATRIX.md',
    'CRM-PHASE10-PRODUCTION-RUNBOOK.md',
    'CRM-PHASE10-PERFORMANCE.md',
] as $doc) {
    c10_assert(is_file($root . '/docs/crm/' . $doc), 'doc ' . $doc);
}

// Tenant isolation patterns (static)
$merge = (string) file_get_contents($root . '/app/services/CrmDuplicateMergeService.php');
c10_assert(substr_count($merge, 'company_id') >= 4, 'merge tenant checks present');
c10_assert(!str_contains($merge, 'AccountingService'), 'merge no Accounting');

echo "\n=== PHP lint (hardening files) ===\n";
$lintFiles = [
    'app/services/CrmAutomationSafetyService.php',
    'app/services/CrmObservability.php',
    'app/services/CrmAutomationService.php',
    'app/services/CrmRevOpsAutomationService.php',
    'app/services/CrmDataQualityEngineService.php',
    'app/services/CrmGovernanceService.php',
    'app/services/CrmCustomer360Service.php',
    'app/services/CrmAutomationRulesEngineService.php',
    'app/services/CrmReportingCenterService.php',
    'app/controllers/Company/CrmControllers.php',
    'routes/modules/ops.php',
];
foreach ($lintFiles as $rel) {
    $path = $root . '/' . $rel;
    $out = [];
    $code = 0;
    exec('php -l ' . escapeshellarg($path) . ' 2>&1', $out, $code);
    c10_assert($code === 0, 'php -l ' . $rel);
}

echo "\n=== Route audit ===\n";
$out = [];
$code = 0;
exec('php ' . escapeshellarg($root . '/tools/audit-route-controller-imports.php') . ' 2>&1', $out, $code);
echo implode("\n", $out) . "\n";
c10_assert($code === 0, 'route controller imports OK');

echo "\n=== Phase 2–9 regression ===\n";
foreach ([2, 3, 4, 5, 6, 7, 8, 9] as $phase) {
    $script = $root . '/tests/run-crm-phase' . $phase . '-tests.php';
    if (!is_file($script)) {
        c10_assert(false, 'phase' . $phase . ' test missing');
        continue;
    }
    $out = [];
    $code = 0;
    exec('php ' . escapeshellarg($script) . ' 2>&1', $out, $code);
    $tail = $out !== [] ? $out[count($out) - 1] : '';
    echo "phase{$phase}: {$tail}\n";
    c10_assert($code === 0, 'phase' . $phase . ' regression');
}

echo "\nCRM Phase 10 tests: {$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
