<?php
declare(strict_types=1);

namespace Rateb\App\Services;

/**
 * ERP Agent Governance Layer (not an agent / not an automation engine).
 * Application-owned decisions for risk, execution level, boundaries, autonomy, observability, and decision trace.
 */
final class ErpGovernanceLayer
{
    public const LEVEL_READ_ONLY = 'READ_ONLY';
    public const LEVEL_ANALYSIS_ONLY = 'ANALYSIS_ONLY';
    public const LEVEL_RECOMMENDATION = 'RECOMMENDATION';
    public const LEVEL_CONFIRMED_WRITE = 'CONFIRMED_WRITE';
    public const LEVEL_APPROVED_WRITE = 'APPROVED_WRITE';
    public const LEVEL_CONTROLLED_AUTONOMY = 'CONTROLLED_AUTONOMY';

    public const RISK_LOW = 'LOW';
    public const RISK_MEDIUM = 'MEDIUM';
    public const RISK_HIGH = 'HIGH';
    public const RISK_CRITICAL = 'CRITICAL';

    /**
     * @param array<string, mixed> $intent
     * @param array<string, mixed>|null $actionPlan
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    public static function evaluate(
        string $message,
        array $intent,
        ProcurementAgentContext $ctx,
        array $config,
        ?array $actionPlan = null,
        bool $hasConfirmedWrites = false
    ): array {
        $domains = array_values(array_unique(array_filter(
            is_array($intent['domains'] ?? null) ? $intent['domains'] : [],
            static fn($d) => is_string($d) && $d !== ''
        )));
        $actions = is_array($actionPlan['actions'] ?? null) ? $actionPlan['actions'] : [];
        $unsupported = is_array($actionPlan['unsupported'] ?? null) ? $actionPlan['unsupported'] : [];
        $recommendations = is_array($actionPlan['recommendations'] ?? null) ? $actionPlan['recommendations'] : [];

        $hasWriteAction = false;
        $hasSensitive = false;
        $permissions = [];
        foreach ($actions as $a) {
            if (!is_array($a)) {
                continue;
            }
            $class = (string) ($a['class'] ?? ErpActionPlanner::classifyTool((string) ($a['tool'] ?? '')));
            if ($class === ErpActionPlanner::CLASS_SENSITIVE_WRITE) {
                $hasSensitive = true;
                $hasWriteAction = true;
            } elseif ($class === ErpActionPlanner::CLASS_WRITE) {
                $hasWriteAction = true;
            }
            $perm = (string) ($a['permission'] ?? '');
            if ($perm !== '') {
                $permissions[$perm] = true;
            }
        }

        $writeIntent = !empty($intent['write_intent']) || $hasWriteAction;
        $decision = !empty($intent['decision_support']);
        $cross = ($intent['mode'] ?? '') === 'cross';
        $analysis = $cross || $decision || (($intent['intent_kind'] ?? '') === 'analysis');

        $risk = self::evaluateRisk([
            'write' => $writeIntent,
            'sensitive' => $hasSensitive,
            'cross_domain' => $cross && count($domains) >= 2,
            'domains' => $domains,
            'financial_hint' => self::match($message, '/(amount|total|SAR|مبلغ|مالي|قيمة)/ui')
                || in_array('accounting', $domains, true),
            'approval' => $hasSensitive,
            'accounting' => in_array('accounting', $domains, true),
        ]);

        $executionLevel = self::LEVEL_READ_ONLY;
        if ($unsupported !== [] || $recommendations !== []) {
            $executionLevel = self::LEVEL_RECOMMENDATION;
        }
        if ($analysis && !$writeIntent) {
            $executionLevel = $decision ? self::LEVEL_RECOMMENDATION : self::LEVEL_ANALYSIS_ONLY;
        }
        if ($writeIntent && $hasWriteAction) {
            $executionLevel = $hasSensitive ? self::LEVEL_APPROVED_WRITE : self::LEVEL_CONFIRMED_WRITE;
        }
        if (!$writeIntent && !$analysis && $unsupported === [] && $recommendations === []) {
            $executionLevel = self::LEVEL_READ_ONLY;
        }

        $autonomy = self::autonomyDecision($actions, $config, $hasConfirmedWrites, $hasSensitive);
        if (!empty($autonomy['allowed']) && $executionLevel === self::LEVEL_CONFIRMED_WRITE) {
            // Foundation only — never escalate above permissions; confirmation still required when config says so.
            $executionLevel = self::LEVEL_CONTROLLED_AUTONOMY;
        }

        $boundaries = self::boundaryLimits($config);
        $permissionOk = true;
        $missingPerms = [];
        foreach (array_keys($permissions) as $perm) {
            if (!$ctx->can($perm)) {
                $permissionOk = false;
                $missingPerms[] = $perm;
            }
        }

        $requireConfirm = !empty($config['agent']['require_confirmation_for_write'] ?? true);
        $confirmationRequired = $hasWriteAction && $requireConfirm && !$hasConfirmedWrites;
        $approvalRequired = $hasSensitive;

        return [
            'governance_version' => 'phase15_v1',
            'intent' => [
                'mode' => (string) ($intent['mode'] ?? 'single'),
                'kind' => (string) ($intent['intent_kind'] ?? 'query'),
                'write_intent' => $writeIntent,
                'decision_support' => $decision,
                'summary' => mb_substr(trim($message), 0, 200),
            ],
            'risk' => $risk,
            'domains' => $domains,
            'tools' => array_values(array_unique(array_filter(array_map(
                static fn($a) => is_array($a) ? (string) ($a['tool'] ?? '') : '',
                $actions
            )))),
            'permissions' => array_keys($permissions),
            'permission_ok' => $permissionOk,
            'missing_permissions' => $missingPerms,
            'policy' => [
                'guard' => ProcurementPolicyGuard::class,
                'require_confirmation_for_write' => $requireConfirm,
                'confirmation_required' => $confirmationRequired,
                'approval_required' => $approvalRequired,
            ],
            'approval' => [
                'required' => $approvalRequired,
                'via' => $approvalRequired ? 'existing_purchase_request_workflow' : null,
            ],
            'confirmation' => [
                'required' => $confirmationRequired,
                'satisfied' => $hasWriteAction ? $hasConfirmedWrites : true,
            ],
            'execution_level' => $executionLevel,
            'controlled_autonomy' => $autonomy,
            'boundaries' => $boundaries,
            'tenant' => [
                'company_id' => (int) $ctx->companyId,
                'user_id' => (int) $ctx->userId,
            ],
            'llm_cannot_override' => true,
            'application_decides' => true,
        ];
    }

    /**
     * @param array{write?:bool,sensitive?:bool,cross_domain?:bool,domains?:list<string>,financial_hint?:bool,approval?:bool} $signals
     */
    public static function evaluateRisk(array $signals): string
    {
        if (!empty($signals['sensitive']) || !empty($signals['approval'])) {
            return self::RISK_CRITICAL;
        }
        if (!empty($signals['write']) && (!empty($signals['financial_hint']) || !empty($signals['accounting']))) {
            return self::RISK_CRITICAL;
        }
        if (!empty($signals['write'])) {
            return self::RISK_HIGH;
        }
        if (!empty($signals['accounting']) && !empty($signals['cross_domain'])) {
            return self::RISK_HIGH;
        }
        if (!empty($signals['accounting'])) {
            return self::RISK_MEDIUM;
        }
        if (!empty($signals['cross_domain']) && count($signals['domains'] ?? []) >= 3) {
            return self::RISK_MEDIUM;
        }
        if (!empty($signals['cross_domain'])) {
            return self::RISK_MEDIUM;
        }
        return self::RISK_LOW;
    }

