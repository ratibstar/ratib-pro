<?php
declare(strict_types=1);

/**
 * CRM Phase 2 — structure / workflow tests (no DB required).
 * Run: php rateb-erp/tests/run-crm-phase2-tests.php
 */

$root = dirname(__DIR__);
require_once $root . '/app/Core/Bootstrap.php';
\Rateb\App\Core\Bootstrap::init($root);

use Rateb\App\Controllers\Company\CrmActivitiesController;
use Rateb\App\Controllers\Company\CrmCallsController;
use Rateb\App\Controllers\Company\CrmOpportunitiesController;
use Rateb\App\Controllers\Company\CrmQuotationsController;
use Rateb\App\Models\CrmConversion;
use Rateb\App\Models\CrmEntityStatusHistory;
use Rateb\App\Services\CrmConversionService;
use Rateb\App\Services\CrmDashboardService;
use Rateb\App\Services\CrmQuotationService;
use Rateb\App\Services\CrmQuotationWorkflowService;
use Rateb\App\Services\CrmTimelineService;

$passed = 0;
$failed = 0;

function c2_assert(bool $cond, string $label): void
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

c2_assert(class_exists(CrmQuotationWorkflowService::class), 'CrmQuotationWorkflowService');
c2_assert(class_exists(CrmConversionService::class), 'CrmConversionService');
c2_assert(class_exists(CrmDashboardService::class), 'CrmDashboardService');
c2_assert(class_exists(CrmEntityStatusHistory::class), 'CrmEntityStatusHistory model');
c2_assert(class_exists(CrmConversion::class), 'CrmConversion model');
c2_assert(class_exists(CrmCallsController::class), 'CrmCallsController');
c2_assert(class_exists(CrmActivitiesController::class), 'CrmActivitiesController');
c2_assert(method_exists(CrmOpportunitiesController::class, 'show'), 'opportunity show');
c2_assert(method_exists(CrmOpportunitiesController::class, 'convertToQuotation'), 'opportunity convert');
c2_assert(method_exists(CrmQuotationsController::class, 'transition'), 'quotation transition');
c2_assert(method_exists(CrmQuotationsController::class, 'convertToCustomer'), 'quotation convert customer');
c2_assert(method_exists(CrmTimelineService::class, 'listExpanded'), 'timeline listExpanded');
c2_assert(method_exists(CrmTimelineService::class, 'listForQuotation'), 'timeline listForQuotation');
c2_assert(method_exists(CrmTimelineService::class, 'listForOpportunity'), 'timeline listForOpportunity');
c2_assert(method_exists(CrmQuotationService::class, 'convertToInvoice'), 'invoice convert method exists');
c2_assert(method_exists(CrmQuotationService::class, 'statusHistory'), 'quotation statusHistory');

$statuses = CrmQuotationWorkflowService::statuses();
c2_assert($statuses === ['draft', 'sent', 'accepted', 'rejected', 'expired'], 'quotation statuses');
$map = CrmQuotationWorkflowService::allowedTransitions();
c2_assert(in_array('sent', $map['draft'] ?? [], true), 'draft→sent allowed');
c2_assert(in_array('accepted', $map['sent'] ?? [], true), 'sent→accepted allowed');
c2_assert(in_array('rejected', $map['sent'] ?? [], true), 'sent→rejected allowed');
c2_assert(in_array('expired', $map['sent'] ?? [], true), 'sent→expired allowed');
c2_assert(($map['accepted'] ?? null) === [], 'accepted terminal');

$invoiceBlocked = false;
try {
    (new CrmQuotationService())->convertToInvoice(1);
} catch (\RuntimeException $e) {
    $invoiceBlocked = $e->getMessage() === 'quotation_to_invoice_disabled_phase2';
}
c2_assert($invoiceBlocked, 'quote→invoice blocked');

$migration = $root . '/migrations/231_crm_phase2_conversions_audit.sql';
c2_assert(is_file($migration), 'migration 231 exists');
$sql = (string) file_get_contents($migration);
c2_assert(str_contains($sql, 'CREATE TABLE IF NOT EXISTS rateb_crm_entity_status_history'), 'entity status history table');
c2_assert(str_contains($sql, 'CREATE TABLE IF NOT EXISTS rateb_crm_conversions'), 'conversions table');
c2_assert(str_contains($sql, 'crm.quote.view'), 'quote view permission');
c2_assert(str_contains($sql, 'crm.quote.convert'), 'quote convert permission');
c2_assert(!preg_match('/\bDROP\s+TABLE\b|\bTRUNCATE\b/i', $sql), 'no DROP/TRUNCATE');
c2_assert(!preg_match('/\bALTER\s+TABLE\b/i', $sql), 'no ALTER TABLE');

$ops = (string) file_get_contents($root . '/routes/modules/ops.php');
c2_assert(str_contains($ops, 'convert-opportunity'), 'lead convert route');
c2_assert(str_contains($ops, 'convert-quotation'), 'opportunity convert route');
c2_assert(str_contains($ops, 'convert-customer'), 'quotation convert customer route');
c2_assert(str_contains($ops, "crm/quotations/{id}/transition"), 'quotation transition route');
c2_assert(str_contains($ops, 'CrmCallsController'), 'calls controller import');
c2_assert(str_contains($ops, "crm/activities"), 'activities routes');
c2_assert(str_contains($ops, "crm/contacts/{id}"), 'contact show route');
c2_assert(str_contains($ops, "crm/companies/{id}"), 'company show route');
c2_assert(str_contains($ops, "crm/opportunities/{id}"), 'opportunity show route');

c2_assert(is_file($root . '/views/company/crm/opportunities/show.php'), 'opportunity show view');
c2_assert(is_file($root . '/views/company/crm/calls/index.php'), 'calls view');
c2_assert(is_file($root . '/views/company/crm/activities/index.php'), 'activities view');
c2_assert(is_file($root . '/views/company/crm/contacts/show.php'), 'contact show view');
c2_assert(is_file($root . '/views/company/crm/companies/show.php'), 'company show view');

$dash = (string) file_get_contents($root . '/views/company/crm/dashboard.php');
c2_assert(str_contains($dash, 'crm_kpi_leads'), 'dashboard KPI leads');
c2_assert(str_contains($dash, 'crm_kpi_pipeline_value'), 'dashboard KPI pipeline');

$perms = (string) file_get_contents($root . '/config/permissions-system.php');
c2_assert(str_contains($perms, 'crm.quote.convert'), 'permissions-system includes quote.convert');

$svc = (string) file_get_contents($root . '/app/services/CrmConversionService.php');
c2_assert(str_contains($svc, 'leadToOpportunity'), 'conversion lead→opp');
c2_assert(str_contains($svc, 'opportunityToQuotation'), 'conversion opp→quote');
c2_assert(str_contains($svc, 'quotationToCustomer'), 'conversion quote→customer');
c2_assert(!str_contains($svc, 'AccountingService'), 'no AccountingService coupling');
c2_assert(!preg_match('/createInvoice|toInvoice|InvoiceService|rateb_invoices/i', $svc), 'conversion service has no invoice logic');

echo "\nCRM Phase 2 tests: {$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
