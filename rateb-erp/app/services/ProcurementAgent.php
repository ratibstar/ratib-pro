<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\Database;
use Rateb\App\Services\Agent\LlmClientInterface;
use Rateb\App\Services\Agent\OpenAiCompatibleClient;

/**
 * Procurement Agent
 * Orchestrates: LLM → Policy Guard → Tool Executor → Audit
 * Phase 5: production hardening + controlled autonomy (single ERP agent).
 */
final class ProcurementAgent
{
    private LlmClientInterface $llmClient;
    private array $toolDefinitions;
    private array $config;
    /** @var class-string */
    private string $toolRegistryClass;
    /** @var class-string */
    private string $toolExecutorClass;

    public function __construct(array $config = [], ?LlmClientInterface $llmClient = null)
    {
        $this->config = $config;
        $agentCfg = is_array($config['agent'] ?? null) ? $config['agent'] : [];

        $registry = (string) ($agentCfg['tool_registry'] ?? ProcurementToolRegistry::class);
        if ($registry === '' || !class_exists($registry) || !method_exists($registry, 'getOpenAiToolDefinitions')) {
            $registry = ProcurementToolRegistry::class;
        }
        $executor = (string) ($agentCfg['tool_executor'] ?? ProcurementToolExecutor::class);
        if ($executor === '' || !class_exists($executor) || !method_exists($executor, 'execute')) {
            $executor = ProcurementToolExecutor::class;
        }
        $this->toolRegistryClass = $registry;
        $this->toolExecutorClass = $executor;
        $this->toolDefinitions = $this->toolRegistryClass::getOpenAiToolDefinitions();

        if ($llmClient !== null) {
            $this->llmClient = $llmClient;
        } else {
            $llmConfig = $config['llm'] ?? [];
            $this->llmClient = new OpenAiCompatibleClient(is_array($llmConfig) ? $llmConfig : []);
        }
    }

