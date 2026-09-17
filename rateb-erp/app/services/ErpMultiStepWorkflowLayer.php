<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\SystemSetting;

/**
 * Multi-Step Workflow Intelligence — coordinates ActionPlanner steps safely.
 * Not a BPM / Automation / Workflow Engine. Never bypasses Governance or Confirmation.
 * Writes still: ActionPlanner → Governance → Confirmation → Execute → Verify.
 */
final class ErpMultiStepWorkflowLayer
{
    public const ST_PLANNED = 'PLANNED';
    public const ST_AUTHORIZED = 'AUTHORIZED';
    public const ST_CONFIRMED = 'CONFIRMED';
    public const ST_RUNNING = 'RUNNING';
    public const ST_VERIFICATION_PENDING = 'VERIFICATION_PENDING';
    public const ST_VERIFIED = 'VERIFIED';
    public const ST_BLOCKED = 'BLOCKED';
    public const ST_FAILED = 'FAILED';
    public const ST_PARTIAL = 'PARTIAL';
    public const ST_RECOVERY_REQUIRED = 'RECOVERY_REQUIRED';
    public const ST_COMPLETED = 'COMPLETED';
    public const ST_CANCELLED = 'CANCELLED';

    public const STEP_PENDING = 'PENDING';
    public const STEP_READY = 'READY';
    public const STEP_RUNNING = 'RUNNING';
    public const STEP_VERIFICATION_PENDING = 'VERIFICATION_PENDING';
    public const STEP_VERIFIED = 'VERIFIED';
    public const STEP_COMPLETED = 'COMPLETED';
    public const STEP_FAILED = 'FAILED';
    public const STEP_BLOCKED = 'BLOCKED';
    public const STEP_SKIPPED = 'SKIPPED';
    public const STEP_CANCELLED = 'CANCELLED';

    private const STUCK_MINUTES = 30;
    private const MAX_ACTIVE = 40;
    private const MAX_HISTORY = 80;

    /** @var array<string, array{from: list<string>}> */
    private const TRANSITIONS = [
        self::ST_PLANNED => ['from' => []],
        self::ST_AUTHORIZED => ['from' => [self::ST_PLANNED]],
        self::ST_CONFIRMED => ['from' => [self::ST_AUTHORIZED, self::ST_PLANNED]],
        self::ST_RUNNING => ['from' => [self::ST_CONFIRMED, self::ST_AUTHORIZED, self::ST_VERIFIED, self::ST_RECOVERY_REQUIRED]],
        self::ST_VERIFICATION_PENDING => ['from' => [self::ST_RUNNING]],
        self::ST_VERIFIED => ['from' => [self::ST_VERIFICATION_PENDING, self::ST_RUNNING]],
        self::ST_BLOCKED => ['from' => [self::ST_PLANNED, self::ST_AUTHORIZED, self::ST_CONFIRMED, self::ST_RUNNING, self::ST_VERIFICATION_PENDING]],
        self::ST_FAILED => ['from' => [self::ST_RUNNING, self::ST_VERIFICATION_PENDING, self::ST_BLOCKED]],
        self::ST_PARTIAL => ['from' => [self::ST_RUNNING, self::ST_VERIFICATION_PENDING, self::ST_FAILED, self::ST_BLOCKED]],
        self::ST_RECOVERY_REQUIRED => ['from' => [self::ST_FAILED, self::ST_PARTIAL, self::ST_BLOCKED, self::ST_VERIFICATION_PENDING]],
        self::ST_COMPLETED => ['from' => [self::ST_VERIFIED, self::ST_RUNNING, self::ST_CONFIRMED]],
        self::ST_CANCELLED => ['from' => [
            self::ST_PLANNED, self::ST_AUTHORIZED, self::ST_CONFIRMED, self::ST_RUNNING,
            self::ST_BLOCKED, self::ST_FAILED, self::ST_PARTIAL, self::ST_RECOVERY_REQUIRED,
            self::ST_VERIFICATION_PENDING,
        ]],
    ];

    /** @var array<string, mixed> */
    private static array $memo = [];

    public static function clearMemo(): void
    {
        self::$memo = [];
    }

