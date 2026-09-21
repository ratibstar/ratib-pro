<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\Database;
use Rateb\App\Services\Agent\LlmClientInterface;

/**
 * Unified RATEB ERP Agent Core
 *
 * One ERP agent. Domains plug into this runtime.
 * Phase 9: cross-domain orchestration (Supplier ↔ Procurement ↔ Inventory)
 * without creating a second agent.
 */
final class ErpAgent
{
    public const AGENT_ID = 'rateb_erp_agent';
    public const MODE_CROSS = 'cross';

    private array $config;
    private ?LlmClientInterface $llmClient;

    public function __construct(array $config = [], ?LlmClientInterface $llmClient = null)
    {
        $this->config = $config;
        $this->llmClient = $llmClient;
    }

    /**
     * @param array{
     *     message: string,
     *     request_id?: string,
     *     history?: array,
     *     confirmed_writes?: list<string>,
     *     domain?: string,
     *     conversation_scope?: string
     * } $input
     * @return array{
     *     response: string,
     *     tool_calls: array,
     *     pending_confirmations: array,
     *     audit: array,
     *     domain: string,
     *     agent: string,
     *     observability?: array,
     *     orchestration?: array
     * }
     */
    public function process(array $input, ProcurementAgentContext $ctx): array
    {
        $requestId = (string) ($input['request_id'] ?? bin2hex(random_bytes(16)));
        $input['request_id'] = $requestId;

        if ($ctx->companyId < 1 || $ctx->userId < 1) {
            return $this->domainFailureResponse($ctx, $requestId, 'unauthorized', '');
        }

        $message = (string) ($input['message'] ?? '');
        $explicit = strtolower(trim((string) ($input['domain'] ?? '')));
        // Never force procurement: empty explicit → keyword/intent routing
        $intent = ErpOrchestrationPlanner::detectIntent(
            $message,
            $ctx,
            ($explicit !== '' && $explicit !== 'auto') ? $explicit : null
        );

        $scopeKey = (string) ($input['conversation_scope'] ?? $ctx->conversationScopeKey());
        $history = is_array($input['history'] ?? null) ? $input['history'] : [];
        $confirmedWrites = is_array($input['confirmed_writes'] ?? null) ? $input['confirmed_writes'] : [];
        $pending = ErpActionPlanner::loadPendingState($scopeKey);
        $turn = ErpActionPlanner::resolveConversationTurn($message, $ctx, $history, $pending, $confirmedWrites);

        if (($turn['mode'] ?? '') === 'reject') {
            ErpActionPlanner::clearPendingState($scopeKey);
            $gov = ErpGovernanceLayer::evaluate($message, $intent, $ctx, $this->config, null, false);
            $gov['execution_level'] = ErpGovernanceLayer::LEVEL_READ_ONLY;
            return $this->finalizeWithGovernance([
                'response' => (string) ($turn['response'] ?? ''),
                'tool_calls' => [],
                'pending_confirmations' => [],
                'audit' => [],
                'domain' => 'action',
                'agent' => self::AGENT_ID,
                'observability' => ['success' => true, 'duration_ms' => 0],
                '_started_at' => microtime(true),
            ], $gov, $ctx, $requestId);
        }

        if (($turn['mode'] ?? '') === 'stale' || ($turn['mode'] ?? '') === 'already_executed') {
            // Keep VERIFIED pending on duplicate confirm so further clicks stay idempotent.
            // Only clear when explicitly requested (e.g. stale tenant mismatch).
            if (($turn['mode'] ?? '') !== 'already_executed' && !empty($turn['clear_pending'])) {
                ErpActionPlanner::clearPendingState($scopeKey);
            }
            $gov = ErpGovernanceLayer::evaluate($message, $intent, $ctx, $this->config, null, false);
            $gov['execution_level'] = ($turn['mode'] ?? '') === 'already_executed'
                ? ErpGovernanceLayer::LEVEL_CONFIRMED_WRITE
                : ErpGovernanceLayer::LEVEL_READ_ONLY;
            return $this->finalizeWithGovernance([
                'response' => (string) ($turn['response'] ?? ''),
                'tool_calls' => [],
                'pending_confirmations' => [],
                'audit' => [],
                'domain' => 'action',
                'agent' => self::AGENT_ID,
                'observability' => [
                    'success' => true,
                    'duration_ms' => 0,
                    'conversation_phase' => (string) ($turn['mode'] ?? ''),
                ],
                '_started_at' => microtime(true),
            ], $gov, $ctx, $requestId);
        }

        if (($turn['mode'] ?? '') === 'collect') {
            ErpActionPlanner::savePendingState($scopeKey, is_array($turn['pending'] ?? null) ? $turn['pending'] : []);
            $gov = ErpGovernanceLayer::evaluate($message, array_merge($intent, ['write_intent' => true]), $ctx, $this->config, [
                'actions' => [['class' => ErpActionPlanner::CLASS_WRITE, 'tool' => 'create_draft_purchase_request', 'permission' => 'procurement.manage']],
            ], false);
            $gov['execution_level'] = ErpGovernanceLayer::LEVEL_CONFIRMED_WRITE;
            $gov['confirmation']['required'] = true;
            $gov['policy']['confirmation_required'] = true;
            return $this->finalizeWithGovernance([
                'response' => (string) ($turn['response'] ?? ''),
                'tool_calls' => [],
                'pending_confirmations' => [],
                'audit' => [],
                'domain' => 'procurement',
                'agent' => self::AGENT_ID,
                'observability' => ['success' => true, 'duration_ms' => 0, 'conversation_phase' => 'collecting'],
                '_started_at' => microtime(true),
            ], $gov, $ctx, $requestId);
        }

        if (($turn['mode'] ?? '') === 'propose') {
            ErpActionPlanner::savePendingState($scopeKey, is_array($turn['pending'] ?? null) ? $turn['pending'] : []);
            $plan = is_array($turn['action_plan'] ?? null) ? $turn['action_plan'] : [];
            $gov = ErpGovernanceLayer::evaluate($message, array_merge($intent, ['write_intent' => true]), $ctx, $this->config, $plan, false);
            $pendingConf = [];
            foreach (($plan['actions'] ?? []) as $a) {
                if (!is_array($a)) {
                    continue;
                }
                $pendingConf[] = [
                    'tool' => (string) ($a['tool'] ?? ''),
                    'arguments' => is_array($a['arguments'] ?? null) ? $a['arguments'] : [],
                    'confirm_key' => (string) ($a['confirm_key'] ?? ''),
                    'permission' => (string) ($a['permission'] ?? ''),
                    'class' => (string) ($a['class'] ?? ''),
                    'action_id' => (string) (($a['action_id'] ?? '') ?: ($plan['action_id'] ?? '') ?: (($turn['pending']['action_id'] ?? '') ?: '')),
                    'previous_state' => $a['previous_state'] ?? null,
                    'impact_preview' => [
                        'summary' => $this->actionImpactSummary((string) ($a['tool'] ?? ''), is_array($a['arguments'] ?? null) ? $a['arguments'] : [], $ctx),
                    ],
                ];
            }
            return $this->finalizeWithGovernance([
                'response' => (string) ($turn['response'] ?? ''),
                'tool_calls' => [],
                'pending_confirmations' => $pendingConf,
                'audit' => [],
                'domain' => 'procurement',
                'agent' => self::AGENT_ID,
                'action' => ['plan' => $plan, 'results' => [], 'partial' => false],
                'observability' => ['success' => true, 'duration_ms' => 0, 'conversation_phase' => 'awaiting_confirmation'],
                '_started_at' => microtime(true),
            ], $gov, $ctx, $requestId);
        }

        if (($turn['mode'] ?? '') === 'confirm' && is_array($turn['action_plan'] ?? null)) {
            $input['confirmed_writes'] = is_array($turn['confirmed_writes'] ?? null) ? $turn['confirmed_writes'] : $confirmedWrites;
            $input['_forced_action_plan'] = $turn['action_plan'];
            $intent['write_intent'] = true;
            $gov = ErpGovernanceLayer::evaluate(
                (string) (($turn['action_plan']['intent'] ?? '') ?: $message),
                $intent,
                $ctx,
                $this->config,
                $turn['action_plan'],
                true
            );
            $bound = ErpGovernanceLayer::checkBoundaries(
                $gov,
                $gov['domains'] ?? [],
                $gov['tools'] ?? [],
                0,
                count($turn['action_plan']['actions'] ?? [])
            );
            if (!$bound['ok']) {
                return $this->governanceStopResponse($ctx, $requestId, $gov, $bound, $intent);
            }
            $result = $this->processActions($input, $ctx, $intent, $requestId, $gov);
            // Lifecycle: only VERIFIED (real record) blocks future confirms for this action_id
            if (empty($result['pending_confirmations'])) {
                $executedKeys = is_array($turn['confirmed_writes'] ?? null) ? $turn['confirmed_writes'] : [];
                $verifiedRows = [];
                $failed = false;
                foreach (($result['action']['results'] ?? []) as $resRow) {
                    if (!is_array($resRow)) {
                        continue;
                    }
                    $ok = !empty($resRow['success']) && !empty($resRow['verification']['verified']);
                    if ($ok) {
                        $rid = (int) ($resRow['data']['id'] ?? $resRow['new_state']['id'] ?? 0);
                        $ck = '';
                        foreach (($turn['action_plan']['actions'] ?? []) as $a) {
                            if (is_array($a) && (string) ($a['tool'] ?? '') === (string) ($resRow['tool'] ?? '')) {
                                $ck = (string) ($a['confirm_key'] ?? '');
                                break;
                            }
                        }
                        if ($ck === '' && $executedKeys !== []) {
                            $ck = (string) $executedKeys[0];
                        }
                        $aid = (string) (($turn['pending']['action_id'] ?? '')
                            ?: ($turn['action_plan']['action_id'] ?? '')
                            ?: '');
                        if ($rid > 0 && $ck !== '' && $aid !== '') {
                            $verifiedRows[] = [
                                'action_id' => $aid,
                                'confirm_key' => $ck,
                                'record_id' => $rid,
                            ];
                        }
                    } else {
                        $failed = true;
                    }
                }
                if ($verifiedRows !== []) {
                    ErpActionPlanner::savePendingState($scopeKey, [
                        'phase' => ErpActionPlanner::PHASE_VERIFIED,
                        'executed_keys' => $executedKeys,
                        'verified_actions' => $verifiedRows,
                        'action_id' => (string) ($verifiedRows[0]['action_id'] ?? ''),
                        'company_id' => (int) $ctx->companyId,
                        'confirmations' => [],
                        'parameter_snapshot' => is_array($turn['pending']['parameter_snapshot'] ?? null)
                            ? $turn['pending']['parameter_snapshot']
                            : (is_array($turn['action_plan']['parameter_snapshot'] ?? null)
                                ? $turn['action_plan']['parameter_snapshot']
                                : []),
                    ]);
                } elseif ($failed) {
                    // Keep snapshot for safe retry — never mark EXECUTED/VERIFIED
                    $failPending = is_array($turn['pending'] ?? null) ? $turn['pending'] : [];
                    $failPending['phase'] = ErpActionPlanner::PHASE_FAILED;
                    $failPending['company_id'] = (int) $ctx->companyId;
                    ErpActionPlanner::savePendingState($scopeKey, $failPending);
                } else {
                    ErpActionPlanner::clearPendingState($scopeKey);
                }
            } else {
                ErpActionPlanner::savePendingState($scopeKey, is_array($turn['pending'] ?? null) ? $turn['pending'] : []);
            }
            return $this->finalizeWithGovernance($result, $gov, $ctx, $requestId);
        }

        // Action layer only when NL maps to registered actions / unsupported recommendations.
        // Otherwise keep existing domain/LLM write confirmation path (ProcurementAgent).
        $hasConfirmedWrites = !empty($input['confirmed_writes']) && is_array($input['confirmed_writes']);
        $actionPreview = ErpActionPlanner::buildActionPlan($message, $ctx, null);
        $actionLayerApplicable = (
            (!empty($actionPreview['actions']) || !empty($actionPreview['unsupported']))
            && (
                $hasConfirmedWrites
                || !empty($intent['write_intent'])
                || ErpActionPlanner::hasActionIntent($message)
            )
        );
        if ($actionLayerApplicable) {
            $gov = ErpGovernanceLayer::evaluate($message, $intent, $ctx, $this->config, $actionPreview, $hasConfirmedWrites);
            $bound = ErpGovernanceLayer::checkBoundaries(
                $gov,
                $gov['domains'] ?? [],
                $gov['tools'] ?? [],
                0,
                count($actionPreview['actions'] ?? [])
            );
            if (!$bound['ok']) {
                return $this->governanceStopResponse($ctx, $requestId, $gov, $bound, $intent);
            }
            $result = $this->processActions($input, $ctx, $intent, $requestId, $gov);
            return $this->finalizeWithGovernance($result, $gov, $ctx, $requestId);
        }

        // Cross-domain orchestration (READ/ANALYSIS) — never invents tools/domains.
        if (($intent['mode'] ?? '') === 'cross'
            && count($intent['domains'] ?? []) >= 2
            && empty($intent['write_intent'])
        ) {
            $gov = ErpGovernanceLayer::evaluate($message, $intent, $ctx, $this->config, null, false);
            $domains = array_slice($intent['domains'] ?? [], 0, (int) ($gov['boundaries']['max_domains_per_request'] ?? 6));
            $intent['domains'] = $domains;
            $gov['domains'] = $domains;
            $bound = ErpGovernanceLayer::checkBoundaries($gov, $domains, [], 0, 0);
            if (!$bound['ok']) {
                return $this->governanceStopResponse($ctx, $requestId, $gov, $bound, $intent);
            }
            $result = $this->processCrossDomain($input, $ctx, $intent, $requestId);
            return $this->finalizeWithGovernance($result, $gov, $ctx, $requestId);
        }

        $resolved = $this->resolveDomain($input, $ctx);
        if (!$resolved['ok']) {
            return $this->domainFailureResponse($ctx, $requestId, (string) $resolved['error_code'], (string) ($resolved['domain'] ?? ''));
        }

        /** @var array $domain */
        $domain = $resolved['domain'];
        $domainId = (string) $domain['id'];

        if (!ErpDomainRegistry::isDomainEntitled($domainId, $ctx)) {
            return $this->domainFailureResponse($ctx, $requestId, 'module_not_entitled', $domainId);
        }

        $decorate = static function (array $result, string $domainId, array $resolved, array $intent): array {
            $result['domain'] = $domainId;
            $result['agent'] = self::AGENT_ID;
            $result['domain_selection'] = [
                'resolved' => $domainId,
                'source' => (string) ($resolved['source'] ?? 'default'),
                'active_domains' => ErpDomainRegistry::activeDomainIds(),
                'intent_mode' => (string) ($intent['mode'] ?? 'single'),
                'intent_domains' => $intent['domains'] ?? [],
            ];
            return $result;
        };

        $govSingle = ErpGovernanceLayer::evaluate($message, array_merge($intent, [
            'domains' => [$domainId],
            'mode' => 'single',
        ]), $ctx, $this->config, null, $hasConfirmedWrites);

        // Direct list/module queries → live tools (no LLM invent/refuse/empty).
        if (
            empty($intent['write_intent'])
            && empty($input['confirmed_writes'])
            && $this->isDirectDataRequest($message)
        ) {
            $detIntent = array_merge($intent, [
                'domains' => [$domainId],
                'mode' => 'single',
                'primary' => $domainId,
                'intent_kind' => 'query',
                'decision_support' => false,
            ]);
            $result = $this->processSingleDomainRead($input, $ctx, $detIntent, $domainId, $requestId);
            return $this->finalizeWithGovernance(
                $decorate($result, $domainId, $resolved, $intent),
                $govSingle,
                $ctx,
                $requestId
            );
        }

        if ($domainId === ErpDomainRegistry::DOMAIN_PROCUREMENT) {
            $runtime = new ProcurementAgent($this->config, $this->llmClient);
            $result = $runtime->process($input, $ctx);
            return $this->finalizeWithGovernance(
                $decorate(is_array($result) ? $result : [], $domainId, $resolved, $intent),
                $govSingle,
                $ctx,
                $requestId
            );
        }

        if ($domainId === ErpDomainRegistry::DOMAIN_INVENTORY) {
            $runtime = new InventoryAgent($this->config, $this->llmClient);
            $result = $runtime->process($input, $ctx);
            return $this->finalizeWithGovernance(
                $decorate(is_array($result) ? $result : [], $domainId, $resolved, $intent),
                $govSingle,
                $ctx,
                $requestId
            );
        }

        if ($domainId === ErpDomainRegistry::DOMAIN_SUPPLIERS) {
            $runtime = new SupplierAgent($this->config, $this->llmClient);
            $result = $runtime->process($input, $ctx);
            return $this->finalizeWithGovernance(
                $decorate(is_array($result) ? $result : [], $domainId, $resolved, $intent),
                $govSingle,
                $ctx,
                $requestId
            );
        }

        if ($domainId === ErpDomainRegistry::DOMAIN_SALES) {
            // No SalesAgent — reuse hardened ProcurementAgent engine with Sales tools only.
            $cfg = $this->config;
            $agentCfg = is_array($cfg['agent'] ?? null) ? $cfg['agent'] : [];
            $agentCfg['tool_registry'] = SalesToolRegistry::class;
            $agentCfg['tool_executor'] = SalesToolExecutor::class;
            $agentCfg['system_prompt'] = (string) ($agentCfg['sales_system_prompt']
                ?? 'You are the RATEB ERP Agent operating in the Sales domain (POS). Use only approved sales tools. Never invent sales numbers. Tenant-scope every operation. Speak only in the UI language. Prefer analyze_sales, get_sales_inventory_links, get_sales_procurement_links, get_sales_supplier_links, and analyze_sales_cross_domain for analysis. Never auto-execute writes — sales mutations are not available via this agent.');
            $cfg['agent'] = $agentCfg;
            $runtime = new ProcurementAgent($cfg, $this->llmClient);
            $result = $runtime->process($input, $ctx);
            return $this->finalizeWithGovernance(
                $decorate(is_array($result) ? $result : [], $domainId, $resolved, $intent),
                $govSingle,
                $ctx,
                $requestId
            );
        }

        if ($domainId === ErpDomainRegistry::DOMAIN_CRM) {
            // No CrmAgent — reuse hardened ProcurementAgent engine with CRM tools only.
            $cfg = $this->config;
            $agentCfg = is_array($cfg['agent'] ?? null) ? $cfg['agent'] : [];
            $agentCfg['tool_registry'] = CrmToolRegistry::class;
            $agentCfg['tool_executor'] = CrmToolExecutor::class;
            $agentCfg['system_prompt'] = (string) ($agentCfg['crm_system_prompt']
                ?? 'You are the RATEB ERP Agent operating in the CRM / Customer domain. Use only approved CRM tools. Never invent customer numbers. Tenant-scope every operation. Speak only in the UI language. Prefer analyze_crm, get_crm_sales_links, get_crm_inventory_links, get_crm_procurement_links, get_crm_supplier_links, and analyze_crm_commercial_intelligence for analysis. Never auto-execute writes — CRM mutations are not available via this agent.');
            $cfg['agent'] = $agentCfg;
            $runtime = new ProcurementAgent($cfg, $this->llmClient);
            $result = $runtime->process($input, $ctx);
            return $this->finalizeWithGovernance(
                $decorate(is_array($result) ? $result : [], $domainId, $resolved, $intent),
                $govSingle,
                $ctx,
                $requestId
            );
        }

        if ($domainId === ErpDomainRegistry::DOMAIN_LOGISTICS) {
            // No LogisticsAgent — reuse hardened ProcurementAgent engine with Logistics tools only.
            $cfg = $this->config;
            $agentCfg = is_array($cfg['agent'] ?? null) ? $cfg['agent'] : [];
            $agentCfg['tool_registry'] = LogisticsToolRegistry::class;
            $agentCfg['tool_executor'] = LogisticsToolExecutor::class;
            $agentCfg['system_prompt'] = (string) ($agentCfg['logistics_system_prompt']
                ?? 'You are the RATEB ERP Agent operating in the Logistics domain. Use only approved logistics tools. Never invent shipment numbers. Tenant-scope every operation. Speak only in the UI language. Prefer analyze_logistics, get_logistics_crm_links, get_logistics_sales_links, get_logistics_inventory_links, get_logistics_procurement_links, get_logistics_supplier_links, and analyze_logistics_end_to_end for analysis. Never auto-execute writes — logistics mutations are not available via this agent.');
            $cfg['agent'] = $agentCfg;
            $runtime = new ProcurementAgent($cfg, $this->llmClient);
            $result = $runtime->process($input, $ctx);
            return $this->finalizeWithGovernance(
                $decorate(is_array($result) ? $result : [], $domainId, $resolved, $intent),
                $govSingle,
                $ctx,
                $requestId
            );
        }

        if ($domainId === ErpDomainRegistry::DOMAIN_ACCOUNTING) {
            // No AccountingAgent — reuse hardened ProcurementAgent engine with Accounting tools only.
            $cfg = $this->config;
            $agentCfg = is_array($cfg['agent'] ?? null) ? $cfg['agent'] : [];
            $agentCfg['tool_registry'] = AccountingToolRegistry::class;
            $agentCfg['tool_executor'] = AccountingToolExecutor::class;
            $agentCfg['system_prompt'] = (string) ($agentCfg['accounting_system_prompt']
                ?? 'You are the RATEB ERP Agent operating in the Accounting / Financial domain. Use only approved accounting tools. Never invent balances, invoices, or journals. Tenant-scope every operation. Speak only in the UI language. Prefer analyze_accounting, analyze_financial_intelligence, get_accounting_sales_links, get_accounting_procurement_links, get_accounting_supplier_links, get_accounting_inventory_links, and get_accounting_logistics_links. Never create or mutate ledger entries directly — only submit_journal_for_approval after explicit confirmation via existing AccountingService workflow.');
            $cfg['agent'] = $agentCfg;
            $runtime = new ProcurementAgent($cfg, $this->llmClient);
            $result = $runtime->process($input, $ctx);
            return $this->finalizeWithGovernance(
                $decorate(is_array($result) ? $result : [], $domainId, $resolved, $intent),
                $govSingle,
                $ctx,
                $requestId
            );
        }

        if ($domainId === ErpDomainRegistry::DOMAIN_EXECUTIVE) {
            // No ExecutiveAgent — reuse ProcurementAgent engine with Executive tools only.
            $cfg = $this->config;
            $agentCfg = is_array($cfg['agent'] ?? null) ? $cfg['agent'] : [];
            $agentCfg['tool_registry'] = ExecutiveToolRegistry::class;
            $agentCfg['tool_executor'] = ExecutiveToolExecutor::class;
            $agentCfg['system_prompt'] = (string) ($agentCfg['executive_system_prompt']
                ?? 'You are the RATEB ERP Agent in Executive Intelligence mode. Use only approved executive tools. Never invent KPIs or forecasts. Present evidence-backed summaries only. Never auto-execute writes — route actions through confirmation and governance.');
            $cfg['agent'] = $agentCfg;
            $runtime = new ProcurementAgent($cfg, $this->llmClient);
            $result = $runtime->process($input, $ctx);
            return $this->finalizeWithGovernance(
                $decorate(is_array($result) ? $result : [], $domainId, $resolved, $intent),
                $govSingle,
                $ctx,
                $requestId
            );
        }

        // Remaining active domains (HR, Recruitment, Projects, …) share the same hardened runtime.
        $result = $this->runDomainWithTools($domain, $input, $ctx);
        if ($result === null) {
            return $this->domainFailureResponse($ctx, $requestId, 'domain_not_implemented', $domainId);
        }
        return $this->finalizeWithGovernance(
            $decorate($result, $domainId, $resolved, $intent),
            $govSingle,
            $ctx,
            $requestId
        );
    }

