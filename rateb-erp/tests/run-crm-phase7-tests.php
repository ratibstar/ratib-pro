<?php
declare(strict_types=1);

/**
 * CRM Phase 7 — Revenue Intelligence + Governance structure tests (no DB required).
 * Run: php rateb-erp/tests/run-crm-phase7-tests.php
 */

$root = dirname(__DIR__);
require_once $root . '/app/Core/Bootstrap.php';
\Rateb\App\Core\Bootstrap::init($root);

use Rateb\App\Controllers\Company\CrmForecastController;
use Rateb\App\Controllers\Company\CrmGovernanceController;
use Rateb\App\Controllers\Company\CrmPerformanceController;
use Rateb\App\Controllers\Company\CrmRevenueController;
use Rateb\App\Models\CrmDataQualityIssue;
use Rateb\App\Models\CrmForecastChangeLog;
use Rateb\App\Models\CrmGovernanceSetting;
use Rateb\App\Models\CrmHealthHistory;
use Rateb\App\Services\CrmCustomerHealthService;
use Rateb\App\Services\CrmEnterpriseForecastService;
use Rateb\App\Services\CrmGovernanceService;
use Rateb\App\Services\CrmQuotationService;
use Rateb\App\Services\CrmRevenueIntelligenceService;
use Rateb\App\Services\CrmSalesPerformanceService;

$passed = 0;
$failed = 0;

function c7_assert(bool $cond, string $label): void
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

c7_assert(class_exists(CrmRevenueIntelligenceService::class), 'CrmRevenueIntelligenceService');
c7_assert(class_exists(CrmEnterpriseForecastService::class), 'CrmEnterpriseForecastService');
c7_assert(class_exists(CrmGovernanceService::class), 'CrmGovernanceService');
c7_assert(class_exists(CrmSalesPerformanceService::class), 'CrmSalesPerformanceService');
c7_assert(class_exists(CrmRevenueController::class), 'CrmRevenueController');
c7_assert(class_exists(CrmForecastController::class), 'CrmForecastController');
c7_assert(class_exists(CrmGovernanceController::class), 'CrmGovernanceController');
c7_assert(class_exists(CrmPerformanceController::class), 'CrmPerformanceController');
c7_assert(class_exists(CrmForecastChangeLog::class), 'CrmForecastChangeLog');
c7_assert(class_exists(CrmGovernanceSetting::class), 'CrmGovernanceSetting');
c7_assert(class_exists(CrmDataQualityIssue::class), 'CrmDataQualityIssue');
c7_assert(class_exists(CrmHealthHistory::class), 'CrmHealthHistory');

c7_assert(method_exists(CrmRevenueIntelligenceService::class, 'dashboard'), 'revenue dashboard');
c7_assert(method_exists(CrmRevenueIntelligenceService::class, 'winLossIntelligence'), 'win/loss intel');
c7_assert(method_exists(CrmRevenueIntelligenceService::class, 'historicalTrendAnalysis'), 'historical trends');
c7_assert(method_exists(CrmRevenueIntelligenceService::class, 'conversionFunnelAnalytics'), 'conversion funnel');
c7_assert(method_exists(CrmRevenueIntelligenceService::class, 'salesCycleAnalytics'), 'sales cycle analytics');
c7_assert(method_exists(CrmEnterpriseForecastService::class, 'compute'), 'enterprise forecast compute');
c7_assert(method_exists(CrmEnterpriseForecastService::class, 'snapshot'), 'enterprise forecast snapshot');
c7_assert(method_exists(CrmEnterpriseForecastService::class, 'changeHistory'), 'forecast change history');
c7_assert(method_exists(CrmEnterpriseForecastService::class, 'teamForecastRollup'), 'team forecast rollup');
c7_assert(method_exists(CrmGovernanceService::class, 'healthDashboard'), 'governance health');
c7_assert(method_exists(CrmGovernanceService::class, 'runDataQualityScan'), 'data quality scan');
c7_assert(method_exists(CrmGovernanceService::class, 'saveSetting'), 'governance settings');
c7_assert(method_exists(CrmGovernanceService::class, 'resolveIssue'), 'resolve issue');
c7_assert(method_exists(CrmSalesPerformanceService::class, 'repProductivity'), 'rep productivity');
c7_assert(method_exists(CrmSalesPerformanceService::class, 'responseSlaTracking'), 'response SLA');
c7_assert(method_exists(CrmCustomerHealthService::class, 'healthHistory'), 'health history');
c7_assert(method_exists(CrmCustomerHealthService::class, 'riskTrends'), 'risk trends');
c7_assert(method_exists(CrmCustomerHealthService::class, 'engagementTimeline'), 'engagement timeline');

$blocked = false;
try {
    (new CrmQuotationService())->convertToInvoice(1);
} catch (\RuntimeException $e) {
    $blocked = str_contains($e->getMessage(), 'disabled');
}
c7_assert($blocked, 'quote→invoice still disabled');

