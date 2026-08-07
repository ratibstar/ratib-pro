<?php
declare(strict_types=1);

/**
 * CRM Sales Quotations Phase 1 — structure tests (no DB required).
 * Run: php rateb-erp/tests/run-crm-quotations-phase1-tests.php
 */

$root = dirname(__DIR__);
require_once $root . '/app/Core/Bootstrap.php';
\Rateb\App\Core\Bootstrap::init($root);

use Rateb\App\Controllers\Company\CrmQuotationsController;
use Rateb\App\Models\CrmQuotation;
use Rateb\App\Models\CrmQuotationLine;
use Rateb\App\Services\CrmQuotationService;
use Rateb\App\Services\CrmSupport;

$passed = 0;
$failed = 0;

function cq1_assert(bool $cond, string $label): void
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

cq1_assert(class_exists(CrmQuotation::class), 'CrmQuotation model');
cq1_assert(class_exists(CrmQuotationLine::class), 'CrmQuotationLine model');
cq1_assert(class_exists(CrmQuotationService::class), 'CrmQuotationService');
cq1_assert(class_exists(CrmQuotationsController::class), 'CrmQuotationsController');
cq1_assert(method_exists(CrmSupport::class, 'nextQuotationNo'), 'CrmSupport::nextQuotationNo');

$migration = $root . '/migrations/228_crm_sales_quotations.sql';
cq1_assert(is_file($migration), 'migration 228 exists');
$sql = (string) file_get_contents($migration);
cq1_assert(str_contains($sql, 'CREATE TABLE IF NOT EXISTS rateb_crm_quotations'), 'creates quotations table');
cq1_assert(str_contains($sql, 'CREATE TABLE IF NOT EXISTS rateb_crm_quotation_lines'), 'creates quotation lines');
cq1_assert(str_contains($sql, 'lead_id'), 'links lead_id');
cq1_assert(str_contains($sql, 'opportunity_id'), 'links opportunity_id');
cq1_assert(str_contains($sql, 'customer_id'), 'links customer_id');
cq1_assert(!preg_match('/\bDROP\s+TABLE\b|\bTRUNCATE\b/i', $sql), 'no DROP TABLE/TRUNCATE');
cq1_assert(!preg_match('/\bALTER TABLE\b/i', $sql), 'no ALTER TABLE');

$ops = (string) file_get_contents($root . '/routes/modules/ops.php');
cq1_assert(str_contains($ops, 'use Rateb\\App\\Controllers\\Company\\CrmQuotationsController'), 'ops.php imports CrmQuotationsController');
cq1_assert(str_contains($ops, "crm/quotations"), 'ops.php registers quotations routes');

cq1_assert(is_file($root . '/views/company/crm/quotations/index.php'), 'quotations index view');
cq1_assert(is_file($root . '/views/company/crm/quotations/form.php'), 'quotations form view');
cq1_assert(is_file($root . '/views/company/crm/quotations/show.php'), 'quotations show view');

$sidebar = (string) file_get_contents($root . '/views/partials/sidebar-ops-nav.php');
cq1_assert(str_contains($sidebar, 'crm/quotations'), 'sidebar includes quotations');

$entity = require $root . '/config/entity-permissions.php';
cq1_assert(($entity['crm-quotations']['module'] ?? '') === 'crm', 'entity crm-quotations mapped');

echo "\nCRM Quotations Phase 1 tests: {$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
