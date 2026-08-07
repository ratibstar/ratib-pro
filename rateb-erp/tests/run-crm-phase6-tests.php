<?php
declare(strict_types=1);

/**
 * CRM Phase 6 — Intelligence + Sales Execution structure tests (no DB required).
 * Run: php rateb-erp/tests/run-crm-phase6-tests.php
 */

$root = dirname(__DIR__);
require_once $root . '/app/Core/Bootstrap.php';
\Rateb\App\Core\Bootstrap::init($root);

use Rateb\App\Controllers\Company\CrmDashboardsController;
use Rateb\App\Controllers\Company\CrmIntelligenceController;
use Rateb\App\Controllers\Company\CrmReportsController;
use Rateb\App\Controllers\Company\CrmWorkspaceController;
use Rateb\App\Models\CrmSavedReportFilter;
use Rateb\App\Models\CrmScoreHistory;
use Rateb\App\Services\CrmActivityIntelligenceService;
use Rateb\App\Services\CrmAdvancedDashboardService;
use Rateb\App\Services\CrmAutomationRulesEngineService;
use Rateb\App\Services\CrmCustomerHealthService;
use Rateb\App\Services\CrmOpportunityIntelligenceService;
use Rateb\App\Services\CrmQuotationService;
use Rateb\App\Services\CrmReportExportService;
use Rateb\App\Services\CrmSalesWorkspaceService;

$passed = 0;
$failed = 0;

function c6_assert(bool $cond, string $label): void
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

c6_assert(class_exists(CrmSalesWorkspaceService::class), 'CrmSalesWorkspaceService');
c6_assert(class_exists(CrmOpportunityIntelligenceService::class), 'CrmOpportunityIntelligenceService');
c6_assert(class_exists(CrmActivityIntelligenceService::class), 'CrmActivityIntelligenceService');
c6_assert(class_exists(CrmAdvancedDashboardService::class), 'CrmAdvancedDashboardService');
c6_assert(class_exists(CrmCustomerHealthService::class), 'CrmCustomerHealthService');
c6_assert(class_exists(CrmAutomationRulesEngineService::class), 'CrmAutomationRulesEngineService');
c6_assert(class_exists(CrmReportExportService::class), 'CrmReportExportService');
c6_assert(class_exists(CrmWorkspaceController::class), 'CrmWorkspaceController');
c6_assert(class_exists(CrmDashboardsController::class), 'CrmDashboardsController');
c6_assert(class_exists(CrmIntelligenceController::class), 'CrmIntelligenceController');
c6_assert(class_exists(CrmScoreHistory::class), 'CrmScoreHistory');
c6_assert(class_exists(CrmSavedReportFilter::class), 'CrmSavedReportFilter');

c6_assert(method_exists(CrmSalesWorkspaceService::class, 'assemble'), 'workspace assemble');
c6_assert(method_exists(CrmOpportunityIntelligenceService::class, 'score'), 'opp score');
c6_assert(method_exists(CrmOpportunityIntelligenceService::class, 'staleOpportunities'), 'stale detection');
c6_assert(method_exists(CrmActivityIntelligenceService::class, 'analyze'), 'activity analyze');
c6_assert(method_exists(CrmAdvancedDashboardService::class, 'forRole'), 'dashboards forRole');
c6_assert(method_exists(CrmCustomerHealthService::class, 'compute'), 'customer health');
c6_assert(method_exists(CrmAutomationRulesEngineService::class, 'evaluate'), 'rules evaluate');
c6_assert(method_exists(CrmAutomationRulesEngineService::class, 'saveRule'), 'rules save');
c6_assert(method_exists(CrmAutomationRulesEngineService::class, 'executionHistory'), 'rules history');
c6_assert(method_exists(CrmReportExportService::class, 'build'), 'export build');
c6_assert(method_exists(CrmReportExportService::class, 'streamCsv'), 'export stream');
c6_assert(method_exists(CrmReportExportService::class, 'saveFilter'), 'saved filters');
c6_assert(method_exists(CrmReportsController::class, 'export'), 'reports export action');
c6_assert(method_exists(CrmReportsController::class, 'saveFilter'), 'reports saveFilter action');

$blocked = false;
try {
    (new CrmQuotationService())->convertToInvoice(1);
} catch (\RuntimeException $e) {
    $blocked = str_contains($e->getMessage(), 'disabled');
}
c6_assert($blocked, 'quote→invoice still disabled');

