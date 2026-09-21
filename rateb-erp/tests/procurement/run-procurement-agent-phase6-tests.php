<?php
declare(strict_types=1);

/**
 * Phase 6 — Unified RATEB ERP Agent Core gates.
 * Run: php rateb-erp/tests/procurement/run-procurement-agent-phase6-tests.php
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
use Rateb\App\Services\ErpAgent;
use Rateb\App\Services\ErpDomainRegistry;
use Rateb\App\Services\ErpToolRegistry;
use Rateb\App\Services\ProcurementAgentContext;
use Rateb\App\Services\ProcurementPolicyGuard;
use Rateb\App\Services\ProcurementToolExecutor;
use Rateb\App\Services\ProcurementToolRegistry;

$results = [];
$pass = static function (string $name) use (&$results): void {
    $results[] = ['name' => $name, 'ok' => true];
    echo "PASS: {$name}\n";
};
$fail = static function (string $name, string $detail = '') use (&$results): void {
    $results[] = ['name' => $name, 'ok' => false, 'detail' => $detail];
    echo "FAIL: {$name}" . ($detail !== '' ? " — {$detail}" : '') . "\n";
};

final class Phase6MockLlm implements LlmClientInterface
{
    /** @var list<array{mode:string,payload?:array}> */
    private array $script;
    private int $i = 0;

    public function __construct(array $script)
    {
        $this->script = $script;
    }

    public function chatCompletion(array $messages, array $tools, ?string $toolChoice = 'auto'): array
    {
        $step = $this->script[$this->i] ?? ['mode' => 'text', 'payload' => ['content' => 'done']];
        $this->i++;
        $mode = (string) ($step['mode'] ?? 'text');
        if ($mode === 'throw') {
            throw new RuntimeException((string) ($step['payload']['code'] ?? 'llm_timeout'));
        }
        if ($mode === 'tools') {
            return [
                'message' => [
                    'role' => 'assistant',
                    'content' => null,
                    'tool_calls' => $step['payload']['tool_calls'] ?? [],
                ],
                'usage' => null,
                'model' => 'mock',
            ];
        }
        return [
            'message' => ['role' => 'assistant', 'content' => (string) ($step['payload']['content'] ?? 'OK')],
            'usage' => null,
            'model' => 'mock',
        ];
    }

    public function getModel(): string
    {
        return 'mock';
    }

    public function getProvider(): string
    {
        return 'mock';
    }
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
$makeCtx = static function (array $perms, bool $sa, int $cid, string $locale = 'en') use ($ref, $ctor, $userId): ProcurementAgentContext {
    $ctx = $ref->newInstanceWithoutConstructor();
    $ctor->invoke($ctx, $userId, $cid, $locale, 'phase6-test', $perms, $sa, ['procurement', 'suppliers']);
    return $ctx;
};
$fullPerms = [
    'procurement.manage', 'procurement.view', 'procurement.create',
    'procurement.update', 'procurement.submit', 'suppliers.manage',
];
$ctx = $makeCtx($fullPerms, true, $companyId, 'en');
$config = require RATEB_ROOT . '/config/agent.php';

// --- UNIFIED CORE / DOMAIN REGISTRY ---
$active = ErpDomainRegistry::getActiveDomains();
$coreOk = class_exists(ErpAgent::class)
    && isset($active['procurement'])
    && ErpDomainRegistry::defaultDomainId() === 'procurement'
    && ErpDomainRegistry::isActive('procurement')
    && ($active['procurement']['runtime'] ?? '') === \Rateb\App\Services\ProcurementAgent::class;
$coreOk ? $pass('UNIFIED CORE') : $fail('UNIFIED CORE');

$registryOk = ErpDomainRegistry::isActive('procurement')
    && ErpDomainRegistry::isActive('hr')
    && ErpDomainRegistry::isActive('inventory')
    && !ErpDomainRegistry::isReservedFuture('hr')
    && ErpDomainRegistry::toolsForDomain('hr') !== []
    && ErpDomainRegistry::toolsForDomain('procurement') !== [];
$registryOk ? $pass('DOMAIN REGISTRY') : $fail('DOMAIN REGISTRY');

$future = ErpDomainRegistry::futureReadiness();
$futureOk = !empty($future['single_agent'])
    && !empty($future['can_add_domain_without_new_agent'])
    && in_array('procurement', $future['active'] ?? [], true)
    && in_array('hr', $future['active'] ?? [], true)
    && ($future['reserved_future'] ?? []) === [];
