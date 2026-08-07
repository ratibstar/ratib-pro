<?php
declare(strict_types=1);

/**
 * CRM Phase 9 — AI-Ready Intelligence + Optimization structure tests (no DB required).
 * Run: php rateb-erp/tests/run-crm-phase9-tests.php
 */

$root = dirname(__DIR__);
require_once $root . '/app/Core/Bootstrap.php';
\Rateb\App\Core\Bootstrap::init($root);

use Rateb\App\Controllers\Company\CrmInsightsController;
use Rateb\App\Controllers\Company\CrmIntelligenceLayerController;
use Rateb\App\Controllers\Company\CrmMergeController;
use Rateb\App\Controllers\Company\CrmPredictiveRulesController;
use Rateb\App\Models\CrmFreshnessSnapshot;
use Rateb\App\Models\CrmIntelligenceInsight;
use Rateb\App\Models\CrmMergeRequest;
use Rateb\App\Models\CrmPredictiveRule;
use Rateb\App\Services\CrmDataFreshnessService;
use Rateb\App\Services\CrmDuplicateMergeService;
use Rateb\App\Services\CrmExecutiveInsightsService;
use Rateb\App\Services\CrmIntelligenceLayerService;
use Rateb\App\Services\CrmPredictiveRulesEngineService;
use Rateb\App\Services\CrmQuotationService;
use Rateb\App\Services\CrmUnifiedActivityIntelligenceService;
use Rateb\App\Services\CrmUnifiedSearchService;

$passed = 0;
$failed = 0;

function c9_assert(bool $cond, string $label): void
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

c9_assert(class_exists(CrmIntelligenceLayerService::class), 'CrmIntelligenceLayerService');
c9_assert(class_exists(CrmPredictiveRulesEngineService::class), 'CrmPredictiveRulesEngineService');
c9_assert(class_exists(CrmUnifiedActivityIntelligenceService::class), 'CrmUnifiedActivityIntelligenceService');
c9_assert(class_exists(CrmExecutiveInsightsService::class), 'CrmExecutiveInsightsService');
c9_assert(class_exists(CrmDuplicateMergeService::class), 'CrmDuplicateMergeService');
c9_assert(class_exists(CrmDataFreshnessService::class), 'CrmDataFreshnessService');
c9_assert(class_exists(CrmIntelligenceLayerController::class), 'CrmIntelligenceLayerController');
c9_assert(class_exists(CrmPredictiveRulesController::class), 'CrmPredictiveRulesController');
c9_assert(class_exists(CrmInsightsController::class), 'CrmInsightsController');
c9_assert(class_exists(CrmMergeController::class), 'CrmMergeController');
c9_assert(class_exists(CrmPredictiveRule::class), 'CrmPredictiveRule');
c9_assert(class_exists(CrmIntelligenceInsight::class), 'CrmIntelligenceInsight');
c9_assert(class_exists(CrmMergeRequest::class), 'CrmMergeRequest');
c9_assert(class_exists(CrmFreshnessSnapshot::class), 'CrmFreshnessSnapshot');

c9_assert(method_exists(CrmIntelligenceLayerService::class, 'analyze'), 'intel analyze');
c9_assert(method_exists(CrmIntelligenceLayerService::class, 'opportunityScoringEvolution'), 'scoring evolution');
c9_assert(method_exists(CrmIntelligenceLayerService::class, 'pipelineAnomalyDetection'), 'anomaly detection');
c9_assert(method_exists(CrmPredictiveRulesEngineService::class, 'evaluate'), 'predictive evaluate');
c9_assert(method_exists(CrmPredictiveRulesEngineService::class, 'saveRule'), 'predictive saveRule');
c9_assert(method_exists(CrmUnifiedActivityIntelligenceService::class, 'activityPatterns'), 'activity patterns');
c9_assert(method_exists(CrmUnifiedActivityIntelligenceService::class, 'repEffectiveness'), 'rep effectiveness');
c9_assert(method_exists(CrmExecutiveInsightsService::class, 'assemble'), 'insights assemble');
c9_assert(method_exists(CrmDuplicateMergeService::class, 'execute'), 'merge execute');
c9_assert(method_exists(CrmDataFreshnessService::class, 'check'), 'freshness check');
c9_assert(method_exists(CrmUnifiedSearchService::class, 'search'), 'search method');

$blocked = false;
try {
    (new CrmQuotationService())->convertToInvoice(1);
} catch (\RuntimeException $e) {
    $blocked = str_contains($e->getMessage(), 'disabled');
}
c9_assert($blocked, 'quote→invoice still disabled');