    /**
     * Action & Automation layer — plans/executes only registered WRITE tools safely.
     *
     * @param array<string, mixed> $input
     * @param array<string, mixed> $intent
     * @return array<string, mixed>
     */
    private function processActions(
        array $input,
        ProcurementAgentContext $ctx,
        array $intent,
        string $requestId,
        ?array $governance = null
    ): array {
        $startedAt = microtime(true);
        $message = (string) ($input['message'] ?? '');
        $confirmedWrites = is_array($input['confirmed_writes'] ?? null) ? $input['confirmed_writes'] : [];
        $auditEntries = [];
        $toolCalls = [];
        $pendingConfirmations = [];
        $executionResults = [];
        $executedKeys = [];
        $partial = false;
        $maxWrites = max(1, min(3, (int) (($governance['boundaries']['max_writes_per_request'] ?? null)
            ?? ($this->config['agent']['max_writes_per_turn'] ?? $this->config['agent']['max_writes_per_request'] ?? 1))));
        $writesDone = 0;

        // Controlled autonomy never bypasses confirmation while policy requires it.
        $autonomy = is_array($governance['controlled_autonomy'] ?? null) ? $governance['controlled_autonomy'] : [];
        if (!empty($autonomy['blocked']) || empty($autonomy['allowed'])) {
            // keep confirmedWrites as the only write gate
        } else {
            // Even if foundation allowed autonomy, still respect require_confirmation_for_write.
            if (!empty($this->config['agent']['require_confirmation_for_write'])) {
                $autonomy['allowed'] = false;
                $autonomy['blocked'] = true;
                $autonomy['reason'] = 'confirmation_policy_blocks_unattended_write';
                if (is_array($governance)) {
                    $governance['controlled_autonomy'] = $autonomy;
                }
            }
        }

        // Optional light intelligence for shortage → PR suggestions (READ only, no write).
        $intelligence = null;
        if (empty($confirmedWrites) && preg_match('/(ناقص|نقص|shortfall|low\s*stock|منتج)/ui', $message) === 1) {
            $readIntent = $intent;
            $readIntent['write_intent'] = false;
            $readIntent['decision_support'] = true;
            $readIntent['intent_kind'] = 'decision';
            if (($readIntent['domains'] ?? []) === [] || count($readIntent['domains']) < 2) {
                $readIntent['domains'] = array_values(array_filter(
                    ErpDomainRegistry::activeDomainIds(),
                    static function (string $id) use ($ctx): bool {
                        $meta = ErpDomainRegistry::resolve($id);
                        return $meta !== null && $ctx->moduleEnabled((string) $meta['module']);
                    }
                ));
                $readIntent['domains'] = array_slice($readIntent['domains'], 0, 4);
                $readIntent['mode'] = count($readIntent['domains']) >= 2 ? 'cross' : 'single';
            }
            if (($readIntent['mode'] ?? '') === 'cross') {
                $planRead = ErpOrchestrationPlanner::buildPlan($readIntent, $ctx, 3);
                $confirmed = [];
                $failed = [];
                foreach ($planRead as $step) {
                    $toolName = (string) ($step['tool'] ?? '');
                    $args = is_array($step['arguments'] ?? null) ? $step['arguments'] : [];
                    $domainId = (string) ($step['domain'] ?? '');
                    $policy = ProcurementPolicyGuard::checkAndExecute([
                        'tool' => $toolName,
                        'arguments' => $args,
                        'request_company_id' => null,
                        'request_id' => $requestId,
                        'write_confirmed' => false,
                    ], $ctx);
                    if (!$policy['allowed']) {
                        $failed[] = [
                            'tool' => $toolName,
                            'domain' => $domainId,
                            'purpose' => (string) ($step['purpose'] ?? ''),
                            'error_code' => (string) ($policy['error_code'] ?? 'permission_denied'),
                        ];
                        continue;
                    }
                    $res = $this->executeRegisteredTool($toolName, $args, $ctx);
                    if (!empty($res['success'])) {
                        $confirmed[] = [
                            'tool' => $toolName,
                            'domain' => $domainId,
                            'purpose' => (string) ($step['purpose'] ?? ''),
                            'data' => $res['data'] ?? null,
                        ];
                    } else {
                        $failed[] = [
                            'tool' => $toolName,
                            'domain' => $domainId,
                            'purpose' => (string) ($step['purpose'] ?? ''),
                            'error_code' => (string) ($res['error_code'] ?? 'tool_exception'),
                        ];
                    }
                }
                $base = $this->buildCrossDomainIntelligence($confirmed, $failed, $readIntent, $ctx);
                $intelligence = ErpIntelligenceLayer::enrich($base, $confirmed, $failed, $readIntent, $ctx);
            }
        }

        $plan = is_array($input['_forced_action_plan'] ?? null)
            ? $input['_forced_action_plan']
            : ErpActionPlanner::buildActionPlan($message, $ctx, $intelligence);

        // Phase 22: multi-step workflow coordination (never bypasses ActionPlanner/Governance)
        $workflow = null;
        $workflowIntent = ErpOrchestrationPlanner::hasWorkflowIntent($message)
            || count($plan['actions'] ?? []) >= 2
            || (!empty($plan['actions']) && preg_match('/(ناقص|نقص|shortfall|low\s*stock)/ui', $message) === 1);
        if ($workflowIntent) {
            $workflow = ErpMultiStepWorkflowLayer::plan($message, $ctx, $intelligence, ['persist' => true]);
            $workflow['request_id'] = $requestId;
            ErpMultiStepWorkflowLayer::saveWorkflow((int) $ctx->companyId, $workflow);
            $workflow = ErpMultiStepWorkflowLayer::authorize($workflow, $ctx);
            ErpMultiStepWorkflowLayer::saveWorkflow((int) $ctx->companyId, $workflow);
            $auditEntries[] = $this->logAudit($ctx, $requestId, 'multi_step_workflow_plan', [
                'workflow_id' => $workflow['workflow_id'] ?? null,
                'status' => $workflow['status'] ?? null,
                'step_count' => count($workflow['steps'] ?? []),
                'requires_confirmation' => !empty($workflow['requires_confirmation']),
            ], [
                'event_type' => 'multi_step_workflow_plan',
                'error_code' => null,
                'action_layer' => true,
            ], 'success');
        }

        $auditEntries[] = $this->logAudit($ctx, $requestId, 'action_plan', [
            'intent' => $plan['intent'] ?? '',
            'domains' => $plan['domains'] ?? [],
            'actions' => array_map(static fn($a) => [
                'tool' => $a['tool'] ?? '',
                'class' => $a['class'] ?? '',
                'confirm_key' => $a['confirm_key'] ?? '',
            ], $plan['actions'] ?? []),
            'unsupported' => $plan['unsupported'] ?? [],
            'requires_confirmation' => !empty($plan['requires_confirmation']),
        ], [
            'event_type' => 'action_plan',
            'error_code' => null,
            'action_layer' => true,
        ], 'success');

        foreach (($plan['actions'] ?? []) as $action) {
            if (!is_array($action)) {
                continue;
            }
            $toolName = (string) ($action['tool'] ?? '');
            $arguments = is_array($action['arguments'] ?? null) ? $action['arguments'] : [];
            $class = (string) ($action['class'] ?? ErpActionPlanner::classifyTool($toolName));
            $confirmKey = (string) ($action['confirm_key'] ?? ErpActionPlanner::confirmKey($toolName, $arguments));
            $isWrite = in_array($class, [ErpActionPlanner::CLASS_WRITE, ErpActionPlanner::CLASS_SENSITIVE_WRITE], true);

            if ($class === ErpActionPlanner::CLASS_RECOMMENDATION) {
                continue;
            }

            // Unsupported tools never execute
            if ($toolName === '' || !(
                ErpToolRegistry::isAllowed($toolName)
                || ProcurementToolRegistry::isAllowed($toolName)
                || AccountingToolRegistry::isAllowed($toolName)
            )) {
                $executionResults[] = [
                    'tool' => $toolName,
                    'success' => false,
                    'error_code' => 'tool_not_allowed',
                ];
                $partial = true;
                break;
            }

            if ($isWrite) {
                $writeConfirmed = in_array($toolName, $confirmedWrites, true)
                    || in_array($confirmKey, $confirmedWrites, true);

                if (!$writeConfirmed) {
                    $pendingConfirmations[] = [
                        'tool' => $toolName,
                        'arguments' => $arguments,
                        'confirm_key' => $confirmKey,
                        'permission' => (string) ($action['permission'] ?? ''),
                        'class' => $class,
                        'previous_state' => $action['previous_state'] ?? null,
                        'impact_preview' => [
                            'summary' => $this->actionImpactSummary($toolName, $arguments, $ctx),
                        ],
                    ];
                    $auditEntries[] = $this->logAudit($ctx, $requestId, $toolName, array_merge($arguments, [
                        '_idempotency_key' => $confirmKey,
                    ]), [
                        'event_type' => 'confirmation_required',
                        'error_code' => 'write_confirmation_required',
                        'write_confirmed' => false,
                        'action_layer' => true,
                        'class' => $class,
                    ], 'denied');
                    continue;
                }

                if (isset($executedKeys[$confirmKey]) || ErpActionPlanner::wasAlreadyExecuted($ctx, $requestId, $confirmKey)) {
                    $executionResults[] = [
                        'tool' => $toolName,
                        'success' => false,
                        'error_code' => 'duplicate_action',
                        'verification' => ['verified' => false, 'incomplete' => false, 'message' => 'duplicate_blocked'],
                    ];
                    $toolCalls[] = [
                        'tool' => $toolName,
                        'arguments' => $arguments,
                        'result' => ['success' => false, 'error_code' => 'duplicate_action'],
                        'audit_status' => 'denied',
                    ];
                    $auditEntries[] = $this->logAudit($ctx, $requestId, $toolName, array_merge($arguments, [
                        '_idempotency_key' => $confirmKey,
                    ]), [
                        'event_type' => 'duplicate_blocked',
                        'error_code' => 'duplicate_action',
                        'action_layer' => true,
                    ], 'denied');
                    $partial = true;
                    break;
                }

                if ($writesDone >= $maxWrites) {
                    $pendingConfirmations[] = [
                        'tool' => $toolName,
                        'arguments' => $arguments,
                        'confirm_key' => $confirmKey,
                        'permission' => (string) ($action['permission'] ?? ''),
                        'class' => $class,
                        'impact_preview' => [
                            'summary' => $this->actionImpactSummary($toolName, $arguments, $ctx),
                        ],
                    ];
                    $partial = true;
                    $auditEntries[] = $this->logAudit($ctx, $requestId, $toolName, $arguments, [
                        'event_type' => 'write_sequence_blocked',
                        'error_code' => 'write_sequence_blocked',
                        'action_layer' => true,
                    ], 'denied');
                    break;
                }

                // State validation / stale protection
                $stateCheck = ErpActionPlanner::validateActionState($action, $ctx);
                if (!$stateCheck['ok']) {
                    $code = (string) ($stateCheck['error_code'] ?? 'stale_state');
                    if (!empty($stateCheck['stale'])) {
                        // STOP → re-read → require new confirmation
                        $fresh = ErpActionPlanner::readStateSnapshot($toolName, $arguments, $ctx);
                        $pendingConfirmations[] = [
                            'tool' => $toolName,
                            'arguments' => $arguments,
                            'confirm_key' => $confirmKey,
                            'permission' => (string) ($action['permission'] ?? ''),
                            'class' => $class,
                            'previous_state' => $fresh,
                            'stale' => true,
                            'impact_preview' => [
                                'summary' => $this->actionImpactSummary($toolName, $arguments, $ctx),
                                'note' => 'state_changed_reconfirm_required',
                            ],
                        ];
                    }
                    $executionResults[] = [
                        'tool' => $toolName,
                        'success' => false,
                        'error_code' => $code,
                        'verification' => ['verified' => false, 'incomplete' => false, 'message' => $code],
                        'current_state' => $stateCheck['current_state'] ?? null,
                    ];
                    $toolCalls[] = [
                        'tool' => $toolName,
                        'arguments' => $arguments,
                        'result' => ['success' => false, 'error_code' => $code],
                        'audit_status' => 'denied',
                    ];
                    $auditEntries[] = $this->logAudit($ctx, $requestId, $toolName, array_merge($arguments, [
                        '_idempotency_key' => $confirmKey,
                        '_previous_state' => $action['previous_state'] ?? null,
                        '_current_state' => $stateCheck['current_state'] ?? null,
                    ]), [
                        'event_type' => 'state_validation_failed',
                        'error_code' => $code,
                        'stale' => !empty($stateCheck['stale']),
                        'action_layer' => true,
                    ], 'denied');
                    $partial = true;
                    break;
                }

                $policy = ProcurementPolicyGuard::checkAndExecute([
                    'tool' => $toolName,
                    'arguments' => $arguments,
                    'request_company_id' => null,
                    'request_id' => $requestId,
                    'write_confirmed' => true,
                ], $ctx);

                if (!$policy['allowed']) {
                    $code = (string) ($policy['error_code'] ?? 'permission_denied');
                    $executionResults[] = [
                        'tool' => $toolName,
                        'success' => false,
                        'error_code' => $code,
                    ];
                    $toolCalls[] = [
                        'tool' => $toolName,
                        'arguments' => $arguments,
                        'result' => ['success' => false, 'error_code' => $code],
                        'audit_status' => 'denied',
                    ];
                    $auditEntries[] = $this->logAudit($ctx, $requestId, $toolName, $arguments, [
                        'event_type' => 'policy_denied',
                        'error_code' => $code,
                        'policy_checks' => $policy['policy_checks'] ?? [],
                        'action_layer' => true,
                    ], 'denied');
                    try {
                        ErpOperationalLearningLayer::recordActionOutcome(
                            $ctx,
                            $action,
                            ['success' => false, 'error_code' => $code],
                            ['verified' => false, 'incomplete' => false, 'message' => 'policy_denied'],
                            [
                                'request_id' => $requestId,
                                'action_id' => 'act_blocked_' . substr(sha1($requestId . '|' . $toolName), 0, 10),
                                'blocked' => true,
                                'recommendation' => $action['recommended_action'] ?? null,
                            ]
                        );
                    } catch (\Throwable $e) {
                        // ignore
                    }
                    $partial = true;
                    break;
                }

                $t0 = microtime(true);
                $result = $this->executeRegisteredTool($toolName, $arguments, $ctx);
                $durationMs = (int) ((microtime(true) - $t0) * 1000);
                $verification = ErpActionPlanner::verifyAction($action, is_array($result) ? $result : [], $ctx);

                $ok = !empty($result['success']);
                if ($ok) {
                    $writesDone++;
                    $executedKeys[$confirmKey] = true;
                } else {
                    $partial = true;
                }

                // If tool said success but verification incomplete — do not claim final PASS
                if ($ok && !empty($verification['incomplete'])) {
                    $ok = false;
                    $partial = true;
                    $result['success'] = false;
                    $result['error_code'] = 'verification_incomplete';
                    $result['verification'] = $verification;
                }

                $executionResults[] = [
                    'tool' => $toolName,
                    'success' => $ok && !empty($verification['verified']),
                    'error_code' => $ok ? null : (string) ($result['error_code'] ?? $result['error'] ?? 'tool_exception'),
                    'data' => $result['data'] ?? null,
                    'verification' => $verification,
                    'previous_state' => $action['previous_state'] ?? null,
                    'new_state' => $verification['new_state'] ?? null,
                ];

                // Phase 22: record step into multi-step workflow (if active)
                if (is_array($workflow) && !empty($workflow['workflow_id'])) {
                    $stepNo = null;
                    foreach (($workflow['steps'] ?? []) as $ws) {
                        if (!is_array($ws)) {
                            continue;
                        }
                        if ((string) ($ws['tool'] ?? '') === $toolName
                            && (string) ($ws['confirm_key'] ?? '') === $confirmKey
                        ) {
                            $stepNo = (int) ($ws['step_no'] ?? 0);
                            break;
                        }
                    }
                    if ($stepNo === null) {
                        foreach (($workflow['steps'] ?? []) as $ws) {
                            if (is_array($ws) && (string) ($ws['tool'] ?? '') === $toolName
                                && in_array(($ws['status'] ?? ''), ['READY', 'PENDING', 'RUNNING'], true)
                            ) {
                                $stepNo = (int) ($ws['step_no'] ?? 0);
                                break;
                            }
                        }
                    }
                    if ($stepNo !== null && $stepNo > 0) {
                        $workflow['request_id'] = $requestId;
                        $workflow = ErpMultiStepWorkflowLayer::recordStepResult(
                            $workflow,
                            $stepNo,
                            is_array($result) ? $result : ['success' => false],
                            $verification,
                            $ctx,
                            []
                        );
                        $executionResults[array_key_last($executionResults)]['workflow'] = [
                            'workflow_id' => $workflow['workflow_id'] ?? null,
                            'status' => $workflow['status'] ?? null,
                            'step_no' => $stepNo,
                        ];
                    }
                }

                try {
                    $learningMeta = [
                        'request_id' => $requestId,
                        'action_id' => 'act_' . substr(sha1($requestId . '|' . $toolName . '|' . $confirmKey), 0, 12),
                        'warning_id' => $action['warning_id'] ?? ($action['arguments']['_warning_id'] ?? null),
                        'recommendation' => $action['recommended_action'] ?? ($action['purpose'] ?? null),
                    ];
                    $learning = ErpOperationalLearningLayer::recordActionOutcome(
                        $ctx,
                        $action,
                        is_array($result) ? $result : [],
                        $verification,
                        $learningMeta
                    );
                    $executionResults[array_key_last($executionResults)]['learning'] = [
                        'outcome_id' => $learning['outcome']['outcome_id'] ?? null,
                        'outcome_status' => $learning['outcome']['outcome_status'] ?? null,
                        'learned' => !empty($learning['learned']),
                    ];
                } catch (\Throwable $e) {
                    // Learning must never break action execution
                }
                $toolCalls[] = [
                    'tool' => $toolName,
                    'domain' => (string) ($action['domain'] ?? ErpToolRegistry::domainForTool($toolName) ?? 'procurement'),
                    'arguments' => $arguments,
                    'result' => $result,
                    'audit_status' => ($ok && !empty($verification['verified'])) ? 'success' : 'error',
                ];
                $auditEntries[] = $this->logAudit($ctx, $requestId, $toolName, array_merge($arguments, [
                    '_idempotency_key' => $confirmKey,
                    '_write_confirmed' => true,
                    '_previous_state' => $action['previous_state'] ?? null,
                    '_new_state' => $verification['new_state'] ?? null,
                    '_verification' => $verification,
                ]), [
                    'event_type' => ($ok && !empty($verification['verified'])) ? 'action_success' : 'action_failure',
                    'error_code' => $ok ? null : (string) ($result['error_code'] ?? 'tool_exception'),
                    'duration_ms' => $durationMs,
                    'policy_checks' => $policy['policy_checks'] ?? [],
                    'write_confirmed' => true,
                    'verification' => $verification,
                    'action_layer' => true,
                    'class' => $class,
                ], ($ok && !empty($verification['verified'])) ? 'success' : 'error');

                if (!$ok || empty($verification['verified'])) {
                    // Stop dependent writes on failure / incomplete verification
                    $partial = true;
                    break;
                }
            }
        }

        // Persist pending confirmations so text confirmations (انشئ / نعم) can resume
        $scopeKey = (string) ($input['conversation_scope'] ?? $ctx->conversationScopeKey());
        $forcedPlan = !empty($input['_forced_action_plan']);
        if ($pendingConfirmations !== [] && $scopeKey !== '') {
            $prev = ErpActionPlanner::loadPendingState($scopeKey);
            ErpActionPlanner::savePendingState($scopeKey, array_merge($prev, [
                'phase' => ErpActionPlanner::PHASE_PENDING_CONFIRMATION,
                'confirmations' => $pendingConfirmations,
                'action_plan' => $plan,
                'intent' => (string) ($plan['intent'] ?? $message),
                'company_id' => (int) $ctx->companyId,
                // Never inherit executed proof into a fresh confirmation_required proposal
                'verified_actions' => [],
                'executed_keys' => [],
            ]));
        } elseif ($pendingConfirmations === [] && $executionResults !== [] && $scopeKey !== '' && !$forcedPlan) {
            // Conversation confirm path owns lifecycle persistence (VERIFIED/FAILED)
            $anyVerified = false;
            foreach ($executionResults as $er) {
                if (is_array($er) && !empty($er['success']) && !empty($er['verification']['verified'])) {
                    $anyVerified = true;
                    break;
                }
            }
            if ($anyVerified) {
                ErpActionPlanner::clearPendingState($scopeKey);
            }
            // On failure keep prior pending so the user can retry confirm safely
        }

        $response = ErpActionPlanner::formatActionResponse(
            $plan,
            $executionResults,
            $pendingConfirmations,
            $ctx,
            $partial
        );

        if (is_array($workflow) && !empty($workflow['workflow_id'])) {
            if ($confirmedWrites !== [] && in_array(($workflow['status'] ?? ''), [
                ErpMultiStepWorkflowLayer::ST_AUTHORIZED,
                ErpMultiStepWorkflowLayer::ST_PLANNED,
            ], true)) {
                $keys = [];
                foreach ($confirmedWrites as $cw) {
                    if (is_array($cw) && (string) ($cw['confirm_key'] ?? '') !== '') {
                        $keys[] = (string) $cw['confirm_key'];
                    } elseif (is_string($cw) && $cw !== '') {
                        $keys[] = $cw;
                    }
                }
                foreach (($plan['actions'] ?? []) as $a) {
                    if (is_array($a) && (string) ($a['confirm_key'] ?? '') !== '') {
                        $keys[] = (string) $a['confirm_key'];
                    }
                }
                $workflow = ErpMultiStepWorkflowLayer::confirm($workflow, array_values(array_unique($keys)));
            }
            ErpMultiStepWorkflowLayer::saveWorkflow((int) $ctx->companyId, $workflow);
            $response .= "\n\n" . $this->formatWorkflowBrief($workflow, $ctx);
        }

        $auditEntries[] = $this->logAudit($ctx, $requestId, 'action_summary', [
            'pending_count' => count($pendingConfirmations),
            'executed_count' => count($executionResults),
            'partial' => $partial,
            'writes_done' => $writesDone,
            'workflow_id' => is_array($workflow) ? ($workflow['workflow_id'] ?? null) : null,
            'workflow_status' => is_array($workflow) ? ($workflow['status'] ?? null) : null,
        ], [
            'event_type' => 'action_summary',
            'action_layer' => true,
            'error_code' => null,
        ], 'success');

        return [
            'response' => $response,
            'tool_calls' => $toolCalls,
            'pending_confirmations' => $pendingConfirmations,
            'audit' => $auditEntries,
            'domain' => 'action',
            'agent' => self::AGENT_ID,
            'action' => [
                'plan' => $plan,
                'results' => $executionResults,
                'partial' => $partial,
                'intelligence_used' => $intelligence !== null,
                'workflow' => $workflow,
            ],
            'workflow' => $workflow,
            'domain_selection' => [
                'resolved' => 'action',
                'source' => 'action_layer',
                'active_domains' => ErpDomainRegistry::activeDomainIds(),
                'intent_mode' => (string) ($intent['mode'] ?? 'single'),
                'intent_domains' => $intent['domains'] ?? [],
            ],
            'observability' => [
                'request_id' => $requestId,
                'company_id' => $ctx->companyId,
                'user_id' => $ctx->userId,
                'success' => !$partial || $executionResults !== [] || $pendingConfirmations !== [],
                'domain' => 'action',
                'action_layer' => true,
                'workflow_id' => is_array($workflow) ? ($workflow['workflow_id'] ?? null) : null,
                'duration_ms' => (int) ((microtime(true) - $startedAt) * 1000),
                'pending_confirmations' => count($pendingConfirmations),
                'writes_executed' => $writesDone,
            ],
            '_governance_seed' => $governance,
            '_started_at' => $startedAt,
        ];
    }