$futureOk ? $pass('FUTURE DOMAIN READINESS') : $fail('FUTURE DOMAIN READINESS');

// SHARED CONTEXT
$scope = $ctx->conversationScopeKey();
$ctxOtherUser = $makeCtx($fullPerms, true, $companyId, 'en');
// force different session via reflection field
$prop = $ref->getProperty('sessionId');
$prop->setAccessible(true);
$prop->setValue($ctxOtherUser, 'phase6-other-session');
$propUid = $ref->getProperty('userId');
$propUid->setAccessible(true);
$propUid->setValue($ctxOtherUser, $userId + 99);
$sharedCtxOk = str_contains($scope, (string) $companyId)
    && $scope !== $ctxOtherUser->conversationScopeKey()
    && $ctx->normalizedLocale() === 'en'
    && $ctx->can('procurement.view');
$sharedCtxOk ? $pass('SHARED CONTEXT') : $fail('SHARED CONTEXT');

// SHARED POLICY + TOOL ARCHITECTURE
$policyOk = ProcurementPolicyGuard::checkAndExecute([
    'tool' => 'list_purchase_requests',
    'arguments' => ['limit' => 1],
    'request_company_id' => null,
    'request_id' => 'p6-policy',
    'write_confirmed' => false,
], $ctx)['allowed'] === true;
$unknown = ProcurementPolicyGuard::checkAndExecute([
    'tool' => 'drop_database',
    'arguments' => [],
    'request_company_id' => null,
    'request_id' => 'p6-bad',
    'write_confirmed' => true,
], $ctx);
$toolsFacade = ErpToolRegistry::getTools('procurement');
$toolArchOk = $policyOk
    && !$unknown['allowed']
    && ErpToolRegistry::isAllowed('summarize_procurement', 'procurement')
    && ErpToolRegistry::domainForTool('summarize_procurement') === 'procurement'
    && count($toolsFacade) === count(ProcurementToolRegistry::getTools())
    && ErpToolRegistry::getTools('inventory') === [];
$policyOk && !$unknown['allowed'] ? $pass('SHARED POLICY') : $fail('SHARED POLICY');
$toolArchOk ? $pass('TOOL ARCHITECTURE') : $fail('TOOL ARCHITECTURE');

// Domain resolution via ErpAgent
$erp = new ErpAgent($config, new Phase6MockLlm([
    ['mode' => 'text', 'payload' => ['content' => 'Procurement summary ready.']],
]));
$resDefault = $erp->resolveDomain([], $ctx);
$resExplicit = $erp->resolveDomain(['domain' => 'procurement'], $ctx);
$resHr = $erp->resolveDomain(['domain' => 'hr'], $ctx);
$resUnknown = $erp->resolveDomain(['domain' => 'marketing_xyz'], $ctx);
$domainResolveOk = !empty($resDefault['ok'])
    && ($resDefault['domain']['id'] ?? '') === 'procurement'
    && !empty($resExplicit['ok'])
    && !empty($resHr['ok']) && ($resHr['domain']['id'] ?? '') === 'hr'
    && empty($resUnknown['ok']) && ($resUnknown['error_code'] ?? '') === 'domain_unknown';
if (!$domainResolveOk) {
    $fail('UNIFIED CORE_domain_resolve', json_encode([$resDefault, $resHr, $resUnknown]));
}

// Procurement through unified core
$through = $erp->process([
    'message' => 'hello',
    'request_id' => 'p6-core-' . bin2hex(random_bytes(3)),
    'history' => [],
    'domain' => 'procurement',
], $ctx);
$throughOk = ($through['agent'] ?? '') === ErpAgent::AGENT_ID
    && ($through['domain'] ?? '') === 'procurement'
    && is_string($through['response'] ?? null)
    && $through['response'] !== '';
if ($throughOk && $domainResolveOk) {
    // already passed UNIFIED CORE; reinforce
} elseif (!$throughOk) {
    $fail('UNIFIED CORE_runtime', json_encode($through));
}

// ERROR HANDLING via unified core
$erpFail = new ErpAgent($config, new Phase6MockLlm([
    ['mode' => 'throw', 'payload' => ['code' => 'llm_timeout']],
]));
$err = $erpFail->process([
    'message' => 'test timeout',
    'request_id' => 'p6-err-' . bin2hex(random_bytes(3)),
    'domain' => 'procurement',
], $ctx);
$errorOk = ($err['response'] ?? '') !== ''
    && stripos((string) $err['response'], 'RuntimeException') === false
    && (int) ($err['observability']['llm_failures'] ?? 0) >= 1;
