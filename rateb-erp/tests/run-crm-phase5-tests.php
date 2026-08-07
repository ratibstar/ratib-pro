<?php
declare(strict_types=1);

/**
 * CRM Phase 5 — Sales Ops + Customer Lifecycle structure tests (no DB required).
 * Run: php rateb-erp/tests/run-crm-phase5-tests.php
 */

$root = dirname(__DIR__);
require_once $root . '/app/Core/Bootstrap.php';
\Rateb\App\Core\Bootstrap::init($root);

use Rateb\App\Controllers\Company\CrmCustomerProfileController;
use Rateb\App\Controllers\Company\CrmTeamsController;
use Rateb\App\Models\CrmLifecycleEvent;
use Rateb\App\Models\CrmOwnershipRule;
use Rateb\App\Models\CrmSalesTeam;
use Rateb\App\Models\CrmSalesTeamMember;
use Rateb\App\Models\CrmStageTransition;
use Rateb\App\Models\CrmTerritory;
use Rateb\App\Services\CrmAnalyticsService;
use Rateb\App\Services\CrmAutomationService;
use Rateb\App\Services\CrmLifecycleService;
use Rateb\App\Services\CrmPipelineHealthService;
use Rateb\App\Services\CrmQuotationService;
use Rateb\App\Services\CrmRetentionService;
use Rateb\App\Services\CrmSalesTeamService;
use Rateb\App\Services\PipelineService;

$passed = 0;
$failed = 0;

function c5_assert(bool $cond, string $label): void
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

c5_assert(class_exists(CrmLifecycleService::class), 'CrmLifecycleService');
c5_assert(class_exists(CrmSalesTeamService::class), 'CrmSalesTeamService');
c5_assert(class_exists(CrmPipelineHealthService::class), 'CrmPipelineHealthService');
c5_assert(class_exists(CrmRetentionService::class), 'CrmRetentionService');
c5_assert(class_exists(CrmAnalyticsService::class), 'CrmAnalyticsService');
c5_assert(class_exists(CrmTeamsController::class), 'CrmTeamsController');
c5_assert(class_exists(CrmSalesTeam::class), 'CrmSalesTeam');
c5_assert(class_exists(CrmSalesTeamMember::class), 'CrmSalesTeamMember');
c5_assert(class_exists(CrmTerritory::class), 'CrmTerritory');
c5_assert(class_exists(CrmOwnershipRule::class), 'CrmOwnershipRule');
c5_assert(class_exists(CrmLifecycleEvent::class), 'CrmLifecycleEvent');
c5_assert(class_exists(CrmStageTransition::class), 'CrmStageTransition');

c5_assert(method_exists(CrmLifecycleService::class, 'transition'), 'lifecycle transition');
c5_assert(method_exists(CrmLifecycleService::class, 'assignOwnership'), 'lifecycle ownership');
c5_assert(method_exists(CrmLifecycleService::class, 'history'), 'lifecycle history');
c5_assert(method_exists(CrmSalesTeamService::class, 'createTeam'), 'teams create');
c5_assert(method_exists(CrmSalesTeamService::class, 'addMember'), 'teams members');
c5_assert(method_exists(CrmSalesTeamService::class, 'createTerritory'), 'territories create');
c5_assert(method_exists(CrmSalesTeamService::class, 'saveOwnershipRule'), 'ownership rules');
c5_assert(method_exists(CrmPipelineHealthService::class, 'stageDurationTracking'), 'stage duration');
c5_assert(method_exists(CrmPipelineHealthService::class, 'bottleneckAnalysis'), 'bottleneck analysis');
c5_assert(method_exists(CrmPipelineHealthService::class, 'healthScore'), 'pipeline health');
c5_assert(method_exists(CrmRetentionService::class, 'refreshCustomer'), 'retention refresh');
c5_assert(method_exists(CrmRetentionService::class, 'setRenewal'), 'renewal tracking');
c5_assert(method_exists(CrmRetentionService::class, 'atRiskCustomers'), 'at-risk indicators');
c5_assert(method_exists(CrmAnalyticsService::class, 'revenueForecastAccuracy'), 'forecast accuracy analytics');
c5_assert(method_exists(CrmAnalyticsService::class, 'salesCycleDuration'), 'sales cycle');
c5_assert(method_exists(CrmAnalyticsService::class, 'customerAcquisitionCostPlaceholders'), 'CAC placeholders');
c5_assert(method_exists(CrmAnalyticsService::class, 'repPerformance'), 'rep performance');
c5_assert(method_exists(CrmAnalyticsService::class, 'pipelineVelocity'), 'pipeline velocity');
c5_assert(method_exists(CrmAutomationService::class, 'processNoActivityReminders'), 'no activity automation');
c5_assert(method_exists(CrmAutomationService::class, 'processRenewalReminders'), 'renewal automation');
c5_assert(method_exists(CrmAutomationService::class, 'processStaleOpportunities'), 'stale opportunity automation');
c5_assert(method_exists(CrmAutomationService::class, 'processCustomerFollowUps'), 'customer follow-up automation');
c5_assert(method_exists(CrmCustomerProfileController::class, 'transitionLifecycle'), 'controller lifecycle');
c5_assert(method_exists(CrmTeamsController::class, 'storeTeam'), 'controller store team');
c5_assert(method_exists(PipelineService::class, 'upsertStage'), 'pipeline upsert stage');

$blocked = false;
try {
    (new CrmQuotationService())->convertToInvoice(1);
} catch (\RuntimeException $e) {
    $blocked = str_contains($e->getMessage(), 'disabled');
}
c5_assert($blocked, 'quote→invoice still disabled');

