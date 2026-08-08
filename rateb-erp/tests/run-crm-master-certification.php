<?php
declare(strict_types=1);

/**
 * Master CRM Certification Suite (Phase 11).
 * Runs Phase 2–11 regression + route audit + enterprise cert axes + PHP lint.
 *
 * Run: php rateb-erp/tests/run-crm-master-certification.php
 */

$root = dirname(__DIR__);
require_once $root . '/app/Core/Bootstrap.php';
\Rateb\App\Core\Bootstrap::init($root);

use Rateb\App\Services\CrmEnterpriseCertificationService;

$passed = 0;
$failed = 0;
$results = [];

function m_assert(bool $cond, string $label): void
{
    global $passed, $failed, $results;
    if ($cond) {
        ++$passed;
        $results[] = ['PASS', $label];
        echo "PASS: {$label}\n";
    } else {
        ++$failed;
        $results[] = ['FAIL', $label];
        echo "FAIL: {$label}\n";
    }
}

echo "=== Master CRM Certification Suite ===\n";

$cert = (new CrmEnterpriseCertificationService())->certifyAll($root);
m_assert(($cert['overall'] ?? '') === 'PASS', 'enterprise certifyAll');
foreach ($cert['axes'] as $name => $axis) {
    if ($name === 'regression') {
        continue;
    }
    m_assert(($axis['status'] ?? '') === 'PASS', 'cert axis ' . $name);
}

echo "\n=== Phase 2–11 regression ===\n";
foreach ([2, 3, 4, 5, 6, 7, 8, 9, 10, 11] as $phase) {
    $script = $root . '/tests/run-crm-phase' . $phase . '-tests.php';
    if (!is_file($script)) {
        m_assert(false, 'phase' . $phase . ' missing');
        continue;
    }
    $out = [];
    $code = 0;
    exec('php ' . escapeshellarg($script) . ' 2>&1', $out, $code);
    $tail = $out !== [] ? $out[count($out) - 1] : '';
    echo "phase{$phase}: {$tail}\n";
    m_assert($code === 0, 'phase' . $phase . ' regression');
}

echo "\n=== Route audit ===\n";
$out = [];
$code = 0;
exec('php ' . escapeshellarg($root . '/tools/audit-route-controller-imports.php') . ' 2>&1', $out, $code);
echo implode("\n", $out) . "\n";
m_assert($code === 0, 'route controller imports');

echo "\n=== Security / policy guards ===\n";
$quote = (string) file_get_contents($root . '/app/services/CrmQuotationService.php');
m_assert(str_contains($quote, 'disabled') || str_contains($quote, 'convertToInvoice'), 'quote service present');
$blocked = false;
try {
    (new \Rateb\App\Services\CrmQuotationService())->convertToInvoice(1);
} catch (\RuntimeException $e) {
    $blocked = str_contains($e->getMessage(), 'disabled');
}
m_assert($blocked, 'quote→invoice disabled');
m_assert(!str_contains((string) file_get_contents($root . '/app/services/CrmDuplicateMergeService.php'), 'AccountingService'), 'no Accounting in merge');

$reportPath = $root . '/docs/crm/CRM-ENTERPRISE-CERTIFICATION.md';
m_assert(is_file($reportPath), 'certification document exists');

echo "\nMASTER CERTIFICATION: {$passed} passed, {$failed} failed\n";
echo 'OVERALL: ' . (($failed === 0) ? 'PASS' : 'FAIL') . "\n";
if (($cert['blockers'] ?? []) !== []) {
    echo "CERT BLOCKERS:\n";
    foreach ($cert['blockers'] as $b) {
        echo " - {$b}\n";
    }
}
if (($cert['warnings'] ?? []) !== []) {
    echo "WARNINGS:\n";
    foreach ($cert['warnings'] as $w) {
        echo " - {$w}\n";
    }
}
exit($failed > 0 ? 1 : 0);