$errorOk ? $pass('ERROR HANDLING') : $fail('ERROR HANDLING');

// WRITE CONFIRM through unified core
$writeArgs = ['title' => 'Phase6 PR ' . date('His')];
$confirmKey = 'create_draft_purchase_request:' . md5((string) json_encode($writeArgs, JSON_UNESCAPED_UNICODE));
$erpConfirm = new ErpAgent($config, new Phase6MockLlm([
    ['mode' => 'tools', 'payload' => ['tool_calls' => [[
        'id' => 'c1',
        'type' => 'function',
        'function' => [
            'name' => 'create_draft_purchase_request',
            'arguments' => json_encode($writeArgs, JSON_UNESCAPED_UNICODE),
        ],
    ]]]],
]));
$rConfirm = $erpConfirm->process([
    'message' => 'create',
    'request_id' => 'p6-wc-' . bin2hex(random_bytes(3)),
    'domain' => 'procurement',
], $ctx);
$blocked = (($rConfirm['tool_calls'][0]['result']['error_code'] ?? '') === 'write_confirmation_required')
    || (($rConfirm['pending_confirmations'][0]['confirm_key'] ?? '') === $confirmKey);

$erpWrite = new ErpAgent($config, new Phase6MockLlm([
    ['mode' => 'tools', 'payload' => ['tool_calls' => [[
        'id' => 'w1',
        'type' => 'function',
        'function' => [
            'name' => 'create_draft_purchase_request',
            'arguments' => json_encode($writeArgs, JSON_UNESCAPED_UNICODE),
        ],
    ]]]],
    ['mode' => 'text', 'payload' => ['content' => 'Created.']],
]));
$reqWrite = 'p6-write-' . bin2hex(random_bytes(3));
$rWrite = $erpWrite->process([
    'message' => 'create confirmed',
    'request_id' => $reqWrite,
    'domain' => 'procurement',
    'confirmed_writes' => [$confirmKey],
], $ctx);
$createdId = (int) ($rWrite['tool_calls'][0]['result']['data']['id'] ?? 0);
$writeOk = $blocked && !empty($rWrite['tool_calls'][0]['result']['success']) && $createdId > 0;
if ($createdId > 0) {
    ProcurementToolExecutor::execute('cancel_purchase_request', ['id' => $createdId, 'reason' => 'phase6 cleanup'], $ctx);
}
$writeOk ? $pass('WRITE CONFIRM') : $fail('WRITE CONFIRM');

// AUDIT
$auditCount = (int) $pdo->query(
    'SELECT COUNT(*) FROM rateb_agent_audit_events WHERE request_id = ' . $pdo->quote($reqWrite) . ' AND company_id = ' . (int) $companyId
)->fetchColumn();
($auditCount > 0 && !empty($rWrite['audit'])) ? $pass('AUDIT') : $fail('AUDIT', (string) $auditCount);

// Procurement regression tools
$read = ProcurementToolExecutor::execute('list_purchase_requests', ['limit' => 3], $ctx);
$analysis = ProcurementToolExecutor::execute('summarize_procurement', [], $ctx);
$intel = ProcurementToolExecutor::execute('analyze_procurement_intelligence', ['limit' => 5], $ctx);
$adv = ProcurementToolExecutor::execute('analyze_advanced_procurement_operations', ['limit' => 5], $ctx);
$guide = ProcurementToolExecutor::execute('get_procurement_operational_guidance', ['limit' => 5], $ctx);
$appr = ProcurementToolExecutor::execute('list_pending_approvals', ['limit' => 5], $ctx);

!empty($read['success']) ? $pass('PROCUREMENT READ') : $fail('PROCUREMENT READ');
!empty($analysis['success']) && !empty($intel['success']) ? $pass('PROCUREMENT ANALYSIS') : $fail('PROCUREMENT ANALYSIS');
!empty($adv['success']) && isset($adv['data']['executive_summary']) ? $pass('ADVANCED INTELLIGENCE') : $fail('ADVANCED INTELLIGENCE');
!empty($guide['success']) && !empty($guide['data']['guards']['never_auto_write']) ? $pass('OPERATIONAL GUIDANCE') : $fail('OPERATIONAL GUIDANCE');
!empty($appr['success']) ? $pass('APPROVALS') : $fail('APPROVALS');

