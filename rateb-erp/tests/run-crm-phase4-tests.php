<?php
declare(strict_types=1);

/**
 * CRM Phase 4 — revenue ops structure tests (no DB required).
 * Run: php rateb-erp/tests/run-crm-phase4-tests.php
 */

$root = dirname(__DIR__);
require_once $root . '/app/Core/Bootstrap.php';
\Rateb\App\Core\Bootstrap::init($root);

use Rateb\App\Controllers\Company\CrmAdminController;
use Rateb\App\Controllers\Company\CrmQuotationsController;
use Rateb\App\Controllers\Company\CrmReportsController;
use Rateb\App\Models\CrmActivityType;
use Rateb\App\Models\CrmAutomationRule;
use Rateb\App\Models\CrmForecastSnapshot;
use Rateb\App\Models\CrmRevenueEvent;
use Rateb\App\Services\CrmAdminConfigService;
use Rateb\App\Services\CrmAutomationService;
use Rateb\App\Services\CrmCustomer360Service;
use Rateb\App\Services\CrmForecastEngineService;
use Rateb\App\Services\CrmQuotationService;
use Rateb\App\Services\CrmRevenueTrackingService;

$passed = 0;
$failed = 0;

function c4_assert(bool $cond, string $label): void
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

c4_assert(class_exists(CrmRevenueTrackingService::class), 'CrmRevenueTrackingService');
c4_assert(class_exists(CrmForecastEngineService::class), 'CrmForecastEngineService');
c4_assert(class_exists(CrmAdminConfigService::class), 'CrmAdminConfigService');
c4_assert(class_exists(CrmAdminController::class), 'CrmAdminController');
c4_assert(class_exists(CrmRevenueEvent::class), 'CrmRevenueEvent');
c4_assert(class_exists(CrmForecastSnapshot::class), 'CrmForecastSnapshot');
c4_assert(class_exists(CrmActivityType::class), 'CrmActivityType');
c4_assert(class_exists(CrmAutomationRule::class), 'CrmAutomationRule');

c4_assert(method_exists(CrmQuotationService::class, 'duplicate'), 'quote duplicate');
c4_assert(method_exists(CrmQuotationService::class, 'createVersion'), 'quote version');
c4_assert(method_exists(CrmQuotationService::class, 'submitForApproval'), 'quote submit approval');
c4_assert(method_exists(CrmQuotationService::class, 'decideApproval'), 'quote decide approval');
c4_assert(method_exists(CrmQuotationService::class, 'expireOverdue'), 'quote expire');
c4_assert(method_exists(CrmQuotationService::class, 'performanceMetrics'), 'quote metrics');
c4_assert(method_exists(CrmQuotationService::class, 'convertToInvoice'), 'invoice still blocked method');

$blocked = false;
try {
    (new CrmQuotationService())->convertToInvoice(1);
} catch (\RuntimeException $e) {
    $blocked = str_contains($e->getMessage(), 'disabled');
}
c4_assert($blocked, 'quote→invoice still disabled');

c4_assert(method_exists(CrmForecastEngineService::class, 'compute'), 'forecast compute');
c4_assert(method_exists(CrmForecastEngineService::class, 'snapshot'), 'forecast snapshot');
c4_assert(method_exists(CrmForecastEngineService::class, 'accuracyReport'), 'forecast accuracy');
c4_assert(method_exists(CrmForecastEngineService::class, 'winProbabilityTracking'), 'win probability');
c4_assert(method_exists(CrmAutomationService::class, 'processOpportunityInactivity'), 'inactivity alerts');
c4_assert(method_exists(CrmAutomationService::class, 'runAll'), 'automation runAll');
c4_assert(method_exists(CrmReportsController::class, 'snapshot'), 'reports snapshot action');
c4_assert(method_exists(CrmQuotationsController::class, 'duplicate'), 'controller duplicate');
c4_assert(method_exists(CrmQuotationsController::class, 'decideApproval'), 'controller approval');

$migration = $root . '/migrations/233_crm_phase4_revenue_ops.sql';
c4_assert(is_file($migration), 'migration 233 exists');
$sql = (string) file_get_contents($migration);
c4_assert(str_contains($sql, 'CREATE TABLE IF NOT EXISTS rateb_crm_revenue_events'), 'revenue events table');
c4_assert(str_contains($sql, 'CREATE TABLE IF NOT EXISTS rateb_crm_forecast_snapshots'), 'forecast snapshots');
c4_assert(str_contains($sql, 'CREATE TABLE IF NOT EXISTS rateb_crm_activity_types'), 'activity types');
c4_assert(str_contains($sql, 'CREATE TABLE IF NOT EXISTS rateb_crm_automation_rules'), 'automation rules');
c4_assert(str_contains($sql, 'version_no'), 'quote versioning columns');
c4_assert(str_contains($sql, 'approval_status'), 'quote approval columns');
c4_assert(str_contains($sql, 'crm.quote.approve'), 'quote.approve perm');
c4_assert(str_contains($sql, 'crm.config.manage'), 'config.manage perm');
c4_assert(!preg_match('/\bDROP\s+TABLE\b|\bTRUNCATE\b/i', $sql), 'no DROP/TRUNCATE');

$ops = (string) file_get_contents($root . '/routes/modules/ops.php');
c4_assert(str_contains($ops, 'CrmAdminController'), 'admin controller import');
c4_assert(str_contains($ops, "crm/admin"), 'admin routes');
c4_assert(str_contains($ops, 'submit-approval'), 'approval routes');
c4_assert(str_contains($ops, 'decide-approval'), 'decide approval route');
c4_assert(str_contains($ops, '/duplicate'), 'duplicate route');
c4_assert(str_contains($ops, '/version'), 'version route');
c4_assert(str_contains($ops, 'reports/snapshot'), 'forecast snapshot route');

$c360 = (string) file_get_contents($root . '/app/services/CrmCustomer360Service.php');
c4_assert(str_contains($c360, 'order_links'), '360 order links');
c4_assert(str_contains($c360, 'revenue_events'), '360 revenue events');
c4_assert(!preg_match('/new\s+AccountingService|AccountingService::/', $c360), '360 no AccountingService');

$auto = (string) file_get_contents($root . '/app/services/CrmAutomationService.php');
c4_assert(str_contains($auto, 'NotificationService'), 'uses NotificationService');
c4_assert(str_contains($auto, 'opportunity_inactivity'), 'inactivity event');

$wf = (string) file_get_contents($root . '/app/services/CrmQuotationWorkflowService.php');
c4_assert(str_contains($wf, 'CrmRevenueTrackingService'), 'accepted → revenue tracking');
c4_assert(str_contains($wf, 'quotation_approval_pending'), 'blocks send when pending');

$conv = (string) file_get_contents($root . '/app/services/CrmConversionService.php');
c4_assert(str_contains($conv, 'CrmRevenueTrackingService'), 'customer convert → revenue');
c4_assert(!preg_match('/new\s+AccountingService/', $conv), 'conversion no AccountingService');

c4_assert(is_file($root . '/views/company/crm/admin/index.php'), 'admin view');
$sidebar = (string) file_get_contents($root . '/views/partials/sidebar-ops-nav.php');
c4_assert(str_contains($sidebar, 'crm/admin'), 'sidebar admin');

$perms = (string) file_get_contents($root . '/config/permissions-system.php');
c4_assert(str_contains($perms, 'crm.config.manage'), 'permissions-system config');
c4_assert(str_contains($perms, 'crm.forecast.manage'), 'permissions-system forecast');

echo "\nCRM Phase 4 tests: {$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