    /**
     * Build a multi-step workflow plan from intent + ActionPlanner + optional intelligence.
     *
     * @param array<string, mixed>|null $intelligence
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public static function plan(
        string $message,
        ProcurementAgentContext $ctx,
        ?array $intelligence = null,
        array $options = []
    ): array {
        $companyId = (int) $ctx->companyId;
        if ($companyId < 1) {
            return self::emptyWorkflow('tenant_mismatch');
        }

        $t0 = microtime(true);
        $actionPlan = ErpActionPlanner::buildActionPlan($message, $ctx, $intelligence);
        $memory = ErpOperationalMemoryLayer::forIntelligence($ctx, [
            'intent_kind' => 'decision',
            'write_intent' => true,
            'memory' => true,
        ]);

        $steps = [];
        $stepNo = 0;

        // Analysis precondition steps (READ) when shortage / cross-domain intent
        if (self::needsInventoryCheck($message, $intelligence)) {
            $stepNo++;
            $steps[] = self::makeStep($stepNo, [
                'domain' => 'inventory',
                'intent' => 'check_inventory_availability',
                'tool' => 'analyze_inventory',
                'arguments' => ['limit' => 8],
                'class' => ErpActionPlanner::CLASS_ANALYSIS,
                'required_evidence' => ['live_inventory_status'],
                'authorization' => ['permission' => 'inventory.manage', 'module' => 'inventory'],
                'expected_result' => 'inventory_availability_known',
                'dependency' => null,
                'condition' => null,
                'write' => false,
            ]);
        }

        foreach (($actionPlan['actions'] ?? []) as $action) {
            if (!is_array($action)) {
                continue;
            }
            $stepNo++;
            $dep = $steps !== [] ? (int) ($steps[count($steps) - 1]['step_no'] ?? 0) : null;
            $condition = null;
            if (self::needsInventoryCheck($message, $intelligence) && ($action['tool'] ?? '') === 'create_draft_purchase_request') {
                $condition = [
                    'type' => 'if_not',
                    'evidence_key' => 'inventory_available',
                    'then' => 'execute',
                    'else' => 'skip',
                    'reason' => 'procurement_only_when_inventory_insufficient',
                ];
                $dep = 1; // depends on inventory analysis step
            }
            // submit depends on create when both present
            if (($action['tool'] ?? '') === 'submit_purchase_request' && self::hasToolStep($steps, 'create_draft_purchase_request')) {
                $dep = self::stepNoForTool($steps, 'create_draft_purchase_request');
            }

            $steps[] = self::makeStep($stepNo, [
                'domain' => (string) ($action['domain'] ?? 'procurement'),
                'intent' => (string) ($action['purpose'] ?? $action['tool'] ?? ''),
                'tool' => (string) ($action['tool'] ?? ''),
                'arguments' => is_array($action['arguments'] ?? null) ? $action['arguments'] : [],
                'class' => (string) ($action['class'] ?? ErpActionPlanner::CLASS_WRITE),
                'required_evidence' => ['live_state_fingerprint'],
                'authorization' => [
                    'permission' => (string) ($action['permission'] ?? ''),
                    'module' => (string) ($action['module'] ?? ''),
                    'requires_confirmation' => !empty($action['requires_confirmation']),
                    'requires_approval_workflow' => !empty($action['requires_approval_workflow']),
                ],
                'expected_result' => 'verified_' . (string) ($action['tool'] ?? 'action'),
                'dependency' => $dep,
                'condition' => $condition,
                'write' => in_array(($action['class'] ?? ''), [ErpActionPlanner::CLASS_WRITE, ErpActionPlanner::CLASS_SENSITIVE_WRITE], true),
                'confirm_key' => (string) ($action['confirm_key'] ?? ''),
                'state_fingerprint' => (string) ($action['state_fingerprint'] ?? ''),
                'previous_state' => $action['previous_state'] ?? null,
                'action_ref' => $action,
            ]);
        }

        // Unsupported → recommendation steps (non-executable)
        foreach (($actionPlan['unsupported'] ?? []) as $u) {
            if (!is_array($u)) {
                continue;
            }
            $stepNo++;
            $steps[] = self::makeStep($stepNo, [
                'domain' => (string) ($u['domain'] ?? ''),
                'intent' => (string) ($u['requested'] ?? 'unsupported'),
                'tool' => '',
                'arguments' => [],
                'class' => ErpActionPlanner::CLASS_RECOMMENDATION,
                'required_evidence' => [],
                'authorization' => ['permission' => '', 'module' => ''],
                'expected_result' => 'recommendation_only',
                'dependency' => null,
                'condition' => null,
                'write' => false,
                'status' => self::STEP_SKIPPED,
                'skip_reason' => (string) ($u['reason'] ?? 'unsupported'),
            ]);
        }

        if ($steps === []) {
            return [
                'data_source' => 'live_tenant',
                'company_id' => $companyId,
                'workflow_id' => null,
                'status' => self::ST_CANCELLED,
                'steps' => [],
                'message' => 'no_executable_or_analysis_steps',
                'action_plan' => $actionPlan,
                'auto_execute' => false,
            ];
        }

        $workflowId = 'wf_' . $companyId . '_' . date('YmdHis') . '_' . substr(bin2hex(random_bytes(4)), 0, 8);
        $writeCount = count(array_filter($steps, static fn($s) => !empty($s['write'])));
        $wf = [
            'data_source' => 'live_tenant',
            'workflow_id' => $workflowId,
            'company_id' => $companyId,
            'user_id' => (int) $ctx->userId,
            'intent' => mb_substr(trim($message), 0, 240),
            'status' => self::ST_PLANNED,
            'started_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
            'ended_at' => null,
            'current_step' => 1,
            'steps' => $steps,
            'completed_steps' => [],
            'failed_steps' => [],
            'blocked_steps' => [],
            'skipped_steps' => [],
            'recovery_attempts' => 0,
            'recovery' => [
                'required' => false,
                'option' => null,
                'safe_retry' => false,
                'compensation_available' => false,
                'note' => 'no_invented_rollback',
            ],
            'outcome' => null,
            'outcome_status' => null,
            'requires_confirmation' => $writeCount > 0 || !empty($actionPlan['requires_confirmation']),
            'confirmation_scope' => [
                'goal' => mb_substr(trim($message), 0, 240),
                'domains' => array_values(array_unique(array_map(
                    static fn($s) => (string) ($s['domain'] ?? ''),
                    $steps
                ))),
                'write_steps' => $writeCount,
                'expected_impact' => $writeCount > 0 ? 'multi_step_write' : 'analysis_only',
                'required_approvals' => array_values(array_filter(array_map(
                    static fn($s) => !empty($s['authorization']['requires_approval_workflow'])
                        ? (string) ($s['tool'] ?? '')
                        : '',
                    $steps
                ))),
                'risks' => self::planRisks($steps, $memory),
            ],
            'memory_summary' => array_slice($memory['summary_lines'] ?? [], 0, 6),
            'governance' => [
                'bypassed' => false,
                'requires_action_planner' => true,
                'requires_governance' => true,
                'requires_confirmation_per_write' => true,
            ],
            'observability' => [
                'duration_ms' => (int) round((microtime(true) - $t0) * 1000),
                'step_count' => count($steps),
                'write_count' => $writeCount,
            ],
            'explainability' => [
                'hidden_chain_of_thought' => false,
                'format' => 'step_evidence_status_next_action',
            ],
            'auto_execute' => false,
            'llm_cannot_execute_writes' => true,
            'action_plan' => [
                'domains' => $actionPlan['domains'] ?? [],
                'recommendations' => $actionPlan['recommendations'] ?? [],
                'unsupported' => $actionPlan['unsupported'] ?? [],
            ],
        ];

        // Mark first ready step
        $wf = self::refreshStepReadiness($wf);

        if (!empty($options['persist'])) {
            self::saveWorkflow($companyId, $wf);
        }

        return $wf;
    }

    /**
     * Transition workflow status with legal-state enforcement.
     *
     * @param array<string, mixed> $wf
     * @return array<string, mixed>
     */
    public static function transition(array $wf, string $to, ?string $reason = null): array
    {
        $from = (string) ($wf['status'] ?? '');
        if (!self::canTransition($from, $to)) {
            $wf['last_illegal_transition'] = [
                'from' => $from,
                'to' => $to,
                'blocked' => true,
                'at' => date('Y-m-d H:i:s'),
            ];
            return $wf;
        }
        $wf['status'] = $to;
        $wf['updated_at'] = date('Y-m-d H:i:s');
        if ($reason !== null) {
            $wf['status_reason'] = $reason;
        }
        if (in_array($to, [self::ST_COMPLETED, self::ST_CANCELLED, self::ST_FAILED], true)) {
            $wf['ended_at'] = date('Y-m-d H:i:s');
        }
        return $wf;
    }

