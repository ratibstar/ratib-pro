<?php
declare(strict_types=1);

/**
 * CRM Phase 3 — structure / maturity tests (no DB required).
 * Run: php rateb-erp/tests/run-crm-phase3-tests.php
 */

$root = dirname(__DIR__);
require_once $root . '/app/Core/Bootstrap.php';
\Rateb\App\Core\Bootstrap::init($root);

use Rateb\App\Controllers\Company\CrmAutomationController;
use Rateb\App\Controllers\Company\CrmPipelineController;
use Rateb\App\Controllers\Company\CrmReportsController;
use Rateb\App\Models\CrmActivityReminder;
use Rateb\App\Models\CrmAutomationLog;
use Rateb\App\Models\CrmLossReason;
use Rateb\App\Models\CrmOpportunityOutcome;
use Rateb\App\Services\CrmAutomationService;
use Rateb\App\Services\CrmCustomer360Service;
use Rateb\App\Services\CrmReportService;
use Rateb\App\Services\OpportunityService;
use Rateb\App\Services\PipelineService;

$passed = 0;
$failed = 0;

function c3_assert(bool $cond, string $label): void
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

c3_assert(class_exists(CrmAutomationService::class), 'CrmAutomationService');
c3_assert(class_exists(CrmReportService::class), 'CrmReportService');
c3_assert(class_exists(CrmCustomer360Service::class), 'CrmCustomer360Service');
c3_assert(class_exists(CrmReportsController::class), 'CrmReportsController');
c3_assert(class_exists(CrmAutomationController::class), 'CrmAutomationController');
c3_assert(class_exists(CrmLossReason::class), 'CrmLossReason');
c3_assert(class_exists(CrmOpportunityOutcome::class), 'CrmOpportunityOutcome');
c3_assert(class_exists(CrmActivityReminder::class), 'CrmActivityReminder');
c3_assert(class_exists(CrmAutomationLog::class), 'CrmAutomationLog');

c3_assert(method_exists(PipelineService::class, 'upsertStage'), 'PipelineService::upsertStage');
c3_assert(method_exists(PipelineService::class, 'listLossReasons'), 'PipelineService::listLossReasons');
c3_assert(method_exists(PipelineService::class, 'createLossReason'), 'PipelineService::createLossReason');
c3_assert(method_exists(OpportunityService::class, 'expectedRevenue'), 'OpportunityService::expectedRevenue');
c3_assert(OpportunityService::expectedRevenue(1000, 40) === 400.0, 'expected revenue calc');

c3_assert(method_exists(CrmAutomationService::class, 'onLeadAssigned'), 'automation lead assignment');
c3_assert(method_exists(CrmAutomationService::class, 'onOpportunityStageChanged'), 'automation stage change');
c3_assert(method_exists(CrmAutomationService::class, 'processFollowUpReminders'), 'automation follow-up');
c3_assert(method_exists(CrmAutomationService::class, 'processQuoteExpiryAlerts'), 'automation quote expiry');

c3_assert(method_exists(CrmReportService::class, 'salesFunnel'), 'report salesFunnel');
c3_assert(method_exists(CrmReportService::class, 'conversionRates'), 'report conversionRates');
c3_assert(method_exists(CrmReportService::class, 'leadSources'), 'report leadSources');
c3_assert(method_exists(CrmReportService::class, 'salesPerformance'), 'report salesPerformance');
c3_assert(method_exists(CrmReportService::class, 'lostOpportunities'), 'report lostOpportunities');
c3_assert(method_exists(CrmReportService::class, 'forecast'), 'report forecast');
c3_assert(method_exists(CrmCustomer360Service::class, 'assemble'), 'customer 360 assemble');

