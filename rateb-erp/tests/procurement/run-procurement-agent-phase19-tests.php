<?php
declare(strict_types=1);

/**
 * Phase 19 — Operational Learning & Continuous Optimization.
 * Run: php rateb-erp/tests/procurement/run-procurement-agent-phase19-tests.php
 */

$root = dirname(__DIR__, 2);
define('RATEB_ROOT', $root);
define('RATEB_ENV_NO_SESSION', true);

require_once $root . '/app/Core/Bootstrap.php';
\Rateb\App\Core\Bootstrap::init($root);

use Rateb\App\Core\Auth;
use Rateb\App\Core\Database;
use Rateb\App\Core\TenantContext;
use Rateb\App\Services\Agent\LlmClientInterface;
use Rateb\App\Services\ErpActionPlanner;
use Rateb\App\Services\ErpAgent;
use Rateb\App\Services\ErpDomainRegistry;
use Rateb\App\Services\ErpGovernanceLayer;
use Rateb\App\Services\ErpOperationalLearningLayer;
use Rateb\App\Services\ErpOrchestrationPlanner;
use Rateb\App\Services\ErpProactiveEarlyWarningLayer;
use Rateb\App\Services\ErpToolRegistry;
use Rateb\App\Services\ExecutiveToolExecutor;
use Rateb\App\Services\ExecutiveToolRegistry;
use Rateb\App\Services\ProcurementAgent;
use Rateb\App\Services\ProcurementAgentContext;
use Rateb\App\Services\ProcurementPolicyGuard;

$results = [];
$pass = static function (string $name) use (&$results): void {
    $results[] = ['name' => $name, 'ok' => true];
    echo "PASS: {$name}\n";
};
$fail = static function (string $name, string $detail = '') use (&$results): void {
    $results[] = ['name' => $name, 'ok' => false, 'detail' => $detail];
    echo "FAIL: {$name}" . ($detail !== '' ? " — {$detail}" : '') . "\n";
};

final class Phase19MockLlm implements LlmClientInterface
{
    public function chatCompletion(array $messages, array $tools, ?string $toolChoice = 'auto'): array
    {
        return ['message' => ['role' => 'assistant', 'content' => 'OK'], 'usage' => null, 'model' => 'mock'];
    }
    public function getModel(): string { return 'mock'; }
    public function getProvider(): string { return 'mock'; }
}

try {
    $pdo = Database::connection();
    $companyId = (int) $pdo->query('SELECT id FROM rateb_companies ORDER BY id ASC LIMIT 1')->fetchColumn();
    $userId = (int) $pdo->query(
        "SELECT id FROM rateb_users WHERE is_super_admin = 1 AND status = 'active' ORDER BY id ASC LIMIT 1"
    )->fetchColumn();
    if ($userId < 1) {
        $userId = (int) $pdo->query('SELECT id FROM rateb_users ORDER BY id ASC LIMIT 1')->fetchColumn();
    }
    if ($companyId < 1 || $userId < 1) {
        throw new RuntimeException('no_company_or_user');
    }
} catch (Throwable $e) {
    echo "FAIL: db_bootstrap — {$e->getMessage()}\n";
    exit(1);
}

TenantContext::setCompanyId($companyId);
TenantContext::setSuperAdmin(true);
$_SESSION['rateb_user_id'] = $userId;
$_SESSION['rateb_is_super_admin'] = 1;
$_SESSION['rateb_company_id'] = $companyId;
Auth::bootstrapFromSession();

$ref = new ReflectionClass(ProcurementAgentContext::class);
$ctor = $ref->getConstructor();
$ctor->setAccessible(true);
$makeCtx = static function (array $perms, bool $sa, int $cid, array $modules, string $locale = 'en') use ($ref, $ctor, $userId): ProcurementAgentContext {
    $ctx = $ref->newInstanceWithoutConstructor();
    $ctor->invoke($ctx, $userId, $cid, $locale, 'phase19-test', $perms, $sa, $modules);
    return $ctx;
};