    /**
     * Process a chat request from the user
     *
     * @param array{
     *     message: string,
     *     request_id: string,
     *     company_id: int|null,
     *     history: array<int, array{role: string, content: string}>,
     *     confirmed_writes?: list<string>
     * } $input
     * @return array{
     *     response: string,
     *     tool_calls: array,
     *     pending_confirmations: array,
     *     audit: array,
     *     observability?: array
     * }
     */
    public function process(array $input, ProcurementAgentContext $ctx): array
    {
        $startedAt = microtime(true);
        $requestId = (string) ($input['request_id'] ?? bin2hex(random_bytes(16)));
        $userMessage = trim((string) ($input['message'] ?? ''));
        $history = $ctx->sanitizeHistory($input['history'] ?? []);
        $toolCalls = [];
        $auditEntries = [];
        $pendingConfirmations = [];
        $observability = [
            'request_id' => $requestId,
            'company_id' => $ctx->companyId,
            'user_id' => $ctx->userId,
            'llm_failures' => 0,
            'tool_failures' => 0,
            'confirmation_required_events' => 0,
            'writes_executed' => 0,
            'success' => false,
        ];

        if ($userMessage === '') {
            return [
                'response' => '',
                'tool_calls' => [],
                'pending_confirmations' => [],
                'audit' => [],
                'observability' => $observability,
            ];
        }

        try {
            $auditEntries[] = $this->logAudit($ctx, $requestId, 'user_request', [
                'message' => mb_substr($userMessage, 0, 2000),
                'history_count' => count($history),
                'conversation_scope' => $ctx->conversationScopeKey(),
            ], [
                'event_type' => 'user_request',
                'error_code' => null,
            ], 'success');

            $messages = [
                ['role' => 'system', 'content' => $this->getSystemPrompt($ctx, $userMessage)],
            ];
            foreach ($history as $msg) {
                $messages[] = ['role' => $msg['role'], 'content' => $msg['content']];
            }
            $messages[] = ['role' => 'user', 'content' => $userMessage];

            $maxIterations = max(1, (int) ($this->config['agent']['max_tool_calls_per_request'] ?? 10));
            $maxWrites = max(1, (int) ($this->config['agent']['max_writes_per_request'] ?? 1));
            $requireWriteConfirm = (bool) ($this->config['agent']['require_confirmation_for_write'] ?? true);
            $confirmedWrites = $input['confirmed_writes'] ?? [];
            if (!is_array($confirmedWrites)) {
                $confirmedWrites = [];
            }
            $confirmedWrites = array_values(array_filter(array_map('strval', $confirmedWrites)));

            $executedWriteKeys = [];
            $writesExecuted = 0;
            $iterations = 0;

            while ($iterations < $maxIterations) {
                $iterations++;
                $llmStarted = microtime(true);

                try {
                    $llmResponse = $this->llmClient->chatCompletion($messages, $this->toolDefinitions, 'auto');
                } catch (\Throwable $e) {
                    $observability['llm_failures']++;
                    $code = $this->mapLlmFailureCode($e);
                    $durationMs = (int) ((microtime(true) - $llmStarted) * 1000);
                    $auditEntries[] = $this->logAudit($ctx, $requestId, 'llm_call', [], [
                        'event_type' => 'llm_failure',
                        'error_code' => $code,
                        'duration_ms' => $durationMs,
                    ], 'error');
                    $observability['duration_ms'] = (int) ((microtime(true) - $startedAt) * 1000);
                    $observability['success'] = false;
                    return [
                        'response' => $this->safeUserFallback($ctx, $code),
                        'tool_calls' => $toolCalls,
                        'pending_confirmations' => $pendingConfirmations,
                        'audit' => $auditEntries,
                        'observability' => $observability,
                    ];
                }

                $message = is_array($llmResponse['message'] ?? null) ? $llmResponse['message'] : [];
                $toolCallsFromLlm = $message['tool_calls'] ?? [];
                if (!is_array($toolCallsFromLlm)) {
                    $observability['llm_failures']++;
                    $auditEntries[] = $this->logAudit($ctx, $requestId, 'llm_call', [], [
                        'event_type' => 'llm_failure',
                        'error_code' => 'llm_invalid_tool_response',
                        'duration_ms' => (int) ((microtime(true) - $llmStarted) * 1000),
                    ], 'error');
                    $observability['duration_ms'] = (int) ((microtime(true) - $startedAt) * 1000);
                    return [
                        'response' => $this->safeUserFallback($ctx, 'llm_invalid_tool_response'),
                        'tool_calls' => $toolCalls,
                        'pending_confirmations' => $pendingConfirmations,
                        'audit' => $auditEntries,
                        'observability' => $observability,
                    ];
                }

                if ($toolCallsFromLlm === []) {
                    $assistantContent = trim((string) ($message['content'] ?? ''));
                    if ($assistantContent === '') {
                        $observability['llm_failures']++;
                        $auditEntries[] = $this->logAudit($ctx, $requestId, 'llm_call', [], [
                            'event_type' => 'llm_failure',
                            'error_code' => 'llm_empty_response',
                            'duration_ms' => (int) ((microtime(true) - $llmStarted) * 1000),
                        ], 'error');
                        $observability['duration_ms'] = (int) ((microtime(true) - $startedAt) * 1000);
                        return [
                            'response' => $this->safeUserFallback($ctx, 'llm_empty_response'),
                            'tool_calls' => $toolCalls,
                            'pending_confirmations' => $pendingConfirmations,
                            'audit' => $auditEntries,
                            'observability' => $observability,
                        ];
                    }

                    $assistantContent = $this->sanitizeUserFacingReply($assistantContent, $ctx, $userMessage);
                    $auditEntries[] = $this->logAudit($ctx, $requestId, 'final_response', [], [
                        'event_type' => 'final_response',
                        'response' => mb_substr($assistantContent, 0, 4000),
                        'tokens_in' => $llmResponse['usage']['prompt_tokens'] ?? 0,
                        'tokens_out' => $llmResponse['usage']['completion_tokens'] ?? 0,
                        'model' => $llmResponse['model'] ?? '',
                        'duration_ms' => (int) ((microtime(true) - $llmStarted) * 1000),
                    ], 'success');
                    $observability['success'] = true;
                    $observability['duration_ms'] = (int) ((microtime(true) - $startedAt) * 1000);
                    return [
                        'response' => $assistantContent,
                        'tool_calls' => $toolCalls,
                        'pending_confirmations' => $pendingConfirmations,
                        'audit' => $auditEntries,
                        'observability' => $observability,
                    ];
                }

                $assistantTurn = ['role' => 'assistant', 'tool_calls' => $toolCallsFromLlm];
                if (array_key_exists('content', $message) && $message['content'] !== null && $message['content'] !== '') {
                    $assistantTurn['content'] = $message['content'];
                }
                $messages[] = $assistantTurn;

                $appendToolResult = function (array &$messages, string $toolCallId, string $toolName, array $payload) use ($ctx): void {
                    $safe = $this->toolPayloadForLlm($payload, $ctx);
                    $messages[] = [
                        'role' => 'tool',
                        'tool_call_id' => $toolCallId !== '' ? $toolCallId : ('call_' . substr(md5($toolName . microtime(true)), 0, 12)),
                        'name' => $toolName !== '' ? $toolName : 'unknown_tool',
                        'content' => json_encode($safe, JSON_UNESCAPED_UNICODE),
                    ];
                };

                foreach ($toolCallsFromLlm as $toolCall) {
                    if (!is_array($toolCall)) {
                        $observability['tool_failures']++;
                        continue;
                    }
                    $function = is_array($toolCall['function'] ?? null) ? $toolCall['function'] : [];
                    $toolName = (string) ($function['name'] ?? '');
                    $argumentsJson = (string) ($function['arguments'] ?? '{}');
                    $toolCallId = (string) ($toolCall['id'] ?? '');

                    if ($toolName === '') {
                        $observability['tool_failures']++;
                        $errPayload = [
                            'success' => false,
                            'error' => 'invalid_tool_response',
                            'error_code' => 'invalid_tool_response',
                            'error_message' => $this->localizedError('invalid_tool_response'),
                        ];
                        $auditEntries[] = $this->logAudit($ctx, $requestId, 'invalid_tool', [], [
                            'event_type' => 'tool_failure',
                            'error_code' => 'invalid_tool_response',
                        ], 'error');
                        $appendToolResult($messages, $toolCallId, 'invalid_tool', $errPayload);
                        continue;
                    }

                    if (!$this->toolRegistryClass::isAllowed($toolName)) {
                        $observability['tool_failures']++;
                        $errPayload = [
                            'success' => false,
                            'error' => 'tool_not_allowed',
                            'error_code' => 'tool_not_allowed',
                            'error_message' => $this->localizedError('tool_not_allowed'),
                        ];
                        $toolCalls[] = [
                            'tool' => $toolName,
                            'arguments' => [],
                            'result' => $errPayload,
                            'audit_status' => 'denied',
                        ];
                        $auditEntries[] = $this->logAudit($ctx, $requestId, $toolName, [], [
                            'event_type' => 'guardrail',
                            'error_code' => 'tool_not_allowed',
                            'policy_checks' => ['tool_allowlist' => false],
                        ], 'denied');
                        $appendToolResult($messages, $toolCallId, $toolName, $errPayload);
                        continue;
                    }

                    $arguments = json_decode($argumentsJson, true);
                    if (json_last_error() !== JSON_ERROR_NONE || !is_array($arguments)) {
                        $arguments = [];
                    }
                    unset($arguments['_confirmed'], $arguments['confirmed'], $arguments['write_confirmed']);

                    try {
                        $arguments = $this->toolRegistryClass::validateArguments($toolName, $arguments);
                    } catch (\Throwable $e) {
                        $observability['tool_failures']++;
                        $errPayload = [
                            'success' => false,
                            'error' => 'invalid_arguments',
                            'error_code' => 'invalid_arguments',
                            'error_message' => $this->localizedError('invalid_arguments'),
                        ];
                        $toolCalls[] = [
                            'tool' => $toolName,
                            'arguments' => $arguments,
                            'result' => $errPayload,
                            'audit_status' => 'error',
                        ];
                        $auditEntries[] = $this->logAudit($ctx, $requestId, $toolName, $arguments, [
                            'event_type' => 'validation_failure',
                            'error_code' => 'invalid_arguments',
                        ], 'error');
                        $appendToolResult($messages, $toolCallId, $toolName, $errPayload);
                        continue;
                    }

                    $toolMeta = $this->toolRegistryClass::getTool($toolName) ?? [];
                    $isWrite = !empty($toolMeta['write']);
                    $confirmKey = $toolName . ':' . md5((string) json_encode($arguments, JSON_UNESCAPED_UNICODE));
                    $writeConfirmed = !$isWrite
                        || !$requireWriteConfirm
                        || in_array($toolName, $confirmedWrites, true)
                        || in_array($confirmKey, $confirmedWrites, true);

                    if ($isWrite && $requireWriteConfirm && !$writeConfirmed) {
                        $observability['confirmation_required_events']++;
                        $pendingConfirmations[] = [
                            'tool' => $toolName,
                            'arguments' => $arguments,
                            'confirm_key' => $confirmKey,
                            'impact_preview' => $this->buildWriteImpactPreview($toolName, $arguments, $ctx),
                        ];
                        $auditEntries[] = $this->logAudit($ctx, $requestId, $toolName, array_merge($arguments, [
                            '_idempotency_key' => $confirmKey,
                        ]), [
                            'event_type' => 'confirmation_required',
                            'error_code' => 'write_confirmation_required',
                            'write_confirmed' => false,
                        ], 'denied');
                        $appendToolResult($messages, $toolCallId, $toolName, [
                            'success' => false,
                            'error' => 'write_confirmation_required',
                            'error_code' => 'write_confirmation_required',
                            'error_message' => $this->localizedError('write_confirmation_required'),
                            'impact_preview' => $this->buildWriteImpactPreview($toolName, $arguments, $ctx),
                        ]);
                        continue;
                    }

                    if ($isWrite && isset($executedWriteKeys[$confirmKey])) {
                        $observability['tool_failures']++;
                        $errPayload = [
                            'success' => false,
                            'error' => 'duplicate_action',
                            'error_code' => 'duplicate_action',
                            'error_message' => $this->localizedError('duplicate_action'),
                        ];
                        $toolCalls[] = [
                            'tool' => $toolName,
                            'arguments' => $arguments,
                            'result' => $errPayload,
                            'audit_status' => 'denied',
                        ];
                        $auditEntries[] = $this->logAudit($ctx, $requestId, $toolName, array_merge($arguments, [
                            '_idempotency_key' => $confirmKey,
                        ]), [
                            'event_type' => 'duplicate_blocked',
                            'error_code' => 'duplicate_action',
                        ], 'denied');
                        $appendToolResult($messages, $toolCallId, $toolName, $errPayload);
                        continue;
                    }

                    if ($isWrite && $this->wasWriteAlreadySucceeded($ctx, $requestId, $confirmKey)) {
                        $observability['tool_failures']++;
                        $executedWriteKeys[$confirmKey] = true;
                        $errPayload = [
                            'success' => false,
                            'error' => 'duplicate_action',
                            'error_code' => 'duplicate_action',
                            'error_message' => $this->localizedError('duplicate_action'),
                        ];
                        $toolCalls[] = [
                            'tool' => $toolName,
                            'arguments' => $arguments,
                            'result' => $errPayload,
                            'audit_status' => 'denied',
                        ];
                        $auditEntries[] = $this->logAudit($ctx, $requestId, $toolName, array_merge($arguments, [
                            '_idempotency_key' => $confirmKey,
                        ]), [
                            'event_type' => 'duplicate_blocked_retry',
                            'error_code' => 'duplicate_action',
                        ], 'denied');
                        $appendToolResult($messages, $toolCallId, $toolName, $errPayload);
                        continue;
                    }

                    if ($isWrite && $writesExecuted >= $maxWrites) {
                        $observability['confirmation_required_events']++;
                        $errPayload = [
                            'success' => false,
                            'error' => 'write_sequence_blocked',
                            'error_code' => 'write_sequence_blocked',
                            'error_message' => $this->localizedError('write_sequence_blocked'),
                        ];
                        $pendingConfirmations[] = [
                            'tool' => $toolName,
                            'arguments' => $arguments,
                            'confirm_key' => $confirmKey,
                            'impact_preview' => $this->buildWriteImpactPreview($toolName, $arguments, $ctx),
                        ];
                        $auditEntries[] = $this->logAudit($ctx, $requestId, $toolName, array_merge($arguments, [
                            '_idempotency_key' => $confirmKey,
                        ]), [
                            'event_type' => 'write_sequence_blocked',
                            'error_code' => 'write_sequence_blocked',
                        ], 'denied');
                        $appendToolResult($messages, $toolCallId, $toolName, $errPayload);
                        continue;
                    }

                    $policyResult = ProcurementPolicyGuard::checkAndExecute([
                        'tool' => $toolName,
                        'arguments' => $arguments,
                        'request_company_id' => null,
                        'request_id' => $requestId,
                        'write_confirmed' => $writeConfirmed,
                    ], $ctx);

                    if (!$policyResult['allowed']) {
                        $observability['tool_failures']++;
                        $code = (string) ($policyResult['error_code'] ?? 'permission_denied');
                        $errPayload = [
                            'success' => false,
                            'error' => $code,
                            'error_code' => $code,
                            'error_message' => $this->localizedError($code),
                        ];
                        $toolCalls[] = [
                            'tool' => $toolName,
                            'arguments' => $arguments,
                            'result' => $errPayload,
                            'audit_status' => 'denied',
                        ];
                        $auditEntries[] = $this->logAudit($ctx, $requestId, $toolName, $arguments, [
                            'event_type' => 'policy_denied',
                            'error_code' => $code,
                            'policy_checks' => $policyResult['policy_checks'] ?? [],
                            'write_confirmed' => $writeConfirmed,
                        ], 'denied');
                        $appendToolResult($messages, $toolCallId, $toolName, $errPayload);
                        continue;
                    }

                    $startTime = microtime(true);
                    try {
                        $result = $this->toolExecutorClass::execute($toolName, $arguments, $ctx);
                    } catch (\Throwable $e) {
                        $result = [
                            'success' => false,
                            'data' => null,
                            'error' => 'tool_exception',
                            'error_code' => 'tool_exception',
                            'error_message' => $this->localizedError('tool_exception'),
                        ];
                    }
                    $durationMs = (int) ((microtime(true) - $startTime) * 1000);

                    if (empty($result['success'])) {
                        $observability['tool_failures']++;
                    } elseif ($isWrite) {
                        $writesExecuted++;
                        $executedWriteKeys[$confirmKey] = true;
                        $observability['writes_executed'] = $writesExecuted;
                    }

                    $toolCalls[] = [
                        'tool' => $toolName,
                        'arguments' => $arguments,
                        'result' => $result,
                        'audit_status' => !empty($result['success']) ? 'success' : 'error',
                    ];

                    $auditEntries[] = $this->logAudit($ctx, $requestId, $toolName, array_merge($arguments, $isWrite ? [
                        '_idempotency_key' => $confirmKey,
                        '_write_confirmed' => $writeConfirmed,
                    ] : []), [
                        'event_type' => !empty($result['success']) ? 'tool_success' : 'tool_failure',
                        'result' => $result,
                        'duration_ms' => $durationMs,
                        'error_code' => $result['error_code'] ?? null,
                        'policy_checks' => $policyResult['policy_checks'] ?? [],
                        'write_confirmed' => $isWrite ? $writeConfirmed : null,
                    ], !empty($result['success']) ? 'success' : 'error');

                    $appendToolResult($messages, $toolCallId, $toolName, $result);
                }

                if ($pendingConfirmations !== []) {
                    $locale = $ctx->normalizedLocale();
                    $confirmMsg = $locale === 'ar'
                        ? 'يلزم تأكيدك قبل تنفيذ عمليات الكتابة المطلوبة.'
                        : 'Confirmation is required before executing the requested write operations.';
                    $observability['duration_ms'] = (int) ((microtime(true) - $startedAt) * 1000);
                    $observability['success'] = true;
                    return [
                        'response' => $confirmMsg,
                        'tool_calls' => $toolCalls,
                        'pending_confirmations' => $pendingConfirmations,
                        'audit' => $auditEntries,
                        'observability' => $observability,
                    ];
                }
            }

            $maxMsg = $ctx->normalizedLocale() === 'ar'
                ? 'تم الوصول للحد الأقصى من خطوات الأدوات. يرجى توضيح طلبك.'
                : 'Maximum tool iterations reached. Please refine your request.';
            $auditEntries[] = $this->logAudit($ctx, $requestId, 'max_iterations', [], [
                'event_type' => 'max_iterations',
                'error_code' => 'max_tool_iterations',
            ], 'error');
            $observability['duration_ms'] = (int) ((microtime(true) - $startedAt) * 1000);
            return [
                'response' => $maxMsg,
                'tool_calls' => $toolCalls,
                'pending_confirmations' => $pendingConfirmations,
                'audit' => $auditEntries,
                'observability' => $observability,
            ];
        } catch (\Throwable $e) {
            $observability['llm_failures']++;
            try {
                $auditEntries[] = $this->logAudit($ctx, $requestId, 'agent_runtime', [], [
                    'event_type' => 'uncaught_exception',
                    'error_code' => 'agent_runtime_error',
                ], 'error');
            } catch (\Throwable $ignored) {
                // never throw from audit fallback
            }
            $observability['duration_ms'] = (int) ((microtime(true) - $startedAt) * 1000);
            $observability['success'] = false;
            return [
                'response' => $this->safeUserFallback($ctx, 'agent_runtime_error'),
                'tool_calls' => $toolCalls,
                'pending_confirmations' => $pendingConfirmations,
                'audit' => $auditEntries,
                'observability' => $observability,
            ];
        }
    }

