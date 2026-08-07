<?php
declare(strict_types=1);

/**
 * CRM Phase 8 — RevOps Command Center structure tests (no DB required).
 * Run: php rateb-erp/tests/run-crm-phase8-tests.php
 */

$root = dirname(__DIR__);
require_once $root . '/app/Core/Bootstrap.php';
\Rateb\App\Core\Bootstrap::init($root);

use Rateb\App\Controllers\Company\CrmCockpitController;
use Rateb\App\Controllers\Company\CrmDataQualityController;
use Rateb\App\Controllers\Company\CrmReportingCenterController;
use Rateb\App\Controllers\Company\CrmRevOpsController;
use Rateb\App\Controllers\Company\CrmSearchController;
use Rateb\App\Controllers\Company\CrmWorkflowGovernanceController;
use Rateb\App\Models\CrmQualitySnapshot;
use Rateb\App\Models\CrmSavedDashboard;
use Rateb\App\Models\CrmScheduledReport;
use Rateb\App\Models\CrmStageGovernanceRule;
use Rateb\App\Services\CrmDataQualityEngineService;
use Rateb\App\Services\CrmExecutiveCockpitService;
use Rateb\App\Services\CrmQuotationService;
use Rateb\App\Services\CrmReportingCenterService;
use Rateb\App\Services\CrmRevOpsAutomationService;
use Rateb\App\Services\CrmRevOpsCommandCenterService;
use Rateb\App\Services\CrmUnifiedSearchService;
use Rateb\App\Services\CrmWorkflowGovernanceService;
use Rateb\App\Services\NotificationService;

$passed = 0;
$failed = 0;

function c8_assert(bool $cond, string $label): void
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

c8_assert(class_exists(CrmRevOpsCommandCenterService::class), 'CrmRevOpsCommandCenterService');
c8_assert(class_exists(CrmExecutiveCockpitService::class), 'CrmExecutiveCockpitService');
c8_assert(class_exists(CrmWorkflowGovernanceService::class), 'CrmWorkflowGovernanceService');
c8_assert(class_exists(CrmDataQualityEngineService::class), 'CrmDataQualityEngineService');
c8_assert(class_exists(CrmRevOpsAutomationService::class), 'CrmRevOpsAutomationService');
c8_assert(class_exists(CrmUnifiedSearchService::class), 'CrmUnifiedSearchService');
c8_assert(class_exists(CrmReportingCenterService::class), 'CrmReportingCenterService');
c8_assert(class_exists(CrmRevOpsController::class), 'CrmRevOpsController');
c8_assert(class_exists(CrmCockpitController::class), 'CrmCockpitController');
c8_assert(class_exists(CrmWorkflowGovernanceController::class), 'CrmWorkflowGovernanceController');
c8_assert(class_exists(CrmDataQualityController::class), 'CrmDataQualityController');
c8_assert(class_exists(CrmSearchController::class), 'CrmSearchController');
c8_assert(class_exists(CrmReportingCenterController::class), 'CrmReportingCenterController');
c8_assert(class_exists(CrmStageGovernanceRule::class), 'CrmStageGovernanceRule');
c8_assert(class_exists(CrmQualitySnapshot::class), 'CrmQualitySnapshot');
c8_assert(class_exists(CrmSavedDashboard::class), 'CrmSavedDashboard');
c8_assert(class_exists(CrmScheduledReport::class), 'CrmScheduledReport');
c8_assert(class_exists(NotificationService::class), 'NotificationService reused');

c8_assert(method_exists(CrmRevOpsCommandCenterService::class, 'assemble'), 'revops assemble');
c8_assert(method_exists(CrmExecutiveCockpitService::class, 'assemble'), 'cockpit assemble');
c8_assert(method_exists(CrmWorkflowGovernanceService::class, 'assertStageMove'), 'assertStageMove');
c8_assert(method_exists(CrmWorkflowGovernanceService::class, 'slaBreaches'), 'slaBreaches');
c8_assert(method_exists(CrmDataQualityEngineService::class, 'computeScores'), 'computeScores');
c8_assert(method_exists(CrmDataQualityEngineService::class, 'qualityTrend'), 'qualityTrend');
c8_assert(method_exists(CrmDataQualityEngineService::class, 'resolutionTracking'), 'resolutionTracking');
c8_assert(method_exists(CrmRevOpsAutomationService::class, 'processEscalations'), 'escalations');
c8_assert(method_exists(CrmRevOpsAutomationService::class, 'processSlaBreaches'), 'sla breach alerts');
c8_assert(method_exists(CrmRevOpsAutomationService::class, 'processForecastAlerts'), 'forecast alerts');
c8_assert(method_exists(CrmRevOpsAutomationService::class, 'processCustomerRiskAlerts'), 'customer risk alerts');
c8_assert(method_exists(CrmUnifiedSearchService::class, 'search'), 'unified search');
c8_assert(method_exists(CrmReportingCenterService::class, 'saveDashboard'), 'save dashboard');
c8_assert(method_exists(CrmReportingCenterService::class, 'runDue'), 'run due reports');

$blocked = false;
try {
    (new CrmQuotationService())->convertToInvoice(1);
} catch (\RuntimeException $e) {
    $blocked = str_contains($e->getMessage(), 'disabled');
}
c8_assert($blocked, 'quote→invoice still disabled');