$migration = $root . '/migrations/238_crm_phase9_ai_ready_optimization.sql';
c9_assert(is_file($migration), 'migration 238 exists');
$sql = (string) file_get_contents($migration);
c9_assert(str_contains($sql, 'rateb_crm_predictive_rules'), 'predictive rules table');
c9_assert(str_contains($sql, 'rateb_crm_intelligence_insights'), 'insights table');
c9_assert(str_contains($sql, 'rateb_crm_merge_requests'), 'merge requests table');
c9_assert(str_contains($sql, 'rateb_crm_freshness_snapshots'), 'freshness snapshots');
c9_assert(str_contains($sql, 'idx_crm_opp_stale_status'), 'opp performance index');
c9_assert(str_contains($sql, 'idx_crm_act_opp_time'), 'activity performance index');
c9_assert(str_contains($sql, 'crm.insights.view'), 'insights perm');
c9_assert(str_contains($sql, 'crm.predictive.manage'), 'predictive perm');
c9_assert(str_contains($sql, 'crm.merge.manage'), 'merge perm');
c9_assert(str_contains($sql, 'crm.intelligence.advanced'), 'advanced intel perm');
c9_assert(str_contains($sql, 'high_probability'), 'default high_probability rule');
c9_assert(str_contains($sql, 'churn_risk'), 'default churn rule');
c9_assert(!preg_match('/\bDROP\s+TABLE\b|\bTRUNCATE\b/i', $sql), 'no DROP/TRUNCATE');
c9_assert(!str_contains($sql, 'CREATE TABLE IF NOT EXISTS rateb_customers'), 'no duplicate customers table');
c9_assert(!str_contains($sql, 'AccountingService'), 'migration no AccountingService');
c9_assert(!preg_match('/openai|anthropic|tensorflow|sklearn|huggingface/i', $sql), 'no external AI in migration');

$ops = (string) file_get_contents($root . '/routes/modules/ops.php');
c9_assert(str_contains($ops, 'CrmIntelligenceLayerController'), 'intel layer import');
c9_assert(str_contains($ops, 'CrmPredictiveRulesController'), 'predictive import');
c9_assert(str_contains($ops, 'CrmInsightsController'), 'insights import');
c9_assert(str_contains($ops, 'CrmMergeController'), 'merge import');
c9_assert(str_contains($ops, 'intelligence-layer'), 'intel layer route');
c9_assert(str_contains($ops, "crm/predictive"), 'predictive route');
c9_assert(str_contains($ops, "crm/insights"), 'insights route');
c9_assert(str_contains($ops, "crm/merge"), 'merge route');

$intel = (string) file_get_contents($root . '/app/services/CrmIntelligenceLayerService.php');
c9_assert(str_contains($intel, 'crm.intelligence.calculate'), 'intel audit');
c9_assert(!preg_match('/curl_exec|file_get_contents\s*\(\s*[\'"]https?:|openai|anthropic/i', $intel), 'no external AI calls');

$pred = (string) file_get_contents($root . '/app/services/CrmPredictiveRulesEngineService.php');
c9_assert(str_contains($pred, 'crm.predictive.rule_execute'), 'rule execute audit');
c9_assert(str_contains($pred, 'follow_up_priority'), 'follow-up priority rule');

$merge = (string) file_get_contents($root . '/app/services/CrmDuplicateMergeService.php');
c9_assert(str_contains($merge, 'crm.merge.execute'), 'merge audit');
c9_assert(!str_contains($merge, 'AccountingService'), 'merge no Accounting');

$search = (string) file_get_contents($root . '/app/services/CrmUnifiedSearchService.php');
c9_assert(str_contains($search, 'relevance'), 'search relevance');
c9_assert(str_contains($search, 'ranked'), 'search ranked');
c9_assert(str_contains($search, 'crm.search.usage'), 'search audit');

c9_assert(is_file($root . '/views/company/crm/intelligence-layer/index.php'), 'intel layer view');
c9_assert(is_file($root . '/views/company/crm/predictive/index.php'), 'predictive view');
c9_assert(is_file($root . '/views/company/crm/insights/index.php'), 'insights view');
c9_assert(is_file($root . '/views/company/crm/merge/index.php'), 'merge view');

$sidebar = (string) file_get_contents($root . '/views/partials/sidebar-ops-nav.php');
c9_assert(str_contains($sidebar, 'crm/insights'), 'sidebar insights');
c9_assert(str_contains($sidebar, 'crm/intelligence-layer'), 'sidebar intel');
c9_assert(str_contains($sidebar, 'crm/predictive'), 'sidebar predictive');
c9_assert(str_contains($sidebar, 'crm/merge'), 'sidebar merge');

$perms = (string) file_get_contents($root . '/config/permissions-system.php');
c9_assert(str_contains($perms, 'crm.insights.view'), 'permissions-system insights');
c9_assert(str_contains($perms, 'crm.intelligence.advanced'), 'permissions-system advanced');

echo "\nCRM Phase 9 tests: {$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
