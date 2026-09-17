<?php
declare(strict_types=1);

/**
 * Phase 20 — RATEB Agent Control Tower.
 * Run: php rateb-erp/tests/procurement/run-procurement-agent-phase20-tests.php
 */

$root = dirname(__DIR__, 2);
define('RATEB_ROOT', $root);
define('RATEB_ENV_NO_SESSION', true);

require_once $root . '/app/Core/Bootstrap.php';
\Rateb\App\Core\Bootstrap::init($root);

use Rateb\App\Core\Auth;
use Rateb\App\Core\Database;
use Rateb\App\Core\TenantContext;
use Rateb\App\Services\ErpControlTowerLayer;
use Rateb\App\Services\ErpDomainRegistry;
use Rateb\App\Services\ErpGovernanceLayer;
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
    $ctor->invoke($ctx, $userId, $cid, $locale, 'phase20-test', $perms, $sa, $modules);
    return $ctx;
};

$fullPerms = [
    'procurement.manage', 'procurement.view', 'inventory.manage', 'pos.view', 'crm.view',
    'logistics.view', 'accounting.view', 'dashboard.view', 'ai.view', 'reports.view', 'suppliers.manage',
];
$modules = ['procurement', 'suppliers', 'inventory', 'pos', 'crm', 'logistics', 'accounting', 'dashboard'];
$ctx = $makeCtx($fullPerms, true, $companyId, $modules, 'en');
$config = require RATEB_ROOT . '/config/agent.php';

$noBad = !class_exists('Rateb\\App\\Services\\MonitoringAgent')
    && !class_exists('Rateb\\App\\Services\\ControlTowerAgent')
    && !class_exists('Rateb\\App\\Services\\BiEngine');
$viewOk = is_file(RATEB_ROOT . '/views/company/ai/control-tower.php')
    && is_file(RATEB_ROOT . '/public/assets/css/rateb-ai-control-tower.css')
    && is_file(RATEB_ROOT . '/public/assets/js/rateb-ai-control-tower.js');
$routeOk = str_contains((string) file_get_contents(RATEB_ROOT . '/routes/modules/ops.php'), "ai/tower");
$ctrlOk = method_exists(\Rateb\App\Controllers\Company\AiController::class, 'tower');

(class_exists(ErpControlTowerLayer::class)
    && $noBad
    && $viewOk
    && $routeOk
    && $ctrlOk
    && ExecutiveToolRegistry::isAllowed('get_control_tower_snapshot')
    && ErpToolRegistry::domainForTool('get_control_tower_snapshot') === 'executive'
    && (ErpDomainRegistry::resolve('executive')['runtime'] ?? '') === ProcurementAgent::class)
    ? $pass('CONTROL TOWER') : $fail('CONTROL TOWER');

ErpControlTowerLayer::clearMemo();
$snap = ErpControlTowerLayer::snapshot($ctx, 12);
(!empty($snap['data_source']) && ($snap['data_source'] ?? '') === 'live_tenant'
    && (int) ($snap['company_id'] ?? 0) === $companyId
    && isset($snap['current_state'], $snap['overview'])
    && empty($snap['auto_execute']))
    ? $pass('SNAPSHOT') : $fail('SNAPSHOT');

(isset($snap['kpis']) && is_array($snap['kpis']))
    ? $pass('KPI') : $fail('KPI');

$evOk = true;
foreach (array_slice($snap['kpis'] ?? [], 0, 5) as $k) {
    if (!is_array($k) || empty($k['evidence']['domain']) || empty($k['evidence']['source'])) {
        $evOk = false;
        break;
    }
}
foreach (array_slice($snap['warnings'] ?? [], 0, 5) as $w) {
    if (!is_array($w)) {
        continue;
    }
    if (empty($w['evidence']['domain'])) {
        $evOk = false;
        break;
    }
}
($evOk && empty($snap['explainability']['hidden_chain_of_thought']))
    ? $pass('EVIDENCE TRACEABILITY') : $fail('EVIDENCE TRACEABILITY');

(isset($snap['warnings']) && is_array($snap['warnings']))
    ? $pass('WARNING LIFECYCLE') : $fail('WARNING LIFECYCLE');

(isset($snap['actions']) && empty($snap['actions']['direct_writes'])
    && !empty($snap['security']['requires_action_planner'])
    && !empty($snap['security']['requires_governance']))
    ? $pass('ACTION LIFECYCLE') : $fail('ACTION LIFECYCLE');

(isset($snap['outcomes']) && is_array($snap['outcomes']))
    ? $pass('OUTCOME') : $fail('OUTCOME');

(isset($snap['learning']) && !empty($snap['learning']['governance_immutable']))
    ? $pass('LEARNING') : $fail('LEARNING');

(isset($snap['agent_effectiveness']) && ($snap['agent_effectiveness']['data_source'] ?? '') === 'live_tenant')
    ? $pass('AGENT EFFECTIVENESS') : $fail('AGENT EFFECTIVENESS');

$tool = ExecutiveToolExecutor::execute('get_control_tower_snapshot', ['limit' => 8], $ctx);
(!empty($tool['success']) && (int) ($tool['data']['company_id'] ?? 0) === $companyId)
    ? $pass('PERMISSION') : $fail('PERMISSION');