$stages = CrmLifecycleService::STAGES;
c5_assert(in_array('prospect', $stages, true) && in_array('renewal', $stages, true), 'lifecycle stages set');

$migration = $root . '/migrations/234_crm_phase5_sales_ops_lifecycle.sql';
c5_assert(is_file($migration), 'migration 234 exists');
$sql = (string) file_get_contents($migration);
c5_assert(str_contains($sql, 'CREATE TABLE IF NOT EXISTS rateb_crm_sales_teams'), 'sales teams table');
c5_assert(str_contains($sql, 'CREATE TABLE IF NOT EXISTS rateb_crm_sales_team_members'), 'team members table');
c5_assert(str_contains($sql, 'CREATE TABLE IF NOT EXISTS rateb_crm_territories'), 'territories table');
c5_assert(str_contains($sql, 'CREATE TABLE IF NOT EXISTS rateb_crm_ownership_rules'), 'ownership rules table');
c5_assert(str_contains($sql, 'CREATE TABLE IF NOT EXISTS rateb_crm_lifecycle_events'), 'lifecycle events table');
c5_assert(str_contains($sql, 'CREATE TABLE IF NOT EXISTS rateb_crm_stage_transitions'), 'stage transitions table');
c5_assert(str_contains($sql, 'crm_lifecycle_stage'), 'customer lifecycle columns');
c5_assert(str_contains($sql, 'expected_duration_days'), 'stage duration column');
c5_assert(str_contains($sql, 'stage_entered_at'), 'opportunity stage_entered_at');
c5_assert(str_contains($sql, 'crm.teams.manage'), 'teams.manage perm');
c5_assert(str_contains($sql, 'crm.lifecycle.manage'), 'lifecycle.manage perm');
c5_assert(str_contains($sql, 'crm.analytics.view'), 'analytics.view perm');
c5_assert(str_contains($sql, 'no_activity'), 'no_activity rule seed');
c5_assert(str_contains($sql, 'renewal_reminder'), 'renewal_reminder rule seed');
c5_assert(str_contains($sql, 'stale_opportunity'), 'stale_opportunity rule seed');
c5_assert(!preg_match('/\bDROP\s+TABLE\b|\bTRUNCATE\b/i', $sql), 'no DROP/TRUNCATE');
c5_assert(!str_contains($sql, 'CREATE TABLE IF NOT EXISTS rateb_customers'), 'no duplicate customers table');

$ops = (string) file_get_contents($root . '/routes/modules/ops.php');
c5_assert(str_contains($ops, 'CrmTeamsController'), 'teams controller import');
c5_assert(str_contains($ops, "crm/teams"), 'teams routes');
c5_assert(str_contains($ops, 'ownership-rules'), 'ownership rules route');
c5_assert(str_contains($ops, '/lifecycle'), 'lifecycle route');
c5_assert(str_contains($ops, '/renewal'), 'renewal route');

$domain = (string) file_get_contents($root . '/app/services/CrmDomainServices.php');
c5_assert(str_contains($domain, 'CrmPipelineHealthService'), 'moveStage records transitions');
c5_assert(str_contains($domain, 'expected_duration_days'), 'upsertStage duration');
c5_assert(str_contains($domain, 'CrmEntityStatusHistory'), 'stage audit history');

$auto = (string) file_get_contents($root . '/app/services/CrmAutomationService.php');
c5_assert(str_contains($auto, 'NotificationService'), 'uses NotificationService');
c5_assert(str_contains($auto, 'processStaleOpportunities'), 'stale in automation');
c5_assert(str_contains($auto, 'processRenewalReminders'), 'renewal in automation');

$c360 = (string) file_get_contents($root . '/app/services/CrmCustomer360Service.php');
c5_assert(str_contains($c360, 'lifecycle_history'), '360 lifecycle history');
c5_assert(str_contains($c360, 'CrmRetentionService'), '360 retention');
c5_assert(!preg_match('/new\s+AccountingService|AccountingService::/', $c360), '360 no AccountingService');

$conv = (string) file_get_contents($root . '/app/services/CrmConversionService.php');
c5_assert(str_contains($conv, 'CrmLifecycleService'), 'conversion lifecycle');
c5_assert(!preg_match('/new\s+AccountingService/', $conv), 'conversion no AccountingService');

$analytics = (string) file_get_contents($root . '/app/services/CrmAnalyticsService.php');
c5_assert(str_contains($analytics, 'cac_placeholder'), 'CAC placeholder present');
c5_assert(!preg_match('/AccountingService/', $analytics), 'analytics no AccountingService');

c5_assert(is_file($root . '/views/company/crm/teams/index.php'), 'teams view');
$sidebar = (string) file_get_contents($root . '/views/partials/sidebar-ops-nav.php');
c5_assert(str_contains($sidebar, 'crm/teams'), 'sidebar teams');

$perms = (string) file_get_contents($root . '/config/permissions-system.php');
c5_assert(str_contains($perms, 'crm.teams.manage'), 'permissions-system teams');
c5_assert(str_contains($perms, 'crm.lifecycle.manage'), 'permissions-system lifecycle');
c5_assert(str_contains($perms, 'crm.analytics.view'), 'permissions-system analytics');

$quoteSvc = (string) file_get_contents($root . '/app/services/CrmQuotationService.php');
c5_assert(str_contains($quoteSvc, 'disabled') || str_contains($quoteSvc, 'quotation_to_invoice'), 'invoice conversion still blocked in service');

echo "\nCRM Phase 5 tests: {$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