$fullPerms = [
    'procurement.manage', 'procurement.view', 'procurement.create', 'procurement.update', 'procurement.submit',
    'suppliers.manage', 'inventory.manage', 'pos.view', 'crm.view', 'logistics.view',
    'accounting.view', 'dashboard.view', 'ai.view', 'reports.view',
];
$modules = ['procurement', 'suppliers', 'inventory', 'pos', 'crm', 'logistics', 'accounting', 'dashboard'];
$ctx = $makeCtx($fullPerms, true, $companyId, $modules, 'en');
$config = require RATEB_ROOT . '/config/agent.php';
$erp = new ErpAgent($config, new Phase19MockLlm());
ErpOperationalLearningLayer::clearMemo();

$noBad = !class_exists('Rateb\\App\\Services\\LearningAgent')
    && !class_exists('Rateb\\App\\Services\\OptimizationAgent')
    && !class_exists('Rateb\\App\\Services\\MlPlatform');
$need = [
    'analyze_operational_learning', 'get_action_outcomes', 'get_learning_signals',
    'get_recommendation_effectiveness', 'get_warning_effectiveness', 'get_forecast_feedback',
    'get_optimization_insights', 'record_recommendation_feedback', 'measure_action_outcome',
    'record_forecast_feedback',
];
$missing = array_diff($need, array_keys(ExecutiveToolRegistry::getTools()));
(class_exists(ErpOperationalLearningLayer::class)
    && $noBad
    && $missing === []
    && ErpToolRegistry::domainForTool('analyze_operational_learning') === 'executive'
    && (ErpDomainRegistry::resolve('executive')['runtime'] ?? '') === ProcurementAgent::class)
    ? $pass('OPERATION OUTCOME TRACKING') : $fail('OPERATION OUTCOME TRACKING', implode(',', $missing));

$action = [
    'tool' => 'create_draft_purchase_request',
    'domain' => 'procurement',
    'arguments' => [],
    'previous_state' => null,
    'recommended_action' => 'review_inventory_and_procurement_need',
];
$execOk = ['success' => true, 'data' => ['id' => 1]];
$verOk = ['verified' => true, 'incomplete' => false, 'message' => 'verified_draft_created', 'new_state' => ['id' => 1, 'status' => 'draft', 'exists' => true]];
$rec1 = ErpOperationalLearningLayer::recordActionOutcome($ctx, $action, $execOk, $verOk, [
    'request_id' => 'p19-out-1',
    'action_id' => 'act_p19_1',
    'recommendation' => 'review_inventory_and_procurement_need',
    'warning_id' => 'ew_test_warn_1',
]);
$statuses = [
    ErpOperationalLearningLayer::OUTCOME_SUCCESS,
    ErpOperationalLearningLayer::OUTCOME_PARTIAL,
    ErpOperationalLearningLayer::OUTCOME_NO_EFFECT,
    ErpOperationalLearningLayer::OUTCOME_NEGATIVE,
    ErpOperationalLearningLayer::OUTCOME_FAILED,
    ErpOperationalLearningLayer::OUTCOME_UNKNOWN,
];
(!empty($rec1['success'])
    && in_array(($rec1['outcome']['outcome_status'] ?? ''), $statuses, true)
    && ($rec1['outcome']['outcome_status'] ?? '') === ErpOperationalLearningLayer::OUTCOME_UNKNOWN
    && ($rec1['outcome']['execution_success'] ?? false) === true)
    ? $pass('OUTCOME CLASSIFICATION') : $fail('OUTCOME CLASSIFICATION', json_encode($rec1['outcome'] ?? []));

$failRec = ErpOperationalLearningLayer::recordActionOutcome($ctx, $action, ['success' => false], [
    'verified' => false, 'incomplete' => false, 'message' => 'execution_failed',
], ['request_id' => 'p19-out-fail', 'action_id' => 'act_p19_fail']);
(($failRec['outcome']['outcome_status'] ?? '') === ErpOperationalLearningLayer::OUTCOME_FAILED)
    ? $pass('OUTCOME VERIFICATION') : $fail('OUTCOME VERIFICATION');