    /**
     * @param array<string, mixed> $result
     * @param array<string, mixed> $governance
     * @return array<string, mixed>
     */
    private function finalizeWithGovernance(
        array $result,
        array $governance,
        ProcurementAgentContext $ctx,
        string $requestId
    ): array {
        $startedAt = (float) ($result['_started_at'] ?? microtime(true));
        unset($result['_started_at'], $result['_governance_seed']);

        // Cap tool list for boundary check from actual calls
        $toolsUsed = [];
        foreach (($result['tool_calls'] ?? []) as $tc) {
            if (is_array($tc) && !empty($tc['tool'])) {
                $toolsUsed[] = (string) $tc['tool'];
            }
        }
        foreach (($governance['tools'] ?? []) as $t) {
            $toolsUsed[] = (string) $t;
        }
        $writes = (int) (($result['observability']['writes_executed'] ?? 0));
        $bound = ErpGovernanceLayer::checkBoundaries(
            $governance,
            is_array($governance['domains'] ?? null) ? $governance['domains'] : [],
            array_values(array_unique($toolsUsed)),
            $writes,
            count($result['action']['results'] ?? [])
        );
        if (!$bound['ok']) {
            $result['pending_confirmations'] = $result['pending_confirmations'] ?? [];
            $result['action']['partial'] = true;
            $result['governance_stopped'] = $bound;
            $audit = is_array($result['audit'] ?? null) ? $result['audit'] : [];
            $audit[] = $this->logAudit($ctx, $requestId, 'governance_boundary_stop', [
                'code' => $bound['code'] ?? null,
                'message' => $bound['message'] ?? null,
            ], [
                'event_type' => 'governance_boundary_stop',
                'error_code' => (string) ($bound['code'] ?? 'boundary'),
            ], 'denied');
            $result['audit'] = $audit;
        }

        $trace = ErpGovernanceLayer::buildDecisionTrace($governance, $result, $ctx);
        $durationMs = (int) (($result['observability']['duration_ms'] ?? 0) ?: ((microtime(true) - $startedAt) * 1000));
        $finalStatus = 'ok';
        if (!empty($result['governance_stopped'])) {
            $finalStatus = 'stopped';
        } elseif (!empty($result['action']['partial'])) {
            $finalStatus = 'partial';
        } elseif (!empty($result['pending_confirmations'])) {
            $finalStatus = 'awaiting_confirmation';
        } elseif (!empty($result['observability']['success'])) {
            $finalStatus = 'success';
        }

        $obs = ErpGovernanceLayer::buildObservability(
            $governance,
            $trace,
            $result,
            $requestId,
            $ctx,
            $durationMs,
            $finalStatus
        );
        // Preserve existing observability keys
        $result['observability'] = array_merge(
            is_array($result['observability'] ?? null) ? $result['observability'] : [],
            $obs
        );
        $result['governance'] = $governance;
        $result['decision_trace'] = $trace;

        // Quiet reads: keep the user reply clean. Show governance only when it affects action.
        if (ErpGovernanceLayer::shouldAttachGovernanceSummary($governance, $trace, $result)) {
            $govSummary = ErpGovernanceLayer::formatGovernanceSummary($governance, $trace, $ctx);
            $resp = (string) ($result['response'] ?? '');
            if ($govSummary !== '' && $resp !== '' && !str_contains($resp, $govSummary)) {
                $result['response'] = rtrim($resp) . "\n\n" . $govSummary;
            } elseif ($govSummary !== '' && $resp === '') {
                $result['response'] = $govSummary;
            }
        }

        $audit = is_array($result['audit'] ?? null) ? $result['audit'] : [];
        $audit[] = $this->logAudit($ctx, $requestId, 'governance_summary', [
            'risk' => $governance['risk'] ?? null,
            'execution_level' => $governance['execution_level'] ?? null,
            'autonomy_blocked' => !empty($governance['controlled_autonomy']['blocked']),
            'final_status' => $finalStatus,
            'domains' => $governance['domains'] ?? [],
        ], [
            'event_type' => 'governance_summary',
            'error_code' => null,
        ], 'success');
        $result['audit'] = $audit;
        $result['agent'] = self::AGENT_ID;

        return $result;
    }