    private function mapLlmFailureCode(\Throwable $e): string
    {
        $msg = strtolower(trim($e->getMessage()));
        $known = [
            'llm_not_configured',
            'llm_timeout',
            'llm_request_failed',
            'llm_empty_response',
            'llm_invalid_response',
            'llm_api_error',
        ];
        foreach ($known as $code) {
            if ($msg === $code || str_contains($msg, $code)) {
                return $code;
            }
        }
        if (str_contains($msg, 'timeout') || str_contains($msg, 'timed out')) {
            return 'llm_timeout';
        }
        return 'llm_request_failed';
    }

    private function safeUserFallback(ProcurementAgentContext $ctx, string $code): string
    {
        $locale = $ctx->normalizedLocale();
        $key = 'ai_tool_err_' . $code;
        $translated = __($key);
        if (is_string($translated) && $translated !== '' && $translated !== $key) {
            return $translated;
        }
        if ($locale === 'ar') {
            return 'تعذر إكمال الطلب حاليًا. يرجى المحاولة مرة أخرى.';
        }
        return 'Unable to complete the request right now. Please try again.';
    }

    private function localizedError(string $code): string
    {
        $key = 'ai_tool_err_' . $code;
        $translated = __($key);
        if (is_string($translated) && $translated !== '' && $translated !== $key) {
            return $translated;
        }
        return $code;
    }

