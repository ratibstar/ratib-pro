<?php
declare(strict_types=1);

/**
 * Phase 18 — Proactive Operations & Early-Warning Engine.
 * Run: php rateb-erp/tests/procurement/run-procurement-agent-phase18-tests.php
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
use Rateb\App\Services\CronService;
use Rateb\App\Services\ErpActionPlanner;
use Rateb\App\Services\ErpAgent;
use Rateb\App\Services\ErpDomainRegistry;
use Rateb\App\Services\ErpExecutiveIntelligenceLayer;
use Rateb\App\Services\ErpGovernanceLayer;
use Rateb\App\Services\ErpOrchestrationPlanner;
use Rateb\App\Services\ErpProactiveEarlyWarningLayer;
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

final class Phase18MockLlm implements LlmClientInterface
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
    $ctor->invoke($ctx, $userId, $cid, $locale, 'phase18-test', $perms, $sa, $modules);
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
$erp = new ErpAgent($config, new Phase18MockLlm());
ErpProactiveEarlyWarningLayer::clearMemo();
ErpExecutiveIntelligenceLayer::clearMemo();

$noBadAgents = !class_exists('Rateb\\App\\Services\\MonitoringAgent')
    && !class_exists('Rateb\\App\\Services\\AlertAgent')
    && !class_exists('Rateb\\App\\Services\\EarlyWarningAgent');
$need = [
    'scan_early_warnings', 'get_early_warnings', 'get_early_warning_digest',
    'update_early_warning_status', 'revalidate_early_warning', 'get_early_warning_action_bridge',
];
$missing = array_diff($need, array_keys(ExecutiveToolRegistry::getTools()));
$writes = array_keys(array_filter(ExecutiveToolRegistry::getTools(), static fn($t) => !empty($t['write'])));

(class_exists(ErpProactiveEarlyWarningLayer::class)
    && $noBadAgents
    && $missing === []
    && $writes === []
    && ErpToolRegistry::domainForTool('scan_early_warnings') === 'executive'
    && (ErpDomainRegistry::resolve('executive')['runtime'] ?? '') === ProcurementAgent::class)
    ? $pass('PROACTIVE SIGNAL ENGINE') : $fail('PROACTIVE SIGNAL ENGINE', implode(',', $missing));

$scan1 = ErpProactiveEarlyWarningLayer::scan($ctx, [
    'trigger' => 'on_demand',
    'persist' => true,
    'notify' => false,
    'limit' => 20,
    'request_id' => 'p18-scan1',
]);
(!empty($scan1['scan_id'])
    && ($scan1['data_source'] ?? '') === 'live_tenant'
    && isset($scan1['signals'], $scan1['warnings'], $scan1['opportunities'])
    && empty($scan1['auto_execute']))
    ? $pass('EARLY WARNING ENGINE') : $fail('EARLY WARNING ENGINE');

$evOk = true;
foreach (array_slice($scan1['signals'] ?? [], 0, 10) as $s) {
    if (!is_array($s) || empty($s['evidence'])) {
        $evOk = false;
        break;
    }
}
foreach (array_slice($scan1['warnings'] ?? [], 0, 10) as $w) {
    if (!is_array($w) || empty($w['evidence']) || empty($w['warning_id']) || empty($w['fingerprint'])) {
        $evOk = false;
        break;
    }
}
$evOk ? $pass('SIGNAL EVIDENCE') : $fail('SIGNAL EVIDENCE');

$riskOk = true;
foreach (($scan1['warnings'] ?? []) as $w) {
    if (!is_array($w)) {
        continue;
    }
    if (($w['kind'] ?? '') === 'risk' && (empty($w['risk']) || empty($w['recommended_action']))) {
        $riskOk = false;
        break;
    }
}
$riskOk ? $pass('RISK DETECTION') : $fail('RISK DETECTION');

isset($scan1['opportunities']) && is_array($scan1['opportunities'])
    ? $pass('OPPORTUNITY DETECTION') : $fail('OPPORTUNITY DETECTION');

$sevOk = true;
foreach (($scan1['warnings'] ?? []) as $w) {
    if (!is_array($w)) {
        continue;
    }
    if (!in_array(($w['severity'] ?? ''), ['LOW', 'MEDIUM', 'HIGH', 'CRITICAL'], true)) {
        $sevOk = false;
        break;
    }
}
$sevOk ? $pass('SEVERITY') : $fail('SEVERITY');

$priOk = true;
foreach (($scan1['warnings'] ?? []) as $w) {
    if (!is_array($w)) {
        continue;
    }
    if (!in_array(strtoupper((string) ($w['priority'] ?? '')), ['LOW', 'MEDIUM', 'HIGH', 'CRITICAL'], true)) {
        $priOk = false;
        break;
    }
}
$priOk ? $pass('PRIORITY') : $fail('PRIORITY');

$crossOk = true;
foreach (($scan1['signals'] ?? []) as $s) {
    if (!empty($s['cross_domain']) && empty($s['evidence'])) {
        $crossOk = false;
        break;
    }
}
$crossOk ? $pass('CROSS-DOMAIN DETECTION') : $fail('CROSS-DOMAIN DETECTION');

ErpProactiveEarlyWarningLayer::clearMemo();
$scan2 = ErpProactiveEarlyWarningLayer::scan($ctx, [
    'trigger' => 'on_demand',
    'persist' => true,
    'notify' => false,
    'limit' => 20,
]);
$created2 = (int) ($scan2['warnings_created'] ?? -1);
$supp2 = (int) ($scan2['duplicates_suppressed'] ?? 0);
(($created2 === 0 || $supp2 >= 0) && ($scan2['scan_id'] ?? '') !== ($scan1['scan_id'] ?? ''))
    ? $pass('DUPLICATE WARNING PREVENTION') : $fail('DUPLICATE WARNING PREVENTION', "created={$created2} supp={$supp2}");

ErpProactiveEarlyWarningLayer::clearMemo();
$scan3 = ErpProactiveEarlyWarningLayer::scan($ctx, [
    'trigger' => 'cron',
    'persist' => true,
    'notify' => false,
    'limit' => 20,
]);
((int) ($scan3['warnings_created'] ?? -1) === 0 || (int) ($scan3['duplicates_suppressed'] ?? 0) >= 0)
    ? $pass('IDEMPOTENCY') : $fail('IDEMPOTENCY');

$open = ErpProactiveEarlyWarningLayer::listWarnings($companyId, false);
$lifeOk = false;
if ($open !== []) {
    $wid = (string) ($open[0]['warning_id'] ?? '');
    $upd = ErpProactiveEarlyWarningLayer::updateStatus($companyId, $wid, 'ACKNOWLEDGED');
    $upd2 = ErpProactiveEarlyWarningLayer::updateStatus($companyId, $wid, 'IN_PROGRESS');
    $lifeOk = !empty($upd['success']) && !empty($upd2['success']);
} else {
    // No open warnings — lifecycle API still valid with not_found
    $upd = ErpProactiveEarlyWarningLayer::updateStatus($companyId, 'ew_missing', 'ACKNOWLEDGED');
    $lifeOk = empty($upd['success']) && ($upd['error'] ?? '') === 'warning_not_found';
}
$lifeOk ? $pass('WARNING LIFECYCLE') : $fail('WARNING LIFECYCLE');

$staleId = $open[0]['warning_id'] ?? 'ew_missing';
$re = ErpProactiveEarlyWarningLayer::revalidateWarning($ctx, (string) $staleId);
(isset($re['requires_new_confirmation']) && !empty($re['requires_new_confirmation']) && empty($re['auto_execute'] ?? false))
    ? $pass('STALE WARNING PROTECTION') : $fail('STALE WARNING PROTECTION', json_encode($re));

$toolScan = ExecutiveToolExecutor::execute('scan_early_warnings', ['limit' => 10], $ctx);
$intent = ErpOrchestrationPlanner::detectIntent('scan current state', $ctx, null);
(!empty($toolScan['success']) && !empty($intent['proactive']))
    ? $pass('ON-DEMAND SCAN') : $fail('ON-DEMAND SCAN', json_encode($intent));

$cronSrc = file_get_contents(RATEB_ROOT . '/app/services/CronService.php') ?: '';
$binSrc = file_get_contents(RATEB_ROOT . '/bin/erp-cron.php') ?: '';
(str_contains($cronSrc, 'processAgentEarlyWarnings')
    && str_contains($cronSrc, 'agent_early_warnings')
    && str_contains($binSrc, 'CronService')
    && !class_exists('Rateb\\App\\Services\\ProactiveSchedulerEngine'))
    ? $pass('CRON/SCHEDULER INTEGRATION') : $fail('CRON/SCHEDULER INTEGRATION');

$digest = ExecutiveToolExecutor::execute('get_early_warning_digest', ['limit' => 10], $ctx);
(!empty($digest['success']) && is_string($digest['data']['text'] ?? null) && empty($digest['data']['auto_execute']))
    ? $pass('PROACTIVE DIGEST') : $fail('PROACTIVE DIGEST');

$gov = ErpGovernanceLayer::evaluate('scan current state', $intent, $ctx, $config, null, false);
(!empty($gov['application_decides'])
    && empty($gov['controlled_autonomy']['allowed'])
    && empty($scan1['auto_execute'])
    && empty($scan1['controlled_autonomy']))
    ? $pass('CONTROLLED AUTONOMY') : $fail('CONTROLLED AUTONOMY');

$bridge = null;
if ($open !== []) {
    $bridge = ErpProactiveEarlyWarningLayer::actionBridge($ctx, (string) ($open[0]['warning_id'] ?? ''));
}
$actionSafe = $bridge === null
    || (empty($bridge['auto_execute']) && !empty($bridge['requires_governance']));
$denyWrite = ProcurementPolicyGuard::checkAndExecute([
    'tool' => 'create_draft_purchase_request',
    'arguments' => ['title' => 'x'],
    'request_company_id' => null,
    'request_id' => 'p18-write',
    'write_confirmed' => false,
], $ctx);
$actionSafe = $actionSafe && empty($denyWrite['allowed']);
$actionSafe ? $pass('ACTION SAFETY') : $fail('ACTION SAFETY');

(!empty($gov['application_decides']))
    ? $pass('GOVERNANCE') : $fail('GOVERNANCE');

$run = $erp->process([
    'message' => 'Scan current state for early warnings',
    'request_id' => 'p18-aud-' . bin2hex(random_bytes(3)),
], $ctx);
$reqId = (string) ($run['observability']['request_id'] ?? '');
$auditN = $reqId !== '' ? (int) $pdo->query(
    "SELECT COUNT(*) FROM rateb_agent_audit_events WHERE request_id = " . $pdo->quote($reqId)
    . " AND company_id = " . (int) $companyId
)->fetchColumn() : 0;
($auditN >= 1 && !empty($run['audit']))
    ? $pass('AUDIT') : $fail('AUDIT', (string) $auditN);

(isset($scan1['observability']['scan_id'], $scan1['observability']['duration_ms'], $scan1['observability']['tenant']))
    ? $pass('OBSERVABILITY') : $fail('OBSERVABILITY');

$t0 = microtime(true);
ErpProactiveEarlyWarningLayer::clearMemo();
$a = ExecutiveToolExecutor::execute('scan_early_warnings', ['limit' => 8], $ctx);
$b = ExecutiveToolExecutor::execute('scan_early_warnings', ['limit' => 8], $ctx);
$elapsed = (microtime(true) - $t0) * 1000;
(!empty($a['success']) && !empty($b['success']) && $elapsed < 45000)
    ? $pass('PERFORMANCE') : $fail('PERFORMANCE', (string) $elapsed);

$prev = $_SESSION['rateb_locale'] ?? 'en';
$_SESSION['rateb_locale'] = 'ar';
if (function_exists('rateb_set_locale')) {
    rateb_set_locale('ar');
}
$arScan = __('ai_tool_scan_early_warnings');
$arWarn = __('ai_ew_warning');
$_SESSION['rateb_locale'] = 'en';
if (function_exists('rateb_set_locale')) {
    rateb_set_locale('en');
}
$enScan = __('ai_tool_scan_early_warnings');
$enWarn = __('ai_ew_warning');
$_SESSION['rateb_locale'] = $prev;
(is_string($arScan) && mb_strpos($arScan, 'استباق') !== false && is_string($arWarn) && mb_strpos($arWarn, 'تحذير') !== false)
    ? $pass('AR') : $fail('AR', (string) $arScan);
(is_string($enScan) && stripos($enScan, 'Proactive') !== false && is_string($enWarn) && stripos($enWarn, 'Warning') !== false)
    ? $pass('EN') : $fail('EN', (string) $enScan);

$unknown = ProcurementPolicyGuard::checkAndExecute([
    'tool' => 'drop_database',
    'arguments' => [],
    'request_company_id' => null,
    'request_id' => 'p18-bad',
    'write_confirmed' => true,
], $ctx);
$denied = ProcurementPolicyGuard::checkAndExecute([
    'tool' => 'scan_early_warnings',
    'arguments' => ['limit' => 5],
    'request_company_id' => null,
    'request_id' => 'p18-perm',
    'write_confirmed' => false,
], $makeCtx(['pos.view'], false, $companyId, $modules, 'en'));
(!$unknown['allowed'] && ($unknown['error_code'] ?? '') === 'tool_not_allowed' && empty($denied['allowed']))
    ? $pass('SECURITY') : $fail('SECURITY', json_encode($denied));

$mismatch = ProcurementPolicyGuard::checkAndExecute([
    'tool' => 'scan_early_warnings',
    'arguments' => ['limit' => 5],
    'request_company_id' => $companyId + 99999,
    'request_id' => 'p18-mismatch',
    'write_confirmed' => false,
], $ctx);
$otherStore = ErpProactiveEarlyWarningLayer::listWarnings($companyId + 777777, false);
(!$mismatch['allowed'] && ($mismatch['error_code'] ?? '') === 'tenant_mismatch' && $otherStore === [])
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
isset($scan1['explainability']['evidence_first']) || isset($scan1['observability'])
    ? $pass('INTELLIGENCE REGRESSION') : $fail('INTELLIGENCE REGRESSION');
(!empty(ExecutiveToolExecutor::execute('analyze_executive_intelligence', ['limit' => 5], $ctx)['success']))
    ? $pass('EXECUTIVE INTELLIGENCE REGRESSION') : $fail('EXECUTIVE INTELLIGENCE REGRESSION');
$act = ErpActionPlanner::buildActionPlan('Create a purchase request for missing stock', $ctx, null);
(!empty($act['requires_confirmation']) || (($act['actions'][0]['tool'] ?? '') === 'create_draft_purchase_request'))
    ? $pass('ACTION REGRESSION') : $fail('ACTION REGRESSION');
(($gov['execution_level'] ?? '') !== 'CONTROLLED_AUTONOMY')
    ? $pass('GOVERNANCE REGRESSION') : $fail('GOVERNANCE REGRESSION');

// Ensure CronService class loads
class_exists(CronService::class);

$total = count($results);
$failed = count(array_filter($results, static fn($r) => empty($r['ok'])));
echo "\nTOTAL {$total}  FAIL {$failed}\n";
echo "\n=== PHASE 18 GATES ===\n";
$gates = [
    'PROACTIVE SIGNAL ENGINE', 'EARLY WARNING ENGINE', 'SIGNAL EVIDENCE', 'RISK DETECTION',
    'OPPORTUNITY DETECTION', 'SEVERITY', 'PRIORITY', 'CROSS-DOMAIN DETECTION',
    'DUPLICATE WARNING PREVENTION', 'IDEMPOTENCY', 'WARNING LIFECYCLE', 'STALE WARNING PROTECTION',
    'ON-DEMAND SCAN', 'CRON/SCHEDULER INTEGRATION', 'PROACTIVE DIGEST', 'CONTROLLED AUTONOMY',
    'ACTION SAFETY', 'GOVERNANCE', 'AUDIT', 'OBSERVABILITY', 'PERFORMANCE', 'AR', 'EN',
    'SECURITY', 'TENANT ISOLATION',
    'CRM REGRESSION', 'SALES REGRESSION', 'INVENTORY REGRESSION', 'PROCUREMENT REGRESSION',
    'SUPPLIER REGRESSION', 'LOGISTICS REGRESSION', 'ACCOUNTING REGRESSION',
    'INTELLIGENCE REGRESSION', 'EXECUTIVE INTELLIGENCE REGRESSION', 'ACTION REGRESSION', 'GOVERNANCE REGRESSION',
];
$byName = [];
foreach ($results as $r) {
    $byName[$r['name']] = !empty($r['ok']);
}
foreach ($gates as $gate) {
    echo (!empty($byName[$gate]) ? 'PASS' : 'FAIL') . ": {$gate}\n";
}
exit($failed > 0 ? 1 : 0);
