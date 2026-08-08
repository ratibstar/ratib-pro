<?php
declare(strict_types=1);

/**
 * CRM Phase 11 — Enterprise certification + data integrity suite.
 * Run: php rateb-erp/tests/run-crm-phase11-tests.php
 */

$root = dirname(__DIR__);
require_once $root . '/app/Core/Bootstrap.php';
\Rateb\App\Core\Bootstrap::init($root);

use Rateb\App\Services\CrmDataIntegrityAuditService;
use Rateb\App\Services\CrmDuplicateMergeService;
use Rateb\App\Services\CrmEnterpriseCertificationService;
use Rateb\App\Services\CrmPerformanceCertificationService;

$passed = 0;
$failed = 0;

function c11_assert(bool $cond, string $label): void
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

echo "=== Phase 11 certification assertions ===\n";

c11_assert(class_exists(CrmDuplicateMergeService::class), 'CrmDuplicateMergeService');
c11_assert(class_exists(CrmDataIntegrityAuditService::class), 'CrmDataIntegrityAuditService');
c11_assert(class_exists(CrmEnterpriseCertificationService::class), 'CrmEnterpriseCertificationService');
c11_assert(class_exists(CrmPerformanceCertificationService::class), 'CrmPerformanceCertificationService');

$merge = (string) file_get_contents($root . '/app/services/CrmDuplicateMergeService.php');
c11_assert(str_contains($merge, 'beginTransaction'), 'merge beginTransaction');
c11_assert(str_contains($merge, '->commit()'), 'merge commit');
c11_assert(str_contains($merge, 'rollBack'), 'merge rollBack');
c11_assert(str_contains($merge, 'repointBulk'), 'merge bulk repoint');
c11_assert(str_contains($merge, 'transactional'), 'merge returns transactional flag');
c11_assert(!str_contains($merge, 'AccountingService'), 'merge no Accounting');
// Atomicity: audit only after successful commit path
$commitPos = strpos($merge, '->commit()');
$auditPos = strpos($merge, "log('crm.merge.execute'");
c11_assert($commitPos !== false && $auditPos !== false && $auditPos > $commitPos, 'audit after commit');

$integrity = (string) file_get_contents($root . '/app/services/CrmDataIntegrityAuditService.php');
foreach ([
    'orphan_opportunity', 'orphan_activity', 'invalid_customer_ref', 'duplicate_active',
    'invalid_lifecycle', 'invalid_pipeline_stage', 'broken_quotation', 'stage_history', 'forecast',
] as $code) {
    c11_assert(str_contains($integrity, $code), 'integrity check ' . $code);
}
c11_assert(str_contains($integrity, "'auto_delete' => false"), 'no auto delete');
c11_assert(!preg_match('/\bDELETE\s+FROM\b/i', $integrity), 'integrity service never DELETE FROM');

$ops = (string) file_get_contents($root . '/routes/modules/ops.php');
c11_assert(str_contains($ops, "crm/integrity"), 'integrity route');
c11_assert(str_contains($ops, 'CrmIntegrityController'), 'integrity controller import/route');
c11_assert(str_contains($ops, 'crm.governance.view'), 'integrity gated');

$ctrl = (string) file_get_contents($root . '/app/controllers/Company/CrmControllers.php');
c11_assert(str_contains($ctrl, 'class CrmIntegrityController'), 'CrmIntegrityController class');
c11_assert(str_contains($ctrl, 'CrmDataIntegrityAuditService'), 'controller uses integrity service');

c11_assert(is_file($root . '/views/company/crm/integrity/index.php'), 'integrity view');
c11_assert(is_file($root . '/docs/crm/CRM-ENTERPRISE-CERTIFICATION.md'), 'certification doc');

$cert = (new CrmEnterpriseCertificationService())->certifyAll($root);
c11_assert(($cert['overall'] ?? '') === 'PASS', 'enterprise certifyAll overall PASS');
foreach (['transaction_integrity', 'data_integrity', 'tenant_isolation', 'authorization', 'automation', 'performance', 'migrations'] as $axis) {
    $st = (string) (($cert['axes'][$axis]['status'] ?? 'FAIL'));
    c11_assert(in_array($st, ['PASS', 'WARN', 'DEFERRED'], true) && $st !== 'FAIL', 'axis ' . $axis . '=' . $st);
}

// Partial-failure contract: execute rolls back on exception (structural + exception path present)
c11_assert(str_contains($merge, 'merge_finalize_failed') || str_contains($merge, 'lead_archive_failed'), 'failure paths throw');
c11_assert(preg_match('/catch\s*\(\s*\\\\?Throwable/', $merge) === 1, 'merge catch Throwable for rollback');

echo "\n=== PHP lint (phase 11 files) ===\n";
foreach ([
    'app/services/CrmDuplicateMergeService.php',
    'app/services/CrmDataIntegrityAuditService.php',
    'app/services/CrmEnterpriseCertificationService.php',
    'app/services/CrmPerformanceCertificationService.php',
    'app/controllers/Company/CrmControllers.php',
    'routes/modules/ops.php',
    'views/company/crm/integrity/index.php',
] as $rel) {
    $path = $root . '/' . $rel;
    $out = [];
    $code = 0;
    exec('php -l ' . escapeshellarg($path) . ' 2>&1', $out, $code);
    c11_assert($code === 0, 'php -l ' . $rel);
}

echo "\nCRM Phase 11 tests: {$passed} passed, {$failed} failed\n";
if (($cert['blockers'] ?? []) !== []) {
    echo "BLOCKERS:\n";
    foreach ($cert['blockers'] as $b) {
        echo " - {$b}\n";
    }
}
exit($failed > 0 ? 1 : 0);