    /**
     * Prevent re-executing a confirmed write after timeout/retry when it already succeeded.
     */
    private function wasWriteAlreadySucceeded(ProcurementAgentContext $ctx, string $requestId, string $confirmKey): bool
    {
        if ($requestId === '' || $confirmKey === '') {
            return false;
        }
        try {
            $db = Database::connection();
            $stmt = $db->prepare(
                "SELECT id, input_json FROM rateb_agent_audit_events
                 WHERE company_id = :cid AND user_id = :uid AND request_id = :rid
                   AND status = 'success'
                   AND tool_name IN ('create_draft_purchase_request','update_purchase_request','cancel_purchase_request','submit_purchase_request')
                 ORDER BY id DESC LIMIT 20"
            );
            $stmt->execute([
                'cid' => $ctx->companyId,
                'uid' => $ctx->userId,
                'rid' => $requestId,
            ]);
            foreach ($stmt->fetchAll() as $row) {
                $input = json_decode((string) ($row['input_json'] ?? ''), true);
                if (!is_array($input)) {
                    continue;
                }
                if ((string) ($input['_idempotency_key'] ?? '') === $confirmKey) {
                    return true;
                }
            }
        } catch (\Throwable $e) {
            return false;
        }
        return false;
    }

    private function getSystemPrompt(ProcurementAgentContext $ctx, string $userMessage = ''): string
    {
        $base = $this->config['agent']['system_prompt'] ?? 'You are the Procurement Ops Agent for RATEB ERP. Use only approved tools. Never attempt SQL, direct DB access, or unregistered tools. All operations are tenant-scoped to the authenticated company.';
        $locale = $this->resolveReplyLocale($ctx, $userMessage);

        $capabilityRule = "\n\n=== PROCUREMENT CAPABILITIES ===\n"
            . "READ: purchase requests, purchase orders, suppliers, statuses, amounts, dates, overdue/pending filters, linked approvals.\n"
            . "ANALYZE/REPORT: summarize_procurement for totals; analyze_procurement_intelligence for pending/overdue/abnormal links; analyze_advanced_procurement_operations for spend/frequency/bottlenecks/priorities/executive summary; get_purchase_request_cycle for one PR cycle. Never invent numbers. If a bucket is empty or data is missing, say so clearly.\n"
            . "OPERATIONAL GUIDANCE: for “what should I do now?” / ماذا أفعل الآن؟ call get_procurement_operational_guidance. Link each recommendation to the related PR/PO/approval. Never auto-execute WRITE from guidance.\n"
            . "WRITE: create_draft_purchase_request, update_purchase_request, cancel_purchase_request, submit_purchase_request — only after explicit user confirmation (runtime confirmed_writes). Before asking confirmation, briefly state the operation, key fields, and expected impact. Do not call write tools with incomplete parameters.\n"
            . "APPROVALS: list_pending_approvals / get_approval_detail only — never bypass ApprovalWorkflow.\n"
            . "CONTEXT: conversation is scoped to the authenticated company and user only. Never mix other tenants or users.\n";

        if ($locale === 'ar') {
            $langRule = "\n\n=== LANGUAGE LOCK (Arabic — non-negotiable) ===\n"
                . "The ERP UI locale is Arabic. Your ENTIRE user-facing reply MUST be Modern Standard Arabic only.\n"
                . "FORBIDDEN in user-facing text:\n"
                . "- Any English words, phrases, or parenthetical English glosses (e.g. NEVER write \"Draft Purchase Request\").\n"
                . "- Tool / function / API names (e.g. NEVER write create_draft_purchase_request or any snake_case tool id).\n"
                . "- Raw JSON, code fences, curl, HTTP, or API payload examples unless the user explicitly asks for a technical sample.\n"
                . "- Mixing English inside Arabic sentences.\n"
                . "REQUIRED vocabulary (use Arabic only):\n"
                . "- purchase request → طلب شراء | draft → مسودة | submit → إرسال | purchase order → أمر شراء\n"
                . "- supplier → مورد | approval → موافقة | pending → معلق | approved → معتمد | rejected → مرفوض\n"
                . "- overdue → متأخر | abnormal → حالة غير طبيعية | cycle → دورة المشتريات | amount → المبلغ\n"
                . "- quantity → الكمية | unit price → سعر الوحدة | department → القسم | priority → الأولوية\n"
                . "- high → عالية | medium → متوسطة | low → منخفضة\n"
                . "Brand: say «رتب» or «نظام رتب» — do not insert English product slogans in the middle of Arabic sentences.\n"
                . "Tools are internal only: call them silently; describe actions in Arabic (مثلاً: سأنشئ مسودة طلب شراء).\n"
                . "Never mention English tool names, never add English in parentheses, never say Draft/Purchase Request/Order in Latin script.\n"
                . "Allowed Latin exceptions only: RATEB AI, SAR, and numeric/document IDs.\n"
                . "Present data as short Arabic prose and/or Markdown tables with Arabic column headers.\n"
                . "If data is unavailable, say clearly: البيانات غير متوفرة في النظام.";
        } else {
            $langRule = "\n\n=== LANGUAGE LOCK (English — non-negotiable) ===\n"
                . "The ERP UI locale is English. Your ENTIRE user-facing reply MUST be English only.\n"
                . "FORBIDDEN: Arabic words/sentences mixed into English replies; tool/function snake_case names; raw JSON/API dumps unless the user explicitly asks.\n"
                . "Describe actions in plain English (e.g. \"I will create a draft purchase request\") — never expose internal tool ids.\n"
                . "Present data as short English prose and/or Markdown tables.\n"
                . "If data is unavailable, say clearly: Data is not available in the system.";
        }

        return rtrim($base) . $capabilityRule . $langRule;
    }