    public static function canTransition(string $from, string $to): bool
    {
        if ($from === $to) {
            return true;
        }
        if ($from === '') {
            return $to === self::ST_PLANNED;
        }
        $rule = self::TRANSITIONS[$to] ?? null;
        if ($rule === null) {
            return false;
        }
        return in_array($from, $rule['from'], true);
    }

    /**
     * Authorize steps via existing PolicyGuard (no bypass).
     *
     * @param array<string, mixed> $wf
     * @return array<string, mixed>
     */
    public static function authorize(array $wf, ProcurementAgentContext $ctx): array
    {
        if ((int) ($wf['company_id'] ?? 0) !== (int) $ctx->companyId) {
            return self::transition($wf, self::ST_BLOCKED, 'tenant_mismatch');
        }
        $blocked = [];
        foreach (($wf['steps'] ?? []) as &$step) {
            if (!is_array($step) || ($step['status'] ?? '') === self::STEP_SKIPPED) {
                continue;
            }
            $tool = (string) ($step['tool'] ?? '');
            if ($tool === '' || empty($step['write'])) {
                $step['authorized'] = true;
                continue;
            }
            $policy = ProcurementPolicyGuard::checkAndExecute([
                'tool' => $tool,
                'arguments' => is_array($step['arguments'] ?? null) ? $step['arguments'] : [],
                'request_company_id' => null,
                'request_id' => 'wf-auth-' . (string) ($wf['workflow_id'] ?? ''),
                'write_confirmed' => false,
            ], $ctx);
            $step['authorized'] = !empty($policy['allowed']);
            $step['auth_error'] = empty($policy['allowed']) ? (string) ($policy['error_code'] ?? 'permission_denied') : null;
            if (!$step['authorized']) {
                $step['status'] = self::STEP_BLOCKED;
                $blocked[] = (int) ($step['step_no'] ?? 0);
            }
        }
        unset($step);
        $wf['blocked_steps'] = $blocked;
        if ($blocked !== []) {
            return self::transition($wf, self::ST_BLOCKED, 'authorization_failed');
        }
        return self::transition($wf, self::ST_AUTHORIZED, 'all_steps_authorized');
    }

    /**
     * Mark workflow confirmed (user confirmed write scope). Does not execute.
     *
     * @param array<string, mixed> $wf
     * @param list<string> $confirmedKeys confirm_keys for write steps
     * @return array<string, mixed>
     */
    public static function confirm(array $wf, array $confirmedKeys): array
    {
        $keys = array_fill_keys(array_map('strval', $confirmedKeys), true);
        $missing = [];
        foreach (($wf['steps'] ?? []) as &$step) {
            if (!is_array($step) || empty($step['write'])) {
                continue;
            }
            if (($step['status'] ?? '') === self::STEP_SKIPPED) {
                continue;
            }
            $ck = (string) ($step['confirm_key'] ?? '');
            $step['confirmed'] = $ck !== '' && isset($keys[$ck]);
            if (!$step['confirmed']) {
                $missing[] = (int) ($step['step_no'] ?? 0);
            }
        }
        unset($step);
        if ($missing !== []) {
            $wf['confirmation_missing_steps'] = $missing;
            return self::transition($wf, self::ST_BLOCKED, 'confirmation_incomplete_for_write_scope');
        }
        return self::transition($wf, self::ST_CONFIRMED, 'write_scope_confirmed');
    }

    /**
     * Evaluate conditional branching for a step using live evidence (never LLM-authored).
     *
     * @param array<string, mixed> $step
     * @param array<string, mixed> $evidence
     * @return array{action: string, reason: string}
     */
    public static function evaluateCondition(array $step, array $evidence): array
    {
        $cond = $step['condition'] ?? null;
        if (!is_array($cond) || ($cond['type'] ?? '') === '') {
            return ['action' => 'execute', 'reason' => 'no_condition'];
        }
        $key = (string) ($cond['evidence_key'] ?? '');
        $type = (string) ($cond['type'] ?? 'if');
        $available = !empty($evidence[$key]);
        if ($type === 'if_not') {
            return $available
                ? ['action' => (string) ($cond['else'] ?? 'skip'), 'reason' => 'inventory_available_skip_procurement']
                : ['action' => (string) ($cond['then'] ?? 'execute'), 'reason' => 'inventory_insufficient_continue'];
        }
        if ($type === 'if') {
            return $available
                ? ['action' => (string) ($cond['then'] ?? 'execute'), 'reason' => 'condition_true']
                : ['action' => (string) ($cond['else'] ?? 'skip'), 'reason' => 'condition_false'];
        }
        return ['action' => 'execute', 'reason' => 'unknown_condition_default_execute'];
    }