$migration = $root . '/migrations/232_crm_phase3_enterprise_maturity.sql';
c3_assert(is_file($migration), 'migration 232 exists');
$sql = (string) file_get_contents($migration);
c3_assert(str_contains($sql, 'CREATE TABLE IF NOT EXISTS rateb_crm_loss_reasons'), 'loss reasons table');
c3_assert(str_contains($sql, 'CREATE TABLE IF NOT EXISTS rateb_crm_opportunity_outcomes'), 'outcomes table');
c3_assert(str_contains($sql, 'CREATE TABLE IF NOT EXISTS rateb_crm_activity_reminders'), 'activity reminders table');
c3_assert(str_contains($sql, 'CREATE TABLE IF NOT EXISTS rateb_crm_automation_log'), 'automation log table');
c3_assert(str_contains($sql, 'crm.pipeline.view'), 'pipeline.view perm');
c3_assert(str_contains($sql, 'crm.pipeline.manage'), 'pipeline.manage perm');
c3_assert(str_contains($sql, 'crm.pipeline.forecast'), 'pipeline.forecast perm');
c3_assert(str_contains($sql, 'crm.activities.view'), 'activities.view perm');
c3_assert(str_contains($sql, 'crm.activities.manage'), 'activities.manage perm');
c3_assert(str_contains($sql, 'crm.activities.assign'), 'activities.assign perm');
c3_assert(str_contains($sql, 'crm.reports.view'), 'reports.view perm');
c3_assert(str_contains($sql, 'crm.reports.export'), 'reports.export perm');
c3_assert(!preg_match('/\bDROP\s+TABLE\b|\bTRUNCATE\b/i', $sql), 'no DROP/TRUNCATE');

$ops = (string) file_get_contents($root . '/routes/modules/ops.php');
c3_assert(str_contains($ops, 'CrmReportsController'), 'reports controller import');
c3_assert(str_contains($ops, 'CrmAutomationController'), 'automation controller import');
c3_assert(str_contains($ops, "crm/reports"), 'reports route');
c3_assert(str_contains($ops, "crm/pipeline/stages"), 'stages route');
c3_assert(str_contains($ops, "crm/pipeline/loss-reasons"), 'loss reasons route');
c3_assert(str_contains($ops, "crm/automation/run"), 'automation run route');

c3_assert(is_file($root . '/views/company/crm/reports/index.php'), 'reports view');
$profile = (string) file_get_contents($root . '/views/company/crm/customer-profile.php');
c3_assert(str_contains($profile, 'crm_companies'), '360 companies');
c3_assert(str_contains($profile, 'opportunities'), '360 opportunities');
c3_assert(str_contains($profile, 'quotations'), '360 quotations');
c3_assert(str_contains($profile, 'invoice_links'), '360 invoice links');
c3_assert(str_contains($profile, 'payment_links'), '360 payment links');

$auto = (string) file_get_contents($root . '/app/services/CrmAutomationService.php');
c3_assert(str_contains($auto, 'NotificationService'), 'uses NotificationService');
c3_assert(!str_contains($auto, 'AccountingService'), 'automation no AccountingService');

$c360 = (string) file_get_contents($root . '/app/services/CrmCustomer360Service.php');
c3_assert(!preg_match('/new\s+AccountingService|AccountingService::/', $c360), '360 no AccountingService usage');
c3_assert(str_contains($c360, 'Link-only'), '360 link-only note');

$conv = (string) file_get_contents($root . '/app/services/CrmConversionService.php');
c3_assert(str_contains($conv, 'quotationToCustomer'), 'phase2 convert intact');

$quoteSvc = (string) file_get_contents($root . '/app/services/CrmQuotationService.php');
c3_assert(str_contains($quoteSvc, 'quotation_to_invoice_disabled_phase2'), 'no quote→invoice');

$perms = (string) file_get_contents($root . '/config/permissions-system.php');
c3_assert(str_contains($perms, 'crm.reports.view'), 'permissions-system reports');
c3_assert(str_contains($perms, 'crm.pipeline.forecast'), 'permissions-system forecast');

$sidebar = (string) file_get_contents($root . '/views/partials/sidebar-ops-nav.php');
c3_assert(str_contains($sidebar, 'crm/reports'), 'sidebar reports');

$pipeCtrl = (string) file_get_contents($root . '/app/controllers/Company/CrmControllers.php');
c3_assert(str_contains($pipeCtrl, 'storeStage'), 'pipeline storeStage');
c3_assert(str_contains($pipeCtrl, 'storeLossReason'), 'pipeline storeLossReason');
c3_assert(method_exists(CrmPipelineController::class, 'storeStage'), 'pipeline controller stage method');

echo "\nCRM Phase 3 tests: {$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