    /**
     * @return array{summary: string, tool: string, arguments: array}
     */
    private function buildWriteImpactPreview(string $toolName, array $arguments, ProcurementAgentContext $ctx): array
    {
        $locale = $ctx->normalizedLocale();
        $id = (int) ($arguments['id'] ?? 0);
        $title = trim((string) ($arguments['title'] ?? ''));

        if ($locale === 'ar') {
            $map = [
                'create_draft_purchase_request' => 'إنشاء مسودة طلب شراء' . ($title !== '' ? ': ' . $title : ''),
                'update_purchase_request' => 'تعديل طلب شراء' . ($id > 0 ? ' #' . $id : ''),
                'cancel_purchase_request' => 'إلغاء طلب شراء' . ($id > 0 ? ' #' . $id : ''),
                'submit_purchase_request' => 'إرسال طلب شراء للموافقة' . ($id > 0 ? ' #' . $id : ''),
            ];
        } else {
            $map = [
                'create_draft_purchase_request' => 'Create draft purchase request' . ($title !== '' ? ': ' . $title : ''),
                'update_purchase_request' => 'Update purchase request' . ($id > 0 ? ' #' . $id : ''),
                'cancel_purchase_request' => 'Cancel purchase request' . ($id > 0 ? ' #' . $id : ''),
                'submit_purchase_request' => 'Submit purchase request for approval' . ($id > 0 ? ' #' . $id : ''),
            ];
        }

        return [
            'summary' => $map[$toolName] ?? $toolName,
            'tool' => $toolName,
            'arguments' => $arguments,
            'conversation_scope' => $ctx->conversationScopeKey(),
        ];
    }