    /**
     * Record step execution + verification result; advance or block.
     *
     * @param array<string, mixed> $wf
     * @param array<string, mixed> $execResult
     * @param array<string, mixed>|null $verification from ErpActionPlanner::verifyAction
     * @param array<string, mixed> $evidence
     * @return array<string, mixed>
     */
    public static function recordStepResult(
        array $wf,
        int $stepNo,
        array $execResult,
        ?array $verification,
        ProcurementAgentContext $ctx,
        array $evidence = []
    ): array {
        if ((int) ($wf['company_id'] ?? 0) !== (int) $ctx->companyId) {
            return self::transition($wf, self::ST_BLOCKED, 'tenant_mismatch');
        }

        $wf = self::transition($wf, self::ST_RUNNING, 'step_execution');
        $idx = self::indexOfStep($wf, $stepNo);
        if ($idx < 0) {
            return self::transition($wf, self::ST_FAILED, 'step_not_found');
        }

        $step = $wf['steps'][$idx];
        // Dependency gate
        if (!self::dependencySatisfied($wf, $step)) {
            $step['status'] = self::STEP_BLOCKED;
            $step['block_reason'] = 'dependency_not_verified';
            $wf['steps'][$idx] = $step;
            $wf['blocked_steps'][] = $stepNo;
            return self::transition($wf, self::ST_BLOCKED, 'dependency_not_verified');
        }

        // Conditional
        if (is_array($step['condition'] ?? null)) {
            $decision = self::evaluateCondition($step, $evidence);
            if (($decision['action'] ?? '') === 'skip') {
                $step['status'] = self::STEP_SKIPPED;
                $step['skip_reason'] = (string) ($decision['reason'] ?? 'condition_skip');
                $wf['steps'][$idx] = $step;
                $wf['skipped_steps'][] = $stepNo;
                $wf = self::refreshStepReadiness($wf);
                return self::finalizeOrContinue($wf, $ctx);
            }
        }

        // Duplicate / idempotency
        $confirmKey = (string) ($step['confirm_key'] ?? '');
        $requestId = (string) ($wf['request_id'] ?? $wf['workflow_id'] ?? '');
        if (!empty($step['write']) && $confirmKey !== ''
            && ErpActionPlanner::wasAlreadyExecuted($ctx, $requestId, $confirmKey)
        ) {
            $step['status'] = self::STEP_VERIFIED;
            $step['duplicate_prevented'] = true;
            $step['result'] = ['success' => true, 'duplicate' => true];
            $wf['steps'][$idx] = $step;
            $wf['completed_steps'][] = $stepNo;
            $wf = self::refreshStepReadiness($wf);
            return self::finalizeOrContinue($wf, $ctx);
        }

        // Stale state for writes
        if (!empty($step['write']) && is_array($step['action_ref'] ?? null)) {
            $stateCheck = ErpActionPlanner::validateActionState($step['action_ref'], $ctx);
            if (!empty($stateCheck['stale']) || empty($stateCheck['ok'])) {
                $step['status'] = self::STEP_BLOCKED;
                $step['block_reason'] = (string) ($stateCheck['error_code'] ?? 'stale_state');
                $step['stale'] = true;
                $wf['steps'][$idx] = $step;
                $wf['blocked_steps'][] = $stepNo;
                $wf['replan_required'] = true;
                return self::transition($wf, self::ST_BLOCKED, 'stale_state_replan_required');
            }
        }

        $step['status'] = self::STEP_RUNNING;
        $step['executed_at'] = date('Y-m-d H:i:s');
        $step['result'] = [
            'success' => !empty($execResult['success']),
            'error_code' => $execResult['error_code'] ?? $execResult['error'] ?? null,
        ];
        $wf['steps'][$idx] = $step;
        $wf['current_step'] = $stepNo;
        $wf = self::transition($wf, self::ST_VERIFICATION_PENDING, 'awaiting_verification');

        if (empty($execResult['success'])) {
            return self::failStep($wf, $stepNo, (string) ($execResult['error_code'] ?? 'execution_failed'), $ctx);
        }

        // Verification gate for writes
        if (!empty($step['write'])) {
            $ver = is_array($verification) ? $verification : ErpActionPlanner::verifyAction(
                is_array($step['action_ref'] ?? null) ? $step['action_ref'] : $step,
                $execResult,
                $ctx
            );
            $step = $wf['steps'][$idx];
            $step['verification'] = $ver;
            if (empty($ver['verified'])) {
                $step['status'] = self::STEP_FAILED;
                $wf['steps'][$idx] = $step;
                return self::failStep($wf, $stepNo, (string) ($ver['message'] ?? 'verification_failed'), $ctx);
            }
            $step['status'] = self::STEP_VERIFIED;
        } else {
            $step['status'] = self::STEP_COMPLETED;
            $step['verification'] = ['verified' => true, 'message' => 'read_analysis_ok'];
        }

        $wf['steps'][$idx] = $step;
        $wf['completed_steps'][] = $stepNo;
        $wf = self::transition($wf, self::ST_VERIFIED, 'step_verified');
        $wf = self::refreshStepReadiness($wf);
        return self::finalizeOrContinue($wf, $ctx);
    }