$migration = $root . '/migrations/235_crm_phase6_intelligence_execution.sql';
c6_assert(is_file($migration), 'migration 235 exists');
$sql = (string) file_get_contents($migration);
c6_assert(str_contains($sql, 'intelligence_score'), 'opp intelligence columns');
c6_assert(str_contains($sql, 'crm_health_score'), 'customer health columns');
c6_assert(str_contains($sql, 'condition_json'), 'rule conditions');
c6_assert(str_contains($sql, 'action_json'), 'rule actions');
c6_assert(str_contains($sql, 'CREATE TABLE IF NOT EXISTS rateb_crm_score_history'), 'score history table');
c6_assert(str_contains($sql, 'CREATE TABLE IF NOT EXISTS rateb_crm_saved_report_filters'), 'saved filters table');
c6_assert(str_contains($sql, 'crm.workspace.view'), 'workspace perm');
c6_assert(str_contains($sql, 'crm.intelligence.view'), 'intelligence perm');
c6_assert(str_contains($sql, 'crm.dashboards.view'), 'dashboards perm');
c6_assert(str_contains($sql, 'crm.export.manage'), 'export perm');
c6_assert(!preg_match('/\bDROP\s+TABLE\b|\bTRUNCATE\b/i', $sql), 'no DROP/TRUNCATE');
c6_assert(!str_contains($sql, 'CREATE TABLE IF NOT EXISTS rateb_customers'), 'no duplicate customers table');

$ops = (string) file_get_contents($root . '/routes/modules/ops.php');
c6_assert(str_contains($ops, 'CrmWorkspaceController'), 'workspace controller import');
c6_assert(str_contains($ops, 'CrmDashboardsController'), 'dashboards controller import');
c6_assert(str_contains($ops, "crm/workspace"), 'workspace route');
c6_assert(str_contains($ops, "crm/dashboards"), 'dashboards route');
c6_assert(str_contains($ops, 'reports/export'), 'export route');
c6_assert(str_contains($ops, 'reports/filters'), 'filters route');
c6_assert(str_contains($ops, 'intelligence/refresh'), 'intelligence refresh route');
c6_assert(str_contains($ops, '/score'), 'score route');

$intel = (string) file_get_contents($root . '/app/services/CrmOpportunityIntelligenceService.php');
c6_assert(str_contains($intel, 'recommended_probability'), 'probability recommendations');
c6_assert(str_contains($intel, 'risk_level'), 'risk indicators');
c6_assert(!preg_match('/\bopenai\b|\banthropic\b|api\.openai|chatgpt/i', $intel), 'no external AI');

$engine = (string) file_get_contents($root . '/app/services/CrmAutomationRulesEngineService.php');
c6_assert(str_contains($engine, 'NotificationService'), 'rules use NotificationService');
c6_assert(str_contains($engine, 'condition_json') || str_contains($engine, 'matches'), 'conditions engine');

$export = (string) file_get_contents($root . '/app/services/CrmReportExportService.php');
c6_assert(str_contains($export, 'fputcsv'), 'csv fputcsv');
c6_assert(str_contains($export, 'crm.export.csv'), 'export audit');

$c360 = (string) file_get_contents($root . '/app/services/CrmCustomer360Service.php');
c6_assert(str_contains($c360, 'CrmCustomerHealthService'), '360 health service');
c6_assert(str_contains($c360, "'health'"), '360 health key');
c6_assert(!preg_match('/new\s+AccountingService|AccountingService::/', $c360), '360 no AccountingService');

$auto = (string) file_get_contents($root . '/app/services/CrmAutomationService.php');
c6_assert(str_contains($auto, 'CrmAutomationRulesEngineService'), 'runAll uses rules engine');
c6_assert(str_contains($auto, 'CrmOpportunityIntelligenceService'), 'runAll scores opps');

$dash = (string) file_get_contents($root . '/app/services/CrmAdvancedDashboardService.php');
c6_assert(str_contains($dash, 'executive'), 'executive dashboard');
c6_assert(str_contains($dash, 'manager'), 'manager dashboard');
c6_assert(str_contains($dash, 'crm.dashboard.access'), 'dashboard access audit');

c6_assert(is_file($root . '/views/company/crm/workspace/index.php'), 'workspace view');
c6_assert(is_file($root . '/views/company/crm/dashboards/index.php'), 'dashboards view');
$sidebar = (string) file_get_contents($root . '/views/partials/sidebar-ops-nav.php');
c6_assert(str_contains($sidebar, 'crm/workspace'), 'sidebar workspace');
c6_assert(str_contains($sidebar, 'crm/dashboards'), 'sidebar dashboards');

$perms = (string) file_get_contents($root . '/config/permissions-system.php');
c6_assert(str_contains($perms, 'crm.workspace.view'), 'permissions-system workspace');
c6_assert(str_contains($perms, 'crm.export.manage'), 'permissions-system export');

$admin = (string) file_get_contents($root . '/views/company/crm/admin/index.php');
c6_assert(str_contains($admin, 'condition_json'), 'admin conditions UI');
c6_assert(str_contains($admin, 'action_json'), 'admin actions UI');

echo "\nCRM Phase 6 tests: {$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