    /**
     * Controlled autonomy foundation — default blocked.
     * LLM never decides; only explicit config + non-sensitive + confirmation policy.
     *
     * @param list<array<string,mixed>> $actions
     * @param array<string, mixed> $config
     * @return array{enabled:bool,allowed:bool,blocked:bool,reason:string,allowlist:list<string>}
     */
    public static function autonomyDecision(array $actions, array $config, bool $hasConfirmedWrites, bool $hasSensitive): array
    {
        $gov = is_array($config['governance'] ?? null) ? $config['governance'] : [];
        $enabled = !empty($gov['controlled_autonomy_enabled']);
        $allowlist = is_array($gov['autonomy_allowlist'] ?? null) ? $gov['autonomy_allowlist'] : [];
        $requireConfirm = !empty($config['agent']['require_confirmation_for_write'] ?? true);

        if (!$enabled) {
            return [
                'enabled' => false,
                'allowed' => false,
                'blocked' => true,
                'reason' => 'controlled_autonomy_disabled_by_default',
                'allowlist' => array_values(array_map('strval', $allowlist)),
            ];
        }
        if ($hasSensitive) {
            return [
                'enabled' => true,
                'allowed' => false,
                'blocked' => true,
                'reason' => 'sensitive_actions_never_autonomous',
                'allowlist' => array_values(array_map('strval', $allowlist)),
            ];
        }
        if ($requireConfirm && !$hasConfirmedWrites) {
            return [
                'enabled' => true,
                'allowed' => false,
                'blocked' => true,
                'reason' => 'confirmation_still_required',
                'allowlist' => array_values(array_map('strval', $allowlist)),
            ];
        }
        if ($allowlist === []) {
            return [
                'enabled' => true,
                'allowed' => false,
                'blocked' => true,
                'reason' => 'empty_autonomy_allowlist',
                'allowlist' => [],
            ];
        }
        foreach ($actions as $a) {
            if (!is_array($a)) {
                continue;
            }
            $tool = (string) ($a['tool'] ?? '');
            if ($tool !== '' && !in_array($tool, $allowlist, true)) {
                return [
                    'enabled' => true,
                    'allowed' => false,
                    'blocked' => true,
                    'reason' => 'tool_not_in_autonomy_allowlist',
                    'allowlist' => array_values(array_map('strval', $allowlist)),
                ];
            }
        }
        // Even when allowlisted: never skip confirmation while require_confirmation_for_write is true.
        return [
            'enabled' => true,
            'allowed' => false,
            'blocked' => true,
            'reason' => 'confirmation_policy_blocks_unattended_write',
            'allowlist' => array_values(array_map('strval', $allowlist)),
        ];
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, int>
     */
    public static function boundaryLimits(array $config): array
    {
        $gov = is_array($config['governance'] ?? null) ? $config['governance'] : [];
        $agent = is_array($config['agent'] ?? null) ? $config['agent'] : [];
        return [
            'max_domains_per_request' => max(1, min(6, (int) ($gov['max_domains_per_request'] ?? 6))),
            'max_tools_per_request' => max(1, min(12, (int) ($gov['max_tools_per_request'] ?? ($agent['max_orchestration_tools'] ?? 10)))),
            'max_writes_per_request' => max(1, min(3, (int) ($gov['max_writes_per_request'] ?? ($agent['max_writes_per_request'] ?? 1)))),
            'max_action_chain' => max(1, min(5, (int) ($gov['max_action_chain'] ?? 3))),
            'max_tool_calls_per_request' => max(1, min(20, (int) ($agent['max_tool_calls_per_request'] ?? 10))),
        ];
    }

    /**
     * @param array<string, mixed> $governance
     * @param list<string> $domains
     * @param list<string> $tools
     * @param int $writes
     * @return array{ok:bool,code:string|null,message:string|null}
     */
    public static function checkBoundaries(array $governance, array $domains, array $tools, int $writes = 0, int $chain = 0): array
    {
        $b = is_array($governance['boundaries'] ?? null) ? $governance['boundaries'] : self::boundaryLimits([]);
        if (count($domains) > (int) ($b['max_domains_per_request'] ?? 6)) {
            return ['ok' => false, 'code' => 'domain_traversal_limit', 'message' => 'excessive_domain_traversal'];
        }
        if (count($tools) > (int) ($b['max_tools_per_request'] ?? 10)) {
            return ['ok' => false, 'code' => 'tool_call_limit', 'message' => 'excessive_tool_calls'];
        }
        if ($writes > (int) ($b['max_writes_per_request'] ?? 1)) {
            return ['ok' => false, 'code' => 'write_limit', 'message' => 'excessive_writes'];
        }
        if ($chain > (int) ($b['max_action_chain'] ?? 3)) {
            return ['ok' => false, 'code' => 'action_chain_limit', 'message' => 'unbounded_action_chain'];
        }
        $uniqueTools = array_unique($tools);
        if (count($tools) > count($uniqueTools)) {
            return ['ok' => false, 'code' => 'duplicate_tool_call', 'message' => 'duplicate_tool_calls_detected'];
        }
        return ['ok' => true, 'code' => null, 'message' => null];
    }

    /**
     * Operational decision trace (no hidden chain-of-thought).
     *
     * @param array<string, mixed> $governance
     * @param array<string, mixed> $result
     * @return array<string, mixed>
     */
    public static function buildDecisionTrace(array $governance, array $result, ProcurementAgentContext $ctx): array
    {
        $pending = is_array($result['pending_confirmations'] ?? null) ? $result['pending_confirmations'] : [];
        $action = is_array($result['action'] ?? null) ? $result['action'] : [];
        $orch = is_array($result['orchestration'] ?? null) ? $result['orchestration'] : [];
        $executed = is_array($action['results'] ?? null) ? $action['results'] : [];
        $failed = is_array($orch['failed'] ?? null) ? $orch['failed'] : [];
        $partial = !empty($action['partial']);

        $executedTools = [];
        foreach ($executed as $r) {
            if (is_array($r) && !empty($r['success'])) {
                $executedTools[] = (string) ($r['tool'] ?? '');
            }
        }
        $notExecuted = [];
        foreach ($pending as $p) {
            if (is_array($p)) {
                $notExecuted[] = (string) ($p['tool'] ?? '') . ':confirmation_required';
            }
        }
        foreach ($failed as $f) {
            if (is_array($f)) {
                $notExecuted[] = (string) ($f['tool'] ?? '') . ':' . (string) ($f['error_code'] ?? 'failed');
            }
        }

        return [
            'understood_intent' => (string) (($governance['intent']['summary'] ?? '') ?: ''),
            'intent_mode' => (string) ($governance['intent']['mode'] ?? ''),
            'evidence_basis' => [
                'data_source' => 'live_tenant',
                'domains' => $governance['domains'] ?? [],
                'tools_planned' => $governance['tools'] ?? [],
                'intelligence_used' => !empty($action['intelligence_used']) || !empty($orch['intelligence']),
            ],
            'proposed_action' => $governance['tools'] ?? [],
            'why_confirmation' => !empty($governance['confirmation']['required'])
                ? 'write_requires_user_confirmation_per_policy'
                : null,
            'why_approval' => !empty($governance['approval']['required'])
                ? 'sensitive_write_uses_existing_approval_workflow'
                : null,
            'executed' => array_values(array_filter($executedTools)),
            'verified' => array_values(array_filter(array_map(
                static function ($r) {
                    if (!is_array($r) || empty($r['verification']['verified'])) {
                        return null;
                    }
                    return (string) ($r['tool'] ?? '');
                },
                $executed
            ))),
            'not_executed' => array_values(array_filter($notExecuted)),
            'risk' => (string) ($governance['risk'] ?? self::RISK_LOW),
            'execution_level' => (string) ($governance['execution_level'] ?? self::LEVEL_READ_ONLY),
            'autonomy_blocked' => !empty($governance['controlled_autonomy']['blocked']),
            'partial' => $partial,
            'locale' => $ctx->normalizedLocale(),
        ];
    }

    /**
     * @param array<string, mixed> $governance
     * @param array<string, mixed> $decisionTrace
     * @param array<string, mixed> $result
     * @return array<string, mixed>
     */
    public static function buildObservability(
        array $governance,
        array $decisionTrace,
        array $result,
        string $requestId,
        ProcurementAgentContext $ctx,
        int $durationMs,
        string $finalStatus
    ): array {
        return [
            'request_id' => $requestId,
            'user_id' => (int) $ctx->userId,
            'company_id' => (int) $ctx->companyId,
            'tenant_id' => (int) $ctx->companyId,
            'intent' => $governance['intent'] ?? [],
            'risk' => $governance['risk'] ?? self::RISK_LOW,
            'domains' => $governance['domains'] ?? [],
            'tools' => $governance['tools'] ?? [],
            'actions' => array_map(
                static fn($r) => is_array($r) ? [
                    'tool' => $r['tool'] ?? '',
                    'success' => !empty($r['success']),
                    'error_code' => $r['error_code'] ?? null,
                ] : [],
                is_array($result['action']['results'] ?? null) ? $result['action']['results'] : []
            ),
            'authorization' => [
                'permission_ok' => !empty($governance['permission_ok']),
                'permissions' => $governance['permissions'] ?? [],
                'missing' => $governance['missing_permissions'] ?? [],
            ],
            'confirmation' => $governance['confirmation'] ?? [],
            'approval' => $governance['approval'] ?? [],
            'execution_level' => $governance['execution_level'] ?? self::LEVEL_READ_ONLY,
            'execution_result' => $finalStatus,
            'verification' => array_map(
                static fn($r) => is_array($r) ? ($r['verification'] ?? null) : null,
                is_array($result['action']['results'] ?? null) ? $result['action']['results'] : []
            ),
            'errors' => array_values(array_filter(array_map(
                static fn($r) => is_array($r) && empty($r['success']) ? (string) ($r['error_code'] ?? 'error') : null,
                is_array($result['action']['results'] ?? null) ? $result['action']['results'] : []
            ))),
            'duration_ms' => $durationMs,
            'final_status' => $finalStatus,
            'decision_trace' => $decisionTrace,
            'secrets_excluded' => true,
            'agent' => ErpAgent::AGENT_ID,
        ];
    }

    /**
     * Format short operational governance summary for the user (AR/EN).
     *
     * @param array<string, mixed> $governance
     * @param array<string, mixed> $trace
     */
    public static function formatGovernanceSummary(array $governance, array $trace, ProcurementAgentContext $ctx): string
    {
        $ar = $ctx->normalizedLocale() === 'ar';
        $lines = [];
        $lines[] = $ar ? 'حوكمة التنفيذ:' : 'Execution governance:';
        $lines[] = ($ar ? '- المخاطر: ' : '- Risk: ') . (string) ($governance['risk'] ?? '');
        $lines[] = ($ar ? '- مستوى التنفيذ: ' : '- Execution level: ') . (string) ($governance['execution_level'] ?? '');
        if (!empty($governance['controlled_autonomy']['blocked'])) {
            $lines[] = $ar
                ? '- الاستقلالية المتحكم بها: موقوفة (' . (string) ($governance['controlled_autonomy']['reason'] ?? '') . ')'
                : '- Controlled autonomy: blocked (' . (string) ($governance['controlled_autonomy']['reason'] ?? '') . ')';
        }
        if (!empty($governance['confirmation']['required'])) {
            $lines[] = $ar ? '- يلزم تأكيد المستخدم قبل الكتابة.' : '- User confirmation required before write.';
        }
        if (!empty($governance['approval']['required'])) {
            $lines[] = $ar ? '- يلزم مسار الموافقة الحالي للعملية الحساسة.' : '- Existing approval workflow required for sensitive action.';
        }
        if (!empty($trace['executed'])) {
            $lines[] = ($ar ? '- تم التنفيذ: ' : '- Executed: ') . implode(', ', $trace['executed']);
        }
        if (!empty($trace['not_executed'])) {
            $lines[] = ($ar ? '- لم يُنفَّذ: ' : '- Not executed: ') . implode(', ', $trace['not_executed']);
        }
        if (!empty($trace['partial'])) {
            $lines[] = $ar ? '- حالة جزئية: تم إيقاف الإجراءات التابعة.' : '- Partial: dependent actions stopped.';
        }
        return implode("\n", $lines);
    }

    private static function match(string $message, string $pattern): bool
    {
        return $message !== '' && preg_match($pattern, $message) === 1;
    }
}