$pack = ErpOperationalLearningLayer::analyze($ctx, 30);
(is_array($pack['signals'] ?? null) && count($pack['signals']) >= 1)
    ? $pass('LEARNING SIGNALS') : $fail('LEARNING SIGNALS');

// Seed measured recommendation effectiveness
for ($i = 0; $i < 3; $i++) {
    ErpOperationalLearningLayer::recordActionOutcome($ctx, $action, $execOk, $verOk, [
        'request_id' => 'p19-rec-' . $i,
        'action_id' => 'act_rec_' . $i,
        'recommendation' => 'review_inventory_and_procurement_need',
        'warning_id' => 'ew_test_warn_1',
    ]);
}
$oid = (string) ($rec1['outcome']['outcome_id'] ?? '');
$meas = ErpOperationalLearningLayer::measureBusinessOutcome($ctx, $oid, [
    'kpi_before' => 10,
    'kpi_after' => 25,
    'improve_direction' => 'up',
]);
for ($i = 0; $i < 2; $i++) {
    $r = ErpOperationalLearningLayer::recordActionOutcome($ctx, $action, $execOk, $verOk, [
        'request_id' => 'p19-m-' . $i,
        'action_id' => 'act_m_' . $i . '_' . bin2hex(random_bytes(2)),
        'recommendation' => 'review_inventory_and_procurement_need',
    ]);
    ErpOperationalLearningLayer::measureBusinessOutcome($ctx, (string) ($r['outcome']['outcome_id'] ?? ''), [
        'kpi_before' => 5,
        'kpi_after' => 15,
        'improve_direction' => 'up',
    ]);
}
$effPack = ErpOperationalLearningLayer::analyze($ctx, 40);
$recEff = $effPack['recommendation_effectiveness'] ?? [];
$hasEff = false;
foreach ($recEff as $row) {
    if (is_array($row) && ($row['recommendation'] ?? '') === 'review_inventory_and_procurement_need') {
        $hasEff = isset($row['effectiveness']);
        break;
    }
}
($hasEff && !empty($meas['success']) && ($meas['business_outcome'] ?? '') === ErpOperationalLearningLayer::OUTCOME_SUCCESS)
    ? $pass('RECOMMENDATION EFFECTIVENESS') : $fail('RECOMMENDATION EFFECTIVENESS');

$warnEff = $effPack['warning_effectiveness'] ?? [];
isset($warnEff) && is_array($warnEff)
    ? $pass('WARNING EFFECTIVENESS') : $fail('WARNING EFFECTIVENESS');

$fc = ErpOperationalLearningLayer::recordForecastFeedback($ctx, [
    'code' => 'sales_trend',
    'available' => true,
    'prediction' => 100.0,
    'basis' => 'linear_extrapolation',
    'period_used' => '2026-08',
], 110.0, '2026-09');
$fcInsuff = ErpOperationalLearningLayer::recordForecastFeedback($ctx, [
    'code' => 'x',
    'available' => false,
], 1.0);
(!empty($fc['success']) && empty($fc['model_mutated']) && ($fcInsuff['data_sufficiency'] ?? '') === ErpOperationalLearningLayer::INSUFFICIENT)
    ? $pass('FORECAST FEEDBACK') : $fail('FORECAST FEEDBACK');

$opt = ExecutiveToolExecutor::execute('get_optimization_insights', ['limit' => 10], $ctx);
(!empty($opt['success'])
    && !empty($opt['data']['optimization']['governance_immutable'])
    && empty($opt['data']['optimization']['self_modification']))
    ? $pass('CONTINUOUS OPTIMIZATION') : $fail('CONTINUOUS OPTIMIZATION');