$migration = $root . '/migrations/237_crm_phase8_revops_command_center.sql';
c8_assert(is_file($migration), 'migration 237 exists');
$sql = (string) file_get_contents($migration);
c8_assert(str_contains($sql, 'rateb_crm_stage_governance_rules'), 'stage governance table');
c8_assert(str_contains($sql, 'rateb_crm_quality_snapshots'), 'quality snapshots');
c8_assert(str_contains($sql, 'rateb_crm_saved_dashboards'), 'saved dashboards');
c8_assert(str_contains($sql, 'rateb_crm_scheduled_reports'), 'scheduled reports');
c8_assert(str_contains($sql, 'crm.revops.view'), 'revops perm');
c8_assert(str_contains($sql, 'crm.cockpit.view'), 'cockpit perm');
c8_assert(str_contains($sql, 'crm.search.view'), 'search perm');
c8_assert(str_contains($sql, 'crm.reporting.center'), 'reporting center perm');
c8_assert(str_contains($sql, 'crm.workflow.governance'), 'workflow governance perm');
c8_assert(str_contains($sql, 'workflow_governance'), 'workflow governance setting');
c8_assert(str_contains($sql, 'duplicate_rules'), 'duplicate rules setting');
c8_assert(!preg_match('/\bDROP\s+TABLE\b|\bTRUNCATE\b/i', $sql), 'no DROP/TRUNCATE');
c8_assert(!str_contains($sql, 'CREATE TABLE IF NOT EXISTS rateb_customers'), 'no duplicate customers table');
c8_assert(!str_contains($sql, 'AccountingService'), 'migration no AccountingService');

$ops = (string) file_get_contents($root . '/routes/modules/ops.php');
c8_assert(str_contains($ops, 'CrmRevOpsController'), 'revops import');
c8_assert(str_contains($ops, 'CrmCockpitController'), 'cockpit import');
c8_assert(str_contains($ops, 'CrmWorkflowGovernanceController'), 'workflow gov import');
c8_assert(str_contains($ops, 'CrmDataQualityController'), 'data quality import');
c8_assert(str_contains($ops, 'CrmSearchController'), 'search import');
c8_assert(str_contains($ops, 'CrmReportingCenterController'), 'reporting import');
c8_assert(str_contains($ops, "crm/revops"), 'revops route');
c8_assert(str_contains($ops, "crm/cockpit"), 'cockpit route');
c8_assert(str_contains($ops, 'workflow-governance'), 'workflow route');
c8_assert(str_contains($ops, "crm/data-quality"), 'data quality route');
c8_assert(str_contains($ops, "crm/search"), 'search route');
c8_assert(str_contains($ops, 'reporting-center'), 'reporting route');

$domain = (string) file_get_contents($root . '/app/services/CrmDomainServices.php');
c8_assert(str_contains($domain, 'CrmWorkflowGovernanceService'), 'moveStage governance hook');

$auto = (string) file_get_contents($root . '/app/services/CrmRevOpsAutomationService.php');
c8_assert(str_contains($auto, 'NotificationService'), 'automation uses NotificationService');
c8_assert(!preg_match('/MailService|AccountingService|InvoiceService/', $auto), 'automation no mail/accounting');

$search = (string) file_get_contents($root . '/app/services/CrmUnifiedSearchService.php');
c8_assert(str_contains($search, 'crm.search.usage'), 'search audit');
c8_assert(str_contains($search, 'rateb_crm_leads'), 'search leads');
c8_assert(str_contains($search, 'rateb_crm_quotations'), 'search quotations');

$report = (string) file_get_contents($root . '/app/services/CrmReportingCenterService.php');
c8_assert(str_contains($report, 'crm.export.csv'), 'scheduled report audit');
c8_assert(!str_contains($report, 'MailService'), 'no new email provider');

c8_assert(is_file($root . '/views/company/crm/revops/index.php'), 'revops view');
c8_assert(is_file($root . '/views/company/crm/cockpit/index.php'), 'cockpit view');
c8_assert(is_file($root . '/views/company/crm/workflow-governance/index.php'), 'workflow view');
c8_assert(is_file($root . '/views/company/crm/data-quality/index.php'), 'data quality view');
c8_assert(is_file($root . '/views/company/crm/search/index.php'), 'search view');
c8_assert(is_file($root . '/views/company/crm/reporting-center/index.php'), 'reporting view');

$sidebar = (string) file_get_contents($root . '/views/partials/sidebar-ops-nav.php');
c8_assert(str_contains($sidebar, 'crm/revops'), 'sidebar revops');
c8_assert(str_contains($sidebar, 'crm/cockpit'), 'sidebar cockpit');
c8_assert(str_contains($sidebar, 'crm/search'), 'sidebar search');
c8_assert(str_contains($sidebar, 'crm/reporting-center'), 'sidebar reporting');

$perms = (string) file_get_contents($root . '/config/permissions-system.php');
c8_assert(str_contains($perms, 'crm.revops.view'), 'permissions-system revops');
c8_assert(str_contains($perms, 'crm.workflow.governance'), 'permissions-system workflow');

echo "\nCRM Phase 8 tests: {$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