    /**
     * @param array<string, mixed> $governance
     * @param array{ok:bool,code:?string,message:?string} $bound
     * @param array<string, mixed> $intent
     * @return array<string, mixed>
     */
    private function governanceStopResponse(
        ProcurementAgentContext $ctx,
        string $requestId,
        array $governance,
        array $bound,
        array $intent
    ): array {
        $ar = $ctx->normalizedLocale() === 'ar';
        $code = (string) ($bound['code'] ?? 'boundary');
        $msg = $ar
            ? 'تم إيقاف التنفيذ بسبب حد الحوكمة: ' . ErpActionPlanner::labelGovToken($code, true)
            : 'Execution stopped due to governance boundary: ' . ErpActionPlanner::labelGovToken($code, false);
        $result = [
            'response' => $msg,
            'tool_calls' => [],
            'pending_confirmations' => [],
            'audit' => [
                $this->logAudit($ctx, $requestId, 'governance_boundary_stop', [
                    'code' => $bound['code'] ?? null,
                    'message' => $bound['message'] ?? null,
                    'domains' => $intent['domains'] ?? [],
                ], [
                    'event_type' => 'governance_boundary_stop',
                    'error_code' => $code,
                ], 'denied'),
            ],
            'domain' => 'governance',
            'agent' => self::AGENT_ID,
            'action' => ['plan' => [], 'results' => [], 'partial' => true],
            'observability' => [
                'request_id' => $requestId,
                'company_id' => $ctx->companyId,
                'user_id' => $ctx->userId,
                'success' => false,
                'duration_ms' => 0,
            ],
            'governance_stopped' => $bound,
        ];
        return $this->finalizeWithGovernance($result, $governance, $ctx, $requestId);
    }

    /**
     * @param array<string, mixed> $wf
     */
    private function formatWorkflowBrief(array $wf, ProcurementAgentContext $ctx): string
    {
        $ar = str_starts_with(strtolower((string) $ctx->locale), 'ar');
        $id = (string) ($wf['workflow_id'] ?? '');
        $status = (string) ($wf['status'] ?? '');
        $steps = is_array($wf['steps'] ?? null) ? $wf['steps'] : [];
        $done = 0;
        $total = count($steps);
        foreach ($steps as $s) {
            if (is_array($s) && in_array(($s['status'] ?? ''), ['VERIFIED', 'COMPLETED', 'SKIPPED'], true)) {
                $done++;
            }
        }
        $lines = [];
        $lines[] = $ar
            ? "سير العمل متعدد الخطوات: {$id} — الحالة: {$status} — التقدم: {$done}/{$total}"
            : "Multi-step workflow: {$id} — status: {$status} — progress: {$done}/{$total}";
        if (!empty($wf['requires_confirmation']) && in_array($status, ['PLANNED', 'AUTHORIZED', 'BLOCKED'], true)) {
            $lines[] = $ar
                ? 'يتطلب تأكيداً لكل خطوات الكتابة قبل التنفيذ. لا تنفيذ تلقائي.'
                : 'Requires confirmation for all write steps before execution. No auto-execute.';
        }
        if (($wf['recovery']['required'] ?? false) === true) {
            $opt = (string) ($wf['recovery']['option'] ?? '');
            $lines[] = $ar
                ? "مطلوب استرداد: {$opt}"
                : "Recovery required: {$opt}";
        }
        return implode("\n", $lines);
    }

    private function actionImpactSummary(string $toolName, array $arguments, ProcurementAgentContext $ctx): string
    {
        $ar = $ctx->normalizedLocale() === 'ar';
        $id = (int) ($arguments['id'] ?? 0);
        $title = trim((string) ($arguments['title'] ?? ''));
        if ($ar) {
            return match ($toolName) {
                'create_draft_purchase_request' => 'إنشاء مسودة طلب شراء' . ($title !== '' ? ': ' . $title : ''),
                'update_purchase_request' => 'تعديل طلب شراء' . ($id > 0 ? ' #' . $id : ''),
                'cancel_purchase_request' => 'إلغاء طلب شراء' . ($id > 0 ? ' #' . $id : ''),
                'submit_purchase_request' => 'إرسال طلب شراء للموافقة' . ($id > 0 ? ' #' . $id : ''),
                default => $toolName,
            };
        }
        return match ($toolName) {
            'create_draft_purchase_request' => 'Create draft purchase request' . ($title !== '' ? ': ' . $title : ''),
            'update_purchase_request' => 'Update purchase request' . ($id > 0 ? ' #' . $id : ''),
            'cancel_purchase_request' => 'Cancel purchase request' . ($id > 0 ? ' #' . $id : ''),
            'submit_purchase_request' => 'Submit purchase request for approval' . ($id > 0 ? ' #' . $id : ''),
            default => $toolName,
        };
    }