    /**
     * Safe recovery metadata — never invents rollback.
     *
     * @param array<string, mixed> $wf
     * @return array<string, mixed>
     */
    public static function planRecovery(array $wf): array
    {
        $failed = [];
        $completed = [];
        $notStarted = [];
        foreach (($wf['steps'] ?? []) as $s) {
            if (!is_array($s)) {
                continue;
            }
            $no = (int) ($s['step_no'] ?? 0);
            $st = (string) ($s['status'] ?? '');
            if ($st === self::STEP_FAILED || $st === self::STEP_BLOCKED) {
                $failed[] = $no;
            } elseif (in_array($st, [self::STEP_VERIFIED, self::STEP_COMPLETED, self::STEP_SKIPPED], true)) {
                $completed[] = $no;
            } else {
                $notStarted[] = $no;
            }
        }
        $lastFailed = $failed !== [] ? max($failed) : null;
        $safeRetry = false;
        $option = 'manual_intervention';
        if ($lastFailed !== null) {
            $step = $wf['steps'][self::indexOfStep($wf, $lastFailed)] ?? [];
            // Safe retry only for failed READ/analysis or idempotent duplicate-safe writes that never applied
            if (empty($step['write'])) {
                $safeRetry = true;
                $option = 'retry_analysis_step';
            } elseif (!empty($step['stale'])) {
                $option = 'replan_after_stale_state';
                $safeRetry = false;
            } elseif (($step['result']['success'] ?? null) === false && empty($step['verification']['verified'])) {
                $option = 'retry_with_new_confirmation_if_idempotent';
                $safeRetry = false; // require new confirmation; ActionPlanner idempotency protects duplicates
            }
        }
        $wf['recovery'] = [
            'required' => true,
            'failed_step' => $lastFailed,
            'completed_steps' => $completed,
            'not_started_steps' => $notStarted,
            'impact' => $completed !== [] ? 'partial_execution' : 'no_execution',
            'option' => $option,
            'safe_retry' => $safeRetry,
            'compensation_available' => false,
            'note' => 'no_invented_rollback',
        ];
        $wf['recovery_attempts'] = (int) ($wf['recovery_attempts'] ?? 0);
        return self::transition($wf, self::ST_RECOVERY_REQUIRED, (string) $option);
    }

    /**
     * Replan after stale state using OrchestrationPlanner + ActionPlanner (no second planner).
     *
     * @param array<string, mixed>|null $intelligence
     * @return array<string, mixed>
     */
    public static function replan(
        string $message,
        ProcurementAgentContext $ctx,
        ?array $intelligence = null,
        ?string $previousWorkflowId = null
    ): array {
        $intent = ErpOrchestrationPlanner::detectIntent($message, $ctx);
        $intent['write_intent'] = true;
        // Reuse intelligence if provided; otherwise memory + decision context
        if ($intelligence === null) {
            $intelligence = [
                'operational_memory' => ErpOperationalMemoryLayer::forIntelligence($ctx, $intent),
                'replan' => true,
                'previous_workflow_id' => $previousWorkflowId,
            ];
        }
        $wf = self::plan($message, $ctx, $intelligence, ['persist' => true]);
        $wf['replanned_from'] = $previousWorkflowId;
        $wf['status_reason'] = 'replan_after_state_change';
        if ($previousWorkflowId) {
            $old = self::getWorkflow((int) $ctx->companyId, $previousWorkflowId);
            if ($old !== null) {
                $old = self::transition($old, self::ST_CANCELLED, 'superseded_by_replan');
                self::saveWorkflow((int) $ctx->companyId, $old);
            }
        }
        self::saveWorkflow((int) $ctx->companyId, $wf);
        return $wf;
    }

    /**
     * Complete workflow and emit learning outcome.
     *
     * @param array<string, mixed> $wf
     * @return array<string, mixed>
     */
    public static function complete(array $wf, ProcurementAgentContext $ctx): array
    {
        $stats = self::stepStats($wf);
        $outcomeStatus = ErpOperationalLearningLayer::OUTCOME_SUCCESS;
        if ($stats['failed'] > 0 && $stats['completed'] > 0) {
            $outcomeStatus = ErpOperationalLearningLayer::OUTCOME_PARTIAL;
            $wf = self::transition($wf, self::ST_PARTIAL, 'partial_success');
        } elseif ($stats['failed'] > 0) {
            $outcomeStatus = ErpOperationalLearningLayer::OUTCOME_FAILED;
            $wf = self::transition($wf, self::ST_FAILED, 'all_failed');
        } else {
            $wf = self::transition($wf, self::ST_COMPLETED, 'all_steps_done');
        }
        $wf['outcome_status'] = $outcomeStatus;
        $wf['outcome'] = [
            'workflow_id' => $wf['workflow_id'] ?? null,
            'status' => $wf['status'],
            'completed' => $stats['completed'],
            'failed' => $stats['failed'],
            'skipped' => $stats['skipped'],
            'total' => $stats['total'],
            'at' => date('Y-m-d H:i:s'),
        ];

        // Learning — never self-modifies agent
        try {
            ErpOperationalLearningLayer::recordActionOutcome(
                $ctx,
                [
                    'tool' => 'multi_step_workflow',
                    'domain' => 'executive',
                    'confirm_key' => (string) ($wf['workflow_id'] ?? ''),
                ],
                [
                    'success' => $outcomeStatus === ErpOperationalLearningLayer::OUTCOME_SUCCESS
                        || $outcomeStatus === ErpOperationalLearningLayer::OUTCOME_PARTIAL,
                    'data' => $wf['outcome'],
                ],
                [
                    'verified' => $outcomeStatus !== ErpOperationalLearningLayer::OUTCOME_FAILED,
                    'message' => 'workflow_outcome',
                ],
                [
                    'business_outcome' => $outcomeStatus,
                    'related_recommendation' => (string) ($wf['intent'] ?? ''),
                    'workflow_id' => $wf['workflow_id'] ?? null,
                ]
            );
        } catch (\Throwable $e) {
            // best-effort
        }

        $wf['ended_at'] = date('Y-m-d H:i:s');
        self::saveWorkflow((int) $ctx->companyId, $wf);
        return $wf;
    }