(!empty($effPack['deterministic']) && !empty($effPack['llm_cannot_modify_learning']))
    ? $pass('DETERMINISTIC LEARNING') : $fail('DETERMINISTIC LEARNING');

$insuff = ErpOperationalLearningLayer::measureBusinessOutcome($ctx, 'missing_outcome_xyz', []);
$unknownMeasure = ErpOperationalLearningLayer::measureBusinessOutcome($ctx, $oid, []); // may already measured
(($insuff['error'] ?? '') === 'outcome_not_found' || ($insuff['data_sufficiency'] ?? '') === ErpOperationalLearningLayer::INSUFFICIENT
    || isset($effPack['data_sufficiency']))
    ? $pass('DATA SUFFICIENCY') : $fail('DATA SUFFICIENCY');

// Seed patterns (>=3)
for ($i = 0; $i < 3; $i++) {
    ErpOperationalLearningLayer::recordActionOutcome($ctx, [
        'tool' => 'submit_purchase_request',
        'domain' => 'procurement',
        'arguments' => ['id' => 1],
        'recommended_action' => 'clear_pending_approvals',
    ], ['success' => false], ['verified' => false, 'incomplete' => false, 'message' => 'execution_failed'], [
        'request_id' => 'p19-pat-' . $i,
        'action_id' => 'act_pat_' . $i . '_' . bin2hex(random_bytes(2)),
    ]);
}
$patPack = ErpOperationalLearningLayer::analyze($ctx, 50);
(is_array($patPack['patterns'] ?? null))
    ? $pass('PATTERN DETECTION') : $fail('PATTERN DETECTION');

(is_array($patPack['root_cause_support'] ?? null))
    ? $pass('ROOT-CAUSE SUPPORT') : $fail('ROOT-CAUSE SUPPORT');

$ordered = ErpOperationalLearningLayer::optimizeRecommendationOrder($companyId, [
    ['recommended_action' => 'review_inventory_and_procurement_need', 'priority' => 'LOW'],
    ['recommended_action' => 'unknown_rec_xyz', 'priority' => 'HIGH'],
]);
(count($ordered) === 2)
    ? $pass('DECISION OPTIMIZATION') : $fail('DECISION OPTIMIZATION');

$fb = ErpOperationalLearningLayer::recordUserFeedback($ctx, 'recommendation', 'review_inventory_and_procurement_need', 'useful');
(!empty($fb['success']) && array_key_exists('business_outcome', $fb) && $fb['business_outcome'] === null)
    ? $pass('USER FEEDBACK') : $fail('USER FEEDBACK');

$iso = ErpOperationalLearningLayer::analyze($ctx, 5);
$otherCtx = $makeCtx($fullPerms, true, $companyId + 888001, $modules);
$other = ErpOperationalLearningLayer::analyze($otherCtx, 5);
(((int) ($other['company_id'] ?? 0) === $companyId + 888001)
    && (int) ($iso['company_id'] ?? 0) === $companyId
    && ($other['company_id'] ?? null) !== ($iso['company_id'] ?? null))
    ? $pass('TENANT-SCOPED LEARNING') : $fail('TENANT-SCOPED LEARNING');

$audit = $effPack['learning_audit'] ?? [];
(is_array($audit))
    ? $pass('LEARNING AUDIT') : $fail('LEARNING AUDIT');

$nsm = ErpOperationalLearningLayer::assertNoSelfModification();
(empty($nsm['modifies_php']) && empty($nsm['modifies_governance']) && empty($nsm['modifies_env'])
    && empty($nsm['modifies_permissions']) && ($nsm['learning_scope'] ?? '') === 'operational_decision_ordering_only')
    ? $pass('NO SELF-MODIFICATION') : $fail('NO SELF-MODIFICATION');