    /**
     * Honor the authenticated UI locale only (do not flip language mid-session).
     */
    private function resolveReplyLocale(ProcurementAgentContext $ctx, string $userMessage = ''): string
    {
        return $ctx->normalizedLocale();
    }

    /**
     * Tool results for the LLM must never include raw English exception prose.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function toolPayloadForLlm(array $payload, ProcurementAgentContext $ctx): array
    {
        if (!empty($payload['success'])) {
            return [
                'success' => true,
                'data' => $payload['data'] ?? null,
            ];
        }

        $code = (string) ($payload['error_code'] ?? $payload['error'] ?? 'tool_error');
        // Collapse legacy English prose into stable codes.
        $map = [
            'Invalid purchase request ID' => 'invalid_pr_id',
            'Purchase request not found' => 'pr_not_found',
            'Invalid approval instance ID' => 'invalid_approval_id',
            'Approval instance not found' => 'approval_not_found',
            'Title is required' => 'title_required',
            'Only draft purchase requests can be submitted' => 'pr_not_draft',
            'write_confirmation_required' => 'write_confirmation_required',
        ];
        if (isset($map[$code])) {
            $code = $map[$code];
        } elseif (preg_match('/^[A-Za-z].*\s/', $code) === 1) {
            $code = 'tool_error';
        }

        $message = (string) ($payload['error_message'] ?? '');
        if ($message === '' || $message === $code) {
            $key = 'ai_tool_err_' . $code;
            $translated = __($key);
            $message = (is_string($translated) && $translated !== '' && $translated !== $key)
                ? $translated
                : $code;
        }

        return [
            'success' => false,
            'error_code' => $code,
            'error_message' => $message,
        ];
    }

    /**
     * Last-line defense: strip leaked tool ids / English jargon from Arabic replies (and reverse).
     */
    private function sanitizeUserFacingReply(string $text, ProcurementAgentContext $ctx, string $userMessage): string
    {
        $text = trim($text);
        if ($text === '') {
            return $text;
        }

        $locale = $this->resolveReplyLocale($ctx, $userMessage);
        $toolLabelsAr = [
            'list_purchase_requests' => 'عرض طلبات الشراء',
            'get_purchase_request' => 'تفاصيل طلب الشراء',
            'list_purchase_orders' => 'عرض أوامر الشراء',
            'get_purchase_order' => 'تفاصيل أمر الشراء',
            'search_suppliers' => 'البحث عن الموردين',
            'list_pending_approvals' => 'عرض الموافقات المعلقة',
            'get_approval_detail' => 'تفاصيل الموافقة',
            'summarize_procurement' => 'ملخص المشتريات',
            'analyze_procurement_intelligence' => 'تحليل ذكاء المشتريات',
            'get_purchase_request_cycle' => 'دورة طلب الشراء',
            'analyze_advanced_procurement_operations' => 'تحليل عمليات المشتريات المتقدم',
            'get_procurement_operational_guidance' => 'التوجيه التشغيلي للمشتريات',
            'create_draft_purchase_request' => 'إنشاء مسودة طلب شراء',
            'update_purchase_request' => 'تعديل طلب الشراء',
            'cancel_purchase_request' => 'إلغاء طلب الشراء',
            'submit_purchase_request' => 'إرسال طلب الشراء',
        ];
        $toolLabelsEn = [
            'list_purchase_requests' => 'list purchase requests',
            'get_purchase_request' => 'get purchase request details',
            'list_purchase_orders' => 'list purchase orders',
            'get_purchase_order' => 'get purchase order details',
            'search_suppliers' => 'search suppliers',
            'list_pending_approvals' => 'list pending approvals',
            'get_approval_detail' => 'get approval details',
            'summarize_procurement' => 'procurement summary',
            'analyze_procurement_intelligence' => 'procurement intelligence analysis',
            'get_purchase_request_cycle' => 'purchase request cycle',
            'analyze_advanced_procurement_operations' => 'advanced procurement operations analysis',
            'get_procurement_operational_guidance' => 'procurement operational guidance',
            'create_draft_purchase_request' => 'create a draft purchase request',
            'update_purchase_request' => 'update the purchase request',
            'cancel_purchase_request' => 'cancel the purchase request',
            'submit_purchase_request' => 'submit the purchase request',
        ];

        $labels = $locale === 'ar' ? $toolLabelsAr : $toolLabelsEn;
        foreach ($labels as $tool => $label) {
            $text = preg_replace('/`?' . preg_quote($tool, '/') . '`?/i', $label, $text) ?? $text;
        }
        // Any remaining snake_case tool-like tokens
        $text = preg_replace('/\b[a-z]+(?:_[a-z0-9]+){2,}\b/', '', $text) ?? $text;

        if ($locale === 'ar') {
            // Remove English-only parenthetical glosses first: (Draft Purchase Request)
            $text = preg_replace('/\(\s*[A-Za-z][A-Za-z0-9\s\-_\/.&]*\s*\)/u', '', $text) ?? $text;

            $replacements = [
                '/\bDraft\s+Purchase\s+Requests?\b/i' => 'مسودة طلب شراء',
                '/\bPurchase\s+Requests?\b/i' => 'طلب شراء',
                '/\bPurchase\s+Orders?\b/i' => 'أمر شراء',
                '/\bPending\s+Approvals?\b/i' => 'الموافقات المعلقة',
                '/\bI will use (the )?tool\b/i' => 'سأستخدم',
                '/\bvia the\b/i' => 'عبر',
                '/\bRATEB\s+ERP\b/i' => 'نظام رتب',
                '/\btool\b/i' => 'أداة',
                '/\bApprovals?\b/i' => 'موافقة',
                '/\bSuppliers?\b/i' => 'مورد',
                '/\bDepartment\b/i' => 'القسم',
                '/\bPriority\b/i' => 'الأولوية',
                '/\bQuantity\b/i' => 'الكمية',
                '/\bUnit\s*Price\b/i' => 'سعر الوحدة',
                '/\bExpected\s+Date\b/i' => 'التاريخ المتوقع',
                '/\bLine\s*Items?\b/i' => 'بنود',
                '/\bCurrency\b/i' => 'العملة',
                '/\bStatus\b/i' => 'الحالة',
                '/\bDraft\b/i' => 'مسودة',
                '/\bSubmitted\b/i' => 'مُرسل',
                '/\bPending\b/i' => 'معلق',
                '/\bApproved\b/i' => 'معتمد',
                '/\bRejected\b/i' => 'مرفوض',
                '/\bGot it!?/i' => 'حسنًا.',
                '/\bHow can I assist you next\b[^.?!]*/i' => 'كيف يمكنني مساعدتك بعد ذلك',
                '/\banything\b\??/i' => '',
            ];
            foreach ($replacements as $pattern => $replacement) {
                $text = preg_replace($pattern, $replacement, $text) ?? $text;
            }
            // Protect allowed Latin tokens, then strip leftover long English runs (3+ words).
            $placeholders = [
                '⟦RAI⟧' => 'RATEB AI',
                '⟦RB⟧' => 'RATEB',
                '⟦SAR⟧' => 'SAR',
            ];
            $text = str_ireplace('RATEB AI', '⟦RAI⟧', $text);
            $text = str_ireplace('RATEB', '⟦RB⟧', $text);
            $text = str_ireplace('SAR', '⟦SAR⟧', $text);
            $text = preg_replace('/\b(?:[A-Za-z]{3,}[\s,;:.!?-]*){3,}/u', '', $text) ?? $text;
            foreach ($placeholders as $ph => $keep) {
                $text = str_replace($ph, $keep, $text);
            }
            $text = preg_replace('/نظام\s+نظام\s+رتب/u', 'نظام رتب', $text) ?? $text;
            $text = preg_replace('/\(\s*\)/u', '', $text) ?? $text;
            $text = preg_replace('/[ \t]{2,}/u', ' ', $text) ?? $text;
            $text = preg_replace('/\s+([،.])/u', '$1', $text) ?? $text;
        } else {
            // Drop accidental Arabic script when UI locale is English (unless user wrote Arabic — then locale=ar).
            $text = preg_replace('/\p{Arabic}+/u', '', $text) ?? $text;
            $text = preg_replace('/[ \t]{2,}/u', ' ', $text) ?? $text;
        }

        return trim($text);
    }