// SECURITY
$noPerm = $makeCtx([], false, $companyId);
$denied = ProcurementPolicyGuard::checkAndExecute([
    'tool' => 'list_purchase_requests',
    'arguments' => ['limit' => 1],
    'request_company_id' => null,
    'request_id' => 'p6-sec',
    'write_confirmed' => false,
], $noPerm);
(!$denied['allowed'] && ($denied['error_code'] ?? '') === 'permission_denied') ? $pass('SECURITY') : $fail('SECURITY');

// TENANT
$otherCompany = (int) $pdo->query(
    'SELECT id FROM rateb_companies WHERE id <> ' . (int) $companyId . ' ORDER BY id ASC LIMIT 1'
)->fetchColumn();
$tenantOk = false;
if ($otherCompany > 0) {
    $foreignPr = (int) $pdo->query(
        'SELECT id FROM rateb_purchase_requests WHERE company_id = ' . (int) $otherCompany . ' ORDER BY id DESC LIMIT 1'
    )->fetchColumn();
    if ($foreignPr > 0) {
        $iso = ProcurementToolExecutor::execute('get_purchase_request', ['id' => $foreignPr], $ctx);
        $tenantOk = empty($iso['success']);
    } else {
        $tenantOk = true;
    }
} else {
    $tenantOk = empty(ProcurementToolExecutor::execute('get_purchase_request', ['id' => 999999991], $ctx)['success']);
}
$mismatch = ProcurementPolicyGuard::checkAndExecute([
    'tool' => 'list_purchase_requests',
    'arguments' => ['limit' => 1],
    'request_company_id' => $companyId + 99999,
    'request_id' => 'p6-mismatch',
    'write_confirmed' => false,
], $ctx);
$tenantOk = $tenantOk && !$mismatch['allowed'] && ($mismatch['error_code'] ?? '') === 'tenant_mismatch';
$tenantOk ? $pass('TENANT ISOLATION') : $fail('TENANT ISOLATION');

// AR / EN
$prev = $_SESSION['rateb_locale'] ?? 'en';
$_SESSION['rateb_locale'] = 'ar';
if (function_exists('rateb_set_locale')) {
    rateb_set_locale('ar');
}
$ar = __('ai_tool_err_write_confirmation_required');
$_SESSION['rateb_locale'] = 'en';
if (function_exists('rateb_set_locale')) {
    rateb_set_locale('en');
}
$en = __('ai_tool_err_write_confirmation_required');
$_SESSION['rateb_locale'] = $prev;
(is_string($ar) && mb_strpos($ar, 'تأكيد') !== false) ? $pass('AR') : $fail('AR', (string) $ar);
(is_string($en) && stripos($en, 'Confirmation') !== false) ? $pass('EN') : $fail('EN', (string) $en);

// REGRESSION — procurement tools + unified wiring + no multi-agent
$regOk = ProcurementToolRegistry::isAllowed('analyze_advanced_procurement_operations')
    && ProcurementToolRegistry::isAllowed('get_procurement_operational_guidance')
    && class_exists(ErpAgent::class)
    && count(ErpDomainRegistry::activeDomainIds()) === 1
    && ErpDomainRegistry::activeDomainIds()[0] === 'procurement'
    && !empty($config['agent']['require_confirmation_for_write']);
$regOk ? $pass('REGRESSION') : $fail('REGRESSION');

$failed = count(array_filter($results, static fn($r) => empty($r['ok'])));
$total = count($results);
echo "\nTOTAL {$total}  FAIL {$failed}\n";

$gates = [
    'UNIFIED CORE', 'DOMAIN REGISTRY', 'SHARED CONTEXT', 'SHARED POLICY', 'TOOL ARCHITECTURE', 'FUTURE DOMAIN READINESS',
    'PROCUREMENT READ', 'PROCUREMENT ANALYSIS', 'ADVANCED INTELLIGENCE', 'OPERATIONAL GUIDANCE', 'WRITE CONFIRM',
    'APPROVALS', 'AUDIT', 'ERROR HANDLING', 'AR', 'EN', 'SECURITY', 'TENANT ISOLATION', 'REGRESSION',
];
echo "\n=== PHASE 6 GATES ===\n";
foreach ($gates as $gate) {
    $hits = array_values(array_filter($results, static fn($r) => $r['name'] === $gate || str_starts_with($r['name'], $gate)));
    $ok = $hits !== [] && count(array_filter($hits, static fn($r) => !empty($r['ok']))) === count($hits);
    echo ($ok ? 'PASS' : 'FAIL') . ": {$gate}\n";
}

exit($failed > 0 ? 1 : 0);