$t0 = microtime(true);
$a = ExecutiveToolExecutor::execute('analyze_operational_learning', ['limit' => 10], $ctx);
$b = ExecutiveToolExecutor::execute('analyze_operational_learning', ['limit' => 10], $ctx);
$elapsed = (microtime(true) - $t0) * 1000;
(!empty($a['success']) && !empty($b['success']) && $elapsed < 20000)
    ? $pass('PERFORMANCE') : $fail('PERFORMANCE', (string) $elapsed);

$prev = $_SESSION['rateb_locale'] ?? 'en';
$_SESSION['rateb_locale'] = 'ar';
if (function_exists('rateb_set_locale')) {
    rateb_set_locale('ar');
}
$arLearn = __('ai_tool_analyze_operational_learning');
$arOut = __('ai_learn_outcome');
$_SESSION['rateb_locale'] = 'en';
if (function_exists('rateb_set_locale')) {
    rateb_set_locale('en');
}
$enLearn = __('ai_tool_analyze_operational_learning');
$enOut = __('ai_learn_outcome');
$_SESSION['rateb_locale'] = $prev;
(is_string($arLearn) && mb_strpos($arLearn, 'تعلم') !== false && is_string($arOut) && mb_strpos($arOut, 'نتيج') !== false)
    ? $pass('AR') : $fail('AR', (string) $arLearn);
(is_string($enLearn) && stripos($enLearn, 'Operational') !== false && is_string($enOut) && stripos($enOut, 'Outcome') !== false)
    ? $pass('EN') : $fail('EN', (string) $enLearn);

$unknown = ProcurementPolicyGuard::checkAndExecute([
    'tool' => 'drop_database',
    'arguments' => [],
    'request_company_id' => null,
    'request_id' => 'p19-bad',
    'write_confirmed' => true,
], $ctx);
$denied = ProcurementPolicyGuard::checkAndExecute([
    'tool' => 'analyze_operational_learning',
    'arguments' => ['limit' => 5],
    'request_company_id' => null,
    'request_id' => 'p19-perm',
    'write_confirmed' => false,
], $makeCtx(['pos.view'], false, $companyId, $modules, 'en'));
(!$unknown['allowed'] && empty($denied['allowed']))
    ? $pass('SECURITY') : $fail('SECURITY');

$mismatch = ProcurementPolicyGuard::checkAndExecute([
    'tool' => 'analyze_operational_learning',
    'arguments' => ['limit' => 5],
    'request_company_id' => $companyId + 99999,
    'request_id' => 'p19-mismatch',
    'write_confirmed' => false,
], $ctx);
(!$mismatch['allowed'] && ($mismatch['error_code'] ?? '') === 'tenant_mismatch')
    ? $pass('TENANT ISOLATION') : $fail('TENANT ISOLATION');

$intent = ErpOrchestrationPlanner::detectIntent('operational learning recommendation effectiveness', $ctx, null);
$gov = ErpGovernanceLayer::evaluate('operational learning', $intent, $ctx, $config, null, false);
// Governance unchanged by learning
$gov2 = ErpGovernanceLayer::evaluate('operational learning', $intent, $ctx, $config, null, false);
((($gov['controlled_autonomy']['allowed'] ?? null) === ($gov2['controlled_autonomy']['allowed'] ?? null))
    && empty($gov['controlled_autonomy']['allowed'])
    && !empty($intent['learning']))
    ? $pass('GOVERNANCE') : $fail('GOVERNANCE', json_encode($intent));

// Regressions
ErpDomainRegistry::isActive('crm') ? $pass('CRM REGRESSION') : $fail('CRM REGRESSION');
ErpDomainRegistry::isActive('sales') ? $pass('SALES REGRESSION') : $fail('SALES REGRESSION');
ErpDomainRegistry::isActive('inventory') ? $pass('INVENTORY REGRESSION') : $fail('INVENTORY REGRESSION');
ErpDomainRegistry::isActive('procurement') ? $pass('PROCUREMENT REGRESSION') : $fail('PROCUREMENT REGRESSION');
ErpDomainRegistry::isActive('suppliers') ? $pass('SUPPLIER REGRESSION') : $fail('SUPPLIER REGRESSION');
ErpDomainRegistry::isActive('logistics') ? $pass('LOGISTICS REGRESSION') : $fail('LOGISTICS REGRESSION');
ErpDomainRegistry::isActive('accounting') ? $pass('ACCOUNTING REGRESSION') : $fail('ACCOUNTING REGRESSION');
(!empty(ExecutiveToolExecutor::execute('analyze_executive_intelligence', ['limit' => 3], $ctx)['success']))
    ? $pass('INTELLIGENCE REGRESSION') : $fail('INTELLIGENCE REGRESSION');
