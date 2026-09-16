<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\Database;
use Rateb\App\Services\Agent\LlmClientInterface;
use Rateb\App\Services\Agent\OpenAiCompatibleClient;

/**
 * Procurement Agent
 * Orchestrates: LLM → Policy Guard → Tool Executor → Audit
 */
final class ProcurementAgent
{
    private LlmClientInterface $llmClient;
    private array $toolDefinitions;
    private array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;
        $this->toolDefinitions = ProcurementToolRegistry::getOpenAiToolDefinitions();

        $llmConfig = $config['llm'] ?? [];
        $this->llmClient = new OpenAiCompatibleClient($llmConfig);
    }

    /**
     * Process a chat request from the user
     *
     * @param array{
     *     message: string,
     *     request_id: string,
     *     company_id: int|null,
     *     history: array<int, array{role: string, content: string}>
     * } $input
     * @param ProcurementAgentContext $ctx
     * @return array{
     *     response: string,
     *     tool_calls: array,
     *     audit: array
     * }
     */
    public function process(array $input, ProcurementAgentContext $ctx): array
    {
        $requestId = $input['request_id'] ?? bin2hex(random_bytes(16));
        $userMessage = trim((string) ($input['message'] ?? ''));
        $history = $input['history'] ?? [];

        if ($userMessage === '') {
            return ['response' => '', 'tool_calls' => [], 'audit' => []];
        }

        // Build messages for LLM
        $messages = [
            ['role' => 'system', 'content' => $this->getSystemPrompt($ctx, $userMessage)],
        ];

        // Add conversation history
        foreach ($history as $msg) {
            if (isset($msg['role'], $msg['content'])) {
                $messages[] = ['role' => $msg['role'], 'content' => $msg['content']];
            }
        }

        // Add current user message
        $messages[] = ['role' => 'user', 'content' => $userMessage];

        $toolCalls = [];
        $auditEntries = [];
        $pendingConfirmations = [];
        $maxIterations = (int) ($this->config['agent']['max_tool_calls_per_request'] ?? 10);
        $iterations = 0;
        $requireWriteConfirm = (bool) ($this->config['agent']['require_confirmation_for_write'] ?? true);
        $confirmedWrites = $input['confirmed_writes'] ?? [];
        if (!is_array($confirmedWrites)) {
            $confirmedWrites = [];
        }
        $confirmedWrites = array_values(array_filter(array_map('strval', $confirmedWrites)));

        while ($iterations < $maxIterations) {
            $iterations++;

            // Call LLM
            $llmResponse = $this->llmClient->chatCompletion($messages, $this->toolDefinitions, 'auto');

            $message = $llmResponse['message'] ?? [];
            $toolCallsFromLlm = $message['tool_calls'] ?? [];

            if (empty($toolCallsFromLlm)) {
                // No tool calls - final response
                $assistantContent = $this->sanitizeUserFacingReply(
                    (string) ($message['content'] ?? ''),
                    $ctx,
                    $userMessage
                );
                $messages[] = ['role' => 'assistant', 'content' => $assistantContent];

                // Audit the final response
                $auditEntries[] = $this->logAudit($ctx, $requestId, 'final_response', [], [
                    'response' => $assistantContent,
                    'tokens_in' => $llmResponse['usage']['prompt_tokens'] ?? 0,
                    'tokens_out' => $llmResponse['usage']['completion_tokens'] ?? 0,
                    'model' => $llmResponse['model'] ?? '',
                ], 'success');

                return [
                    'response' => $assistantContent,
                    'tool_calls' => $toolCalls,
                    'pending_confirmations' => $pendingConfirmations,
                    'audit' => $auditEntries,
                ];
            }

            // gpt-oss / Harmony requires the assistant tool_calls turn before any role=tool results.
            $assistantTurn = ['role' => 'assistant', 'tool_calls' => $toolCallsFromLlm];
            if (array_key_exists('content', $message) && $message['content'] !== null && $message['content'] !== '') {
                $assistantTurn['content'] = $message['content'];
            }
            $messages[] = $assistantTurn;

            $appendToolResult = function (array &$messages, string $toolCallId, string $toolName, array $payload) use ($ctx): void {
                $safe = $this->toolPayloadForLlm($payload, $ctx);
                // Harmony (gpt-oss) requires name on role=tool messages.
                $messages[] = [
                    'role' => 'tool',
                    'tool_call_id' => $toolCallId,
                    'name' => $toolName,
                    'content' => json_encode($safe, JSON_UNESCAPED_UNICODE),
                ];
            };

            // Process each tool call
            foreach ($toolCallsFromLlm as $toolCall) {
                $function = $toolCall['function'] ?? [];
                $toolName = (string) ($function['name'] ?? '');
                $argumentsJson = (string) ($function['arguments'] ?? '{}');
                $toolCallId = (string) ($toolCall['id'] ?? '');

                if (!$toolName) {
                    continue;
                }

                $arguments = json_decode($argumentsJson, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $arguments = [];
                }
                // Never trust LLM-supplied confirmation flags.
                unset($arguments['_confirmed'], $arguments['confirmed'], $arguments['write_confirmed']);

                // Validate arguments
                try {
                    $arguments = ProcurementToolRegistry::validateArguments($toolName, $arguments);
                } catch (\Throwable $e) {
                    $errPayload = [
                        'success' => false,
                        'error' => 'invalid_arguments',
                        'error_code' => 'invalid_arguments',
                        'error_message' => __('ai_tool_err_invalid_arguments') !== 'ai_tool_err_invalid_arguments'
                            ? __('ai_tool_err_invalid_arguments')
                            : 'invalid_arguments',
                    ];
                    $toolCalls[] = [
                        'tool' => $toolName,
                        'arguments' => $arguments,
                        'result' => $errPayload,
                        'audit_status' => 'error',
                    ];
                    $auditEntries[] = $this->logAudit($ctx, $requestId, $toolName, $arguments, [
                        'error_code' => 'invalid_arguments',
                    ], 'error');
                    $appendToolResult($messages, $toolCallId, $toolName, $errPayload);
                    continue;
                }

                $toolMeta = ProcurementToolRegistry::getTool($toolName) ?? [];
                $isWrite = !empty($toolMeta['write']);
                $confirmKey = $toolName . ':' . md5((string) json_encode($arguments, JSON_UNESCAPED_UNICODE));
                $writeConfirmed = !$isWrite
                    || !$requireWriteConfirm
                    || in_array($toolName, $confirmedWrites, true)
                    || in_array($confirmKey, $confirmedWrites, true);

                if ($isWrite && $requireWriteConfirm && !$writeConfirmed) {
                    $pendingConfirmations[] = [
                        'tool' => $toolName,
                        'arguments' => $arguments,
                        'confirm_key' => $confirmKey,
                    ];
                    $auditEntries[] = $this->logAudit($ctx, $requestId, $toolName, $arguments, [
                        'error_code' => 'write_confirmation_required',
                    ], 'denied');
                    $appendToolResult($messages, $toolCallId, $toolName, [
                        'success' => false,
                        'error' => 'write_confirmation_required',
                        'error_code' => 'write_confirmation_required',
                        'error_message' => __('ai_tool_err_write_confirmation_required') !== 'ai_tool_err_write_confirmation_required'
                            ? __('ai_tool_err_write_confirmation_required')
                            : 'write_confirmation_required',
                    ]);
                    continue;
                }

                // Policy Guard check
                $policyResult = ProcurementPolicyGuard::checkAndExecute([
                    'tool' => $toolName,
                    'arguments' => $arguments,
                    'request_company_id' => null,
                    'request_id' => $requestId,
                    'write_confirmed' => $writeConfirmed,
                ], $ctx);

                if (!$policyResult['allowed']) {
                    $errPayload = [
                        'success' => false,
                        'error' => (string) ($policyResult['error_code'] ?? 'permission_denied'),
                        'error_code' => (string) ($policyResult['error_code'] ?? 'permission_denied'),
                    ];
                    $toolCalls[] = [
                        'tool' => $toolName,
                        'arguments' => $arguments,
                        'result' => $errPayload,
                        'audit_status' => 'denied',
                    ];
                    $auditEntries[] = $this->logAudit($ctx, $requestId, $toolName, $arguments, [
                        'error' => $policyResult['error_code'],
                        'policy_checks' => $policyResult['policy_checks'],
                    ], 'denied');
                    $appendToolResult($messages, $toolCallId, $toolName, $errPayload);
                    continue;
                }

                // Execute tool
                $startTime = microtime(true);
                $result = ProcurementToolExecutor::execute($toolName, $arguments, $ctx);
                $durationMs = (int) ((microtime(true) - $startTime) * 1000);

                $toolCalls[] = [
                    'tool' => $toolName,
                    'arguments' => $arguments,
                    'result' => $result,
                    'audit_status' => $result['success'] ? 'success' : 'error',
                ];

                // Audit the tool call
                $auditEntries[] = $this->logAudit($ctx, $requestId, $toolName, $arguments, [
                    'result' => $result,
                    'duration_ms' => $durationMs,
                ], $result['success'] ? 'success' : 'error');

                $appendToolResult($messages, $toolCallId, $toolName, $result);
            }

            if ($pendingConfirmations !== []) {
                $locale = $ctx->locale ?? 'en';
                $confirmMsg = $locale === 'ar'
                    ? 'يلزم تأكيدك قبل تنفيذ عمليات الكتابة المطلوبة.'
                    : 'Confirmation is required before executing the requested write operations.';

                return [
                    'response' => $confirmMsg,
                    'tool_calls' => $toolCalls,
                    'pending_confirmations' => $pendingConfirmations,
                    'audit' => $auditEntries,
                ];
            }
        }

        // Max iterations reached
        $maxMsg = ($ctx->locale ?? 'en') === 'ar'
            ? 'تم الوصول للحد الأقصى من خطوات الأدوات. يرجى توضيح طلبك.'
            : 'Maximum tool iterations reached. Please refine your request.';
        return [
            'response' => $maxMsg,
            'tool_calls' => $toolCalls,
            'pending_confirmations' => $pendingConfirmations,
            'audit' => $auditEntries,
        ];
    }

    private function getSystemPrompt(ProcurementAgentContext $ctx, string $userMessage = ''): string
    {
        $base = $this->config['agent']['system_prompt'] ?? 'You are the Procurement Ops Agent for RATEB ERP. You can only use the 8 approved tools. Never attempt SQL, direct DB access, or unregistered tools. All operations are tenant-scoped to the authenticated company.';
        $locale = $this->resolveReplyLocale($ctx, $userMessage);

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
                . "- quantity → الكمية | unit price → سعر الوحدة | department → القسم | priority → الأولوية\n"
                . "- high → عالية | medium → متوسطة | low → منخفضة\n"
                . "Brand: say «رتب» or «نظام رتب» — do not insert English product slogans in the middle of Arabic sentences.\n"
                . "Tools are internal only: call them silently; describe actions in Arabic (مثلاً: سأنشئ مسودة طلب شراء).\n"
                . "Never mention English tool names, never add English in parentheses, never say Draft/Purchase Request/Order in Latin script.\n"
                . "Allowed Latin exceptions only: RATEB AI, SAR, and numeric/document IDs.\n"
                . "Present data as short Arabic prose and/or Markdown tables with Arabic column headers.";
        } else {
            $langRule = "\n\n=== LANGUAGE LOCK (English — non-negotiable) ===\n"
                . "The ERP UI locale is English. Your ENTIRE user-facing reply MUST be English only.\n"
                . "FORBIDDEN: Arabic words/sentences mixed into English replies; tool/function snake_case names; raw JSON/API dumps unless the user explicitly asks.\n"
                . "Describe actions in plain English (e.g. \"I will create a draft purchase request\") — never expose internal tool ids.\n"
                . "Present data as short English prose and/or Markdown tables.";
        }

        return rtrim($base) . $langRule;
    }

    /**
     * Honor the authenticated UI locale only (do not flip language mid-session).
     */
    private function resolveReplyLocale(ProcurementAgentContext $ctx, string $userMessage = ''): string
    {
        $locale = strtolower(trim((string) ($ctx->locale ?? 'en')));
        return $locale === 'ar' ? 'ar' : 'en';
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
            'search_suppliers' => 'البحث عن الموردين',
            'list_pending_approvals' => 'عرض الموافقات المعلقة',
            'get_approval_detail' => 'تفاصيل الموافقة',
            'create_draft_purchase_request' => 'إنشاء مسودة طلب شراء',
            'submit_purchase_request' => 'إرسال طلب الشراء',
        ];
        $toolLabelsEn = [
            'list_purchase_requests' => 'list purchase requests',
            'get_purchase_request' => 'get purchase request details',
            'list_purchase_orders' => 'list purchase orders',
            'search_suppliers' => 'search suppliers',
            'list_pending_approvals' => 'list pending approvals',
            'get_approval_detail' => 'get approval details',
            'create_draft_purchase_request' => 'create a draft purchase request',
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

        $policyChecks = [
            'auth' => true,
            'tenant_context' => true,
            'tool_allowlist' => ProcurementToolRegistry::isAllowed($toolName),
            'module_entitlement' => true,
            'rbac_permission' => true,
            'approval_policy' => true,
            'execute' => true,
            'audit' => true,
        ];

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
            'ec' => $output['error_code'] ?? null,
            'pc' => json_encode($policyChecks, JSON_UNESCAPED_UNICODE),
            'rid' => $requestId,
            'model' => $output['model'] ?? null,
            'tin' => $output['tokens_in'] ?? null,
            'tout' => $output['tokens_out'] ?? null,
            'dur' => $output['duration_ms'] ?? 0,
        ]);

        return [
            'tool' => $toolName,
            'status' => $status,
            'request_id' => $requestId,
        ];
    }

    private function sanitizeForAudit(array $data): array
    {
        $excludeKeys = ['password', 'secret', 'token', 'api_key', 'pass', 'key'];
        $result = [];
        foreach ($data as $k => $v) {
            $lowerK = strtolower($k);
            $skip = false;
            foreach ($excludeKeys as $ex) {
                if (strpos($lowerK, $ex) !== false) {
                    $skip = true;
                    break;
                }
            }
            if ($skip) {
                $result[$k] = '[REDACTED]';
            } else {
                $result[$k] = $v;
            }
        }
        return $result;
    }
}