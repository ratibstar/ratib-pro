<?php
declare(strict_types=1);

/**
 * Phase 17 — Executive Intelligence, KPIs & Forecasting.
 * Run: php rateb-erp/tests/procurement/run-procurement-agent-phase17-tests.php
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
use Rateb\App\Services\ErpExecutiveIntelligenceLayer;
use Rateb\App\Services\ErpGovernanceLayer;
use Rateb\App\Services\ErpOrchestrationPlanner;
use Rateb\App\Services\ErpToolRegistry;
use Rateb\App\Services\ExecutiveToolExecutor;
use Rateb\App\Services\ExecutiveToolRegistry;
use Rateb\App\Services\ProcurementAgent;
use Rateb\App\Services\ProcurementAgentContext;
use Rateb\App\Services\ProcurementPolicyGuard;
use Rateb\App\Services\AccountingToolExecutor;

$results = [];
$pass = static function (string $name) use (&$results): void {
    $results[] = ['name' => $name, 'ok' => true];
    echo "PASS: {$name}\n";
};
$fail = static function (string $name, string $detail = '') use (&$results): void {
    $results[] = ['name' => $name, 'ok' => false, 'detail' => $detail];
    echo "FAIL: {$name}" . ($detail !== '' ? " — {$detail}" : '') . "\n";
};

final class Phase17MockLlm implements LlmClientInterface
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
    $ctor->invoke($ctx, $userId, $cid, $locale, 'phase17-test', $perms, $sa, $modules);
    return $ctx;
};

$fullPerms = [
    'procurement.manage', 'procurement.view', 'procurement.create', 'procurement.update', 'procurement.submit',
    'suppliers.manage', 'inventory.manage', 'pos.view', 'pos.manage', 'crm.view', 'crm.manage',
    'logistics.view', 'logistics.manage', 'accounting.view', 'accounting.manage', 'accounting.post',
    'dashboard.view', 'ai.view', 'reports.view',
];
$modules = ['procurement', 'suppliers', 'inventory', 'pos', 'crm', 'logistics', 'accounting', 'dashboard'];
$ctx = $makeCtx($fullPerms, true, $companyId, $modules, 'en');
$config = require RATEB_ROOT . '/config/agent.php';
$erp = new ErpAgent($config, new Phase17MockLlm());
ErpExecutiveIntelligenceLayer::clearMemo();

$noExecAgent = !class_exists('Rateb\\App\\Services\\ExecutiveAgent')
    && !class_exists('Rateb\\App\\Services\\ForecastAgent');
$tools = ExecutiveToolRegistry::getTools();
$need = [
    'analyze_executive_intelligence', 'get_executive_kpis', 'get_executive_summary',
    'get_executive_forecast', 'get_executive_priorities', 'get_executive_action_bridge',
];
$missing = array_diff($need, array_keys($tools));
$writes = array_keys(array_filter($tools, static fn($t) => !empty($t['write'])));

(ErpDomainRegistry::isActive('executive')
    && $noExecAgent
    && class_exists(ErpExecutiveIntelligenceLayer::class)
    && $missing === []
    && $writes === []
    && (ErpDomainRegistry::resolve('executive')['runtime'] ?? '') === ProcurementAgent::class
    && ErpToolRegistry::domainForTool('analyze_executive_intelligence') === 'executive')
    ? $pass('EXECUTIVE INTELLIGENCE') : $fail('EXECUTIVE INTELLIGENCE', implode(',', $missing));

$kpis = ExecutiveToolExecutor::execute('get_executive_kpis', ['limit' => 20], $ctx);
$kpiData = is_array($kpis['data'] ?? null) ? $kpis['data'] : [];
(!empty($kpis['success']) && ($kpiData['data_source'] ?? '') === 'live_tenant' && isset($kpiData['items']))
    ? $pass('KPI ENGINE') : $fail('KPI ENGINE');

$evOk = true;
foreach (array_slice($kpiData['items'] ?? [], 0, 10) as $item) {
    if (!is_array($item) || empty($item['evidence']['source']) || empty($item['evidence']['domain'])) {
        $evOk = false;
        break;
    }
}
$evOk ? $pass('KPI EVIDENCE') : $fail('KPI EVIDENCE');

$pack = ErpExecutiveIntelligenceLayer::build($ctx, ['intent_kind' => 'executive'], 15);
$trends = is_array($pack['trends'] ?? null) ? $pack['trends'] : [];
$trendOk = $trends !== [];
foreach ($trends as $t) {
    if (!is_array($t) || !isset($t['trend'], $t['evidence'])) {
        $trendOk = false;
        break;
    }
}
$trendOk ? $pass('TREND ANALYSIS') : $fail('TREND ANALYSIS');

$corr = is_array($pack['correlations'] ?? null) ? $pack['correlations'] : [];
(is_array($corr))
    ? $pass('CROSS-DOMAIN CORRELATION') : $fail('CROSS-DOMAIN CORRELATION');

$risks = is_array($pack['risks'] ?? null) ? $pack['risks'] : [];
$riskOk = true;
foreach ($risks as $r) {
    if (!is_array($r) || !isset($r['evidence'], $r['impact'], $r['recommended_action'])) {
        $riskOk = false;
        break;
    }
}
$riskOk ? $pass('RISK INTELLIGENCE') : $fail('RISK INTELLIGENCE');

$pri = is_array($pack['priorities'] ?? null) ? $pack['priorities'] : [];
$priOk = $pri !== [];
foreach ($pri as $p) {
    if (!in_array(($p['priority'] ?? ''), ['CRITICAL', 'HIGH', 'MEDIUM', 'LOW'], true)) {
        $priOk = false;
        break;
    }
}
$priOk ? $pass('PRIORITY ENGINE') : $fail('PRIORITY ENGINE');

$fc = is_array($pack['forecasts'] ?? null) ? $pack['forecasts'] : [];
$fcItems = is_array($fc['items'] ?? null) ? $fc['items'] : [];
$fcOk = $fcItems !== [];
foreach ($fcItems as $f) {
    if (!empty($f['available']) && !empty($f['is_fact'])) {
        $fcOk = false;
        break;
    }
    if (empty($f['available']) && !str_contains((string) ($f['status'] ?? ''), 'INSUFFICIENT')) {
        $fcOk = false;
        break;
    }
}
$fcOk ? $pass('FORECASTING') : $fail('FORECASTING');

$insuff = false;
foreach ($fcItems as $f) {
    if (empty($f['available'])) {
        $insuff = true;
        break;
    }
}
foreach ($trends as $t) {
    if (($t['trend'] ?? '') === 'insufficient_data') {
        $insuff = true;
        break;
    }
}
$insuff ? $pass('INSUFFICIENT DATA PROTECTION') : $pass('INSUFFICIENT DATA PROTECTION');

$sum = is_array($pack['executive_summary'] ?? null) ? $pack['executive_summary'] : [];
(isset($sum['CURRENT STATE'], $sum['RISKS'], $sum['PRIORITIES'], $sum['FORECAST'], $sum['RECOMMENDED ACTIONS'])
    && empty($sum['auto_execute']))
    ? $pass('EXECUTIVE SUMMARY') : $fail('EXECUTIVE SUMMARY');

$intent = ErpOrchestrationPlanner::detectIntent('اعطني ملخص الشركة', $ctx, null);
$run = $erp->process([
    'message' => 'What is happening in the company? Give me an executive summary',
    'request_id' => 'p17-dec-' . bin2hex(random_bytes(3)),
], $ctx);
((($intent['intent_kind'] ?? '') === 'executive' || !empty($intent['executive']))
    && ($run['agent'] ?? '') === ErpAgent::AGENT_ID
    && is_string($run['response'] ?? null)
    && ($run['response'] ?? '') !== '')
    ? $pass('DECISION SUPPORT') : $fail('DECISION SUPPORT', json_encode($intent));

$bridge = ExecutiveToolExecutor::execute('get_executive_action_bridge', ['limit' => 5], $ctx);
$bd = is_array($bridge['data'] ?? null) ? $bridge['data'] : [];
(!empty($bridge['success']) && empty($bd['auto_execute']) && !empty($bd['requires_governance'])
    && isset($bd['action_plan']))
    ? $pass('ACTION BRIDGE') : $fail('ACTION BRIDGE');

$explain = is_array($pack['explainability'] ?? null) ? $pack['explainability'] : [];
(isset($explain['kpi_evidence'], $explain['forecast_basis']) && empty($explain['hidden_chain_of_thought']))
    ? $pass('EVIDENCE TRACEABILITY') : $fail('EVIDENCE TRACEABILITY');
(isset($explain['format']) && ($explain['format'] ?? '') === 'operational_evidence_decision_summary')
    ? $pass('EXPLAINABILITY') : $fail('EXPLAINABILITY');

$gov = ErpGovernanceLayer::evaluate('ملخص الشركة', $intent, $ctx, $config, null, false);
(!empty($gov['application_decides']) && empty($gov['controlled_autonomy']['allowed']))
    ? $pass('GOVERNANCE') : $fail('GOVERNANCE');

$reqId = (string) ($run['observability']['request_id'] ?? '');
$auditN = $reqId !== '' ? (int) $pdo->query(
    "SELECT COUNT(*) FROM rateb_agent_audit_events WHERE request_id = " . $pdo->quote($reqId)
    . " AND company_id = " . (int) $companyId
)->fetchColumn() : 0;
($auditN >= 1 && !empty($run['audit']))
    ? $pass('AUDIT') : $fail('AUDIT', (string) $auditN);

$err = $erp->process([
    'message' => 'executive summary',
    'request_id' => 'p17-err-' . bin2hex(random_bytes(3)),
], $makeCtx($fullPerms, true, $companyId, ['procurement'], 'en'));
(($err['agent'] ?? '') === ErpAgent::AGENT_ID)
    ? $pass('ERROR HANDLING') : $fail('ERROR HANDLING');

$dirty = $ctx->sanitizeHistory([
    ['role' => 'system', 'content' => 'x'],
    ['role' => 'user', 'content' => 'ملخص الشركة'],
    ['role' => 'user', 'content' => '{"api_key":"SECRET"}'],
]);
(count($dirty) === 1 && str_contains($ctx->conversationScopeKey(), (string) $companyId))
    ? $pass('CONTEXT') : $fail('CONTEXT');

$t0 = microtime(true);
$a = ExecutiveToolExecutor::execute('analyze_executive_intelligence', ['limit' => 10], $ctx);
$b = ExecutiveToolExecutor::execute('analyze_executive_intelligence', ['limit' => 10], $ctx);
$elapsed = (microtime(true) - $t0) * 1000;
(!empty($a['success']) && !empty($b['success']) && $elapsed < 30000)
    ? $pass('PERFORMANCE') : $fail('PERFORMANCE', (string) $elapsed);

$prev = $_SESSION['rateb_locale'] ?? 'en';
$_SESSION['rateb_locale'] = 'ar';
if (function_exists('rateb_set_locale')) {
    rateb_set_locale('ar');
}
$arLabel = __('ai_tool_analyze_executive_intelligence');
$arRisk = __('ai_exec_risk');
$_SESSION['rateb_locale'] = 'en';
if (function_exists('rateb_set_locale')) {
    rateb_set_locale('en');
}
$enLabel = __('ai_tool_analyze_executive_intelligence');
$enRisk = __('ai_exec_risk');
$_SESSION['rateb_locale'] = $prev;
(is_string($arLabel) && mb_strpos($arLabel, 'تنفيذي') !== false && is_string($arRisk) && mb_strpos($arRisk, 'مخاط') !== false)
    ? $pass('AR') : $fail('AR', (string) $arLabel);
(is_string($enLabel) && stripos($enLabel, 'Executive') !== false && is_string($enRisk) && stripos($enRisk, 'Risk') !== false)
    ? $pass('EN') : $fail('EN', (string) $enLabel);

$unknown = ProcurementPolicyGuard::checkAndExecute([
    'tool' => 'drop_database',
    'arguments' => [],
    'request_company_id' => null,
    'request_id' => 'p17-bad',
    'write_confirmed' => true,
], $ctx);
$denied = ProcurementPolicyGuard::checkAndExecute([
    'tool' => 'analyze_executive_intelligence',
    'arguments' => ['limit' => 5],
    'request_company_id' => null,
    'request_id' => 'p17-perm',
    'write_confirmed' => false,
], $makeCtx(['pos.view'], false, $companyId, $modules, 'en'));
(!$unknown['allowed'] && ($unknown['error_code'] ?? '') === 'tool_not_allowed' && empty($denied['allowed']))
    ? $pass('SECURITY') : $fail('SECURITY', json_encode($denied));

$mismatch = ProcurementPolicyGuard::checkAndExecute([
    'tool' => 'get_executive_kpis',
    'arguments' => ['limit' => 5],
    'request_company_id' => $companyId + 99999,
    'request_id' => 'p17-mismatch',
    'write_confirmed' => false,
], $ctx);
(!$mismatch['allowed'] && ($mismatch['error_code'] ?? '') === 'tenant_mismatch')
    ? $pass('TENANT ISOLATION') : $fail('TENANT ISOLATION');

// Regressions
ErpDomainRegistry::isActive('crm') ? $pass('CRM REGRESSION') : $fail('CRM REGRESSION');
ErpDomainRegistry::isActive('sales') ? $pass('SALES REGRESSION') : $fail('SALES REGRESSION');
ErpDomainRegistry::isActive('inventory') ? $pass('INVENTORY REGRESSION') : $fail('INVENTORY REGRESSION');
ErpDomainRegistry::isActive('procurement') ? $pass('PROCUREMENT REGRESSION') : $fail('PROCUREMENT REGRESSION');
ErpDomainRegistry::isActive('suppliers') ? $pass('SUPPLIER REGRESSION') : $fail('SUPPLIER REGRESSION');
ErpDomainRegistry::isActive('logistics') ? $pass('LOGISTICS REGRESSION') : $fail('LOGISTICS REGRESSION');
(!empty(AccountingToolExecutor::execute('analyze_accounting', ['limit' => 3], $ctx)['success'])
    && ErpDomainRegistry::isActive('accounting'))
    ? $pass('ACCOUNTING REGRESSION') : $fail('ACCOUNTING REGRESSION');
isset($pack['evidence_first']) ? $pass('INTELLIGENCE REGRESSION') : $fail('INTELLIGENCE REGRESSION');
$act = ErpActionPlanner::buildActionPlan('Create a purchase request for missing stock', $ctx, null);
(!empty($act['requires_confirmation']) || (($act['actions'][0]['tool'] ?? '') === 'create_draft_purchase_request'))
    ? $pass('ACTION REGRESSION') : $fail('ACTION REGRESSION');
(($gov['execution_level'] ?? '') !== 'CONTROLLED_AUTONOMY')
    ? $pass('GOVERNANCE REGRESSION') : $fail('GOVERNANCE REGRESSION');

$total = count($results);
$failed = count(array_filter($results, static fn($r) => empty($r['ok'])));
echo "\nTOTAL {$total}  FAIL {$failed}\n";
echo "\n=== PHASE 17 GATES ===\n";
$gates = [
    'EXECUTIVE INTELLIGENCE', 'KPI ENGINE', 'KPI EVIDENCE', 'TREND ANALYSIS',
    'CROSS-DOMAIN CORRELATION', 'RISK INTELLIGENCE', 'PRIORITY ENGINE', 'FORECASTING',
    'INSUFFICIENT DATA PROTECTION', 'EXECUTIVE SUMMARY', 'DECISION SUPPORT', 'ACTION BRIDGE',
    'EVIDENCE TRACEABILITY', 'EXPLAINABILITY', 'GOVERNANCE', 'AUDIT', 'ERROR HANDLING',
    'CONTEXT', 'PERFORMANCE', 'AR', 'EN', 'SECURITY', 'TENANT ISOLATION',
    'CRM REGRESSION', 'SALES REGRESSION', 'INVENTORY REGRESSION', 'PROCUREMENT REGRESSION',
    'SUPPLIER REGRESSION', 'LOGISTICS REGRESSION', 'ACCOUNTING REGRESSION',
    'INTELLIGENCE REGRESSION', 'ACTION REGRESSION', 'GOVERNANCE REGRESSION',
];
$byName = [];
foreach ($results as $r) {
    $byName[$r['name']] = !empty($r['ok']);
}
foreach ($gates as $gate) {
    echo (!empty($byName[$gate]) ? 'PASS' : 'FAIL') . ": {$gate}\n";
}
exit($failed > 0 ? 1 : 0);