    private function logAudit(
        ProcurementAgentContext $ctx,
        string $requestId,
        string $toolName,
        array $arguments,
        array $output,
        string $status
    ): array {
        $db = Database::connection();

        // Sanitize input/output for audit (remove secrets if any)
        $safeInput = $this->sanitizeForAudit($arguments);
        $safeOutput = $this->sanitizeForAudit($output);

        $policyChecks = is_array($output['policy_checks'] ?? null)
            ? $output['policy_checks']
            : [
                'auth' => true,
                'tenant_context' => true,
                'tool_allowlist' => $this->toolRegistryClass::isAllowed($toolName),
                'module_entitlement' => true,
                'rbac_permission' => true,
                'approval_policy' => true,
                'parameter_sufficiency' => true,
                'write_confirmation' => true,
                'execute' => true,
                'audit' => true,
            ];

        $errorCode = $output['error_code'] ?? $output['error'] ?? null;
        if (!is_string($errorCode) || $errorCode === '') {
            $errorCode = null;
        }

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
                'model' => $output['model'] ?? null,
                'tin' => $output['tokens_in'] ?? null,
                'tout' => $output['tokens_out'] ?? null,
                'dur' => $output['duration_ms'] ?? 0,
            ]);
        } catch (\Throwable $e) {
            // Observability must never crash the agent response path.
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
            'duration_ms' => $output['duration_ms'] ?? null,
        ];
    }

    private function sanitizeForAudit(array $data): array
    {
        $excludeKeys = ['password', 'secret', 'token', 'api_key', 'pass', 'authorization', 'cookie'];
        $result = [];
        foreach ($data as $k => $v) {
            $lowerK = strtolower((string) $k);
            $skip = false;
            foreach ($excludeKeys as $ex) {
                if (strpos($lowerK, $ex) !== false) {
                    $skip = true;
                    break;
                }
            }
            if ($skip) {
                $result[$k] = '[REDACTED]';
            } elseif (is_array($v)) {
                $result[$k] = $this->sanitizeForAudit($v);
            } else {
                $result[$k] = $v;
            }
        }
        return $result;
    }
}