    /**
     * List active workflows for Control Tower (tenant-scoped).
     *
     * @return list<array<string, mixed>>
     */
    public static function listActive(int $companyId, int $limit = 12): array
    {
        if ($companyId < 1) {
            return [];
        }
        $store = self::loadStore($companyId);
        $active = [];
        $terminal = [self::ST_COMPLETED, self::ST_CANCELLED];
        foreach (($store['workflows'] ?? []) as $wf) {
            if (!is_array($wf)) {
                continue;
            }
            if ((int) ($wf['company_id'] ?? 0) !== $companyId) {
                continue;
            }
            if (in_array(($wf['status'] ?? ''), $terminal, true)) {
                continue;
            }
            $active[] = self::towerRow($wf);
        }
        usort($active, static fn($a, $b) => strcmp((string) ($b['updated_at'] ?? ''), (string) ($a['updated_at'] ?? '')));
        return array_slice($active, 0, max(1, min(30, $limit)));
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function getWorkflow(int $companyId, string $workflowId): ?array
    {
        if ($companyId < 1 || $workflowId === '') {
            return null;
        }
        $store = self::loadStore($companyId);
        $wf = $store['workflows'][$workflowId] ?? null;
        if (!is_array($wf) || (int) ($wf['company_id'] ?? 0) !== $companyId) {
            return null;
        }
        return $wf;
    }

    /**
     * Cron: mark stuck RUNNING / VERIFICATION_PENDING as recovery required.
     *
     * @return array{scanned:int, stuck:int}
     */
    public static function processStuckForCompany(int $companyId): array
    {
        if ($companyId < 1) {
            return ['scanned' => 0, 'stuck' => 0];
        }
        $store = self::loadStore($companyId);
        $stuck = 0;
        $scanned = 0;
        $cutoff = time() - (self::STUCK_MINUTES * 60);
        foreach (($store['workflows'] ?? []) as $id => $wf) {
            if (!is_array($wf)) {
                continue;
            }
            $scanned++;
            $st = (string) ($wf['status'] ?? '');
            if (!in_array($st, [self::ST_RUNNING, self::ST_VERIFICATION_PENDING], true)) {
                continue;
            }
            $updated = strtotime((string) ($wf['updated_at'] ?? '')) ?: 0;
            if ($updated > 0 && $updated < $cutoff) {
                $wf['stuck'] = true;
                $wf['stuck_detected_at'] = date('Y-m-d H:i:s');
                $wf = self::planRecovery($wf);
                $wf['status_reason'] = 'stuck_timeout_manual_intervention';
                $store['workflows'][$id] = $wf;
                $stuck++;
            }
        }
        if ($stuck > 0) {
            self::saveStore($companyId, $store);
        }
        return ['scanned' => $scanned, 'stuck' => $stuck];
    }

    /**
     * Persist helper used by Agent after plan/advance.
     *
     * @param array<string, mixed> $wf
     */
    public static function saveWorkflow(int $companyId, array $wf): void
    {
        if ($companyId < 1 || (string) ($wf['workflow_id'] ?? '') === '') {
            return;
        }
        $wf['company_id'] = $companyId;
        $store = self::loadStore($companyId);
        $store['workflows'][(string) $wf['workflow_id']] = $wf;
        // Bound
        if (count($store['workflows']) > self::MAX_ACTIVE + self::MAX_HISTORY) {
            $store['workflows'] = array_slice($store['workflows'], -self::MAX_HISTORY, null, true);
        }
        self::saveStore($companyId, $store);
        self::$memo = [];
    }

    /**
     * Control Tower section.
     *
     * @return array<string, mixed>
     */
    public static function towerSection(ProcurementAgentContext $ctx, int $limit = 10): array
    {
        $active = self::listActive((int) $ctx->companyId, $limit);
        return [
            'data_source' => 'live_tenant',
            'company_id' => (int) $ctx->companyId,
            'section' => 'active_workflows',
            'items' => $active,
            'count' => count($active),
            'auto_execute' => false,
            'governance' => [
                'direct_writes' => false,
                'requires_action_planner' => true,
                'requires_confirmation' => true,
            ],
        ];
    }

    /**
     * @return array<string, bool|string>
     */
    public static function assertBoundaries(): array
    {
        return [
            'workflow_engine' => false,
            'automation_engine' => false,
            'bpm_platform' => false,
            'bypasses_governance' => false,
            'bypasses_confirmation' => false,
            'invents_rollback' => false,
            'scope' => 'action_planner_coordination_only',
        ];
    }

    // --- internals ---

    /**
     * @param array<string, mixed> $fields
     * @return array<string, mixed>
     */
    private static function makeStep(int $stepNo, array $fields): array
    {
        return [
            'step_no' => $stepNo,
            'domain' => (string) ($fields['domain'] ?? ''),
            'intent' => (string) ($fields['intent'] ?? ''),
            'tool' => (string) ($fields['tool'] ?? ''),
            'arguments' => $fields['arguments'] ?? [],
            'class' => (string) ($fields['class'] ?? ''),
            'required_evidence' => $fields['required_evidence'] ?? [],
            'authorization' => $fields['authorization'] ?? [],
            'expected_result' => (string) ($fields['expected_result'] ?? ''),
            'dependency' => $fields['dependency'] ?? null,
            'condition' => $fields['condition'] ?? null,
            'write' => !empty($fields['write']),
            'confirm_key' => (string) ($fields['confirm_key'] ?? ''),
            'state_fingerprint' => (string) ($fields['state_fingerprint'] ?? ''),
            'previous_state' => $fields['previous_state'] ?? null,
            'action_ref' => $fields['action_ref'] ?? null,
            'status' => (string) ($fields['status'] ?? self::STEP_PENDING),
            'skip_reason' => $fields['skip_reason'] ?? null,
            'authorized' => null,
            'confirmed' => null,
            'idempotency_key' => (string) ($fields['confirm_key'] ?? ('step_' . $stepNo)),
        ];
    }

    /**
     * @param array<string, mixed> $wf
     * @return array<string, mixed>
     */
    private static function refreshStepReadiness(array $wf): array
    {
        foreach (($wf['steps'] ?? []) as &$step) {
            if (!is_array($step)) {
                continue;
            }
            $st = (string) ($step['status'] ?? '');
            if (in_array($st, [self::STEP_VERIFIED, self::STEP_COMPLETED, self::STEP_SKIPPED, self::STEP_FAILED, self::STEP_CANCELLED], true)) {
                continue;
            }
            if (self::dependencySatisfied($wf, $step)) {
                if ($st === self::STEP_PENDING || $st === self::STEP_BLOCKED && ($step['block_reason'] ?? '') === 'dependency_not_verified') {
                    $step['status'] = self::STEP_READY;
                    unset($step['block_reason']);
                }
            } else {
                $step['status'] = self::STEP_BLOCKED;
                $step['block_reason'] = 'dependency_not_verified';
            }
        }
        unset($step);
        return $wf;
    }

    /**
     * @param array<string, mixed> $wf
     * @param array<string, mixed> $step
     */
    private static function dependencySatisfied(array $wf, array $step): bool
    {
        $dep = $step['dependency'] ?? null;
        if ($dep === null || $dep === '' || (int) $dep < 1) {
            return true;
        }
        $depStep = null;
        foreach (($wf['steps'] ?? []) as $s) {
            if (is_array($s) && (int) ($s['step_no'] ?? 0) === (int) $dep) {
                $depStep = $s;
                break;
            }
        }
        if ($depStep === null) {
            return false;
        }
        $st = (string) ($depStep['status'] ?? '');
        // Failed dependency blocks; skipped is OK (condition path)
        if ($st === self::STEP_FAILED) {
            return false;
        }
        return in_array($st, [self::STEP_VERIFIED, self::STEP_COMPLETED, self::STEP_SKIPPED], true);
    }

    /**
     * @param array<string, mixed> $wf
     * @return array<string, mixed>
     */
    private static function finalizeOrContinue(array $wf, ProcurementAgentContext $ctx): array
    {
        $stats = self::stepStats($wf);
        $pending = false;
        foreach (($wf['steps'] ?? []) as $s) {
            if (!is_array($s)) {
                continue;
            }
            if (in_array(($s['status'] ?? ''), [self::STEP_PENDING, self::STEP_READY, self::STEP_RUNNING, self::STEP_VERIFICATION_PENDING], true)) {
                $pending = true;
                break;
            }
        }
        if (!$pending) {
            return self::complete($wf, $ctx);
        }
        // Find next ready
        foreach (($wf['steps'] ?? []) as $s) {
            if (is_array($s) && ($s['status'] ?? '') === self::STEP_READY) {
                $wf['current_step'] = (int) ($s['step_no'] ?? 0);
                $wf['next_action'] = (string) ($s['tool'] ?? $s['intent'] ?? '');
                break;
            }
        }
        $wf['updated_at'] = date('Y-m-d H:i:s');
        self::saveWorkflow((int) $ctx->companyId, $wf);
        return $wf;
    }

    /**
     * @param array<string, mixed> $wf
     * @return array<string, mixed>
     */
    private static function failStep(array $wf, int $stepNo, string $reason, ProcurementAgentContext $ctx): array
    {
        $idx = self::indexOfStep($wf, $stepNo);
        if ($idx >= 0) {
            $wf['steps'][$idx]['status'] = self::STEP_FAILED;
            $wf['steps'][$idx]['fail_reason'] = $reason;
        }
        $wf['failed_steps'][] = $stepNo;
        // Block dependent later steps
        foreach (($wf['steps'] ?? []) as &$s) {
            if (!is_array($s)) {
                continue;
            }
            if ((int) ($s['dependency'] ?? 0) === $stepNo
                && !in_array(($s['status'] ?? ''), [self::STEP_VERIFIED, self::STEP_COMPLETED, self::STEP_SKIPPED], true)
            ) {
                $s['status'] = self::STEP_BLOCKED;
                $s['block_reason'] = 'upstream_step_failed';
                $wf['blocked_steps'][] = (int) ($s['step_no'] ?? 0);
            }
        }
        unset($s);
        $wf = self::transition($wf, self::ST_FAILED, $reason);
        $stats = self::stepStats($wf);
        if ($stats['completed'] > 0) {
            $wf = self::transition($wf, self::ST_PARTIAL, 'partial_after_failure');
        }
        $wf = self::planRecovery($wf);
        self::saveWorkflow((int) $ctx->companyId, $wf);
        return $wf;
    }

    /**
     * @param array<string, mixed> $wf
     * @return array{total:int,completed:int,failed:int,skipped:int,blocked:int}
     */
    private static function stepStats(array $wf): array
    {
        $total = 0;
        $completed = 0;
        $failed = 0;
        $skipped = 0;
        $blocked = 0;
        foreach (($wf['steps'] ?? []) as $s) {
            if (!is_array($s)) {
                continue;
            }
            $total++;
            $st = (string) ($s['status'] ?? '');
            if (in_array($st, [self::STEP_VERIFIED, self::STEP_COMPLETED], true)) {
                $completed++;
            } elseif ($st === self::STEP_FAILED) {
                $failed++;
            } elseif ($st === self::STEP_SKIPPED) {
                $skipped++;
            } elseif ($st === self::STEP_BLOCKED) {
                $blocked++;
            }
        }
        return compact('total', 'completed', 'failed', 'skipped', 'blocked');
    }

    /**
     * @param array<string, mixed> $wf
     */
    private static function indexOfStep(array $wf, int $stepNo): int
    {
        foreach (($wf['steps'] ?? []) as $i => $s) {
            if (is_array($s) && (int) ($s['step_no'] ?? 0) === $stepNo) {
                return (int) $i;
            }
        }
        return -1;
    }

    /**
     * @param list<array<string, mixed>> $steps
     */
    private static function hasToolStep(array $steps, string $tool): bool
    {
        foreach ($steps as $s) {
            if (is_array($s) && ($s['tool'] ?? '') === $tool) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param list<array<string, mixed>> $steps
     */
    private static function stepNoForTool(array $steps, string $tool): ?int
    {
        foreach ($steps as $s) {
            if (is_array($s) && ($s['tool'] ?? '') === $tool) {
                return (int) ($s['step_no'] ?? 0);
            }
        }
        return null;
    }

    /**
     * @param array<string, mixed>|null $intelligence
     */
    private static function needsInventoryCheck(string $message, ?array $intelligence): bool
    {
        if (preg_match('/(ناقص|نقص|shortfall|low\s*stock|مخزون|inventory|منتج|صنف)/ui', $message) === 1) {
            return true;
        }
        return is_array($intelligence) && (
            !empty($intelligence['incomplete_chains'])
            || !empty($intelligence['findings'])
        );
    }

    /**
     * @param list<array<string, mixed>> $steps
     * @param array<string, mixed> $memory
     * @return list<string>
     */
    private static function planRisks(array $steps, array $memory): array
    {
        $risks = [];
        $writes = count(array_filter($steps, static fn($s) => !empty($s['write'])));
        if ($writes > 1) {
            $risks[] = 'multi_write_requires_full_confirmation_scope';
        }
        if (!empty($memory['conflicts'])) {
            $risks[] = 'stale_memory_conflicts_present';
        }
        foreach ($steps as $s) {
            if (!empty($s['authorization']['requires_approval_workflow'])) {
                $risks[] = 'sensitive_write_requires_approval_workflow';
                break;
            }
        }
        return array_values(array_unique($risks));
    }

    /**
     * @param array<string, mixed> $wf
     * @return array<string, mixed>
     */
    private static function towerRow(array $wf): array
    {
        $stats = self::stepStats($wf);
        $done = $stats['completed'] + $stats['skipped'];
        return [
            'workflow_id' => $wf['workflow_id'] ?? '',
            'status' => $wf['status'] ?? '',
            'intent' => $wf['intent'] ?? '',
            'progress' => $stats['total'] > 0 ? round(100 * $done / $stats['total'], 1) : 0,
            'current_step' => $wf['current_step'] ?? null,
            'completed' => $stats['completed'],
            'total' => $stats['total'],
            'blocked_reason' => $wf['status_reason'] ?? null,
            'risk' => !empty($wf['confirmation_scope']['risks']) ? 'elevated' : 'normal',
            'next_action' => $wf['next_action'] ?? null,
            'recovery_status' => $wf['recovery']['option'] ?? null,
            'updated_at' => $wf['updated_at'] ?? '',
            'evidence' => [
                'source' => 'ErpMultiStepWorkflowLayer',
                'workflow_id' => $wf['workflow_id'] ?? '',
                'company_id' => $wf['company_id'] ?? null,
                'timestamp' => $wf['updated_at'] ?? '',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function loadStore(int $companyId): array
    {
        $key = 'agent_multi_step_workflows_c' . $companyId;
        try {
            $raw = (new SystemSetting())->get($key, '{}');
            $decoded = json_decode((string) $raw, true);
            if (!is_array($decoded)) {
                return ['company_id' => $companyId, 'workflows' => []];
            }
            if (isset($decoded['company_id']) && (int) $decoded['company_id'] !== $companyId) {
                return ['company_id' => $companyId, 'workflows' => []];
            }
            $decoded['company_id'] = $companyId;
            if (!isset($decoded['workflows']) || !is_array($decoded['workflows'])) {
                $decoded['workflows'] = [];
            }
            return $decoded;
        } catch (\Throwable $e) {
            return ['company_id' => $companyId, 'workflows' => []];
        }
    }

    /**
     * @param array<string, mixed> $store
     */
    private static function saveStore(int $companyId, array $store): void
    {
        $store['company_id'] = $companyId;
        $key = 'agent_multi_step_workflows_c' . $companyId;
        $json = json_encode($store, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) {
            return;
        }
        try {
            $model = new SystemSetting();
            $existing = $model->queryOne(
                'SELECT id FROM rateb_system_settings WHERE setting_key = :k LIMIT 1',
                ['k' => $key]
            );
            if (is_array($existing) && (int) ($existing['id'] ?? 0) > 0) {
                $model->update((int) $existing['id'], [
                    'setting_value' => $json,
                    'setting_group' => 'agent_multi_step_workflows',
                ]);
            } else {
                $model->create([
                    'setting_key' => $key,
                    'setting_value' => $json,
                    'setting_group' => 'agent_multi_step_workflows',
                ]);
            }
        } catch (\Throwable $e) {
            // best-effort
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function emptyWorkflow(string $reason): array
    {
        return [
            'data_source' => 'live_tenant',
            'error' => $reason,
            'status' => self::ST_CANCELLED,
            'steps' => [],
            'auto_execute' => false,
        ];
    }
}