    /**
     * Cross-domain orchestration (READ/ANALYSIS).
     *
     * @param array<string, mixed> $input
     * @param array{mode: string, domains: list<string>, primary?: string|null, write_intent?: bool} $intent
     * @return array<string, mixed>
     */
    private function processCrossDomain(array $input, ProcurementAgentContext $ctx, array $intent, string $requestId): array
    {
        $startedAt = microtime(true);
        $maxSteps = max(1, min(12, (int) ($this->config['agent']['max_orchestration_tools'] ?? 8)));
        if (!empty($intent['decision_support'])) {
            $maxSteps = min($maxSteps, 6);
        }
        $plan = ErpOrchestrationPlanner::buildPlan($intent, $ctx, $maxSteps);

        $toolCalls = [];
        $auditEntries = [];
        $confirmed = [];
        $failed = [];
        $executedKeys = [];

        $auditEntries[] = $this->logAudit($ctx, $requestId, 'orchestration_plan', [
            'domains' => $intent['domains'] ?? [],
            'plan_size' => count($plan),
            'tools' => array_map(static fn($s) => $s['tool'] ?? '', $plan),
            'intent_kind' => (string) ($intent['intent_kind'] ?? 'analysis'),
            'decision_support' => !empty($intent['decision_support']),
            'minimal' => !empty($intent['minimal']),
        ], [
            'event_type' => 'orchestration_plan',
            'error_code' => null,
            'orchestration' => true,
        ], 'success');

        foreach ($plan as $step) {
            $toolName = (string) ($step['tool'] ?? '');
            $arguments = is_array($step['arguments'] ?? null) ? $step['arguments'] : [];
            $domainId = (string) ($step['domain'] ?? '');
            $purpose = (string) ($step['purpose'] ?? '');
            if ($toolName === '' || $domainId === '') {
                continue;
            }

            $dedupeKey = $toolName . '|' . md5((string) json_encode($arguments, JSON_UNESCAPED_UNICODE));
            if (isset($executedKeys[$dedupeKey])) {
                $auditEntries[] = $this->logAudit($ctx, $requestId, $toolName, $arguments, [
                    'event_type' => 'orchestration_duplicate_skipped',
                    'error_code' => 'duplicate_action',
                    'orchestration' => true,
                    'domain' => $domainId,
                ], 'denied');
                continue;
            }
            $executedKeys[$dedupeKey] = true;

            $policy = ProcurementPolicyGuard::checkAndExecute([
                'tool' => $toolName,
                'arguments' => $arguments,
                'request_company_id' => null,
                'request_id' => $requestId,
                'write_confirmed' => false,
            ], $ctx);

            if (!$policy['allowed']) {
                $code = (string) ($policy['error_code'] ?? 'permission_denied');
                $failed[] = [
                    'tool' => $toolName,
                    'domain' => $domainId,
                    'purpose' => $purpose,
                    'error_code' => $code,
                ];
                $toolCalls[] = [
                    'tool' => $toolName,
                    'domain' => $domainId,
                    'arguments' => $arguments,
                    'result' => [
                        'success' => false,
                        'error' => $code,
                        'error_code' => $code,
                    ],
                    'audit_status' => 'denied',
                ];
                $auditEntries[] = $this->logAudit($ctx, $requestId, $toolName, $arguments, [
                    'event_type' => 'orchestration_policy_denied',
                    'error_code' => $code,
                    'policy_checks' => $policy['policy_checks'] ?? [],
                    'orchestration' => true,
                    'domain' => $domainId,
                ], 'denied');
                continue;
            }

            $t0 = microtime(true);
            $result = $this->executeRegisteredTool($toolName, $arguments, $ctx);
            $durationMs = (int) ((microtime(true) - $t0) * 1000);

            if (!empty($result['success'])) {
                $confirmed[] = [
                    'tool' => $toolName,
                    'domain' => $domainId,
                    'purpose' => $purpose,
                    'data' => $result['data'] ?? null,
                ];
                $toolCalls[] = [
                    'tool' => $toolName,
                    'domain' => $domainId,
                    'arguments' => $arguments,
                    'result' => ['success' => true, 'data' => $result['data'] ?? null],
                    'audit_status' => 'success',
                ];
                $auditEntries[] = $this->logAudit($ctx, $requestId, $toolName, $arguments, [
                    'event_type' => 'orchestration_tool_success',
                    'error_code' => null,
                    'duration_ms' => $durationMs,
                    'orchestration' => true,
                    'domain' => $domainId,
                    'purpose' => $purpose,
                    'policy_checks' => $policy['policy_checks'] ?? [],
                ], 'success');
            } else {
                $code = (string) ($result['error_code'] ?? $result['error'] ?? 'tool_exception');
                $failed[] = [
                    'tool' => $toolName,
                    'domain' => $domainId,
                    'purpose' => $purpose,
                    'error_code' => $code,
                ];
                $toolCalls[] = [
                    'tool' => $toolName,
                    'domain' => $domainId,
                    'arguments' => $arguments,
                    'result' => [
                        'success' => false,
                        'error' => $code,
                        'error_code' => $code,
                    ],
                    'audit_status' => 'error',
                ];
                $auditEntries[] = $this->logAudit($ctx, $requestId, $toolName, $arguments, [
                    'event_type' => 'orchestration_tool_failure',
                    'error_code' => $code,
                    'duration_ms' => $durationMs,
                    'orchestration' => true,
                    'domain' => $domainId,
                ], 'error');
            }
        }

        $intelligence = $this->buildCrossDomainIntelligence($confirmed, $failed, $intent, $ctx);
        $intelligence = ErpIntelligenceLayer::enrich($intelligence, $confirmed, $failed, $intent, $ctx);
        // Always attach relevant operational memory (bounded) — never full history
        if (empty($intelligence['operational_memory'])) {
            $intelligence['operational_memory'] = ErpOperationalMemoryLayer::forIntelligence($ctx, $intent);
        }
        if (($intent['intent_kind'] ?? '') === 'executive' || !empty($intent['executive'])) {
            $execPack = null;
            foreach ($confirmed as $row) {
                if (($row['purpose'] ?? '') === 'executive_intelligence_core' && is_array($row['data'] ?? null)) {
                    $execPack = $row['data'];
                    break;
                }
            }
            if ($execPack === null) {
                $execPack = ErpExecutiveIntelligenceLayer::build($ctx, $intent, 15);
            }
            $intelligence['executive'] = $execPack;
            $intelligence['executive_summary'] = $execPack['executive_summary'] ?? [];
            $intelligence['kpis'] = $execPack['kpis'] ?? [];
            $intelligence['forecasts'] = $execPack['forecasts'] ?? [];
            $memPack = null;
            foreach ($confirmed as $row) {
                if (($row['purpose'] ?? '') === 'operational_memory_core' && is_array($row['data'] ?? null)) {
                    $memPack = $row['data'];
                    break;
                }
            }
            if (is_array($memPack)) {
                $intelligence['operational_memory'] = array_merge(
                    is_array($intelligence['operational_memory'] ?? null) ? $intelligence['operational_memory'] : [],
                    ['relevant_context' => $memPack]
                );
            }
            $proactivePack = null;
            foreach ($confirmed as $row) {
                if (($row['purpose'] ?? '') === 'proactive_early_warning_core' && is_array($row['data'] ?? null)) {
                    $proactivePack = $row['data'];
                    break;
                }
            }
            if ($proactivePack === null && !empty($intent['proactive'])) {
                $proactivePack = ErpProactiveEarlyWarningLayer::scan($ctx, [
                    'trigger' => 'orchestration',
                    'persist' => true,
                    'notify' => false,
                    'limit' => 15,
                    'request_id' => $requestId,
                ]);
            }
            if (is_array($proactivePack)) {
                $intelligence['proactive'] = $proactivePack;
                $intelligence['early_warnings'] = $proactivePack['warnings'] ?? [];
                $response = ErpProactiveEarlyWarningLayer::formatDigest($proactivePack, $ctx)
                    . "\n\n"
                    . ErpExecutiveIntelligenceLayer::formatSummary($execPack, $ctx);
            } else {
                $response = ErpExecutiveIntelligenceLayer::formatSummary($execPack, $ctx);
            }
        } else {
            $response = !empty($intent['decision_support'])
                ? ErpIntelligenceLayer::formatDecisionSummary($intelligence, $ctx)
                : $this->formatOperationalSummary($intelligence, $ctx);
        }

        $auditEntries[] = $this->logAudit($ctx, $requestId, 'intelligence_analysis', [
            'domains' => $intent['domains'] ?? [],
            'intent_kind' => (string) ($intent['intent_kind'] ?? 'analysis'),
            'findings_count' => count($intelligence['findings'] ?? []),
            'decisions_count' => count($intelligence['decisions'] ?? []),
            'evidence_count' => count($intelligence['evidence'] ?? []),
            'conflicts_count' => count($intelligence['conflicts'] ?? []),
            'incomplete_chains' => count($intelligence['incomplete_chains'] ?? []),
            'data_sufficient' => !empty($intelligence['data_sufficient']),
            'recommended_actions' => array_map(
                static fn($d) => is_array($d) ? (string) ($d['recommended_next_step'] ?? '') : '',
                array_slice(is_array($intelligence['decisions'] ?? null) ? $intelligence['decisions'] : [], 0, 5)
            ),
        ], [
            'event_type' => 'intelligence_analysis',
            'error_code' => null,
            'orchestration' => true,
            'evidence_first' => true,
        ], 'success');

        $auditEntries[] = $this->logAudit($ctx, $requestId, 'orchestration_summary', [
            'confirmed_count' => count($confirmed),
            'failed_count' => count($failed),
            'domains' => $intent['domains'] ?? [],
        ], [
            'event_type' => 'orchestration_summary',
            'error_code' => null,
            'orchestration' => true,
            'priorities' => $intelligence['priorities'] ?? [],
            'risks' => $intelligence['risks'] ?? [],
        ], 'success');

        return [
            'response' => $response,
            'tool_calls' => $toolCalls,
            'pending_confirmations' => [],
            'audit' => $auditEntries,
            'domain' => self::MODE_CROSS,
            'agent' => self::AGENT_ID,
            'orchestration' => [
                'mode' => 'cross',
                'domains' => $intent['domains'] ?? [],
                'intent_kind' => (string) ($intent['intent_kind'] ?? 'analysis'),
                'decision_support' => !empty($intent['decision_support']),
                'plan' => array_map(static fn($s) => [
                    'tool' => $s['tool'],
                    'domain' => $s['domain'],
                    'purpose' => $s['purpose'],
                ], $plan),
                'confirmed' => array_map(static fn($c) => [
                    'tool' => $c['tool'],
                    'domain' => $c['domain'],
                    'purpose' => $c['purpose'],
                ], $confirmed),
                'failed' => $failed,
                'intelligence' => $intelligence,
            ],
            'domain_selection' => [
                'resolved' => self::MODE_CROSS,
                'source' => 'orchestration',
                'active_domains' => ErpDomainRegistry::activeDomainIds(),
                'intent_mode' => 'cross',
                'intent_domains' => $intent['domains'] ?? [],
                'intent_kind' => (string) ($intent['intent_kind'] ?? 'analysis'),
                'minimal' => !empty($intent['minimal']),
            ],
            'observability' => [
                'request_id' => $requestId,
                'company_id' => $ctx->companyId,
                'user_id' => $ctx->userId,
                'success' => $confirmed !== [],
                'domain' => self::MODE_CROSS,
                'orchestration' => true,
                'duration_ms' => (int) ((microtime(true) - $startedAt) * 1000),
                'tool_failures' => count($failed),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $arguments
     * @return array{success: bool, data?: mixed, error?: string|null, error_code?: string|null}
     */
    private function executeRegisteredTool(string $toolName, array $arguments, ProcurementAgentContext $ctx): array
    {
        $domainId = ErpDomainRegistry::domainForTool($toolName);
        if ($domainId === null) {
            return ['success' => false, 'data' => null, 'error' => 'tool_not_allowed', 'error_code' => 'tool_not_allowed'];
        }
        $domain = ErpDomainRegistry::resolve($domainId);
        if ($domain === null) {
            return ['success' => false, 'data' => null, 'error' => 'domain_unavailable', 'error_code' => 'domain_unavailable'];
        }
        $executor = (string) ($domain['tool_executor'] ?? '');
        if ($executor === '' || !class_exists($executor) || !method_exists($executor, 'execute')) {
            return ['success' => false, 'data' => null, 'error' => 'tool_not_implemented', 'error_code' => 'tool_not_implemented'];
        }
        try {
            $result = $executor::execute($toolName, $arguments, $ctx);
            return is_array($result) ? $result : [
                'success' => false,
                'data' => null,
                'error' => 'invalid_tool_response',
                'error_code' => 'invalid_tool_response',
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'data' => null,
                'error' => 'tool_exception',
                'error_code' => 'tool_exception',
            ];
        }
    }

    /**
     * @param list<array{tool: string, domain: string, purpose: string, data: mixed}> $confirmed
     * @param list<array{tool: string, domain: string, purpose: string, error_code: string}> $failed
     * @param array<string, mixed> $intent
     * @return array<string, mixed>
     */
    private function buildCrossDomainIntelligence(array $confirmed, array $failed, array $intent, ProcurementAgentContext $ctx): array
    {
        $byPurpose = [];
        foreach ($confirmed as $row) {
            $byPurpose[(string) $row['purpose']] = $row['data'];
        }

        $summary = [
            'as_of' => date('Y-m-d'),
            'data_source' => 'live_tenant',
            'domains' => $intent['domains'] ?? [],
            'company_id' => $ctx->companyId,
        ];

        $priorities = [];
        $risks = [];
        $recommendations = [];
        $followUp = [];
        $metrics = [];

        $cross = $byPurpose['cross_domain_core'] ?? null;
        if (is_array($cross)) {
            $summary['supplier_summary'] = $cross['supplier_summary'] ?? null;
            $followUp = array_merge($followUp, is_array($cross['follow_up'] ?? null) ? $cross['follow_up'] : []);
            $metrics['open_operations'] = is_array($cross['open_operations'] ?? null) ? count($cross['open_operations']) : 0;
            $metrics['overdue_operations'] = is_array($cross['overdue_operations'] ?? null) ? count($cross['overdue_operations']) : 0;
            foreach (($cross['overdue_operations'] ?? []) as $op) {
                $risks[] = [
                    'code' => 'overdue_supplier_po',
                    'urgency' => 'high',
                    'label' => trim((string) (($op['supplier_name'] ?? '') . ' / ' . ($op['order_no'] ?? ''))),
                    'expected_date' => (string) ($op['expected_date'] ?? ''),
                    'amount' => (float) ($op['total_amount'] ?? 0),
                ];
                $priorities[] = [
                    'code' => 'chase_overdue_po',
                    'urgency' => 'high',
                    'domain' => 'suppliers',
                    'message' => 'overdue_purchase_order_needs_follow_up',
                ];
            }
        }

        $salesCross = $byPurpose['sales_cross_domain_core'] ?? null;
        if (is_array($salesCross)) {
            $summary['sales_summary'] = $salesCross['sales_summary'] ?? null;
            $followUp = array_merge($followUp, is_array($salesCross['follow_up'] ?? null) ? $salesCross['follow_up'] : []);
            $metrics['sales_stock_shortfall_count'] = count($salesCross['inventory_links'] ?? []);
            foreach (($salesCross['follow_up'] ?? []) as $fu) {
                if (!is_array($fu)) {
                    continue;
                }
                if ((string) ($fu['code'] ?? '') === 'sales_stock_shortfall') {
                    $risks[] = [
                        'code' => 'sales_stock_shortfall',
                        'urgency' => 'high',
                        'label' => (string) ($fu['label'] ?? ''),
                        'domain' => 'sales',
                    ];
                    $priorities[] = [
                        'code' => 'resolve_sales_stock_gap',
                        'urgency' => 'high',
                        'domain' => 'sales',
                        'message' => 'sales_demand_exceeds_available_stock',
                    ];
                }
            }
        }

        $crmCommercial = $byPurpose['crm_commercial_core'] ?? null;
        if (is_array($crmCommercial)) {
            $summary['crm_summary'] = $crmCommercial['crm_summary'] ?? null;
            $followUp = array_merge($followUp, is_array($crmCommercial['follow_up'] ?? null) ? $crmCommercial['follow_up'] : []);
            $metrics['crm_customer_count'] = (int) (($crmCommercial['crm_summary']['customer_count'] ?? 0));
            $metrics['crm_inventory_shortfall_links'] = count($crmCommercial['inventory_links'] ?? []);
            foreach (($crmCommercial['inventory_links'] ?? []) as $link) {
                $risks[] = [
                    'code' => 'customer_sales_stock_shortfall',
                    'urgency' => 'high',
                    'label' => trim((string) (($link['customer_name'] ?? '') . ' / ' . ($link['item_name'] ?? ''))),
                    'domain' => 'crm',
                ];
                $priorities[] = [
                    'code' => 'resolve_customer_stock_gap',
                    'urgency' => 'high',
                    'domain' => 'crm',
                    'message' => 'customer_sales_demand_exceeds_stock',
                ];
            }
        }

        $logisticsE2e = $byPurpose['logistics_e2e_core'] ?? null;
        if (is_array($logisticsE2e)) {
            $summary['logistics_summary'] = $logisticsE2e['logistics_summary'] ?? null;
            $followUp = array_merge($followUp, is_array($logisticsE2e['follow_up'] ?? null) ? $logisticsE2e['follow_up'] : []);
            $metrics['logistics_chain_count'] = count($logisticsE2e['chain'] ?? []);
            $metrics['logistics_delayed_count'] = (int) (($logisticsE2e['logistics_summary']['delayed_shipment_count'] ?? 0));
            foreach (($logisticsE2e['follow_up'] ?? []) as $fu) {
                if (!is_array($fu)) {
                    continue;
                }
                $risks[] = [
                    'code' => (string) ($fu['code'] ?? 'logistics_follow_up'),
                    'urgency' => (string) ($fu['urgency'] ?? 'medium'),
                    'label' => trim((string) (($fu['tracking_number'] ?? '') . ' ' . ($fu['message'] ?? ''))),
                    'domain' => 'logistics',
                ];
                $priorities[] = [
                    'code' => 'resolve_logistics_gap',
                    'urgency' => (string) ($fu['urgency'] ?? 'medium'),
                    'domain' => 'logistics',
                    'message' => (string) ($fu['message'] ?? 'logistics_needs_attention'),
                ];
            }
        }

        $financialCore = $byPurpose['financial_intelligence_core'] ?? null;
        if (is_array($financialCore)) {
            $summary['financial_summary'] = $financialCore['accounting_summary'] ?? null;
            $summary['cfo_metrics'] = $financialCore['cfo_metrics'] ?? null;
            $followUp = array_merge($followUp, is_array($financialCore['follow_up'] ?? null) ? $financialCore['follow_up'] : []);
            $metrics['financial_correlation_count'] = count($financialCore['correlations'] ?? []);
            $metrics['financial_risk_count'] = count($financialCore['risks'] ?? []);
            foreach (($financialCore['risks'] ?? []) as $risk) {
                if (!is_array($risk)) {
                    continue;
                }
                $risks[] = [
                    'code' => (string) ($risk['code'] ?? 'financial_risk'),
                    'urgency' => (string) ($risk['urgency'] ?? 'medium'),
                    'label' => (string) ($risk['code'] ?? 'financial_risk'),
                    'domain' => 'accounting',
                    'amount' => $risk['amount'] ?? null,
                ];
                $priorities[] = [
                    'code' => 'resolve_financial_gap',
                    'urgency' => (string) ($risk['urgency'] ?? 'medium'),
                    'domain' => 'accounting',
                    'message' => (string) ($risk['code'] ?? 'financial_follow_up_required'),
                ];
            }
        }

        $accountingIntel = $byPurpose['accounting_intelligence'] ?? null;
        if (is_array($accountingIntel)) {
            $metrics['overdue_receivable_count'] = (int) ($accountingIntel['totals']['overdue_receivable_count'] ?? 0);
            $metrics['outstanding_payable_count'] = (int) ($accountingIntel['totals']['outstanding_payable_count'] ?? 0);
            $followUp = array_merge($followUp, is_array($accountingIntel['follow_up'] ?? null) ? $accountingIntel['follow_up'] : []);
        }

        $logistics = $byPurpose['logistics_intelligence'] ?? null;
        if (is_array($logistics)) {
            $metrics['logistics_open_shipment_count'] = (int) ($logistics['summary']['open_shipment_count'] ?? 0);
            $metrics['logistics_delayed_shipment_count'] = (int) ($logistics['summary']['delayed_shipment_count'] ?? 0);
            $metrics['logistics_incomplete_delivery_count'] = (int) ($logistics['summary']['incomplete_delivery_order_count'] ?? 0);
            $followUp = array_merge($followUp, is_array($logistics['follow_up'] ?? null) ? $logistics['follow_up'] : []);
            foreach (($logistics['delayed_shipments'] ?? []) as $row) {
                $risks[] = [
                    'code' => 'delayed_shipment',
                    'urgency' => 'high',
                    'label' => trim((string) (($row['tracking_number'] ?? '') . ' ' . ($row['status'] ?? ''))),
                    'domain' => 'logistics',
                ];
            }
        }

        $crm = $byPurpose['crm_intelligence'] ?? null;
        if (is_array($crm)) {
            $metrics['crm_customer_count'] = (int) ($crm['summary']['customer_count'] ?? 0);
            $metrics['crm_at_risk_count'] = (int) ($crm['summary']['at_risk_count'] ?? 0);
            $metrics['crm_open_followup_count'] = (int) ($crm['summary']['open_followup_count'] ?? 0);
            $followUp = array_merge($followUp, is_array($crm['follow_up'] ?? null) ? $crm['follow_up'] : []);
            foreach (($crm['at_risk_customers'] ?? []) as $row) {
                $risks[] = [
                    'code' => 'customer_at_risk',
                    'urgency' => 'high',
                    'label' => trim((string) (($row['code'] ?? '') . ' ' . ($row['name'] ?? ''))),
                    'domain' => 'crm',
                ];
            }
        }

        $sales = $byPurpose['sales_intelligence'] ?? null;
        if (is_array($sales)) {
            $metrics['sales_order_count'] = (int) ($sales['summary']['order_count'] ?? 0);
            $metrics['sales_open_amount'] = (float) ($sales['summary']['open_amount'] ?? 0);
            $metrics['sales_stock_shortfall_count'] = (int) ($sales['summary']['stock_shortfall_count'] ?? 0);
            $followUp = array_merge($followUp, is_array($sales['follow_up'] ?? null) ? $sales['follow_up'] : []);
        }

        $salesInv = $byPurpose['sales_inventory_links'] ?? null;
        if (is_array($salesInv)) {
            foreach (($salesInv['links'] ?? []) as $link) {
                if (!empty($link['is_shortfall'])) {
                    $risks[] = [
                        'code' => 'sales_inventory_shortfall',
                        'urgency' => 'high',
                        'label' => trim((string) (($link['item_code'] ?? '') . ' ' . ($link['item_name'] ?? ''))),
                        'shortfall_qty' => (float) ($link['shortfall_qty'] ?? 0),
                    ];
                    $recommendations[] = [
                        'code' => 'review_sales_inventory_gap',
                        'urgency' => 'high',
                        'message' => 'sales_product_stock_insufficient',
                        'inventory_id' => (int) ($link['inventory_id'] ?? 0),
                    ];
                }
            }
        }

        $salesProc = $byPurpose['sales_procurement_links'] ?? null;
        if (is_array($salesProc)) {
            foreach (($salesProc['links'] ?? []) as $link) {
                foreach (($link['follow_up'] ?? []) as $fu) {
                    $recommendations[] = [
                        'code' => (string) ($fu['code'] ?? 'sales_procurement_follow_up'),
                        'urgency' => 'high',
                        'message' => (string) ($fu['message'] ?? ''),
                        'inventory_id' => (int) ($link['inventory_id'] ?? 0),
                        'domain' => 'procurement',
                    ];
                    $priorities[] = [
                        'code' => 'procurement_for_sales_shortfall',
                        'urgency' => 'high',
                        'domain' => 'procurement',
                        'message' => 'consider_purchase_for_sales_stock_gap',
                    ];
                }
            }
        }

        $inv = $byPurpose['inventory_intelligence'] ?? null;
        if (is_array($inv)) {
            $metrics['low_stock_count'] = (int) ($inv['summary']['low_stock_count'] ?? count($inv['low_stock'] ?? []));
            $metrics['inventory_item_count'] = (int) ($inv['summary']['item_count'] ?? 0);
            $followUp = array_merge($followUp, is_array($inv['follow_up'] ?? null) ? $inv['follow_up'] : []);
            foreach (($inv['low_stock'] ?? []) as $item) {
                $risks[] = [
                    'code' => 'low_stock',
                    'urgency' => 'high',
                    'label' => trim((string) (($item['item_code'] ?? '') . ' ' . ($item['item_name'] ?? ''))),
                    'quantity' => (float) ($item['quantity'] ?? 0),
                ];
                $priorities[] = [
                    'code' => 'review_low_stock',
                    'urgency' => 'high',
                    'domain' => 'inventory',
                    'message' => 'low_stock_item_needs_review',
                ];
            }
        }

        $invLinks = $byPurpose['inventory_procurement_links'] ?? null;
        if (is_array($invLinks)) {
            foreach (($invLinks['links'] ?? []) as $link) {
                if (!empty($link['is_low_stock']) && empty($link['purchase_requests']) && empty($link['purchase_orders'])) {
                    $recommendations[] = [
                        'code' => 'open_procurement_for_low_stock',
                        'urgency' => 'high',
                        'inventory_id' => (int) ($link['inventory_id'] ?? 0),
                        'message' => 'low_stock_without_open_procurement',
                    ];
                    $priorities[] = [
                        'code' => 'procurement_follow_up_for_stock',
                        'urgency' => 'high',
                        'domain' => 'procurement',
                        'message' => 'purchase_request_may_be_needed',
                    ];
                }
                if (!empty($link['is_low_stock']) && !empty($link['purchase_requests'])) {
                    $recommendations[] = [
                        'code' => 'accelerate_linked_pr',
                        'urgency' => 'medium',
                        'inventory_id' => (int) ($link['inventory_id'] ?? 0),
                        'message' => 'review_linked_purchase_requests',
                    ];
                }
            }
        }

        $sup = $byPurpose['supplier_intelligence'] ?? null;
        if (is_array($sup)) {
            $metrics['supplier_count'] = (int) ($sup['summary']['supplier_count'] ?? 0);
            $metrics['overdue_po_count'] = (int) ($sup['summary']['overdue_purchase_order_count'] ?? 0);
            $metrics['open_po_amount'] = (float) ($sup['summary']['open_purchase_order_amount'] ?? 0);
            $followUp = array_merge($followUp, is_array($sup['follow_up'] ?? null) ? $sup['follow_up'] : []);
        }

        $approvals = $byPurpose['pending_approvals'] ?? null;
        if (is_array($approvals)) {
            $metrics['pending_approvals'] = count($approvals);
            if (count($approvals) > 0) {
                $priorities[] = [
                    'code' => 'clear_pending_approvals',
                    'urgency' => 'medium',
                    'domain' => 'procurement',
                    'message' => 'pending_approvals_require_attention',
                    'count' => count($approvals),
                ];
            }
        }

        $guidance = $byPurpose['operational_guidance'] ?? null;
        if (is_array($guidance)) {
            foreach (($guidance['actions'] ?? $guidance['recommendations'] ?? []) as $action) {
                if (is_array($action)) {
                    $recommendations[] = [
                        'code' => (string) ($action['reason_code'] ?? $action['action'] ?? $action['code'] ?? 'guidance'),
                        'urgency' => (string) ($action['urgency'] ?? 'medium'),
                        'message' => (string) ($action['action'] ?? $action['message'] ?? $action['label'] ?? ''),
                        'domain' => 'procurement',
                    ];
                }
            }
        }

        // Deduplicate priorities/risks by code+label
        $dedupe = static function (array $rows): array {
            $out = [];
            $seen = [];
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $k = ((string) ($row['code'] ?? '')) . '|' . ((string) ($row['label'] ?? $row['message'] ?? ''));
                if (isset($seen[$k])) {
                    continue;
                }
                $seen[$k] = true;
                $out[] = $row;
            }
            return array_slice($out, 0, 20);
        };

        return [
            'data_source' => 'live_tenant',
            'as_of' => date('Y-m-d'),
            'domains' => $intent['domains'] ?? [],
            'summary' => $summary,
            'metrics' => $metrics,
            'priorities' => $dedupe($priorities),
            'risks' => $dedupe($risks),
            'recommendations' => $dedupe($recommendations),
            'follow_up' => array_slice($followUp, 0, 20),
            'partial_failures' => $failed,
            'confirmed_tools' => array_map(static fn($c) => $c['tool'], $confirmed),
            'notes' => [
                'only_confirmed_live_data_included',
                'failed_domains_are_reported_not_invented',
                'writes_require_separate_confirmation',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $intelligence
     */
    private function formatOperationalSummary(array $intelligence, ProcurementAgentContext $ctx): string
    {
        $locale = $ctx->normalizedLocale();
        $ar = $locale === 'ar';
        $lines = [];

        $lines[] = $ar ? 'ملخص تشغيلي موحّد (بيانات حية):' : 'Unified operational summary (live data):';
        $domains = $intelligence['domains'] ?? [];
        if (is_array($domains) && $domains !== []) {
            $domainLabels = [];
            foreach ($domains as $d) {
                if (is_string($d) && $d !== '') {
                    $domainLabels[] = $this->domainDisplayLabel($d, $ar);
                }
            }
            if ($domainLabels !== []) {
                $lines[] = ($ar ? 'المجالات: ' : 'Domains: ') . implode('، ', $domainLabels);
            }
        }

        $metrics = is_array($intelligence['metrics'] ?? null) ? $intelligence['metrics'] : [];
        if ($metrics !== []) {
            $lines[] = $ar ? 'مؤشرات:' : 'Metrics:';
            foreach ($metrics as $k => $v) {
                if (is_scalar($v)) {
                    $lines[] = '- ' . $this->fieldLabel((string) $k, $ar) . ': ' . $this->formatFieldValue((string) $k, $v, $ar);
                }
            }
        }

        $risks = is_array($intelligence['risks'] ?? null) ? $intelligence['risks'] : [];
        if ($risks !== []) {
            $lines[] = $ar ? 'مخاطر / مشاكل:' : 'Risks / issues:';
            foreach (array_slice($risks, 0, 8) as $risk) {
                $label = (string) ($risk['label'] ?? $risk['code'] ?? '');
                $urgency = (string) ($risk['urgency'] ?? '');
                $lines[] = '- [' . $urgency . '] ' . $label;
            }
        } else {
            $lines[] = $ar ? 'لم تُكتشف مخاطر مؤكدة من البيانات المتاحة.' : 'No confirmed risks from available data.';
        }

        $priorities = is_array($intelligence['priorities'] ?? null) ? $intelligence['priorities'] : [];
        if ($priorities !== []) {
            $lines[] = $ar ? 'أولويات المتابعة:' : 'Follow-up priorities:';
            foreach (array_slice($priorities, 0, 8) as $p) {
                $lines[] = '- ' . (string) ($p['message'] ?? $p['code'] ?? '');
            }
        }

        $recs = is_array($intelligence['recommendations'] ?? null) ? $intelligence['recommendations'] : [];
        if ($recs !== []) {
            $lines[] = $ar ? 'توصيات (من البيانات فقط):' : 'Recommendations (from data only):';
            foreach (array_slice($recs, 0, 8) as $r) {
                $lines[] = '- ' . (string) ($r['message'] ?? $r['code'] ?? '');
            }
        }

        $failed = is_array($intelligence['partial_failures'] ?? null) ? $intelligence['partial_failures'] : [];
        if ($failed !== []) {
            $lines[] = $ar
                ? 'تعذر تنفيذ بعض الأجزاء (لم تُختلق نتائج لها):'
                : 'Some parts could not be executed (no invented results):';
            foreach ($failed as $f) {
                $lines[] = '- ' . (string) ($f['domain'] ?? '') . ' / ' . (string) ($f['tool'] ?? '') . ' (' . (string) ($f['error_code'] ?? '') . ')';
            }
        }

        $lines[] = $ar
            ? 'ملاحظة: أي عملية كتابة تتطلب تأكيدًا منفصلاً عبر بوابة التأكيد الحالية.'
            : 'Note: any write operation requires separate confirmation via the existing confirmation gate.';

        return implode("\n", $lines);
    }

    /**
     * Resolve domain without executing tools.
     *
     * @param array<string, mixed> $input
     * @return array{ok: bool, domain?: array, source?: string, error_code?: string}
     */
    public function resolveDomain(array $input, ProcurementAgentContext $ctx): array
    {
        $requested = strtolower(trim((string) ($input['domain'] ?? '')));

        if ($requested !== '') {
            if ($requested === self::MODE_CROSS) {
                return ['ok' => false, 'error_code' => 'domain_unknown', 'domain' => $requested];
            }
            if (ErpDomainRegistry::isActive($requested)) {
                $domain = ErpDomainRegistry::resolve($requested);
                if ($domain === null) {
                    return ['ok' => false, 'error_code' => 'domain_unavailable', 'domain' => $requested];
                }
                if (!ErpDomainRegistry::isDomainEntitled($requested, $ctx)) {
                    return ['ok' => false, 'error_code' => 'module_not_entitled', 'domain' => $requested];
                }
                return ['ok' => true, 'domain' => $domain, 'source' => 'explicit'];
            }
            if (ErpDomainRegistry::isReservedFuture($requested)) {
                return ['ok' => false, 'error_code' => 'domain_not_implemented', 'domain' => $requested];
            }
            return ['ok' => false, 'error_code' => 'domain_unknown', 'domain' => $requested];
        }

        $message = (string) ($input['message'] ?? '');
        $intent = ErpOrchestrationPlanner::detectIntent($message, $ctx, null);

        // Prefer intent primary for single-domain queries (HR, payroll, …) before keyword defaults.
        $primary = (string) ($intent['primary'] ?? '');
        if (
            $primary !== ''
            && ($intent['mode'] ?? '') === 'single'
            && ErpDomainRegistry::isActive($primary)
        ) {
            $domain = ErpDomainRegistry::resolve($primary);
            if ($domain !== null && ErpDomainRegistry::isDomainEntitled($primary, $ctx)) {
                return ['ok' => true, 'domain' => $domain, 'source' => 'intent_primary'];
            }
            if ($domain !== null) {
                return ['ok' => false, 'error_code' => 'module_not_entitled', 'domain' => $primary];
            }
        }

        if (($intent['mode'] ?? '') === 'cross' && count($intent['domains'] ?? []) >= 2) {
            // resolveDomain remains single-domain for callers; cross is handled in process().
            $crossPrimary = (string) ($intent['primary'] ?? ErpDomainRegistry::defaultDomainId());
            $domain = ErpDomainRegistry::resolve($crossPrimary);
            if ($domain !== null && ErpDomainRegistry::isDomainEntitled($crossPrimary, $ctx)) {
                return ['ok' => true, 'domain' => $domain, 'source' => 'cross_primary'];
            }
        }

        if ($message !== '' && ErpDomainRegistry::isActive(ErpDomainRegistry::DOMAIN_EXECUTIVE)) {
            if (ErpOrchestrationPlanner::hasExecutiveIntent($message)) {
                $exec = ErpDomainRegistry::resolve(ErpDomainRegistry::DOMAIN_EXECUTIVE);
                if ($exec !== null) {
                    return ['ok' => true, 'domain' => $exec, 'source' => 'keyword'];
                }
            }
        }
        if ($message !== '' && ErpDomainRegistry::isActive(ErpDomainRegistry::DOMAIN_HR)) {
            if (preg_match('/(hr|human\s*resources|employee|employees|workforce|attendance|leave|الموارد\s*البشرية|موارد\s*بشرية|موظف|موظفين|الحضور|إجازة|اجازة)/ui', $message) === 1) {
                $hr = ErpDomainRegistry::resolve(ErpDomainRegistry::DOMAIN_HR);
                if ($hr !== null && ErpDomainRegistry::isDomainEntitled(ErpDomainRegistry::DOMAIN_HR, $ctx)) {
                    return ['ok' => true, 'domain' => $hr, 'source' => 'keyword'];
                }
            }
        }
        if ($message !== '' && ErpDomainRegistry::isActive(ErpDomainRegistry::DOMAIN_ACCOUNTING)) {
            if (preg_match('/(accounting|financial|finance|receivable|payable|invoice|payment|journal|ledger|vat|محاسبة|مالي|مالية|مستحقات|ذمم|فاتورة|فواتير|دفعة|قيد|ضريبة|الوضع\s*المالي)/ui', $message) === 1) {
                $acc = ErpDomainRegistry::resolve(ErpDomainRegistry::DOMAIN_ACCOUNTING);
                if ($acc !== null && ErpDomainRegistry::isDomainEntitled(ErpDomainRegistry::DOMAIN_ACCOUNTING, $ctx)) {
                    return ['ok' => true, 'domain' => $acc, 'source' => 'keyword'];
                }
            }
        }
        if ($message !== '' && ErpDomainRegistry::isActive(ErpDomainRegistry::DOMAIN_LOGISTICS)) {
            if (preg_match('/(logistics|shipment|shipments|delivery|deliveries|trip|trips|شحن|شحنات|تسليم|توصيل|رحلة|رحلات|لوجست|تتبع)/ui', $message) === 1) {
                $log = ErpDomainRegistry::resolve(ErpDomainRegistry::DOMAIN_LOGISTICS);
                if ($log !== null && ErpDomainRegistry::isDomainEntitled(ErpDomainRegistry::DOMAIN_LOGISTICS, $ctx)) {
                    return ['ok' => true, 'domain' => $log, 'source' => 'keyword'];
                }
            }
        }
        if ($message !== '' && ErpDomainRegistry::isActive(ErpDomainRegistry::DOMAIN_CRM)) {
            if (preg_match('/(crm|customer|customers|lead|leads|opportunity|عميل|عملاء|فرصة|فرص|متابعة\s*العميل)/ui', $message) === 1) {
                $crm = ErpDomainRegistry::resolve(ErpDomainRegistry::DOMAIN_CRM);
                if ($crm !== null && ErpDomainRegistry::isDomainEntitled(ErpDomainRegistry::DOMAIN_CRM, $ctx)) {
                    return ['ok' => true, 'domain' => $crm, 'source' => 'keyword'];
                }
            }
        }
        if ($message !== '' && ErpDomainRegistry::isActive(ErpDomainRegistry::DOMAIN_SALES)) {
            if (preg_match('/(sales|pos|مبيعات|بيع|نقطة\s*البيع|طلب\s*بيع|طلبات\s*البيع)/ui', $message) === 1) {
                $sales = ErpDomainRegistry::resolve(ErpDomainRegistry::DOMAIN_SALES);
                if ($sales !== null && ErpDomainRegistry::isDomainEntitled(ErpDomainRegistry::DOMAIN_SALES, $ctx)) {
                    return ['ok' => true, 'domain' => $sales, 'source' => 'keyword'];
                }
            }
        }
        if ($message !== '' && ErpDomainRegistry::isActive(ErpDomainRegistry::DOMAIN_SUPPLIERS)) {
            if (preg_match('/(supplier|suppliers|مورد|موردين|موردون|المورد|الموردين)/ui', $message) === 1) {
                $sup = ErpDomainRegistry::resolve(ErpDomainRegistry::DOMAIN_SUPPLIERS);
                if ($sup !== null && ErpDomainRegistry::isDomainEntitled(ErpDomainRegistry::DOMAIN_SUPPLIERS, $ctx)) {
                    return ['ok' => true, 'domain' => $sup, 'source' => 'keyword'];
                }
            }
        }
        if ($message !== '' && ErpDomainRegistry::isActive(ErpDomainRegistry::DOMAIN_INVENTORY)) {
            if (preg_match('/(inventory|warehouse|stock|sku|مخزون|مستودع|صنف|أصناف|حركة|حركات)/ui', $message) === 1) {
                $inv = ErpDomainRegistry::resolve(ErpDomainRegistry::DOMAIN_INVENTORY);
                if ($inv !== null && ErpDomainRegistry::isDomainEntitled(ErpDomainRegistry::DOMAIN_INVENTORY, $ctx)) {
                    return ['ok' => true, 'domain' => $inv, 'source' => 'keyword'];
                }
            }
        }

        $defaultId = ErpDomainRegistry::defaultDomainId();
        $domain = ErpDomainRegistry::resolve($defaultId);
        if ($domain === null || empty($domain['active'])) {
            return ['ok' => false, 'error_code' => 'domain_unavailable', 'domain' => $defaultId];
        }
        if (!ErpDomainRegistry::isDomainEntitled($defaultId, $ctx)) {
            return ['ok' => false, 'error_code' => 'module_not_entitled', 'domain' => $defaultId];
        }

        return ['ok' => true, 'domain' => $domain, 'source' => 'default'];
    }

    /**
     * Public intent helper for tests / diagnostics.
     *
     * @return array{mode: string, domains: list<string>, signals: array, write_intent: bool, primary: string|null}
     */
    public function detectIntent(array $input, ProcurementAgentContext $ctx): array
    {
        return ErpOrchestrationPlanner::detectIntent(
            (string) ($input['message'] ?? ''),
            $ctx,
            isset($input['domain']) ? (string) $input['domain'] : null
        );
    }

    /**
     * List/show/module-name queries should hit live tools directly (accurate, no LLM refuse/empty).
     */
    private function isDirectDataRequest(string $message): bool
    {
        $m = trim($message);
        if ($m === '') {
            return false;
        }
        // Never treat create/add phrases as read-only list requests.
        if (preg_match('/(أضف|اضف|ضيف|إضافة|أنشئ|إنشاء|create|add|عدّل|عدل|ألغ|الغ|أرسل|ارسل)/ui', $m) === 1) {
            return false;
        }
        if (preg_match('/^(عرض|اعرض|أظهر|اظهر|list|show|ملخص|احص|إحص|افتح|open)\b/ui', $m) === 1) {
            return true;
        }
        if (mb_strlen($m) > 48) {
            return false;
        }
        return preg_match(
            '/(الموارد\s*البشرية|موارد\s*بشرية|توظيف|مشاريع|عقود|أصول|رواتب|مسير|تصنيع|جودة|موافقات|سوق\s*الخدمات|إشعارات|ذكاء\s*الأعمال|محاسبة|مبيعات|مخزون|مشتريات|موردين|عملاء|شحن|لوجست|crm|hr|pos|bi)\b/ui',
            $m
        ) === 1;
    }

    /**
     * @param array<string, mixed> $domain
     * @param array<string, mixed> $input
     * @return array<string, mixed>|null
     */
    private function runDomainWithTools(array $domain, array $input, ProcurementAgentContext $ctx): ?array
    {
        $domainId = (string) ($domain['id'] ?? '');
        $registry = (string) ($domain['tool_registry'] ?? '');
        $executor = (string) ($domain['tool_executor'] ?? '');
        if ($domainId === '' || $registry === '' || $executor === '' || !class_exists($registry) || !class_exists($executor)) {
            return null;
        }
        $label = (string) ($domain['label'] ?? $domainId);
        $cfg = $this->config;
        $agentCfg = is_array($cfg['agent'] ?? null) ? $cfg['agent'] : [];
        $agentCfg['tool_registry'] = $registry;
        $agentCfg['tool_executor'] = $executor;
        $promptKey = $domainId . '_system_prompt';
        $agentCfg['system_prompt'] = (string) ($agentCfg[$promptKey]
            ?? ('You are the RATEB ERP Agent in the ' . $label . ' domain. Use only approved tools for this domain. '
                . 'Never invent numbers or records. Tenant-scope every operation. Speak only in the UI language. '
                . 'For list/show requests call the matching list/summary tools first, then answer clearly from tool results. '
                . 'Never auto-execute writes — mutations require explicit confirmation.'));
        $cfg['agent'] = $agentCfg;
        $runtime = new ProcurementAgent($cfg, $this->llmClient);
        $result = $runtime->process($input, $ctx);
        return is_array($result) ? $result : null;
    }

    /**
     * Deterministic single-domain READ via orchestration plan (no LLM).
     *
     * @param array<string, mixed> $input
     * @param array<string, mixed> $intent
     * @return array<string, mixed>
     */
    private function processSingleDomainRead(
        array $input,
        ProcurementAgentContext $ctx,
        array $intent,
        string $domainId,
        string $requestId
    ): array {
        $startedAt = microtime(true);
        $plan = ErpOrchestrationPlanner::buildPlan($intent, $ctx, 4);
        $toolCalls = [];
        $auditEntries = [];
        $confirmed = [];
        $failed = [];

        $auditEntries[] = $this->logAudit($ctx, $requestId, 'single_domain_plan', [
            'domain' => $domainId,
            'plan_size' => count($plan),
            'tools' => array_map(static fn($s) => $s['tool'] ?? '', $plan),
        ], [
            'event_type' => 'single_domain_plan',
            'error_code' => null,
        ], 'success');

        foreach ($plan as $step) {
            $toolName = (string) ($step['tool'] ?? '');
            $arguments = is_array($step['arguments'] ?? null) ? $step['arguments'] : [];
            $stepDomain = (string) ($step['domain'] ?? $domainId);
            $purpose = (string) ($step['purpose'] ?? '');
            if ($toolName === '') {
                continue;
            }

            $policy = ProcurementPolicyGuard::checkAndExecute([
                'tool' => $toolName,
                'arguments' => $arguments,
                'request_company_id' => null,
                'request_id' => $requestId,
                'write_confirmed' => false,
            ], $ctx);

            if (!$policy['allowed']) {
                $code = (string) ($policy['error_code'] ?? 'permission_denied');
                $failed[] = [
                    'tool' => $toolName,
                    'domain' => $stepDomain,
                    'purpose' => $purpose,
                    'error_code' => $code,
                ];
                $toolCalls[] = [
                    'tool' => $toolName,
                    'domain' => $stepDomain,
                    'arguments' => $arguments,
                    'result' => ['success' => false, 'error' => $code, 'error_code' => $code],
                    'audit_status' => 'denied',
                ];
                continue;
            }

            $result = $this->executeRegisteredTool($toolName, $arguments, $ctx);
            if (!empty($result['success'])) {
                $confirmed[] = [
                    'tool' => $toolName,
                    'domain' => $stepDomain,
                    'purpose' => $purpose,
                    'data' => $result['data'] ?? null,
                ];
                $toolCalls[] = [
                    'tool' => $toolName,
                    'domain' => $stepDomain,
                    'arguments' => $arguments,
                    'result' => ['success' => true, 'data' => $result['data'] ?? null],
                    'audit_status' => 'success',
                ];
            } else {
                $code = (string) ($result['error_code'] ?? $result['error'] ?? 'tool_exception');
                $failed[] = [
                    'tool' => $toolName,
                    'domain' => $stepDomain,
                    'purpose' => $purpose,
                    'error_code' => $code,
                ];
                $toolCalls[] = [
                    'tool' => $toolName,
                    'domain' => $stepDomain,
                    'arguments' => $arguments,
                    'result' => ['success' => false, 'error' => $code, 'error_code' => $code],
                    'audit_status' => 'error',
                ];
            }
        }

        $response = $this->formatDomainDataReply($confirmed, $failed, $domainId, $ctx);

        return [
            'response' => $response,
            'tool_calls' => $toolCalls,
            'pending_confirmations' => [],
            'audit' => $auditEntries,
            'domain' => $domainId,
            'agent' => self::AGENT_ID,
            'observability' => [
                'request_id' => $requestId,
                'company_id' => $ctx->companyId,
                'user_id' => $ctx->userId,
                'success' => $confirmed !== [],
                'duration_ms' => (int) ((microtime(true) - $startedAt) * 1000),
                'domain' => $domainId,
                'path' => 'deterministic_read',
            ],
        ];
    }

    /**
     * @param list<array{tool: string, domain: string, purpose: string, data: mixed}> $confirmed
     * @param list<array{tool: string, domain: string, purpose: string, error_code: string}> $failed
     */
    private function formatDomainDataReply(
        array $confirmed,
        array $failed,
        string $domainId,
        ProcurementAgentContext $ctx
    ): string {
        $ar = $ctx->normalizedLocale() === 'ar';
        $label = $this->domainDisplayLabel($domainId, $ar);
        $lines = [];
        $lines[] = $ar
            ? ('▸ ' . $label . ' — نتائج مباشرة من بيانات الشركة')
            : ('▸ ' . $label . ' — live company data');

        foreach ($confirmed as $row) {
            $purpose = (string) ($row['purpose'] ?? '');
            $tool = (string) ($row['tool'] ?? '');
            $data = $row['data'] ?? null;
            if (!is_array($data)) {
                continue;
            }
            $title = $this->purposeTitle($purpose, $tool, $ar);
            $lines[] = '';
            $lines[] = '【' . $title . '】';

            if (isset($data['rows']) && is_array($data['rows'])) {
                $rows = $data['rows'];
                $lines[] = $ar
                    ? ('عدد السجلات: ' . count($rows))
                    : ('Records: ' . count($rows));
                if ($rows === []) {
                    $lines[] = $ar ? '- لا توجد سجلات حالياً.' : '- No records found.';
                    if (!empty($data['note']) && (string) $data['note'] === 'table_unavailable') {
                        $lines[] = $ar
                            ? '- جدول البيانات غير متاح لهذه الشركة بعد.'
                            : '- Data table is not available for this company yet.';
                    }
                    continue;
                }
                foreach (array_slice($rows, 0, 25) as $r) {
                    if (is_array($r)) {
                        $lines[] = '- ' . $this->formatRowBrief($r, $ar);
                    }
                }
                if (count($rows) > 25) {
                    $extra = count($rows) - 25;
                    $lines[] = $ar ? ('… و ' . $extra . ' أخرى') : ('… and ' . $extra . ' more');
                }
                continue;
            }

            $body = $this->formatLocalizedDataTree($data, $ar, 0);
            if ($body === []) {
                $lines[] = $ar ? '- تم جلب البيانات بنجاح.' : '- Data retrieved successfully.';
            } else {
                foreach ($body as $line) {
                    $lines[] = $line;
                }
            }
        }

        if ($confirmed === []) {
            if ($failed !== []) {
                $lines[] = $ar
                    ? 'تعذر جلب البيانات. تحقق من صلاحيات الوحدة أو أعد المحاولة.'
                    : 'Could not fetch data. Check module permissions or retry.';
                foreach ($failed as $f) {
                    $toolName = (string) ($f['tool'] ?? '');
                    $code = (string) ($f['error_code'] ?? '');
                    $lines[] = '- ' . ErpActionPlanner::labelTool($toolName, $ar)
                        . ($code !== '' ? ' (' . ErpActionPlanner::labelGovToken($code, $ar) . ')' : '');
                }
            } else {
                $lines[] = $ar
                    ? 'لا توجد أدوات قراءة جاهزة لهذا الطلب في هذا المجال.'
                    : 'No read tools were available for this request in this domain.';
            }
        }

        return implode("\n", array_values(array_filter($lines, static fn($l) => $l !== null)));
    }

    private function domainDisplayLabel(string $domainId, bool $ar): string
    {
        $key = 'ai_cap_' . $domainId;
        if (function_exists('__')) {
            $tr = __($key);
            if (is_string($tr) && $tr !== '' && $tr !== $key) {
                return $tr;
            }
        }
        $fallback = [
            ErpDomainRegistry::DOMAIN_PROCUREMENT => ['ar' => 'المشتريات', 'en' => 'Procurement'],
            ErpDomainRegistry::DOMAIN_INVENTORY => ['ar' => 'المخزون', 'en' => 'Inventory'],
            ErpDomainRegistry::DOMAIN_SUPPLIERS => ['ar' => 'الموردون', 'en' => 'Suppliers'],
            ErpDomainRegistry::DOMAIN_SALES => ['ar' => 'المبيعات', 'en' => 'Sales'],
            ErpDomainRegistry::DOMAIN_CRM => ['ar' => 'العملاء', 'en' => 'CRM'],
            ErpDomainRegistry::DOMAIN_LOGISTICS => ['ar' => 'اللوجستيات', 'en' => 'Logistics'],
            ErpDomainRegistry::DOMAIN_ACCOUNTING => ['ar' => 'الحسابات', 'en' => 'Accounting'],
            ErpDomainRegistry::DOMAIN_EXECUTIVE => ['ar' => 'التحليل التنفيذي', 'en' => 'Executive'],
            ErpDomainRegistry::DOMAIN_HR => ['ar' => 'الموارد البشرية', 'en' => 'HR'],
            ErpDomainRegistry::DOMAIN_RECRUITMENT => ['ar' => 'التوظيف', 'en' => 'Recruitment'],
            ErpDomainRegistry::DOMAIN_PROJECTS => ['ar' => 'المشاريع', 'en' => 'Projects'],
            ErpDomainRegistry::DOMAIN_CONTRACTS => ['ar' => 'العقود', 'en' => 'Contracts'],
            ErpDomainRegistry::DOMAIN_ASSETS => ['ar' => 'الأصول', 'en' => 'Assets'],
            ErpDomainRegistry::DOMAIN_PAYROLL => ['ar' => 'الرواتب', 'en' => 'Payroll'],
            ErpDomainRegistry::DOMAIN_MANUFACTURING => ['ar' => 'التصنيع', 'en' => 'Manufacturing'],
            ErpDomainRegistry::DOMAIN_QUALITY => ['ar' => 'الجودة', 'en' => 'Quality'],
            ErpDomainRegistry::DOMAIN_APPROVALS => ['ar' => 'الموافقات', 'en' => 'Approvals'],
            ErpDomainRegistry::DOMAIN_MARKETPLACE => ['ar' => 'سوق الخدمات', 'en' => 'Marketplace'],
            ErpDomainRegistry::DOMAIN_NOTIFICATIONS => ['ar' => 'الإشعارات', 'en' => 'Notifications'],
            ErpDomainRegistry::DOMAIN_BI => ['ar' => 'ذكاء الأعمال', 'en' => 'Business Intelligence'],
            ErpDomainRegistry::DOMAIN_WEBSITE => ['ar' => 'الموقع والمحتوى', 'en' => 'Website'],
        ];
        if (isset($fallback[$domainId])) {
            return $ar ? $fallback[$domainId]['ar'] : $fallback[$domainId]['en'];
        }
        $meta = ErpDomainRegistry::resolve($domainId);
        return (string) ($meta['label'] ?? $domainId);
    }

    private function purposeTitle(string $purpose, string $tool, bool $ar): string
    {
        $map = [
            'inventory_intelligence' => ['ar' => 'ملخص المخزون', 'en' => 'Inventory summary'],
            'inventory_procurement_links' => ['ar' => 'ربط المخزون بالمشتريات', 'en' => 'Inventory–procurement links'],
            'procurement_operations' => ['ar' => 'عمليات المشتريات', 'en' => 'Procurement operations'],
            'pending_approvals' => ['ar' => 'الموافقات المعلقة', 'en' => 'Pending approvals'],
            'operational_guidance' => ['ar' => 'إرشاد تشغيلي', 'en' => 'Operational guidance'],
            'supplier_intelligence' => ['ar' => 'ملخص الموردين', 'en' => 'Supplier summary'],
            'supplier_procurement_links' => ['ar' => 'ربط الموردين بالمشتريات', 'en' => 'Supplier–procurement links'],
            'supplier_inventory_links' => ['ar' => 'ربط الموردين بالمخزون', 'en' => 'Supplier–inventory links'],
            'sales_intelligence' => ['ar' => 'ملخص المبيعات', 'en' => 'Sales summary'],
            'sales_guidance' => ['ar' => 'إرشاد المبيعات', 'en' => 'Sales guidance'],
            'sales_inventory_links' => ['ar' => 'ربط المبيعات بالمخزون', 'en' => 'Sales–inventory links'],
            'sales_procurement_links' => ['ar' => 'ربط المبيعات بالمشتريات', 'en' => 'Sales–procurement links'],
            'sales_supplier_links' => ['ar' => 'ربط المبيعات بالموردين', 'en' => 'Sales–supplier links'],
            'sales_cross_domain_core' => ['ar' => 'تحليل المبيعات عبر المجالات', 'en' => 'Cross-domain sales'],
            'crm_intelligence' => ['ar' => 'ملخص العملاء', 'en' => 'CRM summary'],
            'crm_guidance' => ['ar' => 'إرشاد العملاء', 'en' => 'CRM guidance'],
            'crm_sales_links' => ['ar' => 'ربط العملاء بالمبيعات', 'en' => 'CRM–sales links'],
            'crm_inventory_links' => ['ar' => 'ربط العملاء بالمخزون', 'en' => 'CRM–inventory links'],
            'crm_procurement_links' => ['ar' => 'ربط العملاء بالمشتريات', 'en' => 'CRM–procurement links'],
            'crm_supplier_links' => ['ar' => 'ربط العملاء بالموردين', 'en' => 'CRM–supplier links'],
            'crm_commercial_core' => ['ar' => 'الذكاء التجاري للعملاء', 'en' => 'CRM commercial intelligence'],
            'logistics_intelligence' => ['ar' => 'ملخص اللوجستيات', 'en' => 'Logistics summary'],
            'logistics_guidance' => ['ar' => 'إرشاد اللوجستيات', 'en' => 'Logistics guidance'],
            'logistics_crm_links' => ['ar' => 'ربط اللوجستيات بالعملاء', 'en' => 'Logistics–CRM links'],
            'logistics_sales_links' => ['ar' => 'ربط اللوجستيات بالمبيعات', 'en' => 'Logistics–sales links'],
            'logistics_inventory_links' => ['ar' => 'ربط اللوجستيات بالمخزون', 'en' => 'Logistics–inventory links'],
            'logistics_procurement_links' => ['ar' => 'ربط اللوجستيات بالمشتريات', 'en' => 'Logistics–procurement links'],
            'logistics_supplier_links' => ['ar' => 'ربط اللوجستيات بالموردين', 'en' => 'Logistics–supplier links'],
            'accounting_intelligence' => ['ar' => 'ملخص الحسابات', 'en' => 'Accounting summary'],
            'accounting_guidance' => ['ar' => 'إرشاد الحسابات', 'en' => 'Accounting guidance'],
            'accounting_sales_links' => ['ar' => 'ربط الحسابات بالمبيعات', 'en' => 'Accounting–sales links'],
            'accounting_procurement_links' => ['ar' => 'ربط الحسابات بالمشتريات', 'en' => 'Accounting–procurement links'],
            'accounting_supplier_links' => ['ar' => 'ربط الحسابات بالموردين', 'en' => 'Accounting–supplier links'],
            'accounting_inventory_links' => ['ar' => 'ربط الحسابات بالمخزون', 'en' => 'Accounting–inventory links'],
            'accounting_logistics_links' => ['ar' => 'ربط الحسابات باللوجستيات', 'en' => 'Accounting–logistics links'],
            'financial_intelligence_core' => ['ar' => 'الذكاء المالي', 'en' => 'Financial intelligence'],
            'executive_intelligence_core' => ['ar' => 'الذكاء التنفيذي', 'en' => 'Executive intelligence'],
            'proactive_early_warning_core' => ['ar' => 'التحذيرات المبكرة', 'en' => 'Early warnings'],
            'operational_learning_core' => ['ar' => 'التعلم التشغيلي', 'en' => 'Operational learning'],
            'operational_memory_core' => ['ar' => 'السياق التشغيلي', 'en' => 'Operational context'],
            'cross_domain_core' => ['ar' => 'تحليل عبر المجالات', 'en' => 'Cross-domain analysis'],
            'hr_employees' => ['ar' => 'الموظفون', 'en' => 'Employees'],
            'hr_summary' => ['ar' => 'ملخص القوى العاملة', 'en' => 'Workforce summary'],
            'recruitment_candidates' => ['ar' => 'المرشحون', 'en' => 'Candidates'],
            'recruitment_summary' => ['ar' => 'ملخص التوظيف', 'en' => 'Recruitment summary'],
            'projects_list' => ['ar' => 'المشاريع', 'en' => 'Projects'],
            'projects_summary' => ['ar' => 'ملخص المشاريع', 'en' => 'Projects summary'],
            'contracts_list' => ['ar' => 'العقود', 'en' => 'Contracts'],
            'contracts_summary' => ['ar' => 'ملخص العقود', 'en' => 'Contracts summary'],
            'assets_list' => ['ar' => 'الأصول', 'en' => 'Assets'],
            'assets_summary' => ['ar' => 'ملخص الأصول', 'en' => 'Assets summary'],
            'payroll_summary' => ['ar' => 'ملخص الرواتب', 'en' => 'Payroll summary'],
            'payroll_cycles' => ['ar' => 'دورات الرواتب', 'en' => 'Payroll cycles'],
            'mfg_orders' => ['ar' => 'أوامر التصنيع', 'en' => 'Production orders'],
            'mfg_summary' => ['ar' => 'ملخص التصنيع', 'en' => 'Manufacturing summary'],
            'quality_summary' => ['ar' => 'ملخص الجودة', 'en' => 'Quality summary'],
            'quality_ncrs' => ['ar' => 'عدم المطابقة', 'en' => 'Nonconformities'],
            'approvals_pending' => ['ar' => 'موافقات معلّقة', 'en' => 'Pending approvals'],
            'approvals_summary' => ['ar' => 'ملخص الموافقات', 'en' => 'Approvals summary'],
            'marketplace_summary' => ['ar' => 'ملخص سوق الخدمات', 'en' => 'Marketplace summary'],
            'marketplace_orders' => ['ar' => 'طلبات السوق', 'en' => 'Marketplace orders'],
            'notifications_digest' => ['ar' => 'ملخص الإشعارات', 'en' => 'Notifications digest'],
            'notifications_unread' => ['ar' => 'إشعارات غير مقروءة', 'en' => 'Unread notifications'],
            'bi_summary' => ['ar' => 'ملخص مؤشرات الأعمال', 'en' => 'BI summary'],
            'bi_kpis' => ['ar' => 'المؤشرات', 'en' => 'KPIs'],
            'website_summary' => ['ar' => 'ملخص الموقع', 'en' => 'Website summary'],
            'cms_pages' => ['ar' => 'صفحات الموقع', 'en' => 'CMS pages'],
        ];
        if (isset($map[$purpose])) {
            return $ar ? $map[$purpose]['ar'] : $map[$purpose]['en'];
        }
        if ($tool !== '') {
            $toolLabel = ErpActionPlanner::labelTool($tool, $ar);
            if ($toolLabel !== '' && $toolLabel !== $tool) {
                return $toolLabel;
            }
        }
        return $ar ? 'النتائج' : 'Results';
    }

    /**
     * @param array<string, mixed> $data
     * @return list<string>
     */
    private function formatLocalizedDataTree(array $data, bool $ar, int $depth): array
    {
        if ($depth > 3) {
            return [];
        }
        $skip = [
            'data_source', 'data source', 'company_id', 'table', 'request_id',
            'tenant_id', 'secrets_excluded', 'agent', 'domain',
        ];
        $lines = [];
        $indent = str_repeat('  ', $depth);

        // Prefer nested summary block when present.
        if (isset($data['summary']) && is_array($data['summary']) && $depth === 0) {
            $lines[] = $indent . ($ar ? 'الملخص:' : 'Summary:');
            foreach ($this->formatLocalizedDataTree($data['summary'], $ar, $depth + 1) as $line) {
                $lines[] = $line;
            }
            if (isset($data['as_of']) && is_scalar($data['as_of'])) {
                $lines[] = $indent . '- ' . $this->fieldLabel('as_of', $ar) . ': ' . $this->formatFieldValue('as_of', $data['as_of'], $ar);
            }
            if (isset($data['notes']) && is_array($data['notes'])) {
                foreach ($data['notes'] as $note) {
                    if (is_scalar($note)) {
                        $lines[] = $indent . '- ' . $this->translateNote((string) $note, $ar);
                    }
                }
            }
            // Also surface other useful top-level lists (low_stock, etc.) briefly.
            foreach ($data as $k => $v) {
                $key = (string) $k;
                if (in_array($key, ['summary', 'as_of', 'notes', 'data_source', 'data source', 'company_id'], true)) {
                    continue;
                }
                if (is_array($v) && isset($v[0]) && is_array($v[0])) {
                    $lines[] = $indent . $this->fieldLabel($key, $ar) . ' (' . count($v) . '):';
                    foreach (array_slice($v, 0, 8) as $row) {
                        if (is_array($row)) {
                            $lines[] = $indent . '- ' . $this->formatRowBrief($row, $ar);
                        }
                    }
                } elseif (is_scalar($v) && !in_array(strtolower($key), $skip, true)) {
                    $lines[] = $indent . '- ' . $this->fieldLabel($key, $ar) . ': ' . $this->formatFieldValue($key, $v, $ar);
                }
            }
            return $lines;
        }

        foreach ($data as $k => $v) {
            $key = (string) $k;
            if (in_array(strtolower($key), $skip, true) || in_array($key, $skip, true)) {
                continue;
            }
            if ($key === 'notes' && is_array($v)) {
                foreach ($v as $note) {
                    if (is_scalar($note)) {
                        $lines[] = $indent . '- ' . $this->translateNote((string) $note, $ar);
                    }
                }
                continue;
            }
            if (is_scalar($v)) {
                $lines[] = $indent . '- ' . $this->fieldLabel($key, $ar) . ': ' . $this->formatFieldValue($key, $v, $ar);
                continue;
            }
            if (!is_array($v) || $v === []) {
                continue;
            }
            // List of row objects
            if (isset($v[0]) && is_array($v[0])) {
                $lines[] = $indent . $this->fieldLabel($key, $ar) . ' (' . count($v) . '):';
                foreach (array_slice($v, 0, 8) as $row) {
                    if (is_array($row)) {
                        $lines[] = $indent . '- ' . $this->formatRowBrief($row, $ar);
                    }
                }
                continue;
            }
            // Associative map of scalars (by_status etc.)
            $allScalar = true;
            foreach ($v as $sv) {
                if (!is_scalar($sv) && $sv !== null) {
                    $allScalar = false;
                    break;
                }
            }
            if ($allScalar) {
                $lines[] = $indent . $this->fieldLabel($key, $ar) . ':';
                foreach ($v as $sk => $sv) {
                    $lines[] = $indent . '  - ' . $this->fieldLabel((string) $sk, $ar) . ': ' . $this->formatFieldValue((string) $sk, $sv, $ar);
                }
                continue;
            }
            $lines[] = $indent . $this->fieldLabel($key, $ar) . ':';
            foreach ($this->formatLocalizedDataTree($v, $ar, $depth + 1) as $line) {
                $lines[] = $line;
            }
        }
        return $lines;
    }

    private function fieldLabel(string $key, bool $ar): string
    {
        $k = strtolower(trim(str_replace([' ', '-'], '_', $key)));
        $map = [
            'as_of' => ['ar' => 'تاريخ البيانات', 'en' => 'As of'],
            'item_count' => ['ar' => 'عدد الأصناف', 'en' => 'Item count'],
            'total_quantity' => ['ar' => 'إجمالي الكمية', 'en' => 'Total quantity'],
            'total_value' => ['ar' => 'إجمالي القيمة', 'en' => 'Total value'],
            'available_item_count' => ['ar' => 'أصناف متوفرة', 'en' => 'Available items'],
            'zero_stock_item_count' => ['ar' => 'أصناف بدون مخزون', 'en' => 'Zero-stock items'],
            'warehouse_count' => ['ar' => 'عدد المستودعات', 'en' => 'Warehouses'],
            'movements_last_7_days' => ['ar' => 'حركات آخر 7 أيام', 'en' => 'Movements (7 days)'],
            'low_stock_count' => ['ar' => 'أصناف منخفضة', 'en' => 'Low stock'],
            'expiring_count' => ['ar' => 'أصناف قاربت الانتهاء', 'en' => 'Expiring'],
            'currency' => ['ar' => 'العملة', 'en' => 'Currency'],
            'summary' => ['ar' => 'الملخص', 'en' => 'Summary'],
            'notes' => ['ar' => 'ملاحظات', 'en' => 'Notes'],
            'total' => ['ar' => 'الإجمالي', 'en' => 'Total'],
            'by_status' => ['ar' => 'حسب الحالة', 'en' => 'By status'],
            'active' => ['ar' => 'نشط', 'en' => 'Active'],
            'inactive' => ['ar' => 'غير نشط', 'en' => 'Inactive'],
            'pending' => ['ar' => 'معلّق', 'en' => 'Pending'],
            'approved' => ['ar' => 'معتمد', 'en' => 'Approved'],
            'rejected' => ['ar' => 'مرفوض', 'en' => 'Rejected'],
            'draft' => ['ar' => 'مسودة', 'en' => 'Draft'],
            'open' => ['ar' => 'مفتوح', 'en' => 'Open'],
            'closed' => ['ar' => 'مغلق', 'en' => 'Closed'],
            'employees' => ['ar' => 'الموظفون', 'en' => 'Employees'],
            'employee_count' => ['ar' => 'عدد الموظفين', 'en' => 'Employees'],
            'leave_count' => ['ar' => 'طلبات الإجازة', 'en' => 'Leave requests'],
            'department_count' => ['ar' => 'عدد الأقسام', 'en' => 'Departments'],
            'candidates' => ['ar' => 'المرشحون', 'en' => 'Candidates'],
            'projects' => ['ar' => 'المشاريع', 'en' => 'Projects'],
            'contracts' => ['ar' => 'العقود', 'en' => 'Contracts'],
            'assets' => ['ar' => 'الأصول', 'en' => 'Assets'],
            'orders' => ['ar' => 'الطلبات', 'en' => 'Orders'],
            'customers' => ['ar' => 'العملاء', 'en' => 'Customers'],
            'leads' => ['ar' => 'العملاء المحتملون', 'en' => 'Leads'],
            'opportunities' => ['ar' => 'الفرص', 'en' => 'Opportunities'],
            'shipments' => ['ar' => 'الشحنات', 'en' => 'Shipments'],
            'trips' => ['ar' => 'الرحلات', 'en' => 'Trips'],
            'invoices' => ['ar' => 'الفواتير', 'en' => 'Invoices'],
            'receivables' => ['ar' => 'الذمم المدينة', 'en' => 'Receivables'],
            'payables' => ['ar' => 'الذمم الدائنة', 'en' => 'Payables'],
            'unread' => ['ar' => 'غير مقروء', 'en' => 'Unread'],
            'kpis' => ['ar' => 'المؤشرات', 'en' => 'KPIs'],
            'low_stock' => ['ar' => 'مخزون منخفض', 'en' => 'Low stock'],
            'expiring' => ['ar' => 'قارب على الانتهاء', 'en' => 'Expiring'],
            'warehouses' => ['ar' => 'المستودعات', 'en' => 'Warehouses'],
            'movements' => ['ar' => 'الحركات', 'en' => 'Movements'],
            'unknown' => ['ar' => 'غير محدد', 'en' => 'Unknown'],
            'status' => ['ar' => 'الحالة', 'en' => 'Status'],
            'name' => ['ar' => 'الاسم', 'en' => 'Name'],
            'code' => ['ar' => 'الرمز', 'en' => 'Code'],
            'quantity' => ['ar' => 'الكمية', 'en' => 'Quantity'],
            'amount' => ['ar' => 'المبلغ', 'en' => 'Amount'],
            'balance' => ['ar' => 'الرصيد', 'en' => 'Balance'],
            'count' => ['ar' => 'العدد', 'en' => 'Count'],
        ];
        if (isset($map[$k])) {
            return $ar ? $map[$k]['ar'] : $map[$k]['en'];
        }
        // Numeric list indexes → hide as labels
        if (ctype_digit($k)) {
            return $ar ? ('عنصر ' . ((int) $k + 1)) : ('Item ' . ((int) $k + 1));
        }
        // Soft humanize remaining snake_case (still better than raw)
        $human = str_replace('_', ' ', $k);
        return $ar ? $human : ucwords($human);
    }

    private function formatFieldValue(string $key, mixed $value, bool $ar): string
    {
        if ($value === null) {
            return $ar ? '—' : '—';
        }
        if (is_bool($value)) {
            return $value ? ($ar ? 'نعم' : 'Yes') : ($ar ? 'لا' : 'No');
        }
        $s = trim((string) $value);
        $k = strtolower($key);
        if ($k === 'status' || $k === 'bucket') {
            return $this->fieldLabel($s, $ar);
        }
        if (in_array($s, ['live_tenant', 'empty_lists_mean_no_matching_records', 'all_values_from_live_tenant_data', 'table_unavailable'], true)) {
            return $this->translateNote($s, $ar);
        }
        return $s;
    }

    private function translateNote(string $note, bool $ar): string
    {
        $map = [
            'empty_lists_mean_no_matching_records' => [
                'ar' => 'القوائم الفارغة تعني عدم وجود سجلات مطابقة حالياً.',
                'en' => 'Empty lists mean no matching records right now.',
            ],
            'all_values_from_live_tenant_data' => [
                'ar' => 'جميع القيم من بيانات الشركة الحية.',
                'en' => 'All values are from live company data.',
            ],
            'table_unavailable' => [
                'ar' => 'جدول البيانات غير متاح لهذه الشركة بعد.',
                'en' => 'Data table is not available for this company yet.',
            ],
            'live_tenant' => [
                'ar' => 'بيانات الشركة الحية',
                'en' => 'Live company data',
            ],
        ];
        $key = strtolower(trim($note));
        if (isset($map[$key])) {
            return $ar ? $map[$key]['ar'] : $map[$key]['en'];
        }
        return $note;
    }

    /**
     * @param array<string, mixed> $r
     */
    private function formatRowBrief(array $r, bool $ar = true): string
    {
        $parts = [];
        foreach (['employee_code', 'code', 'sku', 'name', 'title', 'status', 'email', 'job_title', 'order_no', 'id'] as $k) {
            if (isset($r[$k]) && $r[$k] !== '' && $r[$k] !== null) {
                $val = (string) $r[$k];
                if ($k === 'status') {
                    $val = $this->fieldLabel($val, $ar);
                }
                $parts[] = $val;
            }
        }
        if ($parts === []) {
            $i = 0;
            foreach ($r as $v) {
                if (is_scalar($v) && (string) $v !== '') {
                    $parts[] = (string) $v;
                    if (++$i >= 3) {
                        break;
                    }
                }
            }
        }
        return implode(' — ', array_slice(array_values(array_unique($parts)), 0, 4));
    }

    /**
     * @return array{response: string, tool_calls: array, pending_confirmations: array, audit: array, domain: string, agent: string, observability: array}
     */
    private function domainFailureResponse(
        ProcurementAgentContext $ctx,
        string $requestId,
        string $errorCode,
        string $domainId
    ): array {
        $locale = $ctx->normalizedLocale();
        $messages = [
            'domain_not_implemented' => [
                'ar' => 'هذا المجال غير مفعّل بعد في وكيل رتب.',
                'en' => 'This domain is not enabled yet in the RATEB agent.',
            ],
            'domain_unknown' => [
                'ar' => 'المجال المطلوب غير معروف.',
                'en' => 'The requested domain is unknown.',
            ],
            'domain_unavailable' => [
                'ar' => 'مجال الوكيل غير متاح حاليًا.',
                'en' => 'The agent domain is currently unavailable.',
            ],
            'module_not_entitled' => [
                'ar' => 'ليس لديك صلاحية عرض هذا المجال لهذه الشركة، أو الوحدة غير مفعّلة.',
                'en' => 'You are not entitled to this domain for this company, or the module is disabled.',
            ],
            'unauthorized' => [
                'ar' => 'يلزم تسجيل الدخول للمتابعة.',
                'en' => 'Authentication is required to continue.',
            ],
        ];
        $pair = $messages[$errorCode] ?? [
            'ar' => 'تعذر إكمال الطلب حاليًا.',
            'en' => 'Unable to complete the request right now.',
        ];
        $response = $locale === 'ar' ? $pair['ar'] : $pair['en'];
        if ($domainId !== '') {
            $label = $this->domainDisplayLabel($domainId, $locale === 'ar');
            if ($errorCode === 'module_not_entitled') {
                $response = $locale === 'ar'
                    ? ('لا يمكن فتح «' . $label . '»: الوحدة غير مفعّلة أو بلا صلاحية لهذه الشركة.')
                    : ('Cannot open “' . $label . '”: module disabled or not entitled for this company.');
            }
        }

        return [
            'response' => $response,
            'tool_calls' => [],
            'pending_confirmations' => [],
            'audit' => [[
                'tool' => 'domain_resolution',
                'status' => 'denied',
                'request_id' => $requestId,
                'error_code' => $errorCode,
                'domain' => $domainId,
            ]],
            'domain' => $domainId !== '' ? $domainId : ErpDomainRegistry::defaultDomainId(),
            'agent' => self::AGENT_ID,
            'observability' => [
                'request_id' => $requestId,
                'company_id' => $ctx->companyId,
                'user_id' => $ctx->userId,
                'success' => false,
                'error_code' => $errorCode,
                'domain' => $domainId,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $arguments
     * @param array<string, mixed> $output
     * @return array{tool: string, status: string, request_id: string, error_code?: string|null}
     */
    private function logAudit(
        ProcurementAgentContext $ctx,
        string $requestId,
        string $toolName,
        array $arguments,
        array $output,
        string $status
    ): array {
        $db = Database::connection();
        $safeInput = $this->sanitizeForAudit($arguments);
        $safeOutput = $this->sanitizeForAudit($output);
        $errorCode = $output['error_code'] ?? null;
        if (!is_string($errorCode) || $errorCode === '') {
            $errorCode = null;
        }
        $policyChecks = is_array($output['policy_checks'] ?? null) ? $output['policy_checks'] : [
            'auth' => true,
            'tenant_context' => true,
            'tool_allowlist' => true,
            'module_entitlement' => true,
            'rbac_permission' => true,
            'approval_policy' => true,
            'parameter_sufficiency' => true,
            'write_confirmation' => true,
            'execute' => true,
            'audit' => true,
            'orchestration' => !empty($output['orchestration']),
        ];

        try {
            $db->prepare(
                'INSERT INTO rateb_agent_audit_events
                 (company_id, user_id, session_id, tool_name, input_json, output_json, status, error_code, policy_checks_json, request_id, llm_model, llm_tokens_in, llm_tokens_out, duration_ms)
                 VALUES (:cid, :uid, :sid, :tool, :in, :out, :st, :ec, :pc, :rid, :model, :tin, :tout, :dur)'
            )->execute([
                'cid' => $ctx->companyId,
                'uid' => $ctx->userId,
                'sid' => $ctx->sessionId,
                'tool' => $toolName,
                'in' => json_encode($safeInput, JSON_UNESCAPED_UNICODE),
                'out' => json_encode($safeOutput, JSON_UNESCAPED_UNICODE),
                'st' => $status,
                'ec' => $errorCode,
                'pc' => json_encode($policyChecks, JSON_UNESCAPED_UNICODE),
                'rid' => $requestId,
                'model' => null,
                'tin' => null,
                'tout' => null,
                'dur' => (int) ($output['duration_ms'] ?? 0),
            ]);
        } catch (\Throwable $e) {
            return [
                'tool' => $toolName,
                'status' => 'audit_failed',
                'request_id' => $requestId,
                'error_code' => 'audit_write_failed',
            ];
        }

        return [
            'tool' => $toolName,
            'status' => $status,
            'request_id' => $requestId,
            'event_type' => $output['event_type'] ?? null,
            'error_code' => $errorCode,
            'orchestration' => !empty($output['orchestration']),
        ];
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    private function sanitizeForAudit($value)
    {
        if (!is_array($value)) {
            return $value;
        }
        $out = [];
        foreach ($value as $k => $v) {
            $key = strtolower((string) $k);
            if (str_contains($key, 'password') || str_contains($key, 'secret') || str_contains($key, 'token') || str_contains($key, 'api_key')) {
                $out[$k] = '[redacted]';
                continue;
            }
            $out[$k] = is_array($v) ? $this->sanitizeForAudit($v) : $v;
        }
        return $out;
    }
}