$migration = $root . '/migrations/236_crm_phase7_revenue_governance.sql';
c7_assert(is_file($migration), 'migration 236 exists');
$sql = (string) file_get_contents($migration);
c7_assert(str_contains($sql, 'CREATE TABLE IF NOT EXISTS rateb_crm_forecast_change_log'), 'forecast change log');
c7_assert(str_contains($sql, 'CREATE TABLE IF NOT EXISTS rateb_crm_governance_settings'), 'governance settings');
c7_assert(str_contains($sql, 'CREATE TABLE IF NOT EXISTS rateb_crm_data_quality_issues'), 'data quality issues');
c7_assert(str_contains($sql, 'CREATE TABLE IF NOT EXISTS rateb_crm_health_history'), 'health history table');
c7_assert(str_contains($sql, 'period_type'), 'forecast period_type');
c7_assert(str_contains($sql, 'confidence_score'), 'forecast confidence');
c7_assert(str_contains($sql, 'crm.governance.manage'), 'governance.manage perm');
c7_assert(str_contains($sql, 'crm.forecast.enterprise'), 'forecast.enterprise perm');
c7_assert(str_contains($sql, 'crm.revenue.intel'), 'revenue.intel perm');
c7_assert(str_contains($sql, 'required_fields'), 'required fields seed');
c7_assert(str_contains($sql, 'export_policy'), 'export policy seed');
c7_assert(!preg_match('/\bDROP\s+TABLE\b|\bTRUNCATE\b/i', $sql), 'no DROP/TRUNCATE');
c7_assert(!str_contains($sql, 'CREATE TABLE IF NOT EXISTS rateb_customers'), 'no duplicate customers table');
c7_assert(!str_contains($sql, 'AccountingService'), 'migration no AccountingService');

$ops = (string) file_get_contents($root . '/routes/modules/ops.php');
c7_assert(str_contains($ops, 'CrmRevenueController'), 'revenue controller import');
c7_assert(str_contains($ops, 'CrmForecastController'), 'forecast controller import');
c7_assert(str_contains($ops, 'CrmGovernanceController'), 'governance controller import');
c7_assert(str_contains($ops, 'CrmPerformanceController'), 'performance controller import');
c7_assert(str_contains($ops, "crm/revenue"), 'revenue route');
c7_assert(str_contains($ops, "crm/forecast"), 'forecast route');
c7_assert(str_contains($ops, 'forecast/snapshot'), 'forecast snapshot route');
c7_assert(str_contains($ops, "crm/governance"), 'governance route');
c7_assert(str_contains($ops, 'governance/scan'), 'governance scan route');
c7_assert(str_contains($ops, "crm/performance"), 'performance route');

$rev = (string) file_get_contents($root . '/app/services/CrmRevenueIntelligenceService.php');
c7_assert(!preg_match('/AccountingService|InvoiceService/', $rev), 'revenue intel no Accounting');

$gov = (string) file_get_contents($root . '/app/services/CrmGovernanceService.php');
c7_assert(str_contains($gov, 'duplicate'), 'duplicate detection');
c7_assert(str_contains($gov, 'missing_field') || str_contains($gov, 'missing'), 'missing fields');
c7_assert(str_contains($gov, 'ownership'), 'ownership validation');
c7_assert(str_contains($gov, 'crm.governance'), 'governance audit');

$fc = (string) file_get_contents($root . '/app/services/CrmEnterpriseForecastService.php');
c7_assert(str_contains($fc, 'quarter'), 'quarterly forecasts');
c7_assert(str_contains($fc, 'confidenceScore') || str_contains($fc, 'confidence_score'), 'confidence scoring');
c7_assert(str_contains($fc, 'crm.forecast.change'), 'forecast change audit');

$c360 = (string) file_get_contents($root . '/app/services/CrmCustomer360Service.php');
c7_assert(str_contains($c360, 'health_history'), '360 health history');
c7_assert(str_contains($c360, 'risk_trends'), '360 risk trends');
c7_assert(str_contains($c360, 'engagement_timeline'), '360 engagement timeline');
c7_assert(!preg_match('/new\s+AccountingService|AccountingService::/', $c360), '360 no AccountingService');

$export = (string) file_get_contents($root . '/app/services/CrmReportExportService.php');
c7_assert(str_contains($export, 'validateExportPolicy'), 'export policy check');

c7_assert(is_file($root . '/views/company/crm/revenue/index.php'), 'revenue view');
c7_assert(is_file($root . '/views/company/crm/forecast/index.php'), 'forecast view');
c7_assert(is_file($root . '/views/company/crm/governance/index.php'), 'governance view');
c7_assert(is_file($root . '/views/company/crm/performance/index.php'), 'performance view');

$sidebar = (string) file_get_contents($root . '/views/partials/sidebar-ops-nav.php');
c7_assert(str_contains($sidebar, 'crm/revenue'), 'sidebar revenue');
c7_assert(str_contains($sidebar, 'crm/forecast'), 'sidebar forecast');
c7_assert(str_contains($sidebar, 'crm/governance'), 'sidebar governance');
c7_assert(str_contains($sidebar, 'crm/performance'), 'sidebar performance');

$perms = (string) file_get_contents($root . '/config/permissions-system.php');
c7_assert(str_contains($perms, 'crm.governance.manage'), 'permissions-system governance');
c7_assert(str_contains($perms, 'crm.revenue.intel'), 'permissions-system revenue intel');
c7_assert(str_contains($perms, 'crm.forecast.enterprise'), 'permissions-system forecast enterprise');

echo "\nCRM Phase 7 tests: {$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