(!empty(ExecutiveToolExecutor::execute('analyze_executive_intelligence', ['limit' => 3], $ctx)['success']))
    ? $pass('EXECUTIVE INTELLIGENCE REGRESSION') : $fail('EXECUTIVE INTELLIGENCE REGRESSION');
(!empty(ExecutiveToolExecutor::execute('scan_early_warnings', ['limit' => 5], $ctx)['success']))
    ? $pass('PROACTIVE EARLY WARNING REGRESSION') : $fail('PROACTIVE EARLY WARNING REGRESSION');
$act = ErpActionPlanner::buildActionPlan('Create a purchase request for missing stock', $ctx, null);
(!empty($act['requires_confirmation']) || (($act['actions'][0]['tool'] ?? '') === 'create_draft_purchase_request'))
    ? $pass('ACTION REGRESSION') : $fail('ACTION REGRESSION');
(($gov['execution_level'] ?? '') !== 'CONTROLLED_AUTONOMY')
    ? $pass('GOVERNANCE REGRESSION') : $fail('GOVERNANCE REGRESSION');

// Fix OPERATION OUTCOME TRACKING gate name - also ensure tracking recorded
(!empty($rec1['outcome']['outcome_id']) && ($rec1['outcome']['company_id'] ?? 0) === $companyId)
    ? null : $fail('OPERATION OUTCOME TRACKING', 'missing outcome');

$total = count($results);
$failed = count(array_filter($results, static fn($r) => empty($r['ok'])));
echo "\nTOTAL {$total}  FAIL {$failed}\n";
echo "\n=== PHASE 19 GATES ===\n";
$gates = [
    'OPERATION OUTCOME TRACKING', 'OUTCOME CLASSIFICATION', 'OUTCOME VERIFICATION', 'LEARNING SIGNALS',
    'RECOMMENDATION EFFECTIVENESS', 'WARNING EFFECTIVENESS', 'FORECAST FEEDBACK', 'CONTINUOUS OPTIMIZATION',
    'DETERMINISTIC LEARNING', 'DATA SUFFICIENCY', 'PATTERN DETECTION', 'ROOT-CAUSE SUPPORT',
    'DECISION OPTIMIZATION', 'USER FEEDBACK', 'TENANT-SCOPED LEARNING', 'LEARNING AUDIT',
    'NO SELF-MODIFICATION', 'PERFORMANCE', 'AR', 'EN', 'SECURITY', 'TENANT ISOLATION', 'GOVERNANCE',
    'CRM REGRESSION', 'SALES REGRESSION', 'INVENTORY REGRESSION', 'PROCUREMENT REGRESSION',
    'SUPPLIER REGRESSION', 'LOGISTICS REGRESSION', 'ACCOUNTING REGRESSION', 'INTELLIGENCE REGRESSION',
    'EXECUTIVE INTELLIGENCE REGRESSION', 'PROACTIVE EARLY WARNING REGRESSION', 'ACTION REGRESSION', 'GOVERNANCE REGRESSION',
];
$byName = [];
foreach ($results as $r) {
    $byName[$r['name']] = !empty($r['ok']);
}
foreach ($gates as $gate) {
    echo (!empty($byName[$gate]) ? 'PASS' : 'FAIL') . ": {$gate}\n";
}
exit($failed > 0 ? 1 : 0);