$denied = ProcurementPolicyGuard::checkAndExecute([
    'tool' => 'get_control_tower_snapshot',
    'arguments' => ['limit' => 5],
    'request_company_id' => null,
    'request_id' => 'p20-perm',
    'write_confirmed' => false,
], $makeCtx(['pos.view'], false, $companyId, $modules, 'en'));
$unknown = ProcurementPolicyGuard::checkAndExecute([
    'tool' => 'drop_database',
    'arguments' => [],
    'request_company_id' => null,
    'request_id' => 'p20-bad',
    'write_confirmed' => true,
], $ctx);
(empty($denied['allowed']) && !$unknown['allowed'] && empty($snap['security']['direct_writes']))
    ? $pass('SECURITY') : $fail('SECURITY');

$mismatch = ProcurementPolicyGuard::checkAndExecute([
    'tool' => 'get_control_tower_snapshot',
    'arguments' => ['limit' => 5],
    'request_company_id' => $companyId + 99999,
    'request_id' => 'p20-mismatch',
    'write_confirmed' => false,
], $ctx);
$other = ErpControlTowerLayer::snapshot($makeCtx($fullPerms, true, $companyId + 777001, $modules), 5);
(!$mismatch['allowed'] && ($mismatch['error_code'] ?? '') === 'tenant_mismatch'
    && (int) ($other['company_id'] ?? 0) === $companyId + 777001
    && (int) ($snap['company_id'] ?? 0) === $companyId)
    ? $pass('TENANT ISOLATION') : $fail('TENANT ISOLATION');

$prev = $_SESSION['rateb_locale'] ?? 'en';
$_SESSION['rateb_locale'] = 'ar';
if (function_exists('rateb_set_locale')) {
    rateb_set_locale('ar');
}
$arTitle = __('ai_ct_title');
$_SESSION['rateb_locale'] = 'en';
if (function_exists('rateb_set_locale')) {
    rateb_set_locale('en');
}
$enTitle = __('ai_ct_title');
$_SESSION['rateb_locale'] = $prev;
(is_string($arTitle) && mb_strpos($arTitle, 'برج') !== false)
    ? $pass('AR') : $fail('AR', (string) $arTitle);
(is_string($enTitle) && stripos($enTitle, 'Control Tower') !== false)
    ? $pass('EN') : $fail('EN', (string) $enTitle);

$css = (string) file_get_contents(RATEB_ROOT . '/public/assets/css/rateb-ai-control-tower.css');
(str_contains($css, '[data-bs-theme="dark"]') && str_contains($css, '.rateb-ct'))
    ? $pass('DARK/LIGHT') : $fail('DARK/LIGHT');

$t0 = microtime(true);
$a = ErpControlTowerLayer::snapshot($ctx, 8);
$b = ErpControlTowerLayer::snapshot($ctx, 8);
$elapsed = (microtime(true) - $t0) * 1000;
(!empty($a['observability']) && $elapsed < 25000 && isset($b['company_id']))
    ? $pass('PERFORMANCE') : $fail('PERFORMANCE', (string) $elapsed);

$gov = ErpGovernanceLayer::evaluate('control tower', ['intent_kind' => 'executive'], $ctx, $config, null, false);
(empty($gov['controlled_autonomy']['allowed']))
    ? $pass('GOVERNANCE') : $fail('GOVERNANCE');

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
(!empty(ExecutiveToolExecutor::execute('get_executive_action_bridge', ['limit' => 3], $ctx)['success']))
    ? $pass('ACTION REGRESSION') : $fail('ACTION REGRESSION');
(!empty(ExecutiveToolExecutor::execute('scan_early_warnings', ['limit' => 3], $ctx)['success']))
    ? $pass('PROACTIVE REGRESSION') : $fail('PROACTIVE REGRESSION');
(!empty(ExecutiveToolExecutor::execute('analyze_operational_learning', ['limit' => 3], $ctx)['success']))
    ? $pass('LEARNING REGRESSION') : $fail('LEARNING REGRESSION');
(($gov['execution_level'] ?? '') !== 'CONTROLLED_AUTONOMY')
    ? $pass('GOVERNANCE REGRESSION') : $fail('GOVERNANCE REGRESSION');

$total = count($results);
$failed = count(array_filter($results, static fn($r) => empty($r['ok'])));
echo "\nTOTAL {$total}  FAIL {$failed}\n";
echo "\n=== PHASE 20 GATES ===\n";
$gates = [
    'CONTROL TOWER', 'SNAPSHOT', 'KPI', 'EVIDENCE TRACEABILITY', 'WARNING LIFECYCLE', 'ACTION LIFECYCLE',
    'OUTCOME', 'LEARNING', 'AGENT EFFECTIVENESS', 'PERMISSION', 'SECURITY', 'TENANT ISOLATION',
    'AR', 'EN', 'DARK/LIGHT', 'PERFORMANCE',
    'CRM REGRESSION', 'SALES REGRESSION', 'INVENTORY REGRESSION', 'PROCUREMENT REGRESSION',
    'SUPPLIER REGRESSION', 'LOGISTICS REGRESSION', 'ACCOUNTING REGRESSION', 'INTELLIGENCE REGRESSION',
    'ACTION REGRESSION', 'GOVERNANCE REGRESSION', 'PROACTIVE REGRESSION', 'LEARNING REGRESSION',
];
$byName = [];
foreach ($results as $r) {
    $byName[$r['name']] = !empty($r['ok']);
}
foreach ($gates as $gate) {
    echo (!empty($byName[$gate]) ? 'PASS' : 'FAIL') . ": {$gate}\n";
}
exit($failed > 0 ? 1 : 0);
