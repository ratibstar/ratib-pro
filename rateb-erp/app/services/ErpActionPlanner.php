<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\Database;
use Rateb\App\Models\PurchaseRequest;

/**
 * ERP Agent Action & Automation Layer (not an agent / not a separate engine).
 * Plans and safely executes only real existing WRITE tools (currently Procurement).
 * LLM may propose; application decides after Authorization → Policy → State → Confirmation → Verify → Audit.
 */
final class ErpActionPlanner
{
    public const CLASS_READ = 'READ';
    public const CLASS_ANALYSIS = 'ANALYSIS';
    public const CLASS_RECOMMENDATION = 'RECOMMENDATION';
    public const CLASS_WRITE = 'WRITE';
    public const CLASS_SENSITIVE_WRITE = 'SENSITIVE_WRITE';

    /** @var list<string> */
    private const WRITE_TOOLS = [
        'create_draft_purchase_request',
        'update_purchase_request',
        'cancel_purchase_request',
        'submit_purchase_request',
        'submit_journal_for_approval',
    ];

    /** @var list<string> */
    private const SENSITIVE_WRITE_TOOLS = [
        'submit_purchase_request',
        'submit_journal_for_approval',
    ];

    /** @var list<string> */
    private const ANALYSIS_TOOLS = [
        'analyze_crm', 'analyze_sales', 'analyze_inventory', 'analyze_suppliers', 'analyze_logistics',
        'analyze_crm_commercial_intelligence', 'analyze_sales_cross_domain', 'analyze_supplier_cross_domain',
        'analyze_logistics_end_to_end', 'analyze_procurement_intelligence',
        'analyze_advanced_procurement_operations', 'summarize_procurement',
        'analyze_accounting', 'analyze_financial_intelligence',
        'analyze_executive_intelligence', 'scan_early_warnings', 'get_early_warning_digest',
        'analyze_operational_learning', 'get_optimization_insights', 'get_control_tower_snapshot',
        'get_relevant_operational_context', 'get_operational_memory',
        'plan_multi_step_workflow', 'get_active_workflows', 'get_workflow_status',
    ];

    /** @var list<string> */
    private const RECOMMENDATION_TOOLS = [
        'get_procurement_operational_guidance', 'get_crm_operational_guidance',
        'get_sales_operational_guidance', 'get_logistics_operational_guidance',
        'get_accounting_operational_guidance',
        'get_executive_priorities', 'get_executive_action_bridge',
        'get_early_warnings', 'get_early_warning_action_bridge', 'revalidate_early_warning',
        'get_recommendation_effectiveness', 'get_warning_effectiveness', 'get_forecast_feedback',
        'record_recommendation_feedback',
    ];

    public static function classifyTool(string $toolName): string
    {
        if (in_array($toolName, self::SENSITIVE_WRITE_TOOLS, true)) {
            return self::CLASS_SENSITIVE_WRITE;
        }
        if (in_array($toolName, self::WRITE_TOOLS, true)) {
            return self::CLASS_WRITE;
        }
        $meta = ErpToolRegistry::getTool($toolName) ?? ProcurementToolRegistry::getTool($toolName);
        if (is_array($meta) && !empty($meta['write'])) {
            return self::CLASS_WRITE;
        }
        if (in_array($toolName, self::RECOMMENDATION_TOOLS, true) || str_contains($toolName, 'guidance')) {
            return self::CLASS_RECOMMENDATION;
        }
        if (in_array($toolName, self::ANALYSIS_TOOLS, true) || str_starts_with($toolName, 'analyze_')) {
            return self::CLASS_ANALYSIS;
        }
        return self::CLASS_READ;
    }

    public static function hasActionIntent(string $message): bool
    {
        return self::match(
            $message,
            '/(أنشئ|إنشاء|عدّل|عدل|ألغ|الغ|حدّث|حدث|أرسل|ارسل|submit|create|update|cancel|prepare\s+for\s+delivery|جهز|متابعة\s*للعميل|طلب\s*شراء)/ui'
        ) && (
            ErpOrchestrationPlanner::hasWriteIntent($message)
            || self::match($message, '/(طلب\s*شراء|purchase\s*request|حالة\s*الطلب|متابعة|تسليم|delivery)/ui')
        );
    }

    /**
     * Build an action plan from natural language using only real registered tools.
     *
     * @return array{
     *     intent: string,
     *     domains: list<string>,
     *     actions: list<array<string,mixed>>,
     *     unsupported: list<array<string,mixed>>,
     *     recommendations: list<array<string,mixed>>,
     *     requires_confirmation: bool
     * }
     */
    public static function buildActionPlan(string $message, ProcurementAgentContext $ctx, ?array $intelligence = null): array
    {
        $actions = [];
        $unsupported = [];
        $recommendations = [];
        $domains = [];

        // Unsupported NL actions (no real agent WRITE tools exist)
        if (self::match($message, '/(متابعة\s*للعميل|أنشئ\s*متابعة|create\s+(a\s+)?follow[\s-]?up|crm\s+follow)/ui')) {
            $unsupported[] = [
                'requested' => 'create_crm_followup',
                'domain' => 'crm',
                'reason' => 'no_write_tool_registered_for_crm',
                'class' => self::CLASS_RECOMMENDATION,
            ];
            $recommendations[] = [
                'message' => 'crm_followup_write_not_available_via_agent',
                'domain' => 'crm',
            ];
        }
        if (self::match($message, '/(جهز.*تسليم|prepare.*delivery|حدّث\s*حالة\s*الشحن|update\s+shipment\s+status)/ui')) {
            $unsupported[] = [
                'requested' => 'update_logistics_status',
                'domain' => 'logistics',
                'reason' => 'no_write_tool_registered_for_logistics',
                'class' => self::CLASS_RECOMMENDATION,
            ];
        }
        if (self::match($message, '/(أنشئ|إنشاء|create).{0,40}(قيد|journal|فاتورة|invoice|دفعة|payment)/ui')
            || self::match($message, '/(عدّل|عدل|update|post|رحّل|رحل).{0,40}(قيد|journal|فاتورة|invoice|دفعة|payment)/ui')
        ) {
            if (!self::match($message, '/(أرسل|ارسل|submit).{0,40}(قيد|journal).{0,40}(موافقة|approval)/ui')) {
                $unsupported[] = [
                    'requested' => 'direct_accounting_mutation',
                    'domain' => 'accounting',
                    'reason' => 'llm_cannot_create_or_mutate_ledger_directly',
                    'class' => self::CLASS_RECOMMENDATION,
                ];
                $recommendations[] = [
                    'message' => 'accounting_write_requires_existing_workflow_tool',
                    'domain' => 'accounting',
                ];
            }
        }
        if (self::match($message, '/(حدّث\s*حالة\s*الطلب(?!\s*شراء)|update\s+(sales\s+)?order\s+status)/ui')
            && !self::match($message, '/طلب\s*شراء|purchase\s*request/ui')
        ) {
            $unsupported[] = [
                'requested' => 'update_sales_order_status',
                'domain' => 'sales',
                'reason' => 'no_write_tool_registered_for_sales',
                'class' => self::CLASS_RECOMMENDATION,
            ];
        }

        // Supported procurement writes only
        if (self::match($message, '/(أنشئ|إنشاء|create).{0,40}(طلب\s*شراء|purchase\s*request)/ui')
            || self::match($message, '/(طلب\s*شراء).{0,40}(ناقص|منتج|صنف|shortfall|missing|low\s*stock)/ui')
        ) {
            $title = self::extractTitle($message);
            $args = [
                'title' => $title !== '' ? $title : (self::isAr($ctx) ? 'طلب شراء مقترح من الوكيل' : 'Agent suggested purchase request'),
                'priority' => 'medium',
                'notes' => mb_substr(trim($message), 0, 500),
            ];
            $line = self::suggestLineFromIntelligence($intelligence);
            if ($line !== null) {
                $args['line_items'] = [$line];
            }
            $actions[] = self::makeAction('create_draft_purchase_request', $args, $ctx, 'create_pr_for_shortage');
            $domains[] = 'procurement';
            if (is_array($intelligence) && !empty($intelligence['incomplete_chains'])) {
                $domains[] = 'inventory';
            }
        }

        if (self::match($message, '/(أرسل|ارسل|submit).{0,40}(قيد|journal).{0,40}(موافقة|approval)|submit\s+journal(\s+entry)?\s*#?\s*\d+|أرسل\s*القيد\s*#?\s*\d+/ui')) {
            $id = self::extractId($message);
            if ($id > 0) {
                $actions[] = self::makeAction('submit_journal_for_approval', ['id' => $id], $ctx, 'submit_journal_approval');
                $domains[] = 'accounting';
            } else {
                $recommendations[] = [
                    'message' => 'submit_requires_journal_id',
                    'domain' => 'accounting',
                    'class' => self::CLASS_RECOMMENDATION,
                ];
            }
        } elseif (self::match($message, '/(أرسل|ارسل|submit).{0,40}(طلب\s*شراء|purchase\s*request|للموافقة|for\s+approval)/ui')
            && !self::match($message, '/(قيد|journal)/ui')
        ) {
            $id = self::extractId($message);
            if ($id > 0) {
                $actions[] = self::makeAction('submit_purchase_request', ['id' => $id], $ctx, 'submit_pr_approval');
                $domains[] = 'procurement';
            } else {
                $recommendations[] = [
                    'message' => 'submit_requires_purchase_request_id',
                    'domain' => 'procurement',
                    'class' => self::CLASS_RECOMMENDATION,
                ];
            }
        }

        if (self::match($message, '/(ألغ|الغ|cancel).{0,40}(طلب\s*شراء|purchase\s*request)/ui')) {
            $id = self::extractId($message);
            if ($id > 0) {
                $actions[] = self::makeAction('cancel_purchase_request', ['id' => $id], $ctx, 'cancel_pr');
                $domains[] = 'procurement';
            } else {
                $recommendations[] = [
                    'message' => 'cancel_requires_purchase_request_id',
                    'domain' => 'procurement',
                ];
            }
        }

        if (self::match($message, '/(عدّل|عدل|حدّث|حدث|update).{0,40}(طلب\s*شراء|purchase\s*request)/ui')) {
            $id = self::extractId($message);
            if ($id > 0) {
                $args = ['id' => $id];
                $title = self::extractTitle($message);
                if ($title !== '') {
                    $args['title'] = $title;
                }
                $actions[] = self::makeAction('update_purchase_request', $args, $ctx, 'update_pr');
                $domains[] = 'procurement';
            } else {
                $recommendations[] = [
                    'message' => 'update_requires_purchase_request_id',
                    'domain' => 'procurement',
                ];
            }
        }

        // If write intent but no mapped supported action — recommendation only
        if ($actions === [] && $unsupported === [] && ErpOrchestrationPlanner::hasWriteIntent($message)) {
            $recommendations[] = [
                'message' => 'requested_action_not_mapped_to_registered_write_tool',
                'domain' => '',
                'class' => self::CLASS_RECOMMENDATION,
            ];
        }

        $requiresConfirmation = false;
        foreach ($actions as $a) {
            if (in_array($a['class'] ?? '', [self::CLASS_WRITE, self::CLASS_SENSITIVE_WRITE], true)) {
                $requiresConfirmation = true;
                break;
            }
        }

        return [
            'intent' => mb_substr(trim($message), 0, 240),
            'domains' => array_values(array_unique($domains)),
            'actions' => $actions,
            'unsupported' => $unsupported,
            'recommendations' => $recommendations,
            'requires_confirmation' => $requiresConfirmation,
            'evidence_first' => true,
            'auto_execute' => false,
        ];
    }

    /**
     * @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    private static function makeAction(string $tool, array $arguments, ProcurementAgentContext $ctx, string $purpose): array
    {
        $class = self::classifyTool($tool);
        $meta = ErpToolRegistry::getTool($tool)
            ?? AccountingToolRegistry::getTool($tool)
            ?? ProcurementToolRegistry::getTool($tool)
            ?? [];
        $domain = ErpToolRegistry::domainForTool($tool)
            ?? (string) ($meta['domain'] ?? ($tool === 'submit_journal_for_approval' ? 'accounting' : 'procurement'));
        $state = self::readStateSnapshot($tool, $arguments, $ctx);
        $confirmKey = self::confirmKey($tool, $arguments);

        return [
            'tool' => $tool,
            'arguments' => $arguments,
            'domain' => $domain,
            'module' => (string) ($meta['module'] ?? ($domain === 'accounting' ? 'accounting' : 'procurement')),
            'permission' => (string) ($meta['permission'] ?? ''),
            'class' => $class,
            'purpose' => $purpose,
            'confirm_key' => $confirmKey,
            'requires_confirmation' => in_array($class, [self::CLASS_WRITE, self::CLASS_SENSITIVE_WRITE], true),
            'requires_approval_workflow' => $class === self::CLASS_SENSITIVE_WRITE,
            'previous_state' => $state,
            'state_fingerprint' => self::fingerprint($state),
            'supported' => true,
        ];
    }

    public static function confirmKey(string $tool, array $arguments): string
    {
        return $tool . ':' . md5((string) json_encode($arguments, JSON_UNESCAPED_UNICODE));
    }

    /**
     * Re-validate state before WRITE. Returns allowed + error_code.
     *
     * @param array<string, mixed> $action
     * @return array{ok: bool, error_code: string|null, current_state: array|null, stale: bool}
     */
    public static function validateActionState(array $action, ProcurementAgentContext $ctx): array
    {
        $tool = (string) ($action['tool'] ?? '');
        $args = is_array($action['arguments'] ?? null) ? $action['arguments'] : [];
        $current = self::readStateSnapshot($tool, $args, $ctx);
        $expectedFp = (string) ($action['state_fingerprint'] ?? '');
        $currentFp = self::fingerprint($current);

        if (in_array($tool, ['update_purchase_request', 'cancel_purchase_request', 'submit_purchase_request'], true)) {
            $id = (int) ($args['id'] ?? 0);
            if ($id < 1) {
                return ['ok' => false, 'error_code' => 'invalid_pr_id', 'current_state' => $current, 'stale' => false];
            }
            if ($current === null || empty($current['exists'])) {
                return ['ok' => false, 'error_code' => 'pr_not_found', 'current_state' => $current, 'stale' => false];
            }
            $status = (string) ($current['status'] ?? '');
            if ($tool === 'update_purchase_request' && !in_array($status, ['draft', 'submitted', 'rejected'], true)) {
                return ['ok' => false, 'error_code' => 'pr_not_editable', 'current_state' => $current, 'stale' => false];
            }
            if ($tool === 'cancel_purchase_request') {
                if ($status === 'cancelled') {
                    return ['ok' => false, 'error_code' => 'pr_already_cancelled', 'current_state' => $current, 'stale' => false];
                }
                if (!in_array($status, ['draft', 'submitted', 'rejected'], true)) {
                    return ['ok' => false, 'error_code' => 'pr_not_cancelable', 'current_state' => $current, 'stale' => false];
                }
            }
            if ($tool === 'submit_purchase_request' && $status !== 'draft') {
                return ['ok' => false, 'error_code' => 'pr_not_draft', 'current_state' => $current, 'stale' => false];
            }
            if ($expectedFp !== '' && $currentFp !== '' && $expectedFp !== $currentFp) {
                return ['ok' => false, 'error_code' => 'stale_state', 'current_state' => $current, 'stale' => true];
            }
        }

        if ($tool === 'submit_journal_for_approval') {
            $id = (int) ($args['id'] ?? 0);
            if ($id < 1) {
                return ['ok' => false, 'error_code' => 'invalid_journal_id', 'current_state' => $current, 'stale' => false];
            }
            if ($current === null || empty($current['exists'])) {
                return ['ok' => false, 'error_code' => 'journal_not_found', 'current_state' => $current, 'stale' => false];
            }
            if ((string) ($current['status'] ?? '') !== 'draft') {
                return ['ok' => false, 'error_code' => 'journal_not_draft', 'current_state' => $current, 'stale' => false];
            }
            if ((string) ($current['source_type'] ?? '') !== 'manual') {
                return ['ok' => false, 'error_code' => 'journal_not_manual', 'current_state' => $current, 'stale' => false];
            }
            if (!empty($current['submitted_for_approval'])) {
                return ['ok' => false, 'error_code' => 'approval_already_submitted', 'current_state' => $current, 'stale' => false];
            }
            if ($expectedFp !== '' && $currentFp !== '' && $expectedFp !== $currentFp) {
                return ['ok' => false, 'error_code' => 'stale_state', 'current_state' => $current, 'stale' => true];
            }
        }

        return ['ok' => true, 'error_code' => null, 'current_state' => $current, 'stale' => false];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function readStateSnapshot(string $tool, array $args, ProcurementAgentContext $ctx): ?array
    {
        $companyId = (int) $ctx->companyId;
        if ($companyId < 1) {
            return null;
        }
        if ($tool === 'create_draft_purchase_request') {
            return ['exists' => false, 'entity' => 'purchase_request', 'status' => null];
        }
        $id = (int) ($args['id'] ?? 0);
        if ($id < 1) {
            return null;
        }
        if ($tool === 'submit_journal_for_approval') {
            try {
                $rows = (new \Rateb\App\Models\JournalEntry())->query(
                    'SELECT id, entry_no, status, source_type, submitted_for_approval_at, updated_at, company_id
                     FROM rateb_journal_entries WHERE id = :id AND company_id = :cid LIMIT 1',
                    ['id' => $id, 'cid' => $companyId]
                );
                $row = $rows[0] ?? null;
                if (!$row) {
                    return ['exists' => false, 'entity' => 'journal_entry', 'id' => $id];
                }
                return [
                    'exists' => true,
                    'entity' => 'journal_entry',
                    'id' => (int) $row['id'],
                    'entry_no' => (string) ($row['entry_no'] ?? ''),
                    'status' => (string) ($row['status'] ?? ''),
                    'source_type' => (string) ($row['source_type'] ?? ''),
                    'submitted_for_approval' => trim((string) ($row['submitted_for_approval_at'] ?? '')) !== '',
                    'updated_at' => (string) ($row['updated_at'] ?? ''),
                ];
            } catch (\Throwable $e) {
                return null;
            }
        }
        try {
            $rows = (new PurchaseRequest())->query(
                'SELECT id, request_no, title, status, updated_at, total_estimated, company_id
                 FROM rateb_purchase_requests WHERE id = :id AND company_id = :cid LIMIT 1',
                ['id' => $id, 'cid' => $companyId]
            );
            $row = $rows[0] ?? null;
            if (!$row) {
                return ['exists' => false, 'entity' => 'purchase_request', 'id' => $id];
            }
            return [
                'exists' => true,
                'entity' => 'purchase_request',
                'id' => (int) $row['id'],
                'request_no' => (string) ($row['request_no'] ?? ''),
                'title' => (string) ($row['title'] ?? ''),
                'status' => (string) ($row['status'] ?? ''),
                'updated_at' => (string) ($row['updated_at'] ?? ''),
                'total_estimated' => (float) ($row['total_estimated'] ?? 0),
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * @param array<string, mixed>|null $state
     */
    public static function fingerprint(?array $state): string
    {
        if ($state === null) {
            return '';
        }
        return md5((string) json_encode([
            'id' => $state['id'] ?? null,
            'status' => $state['status'] ?? null,
            'updated_at' => $state['updated_at'] ?? null,
            'exists' => $state['exists'] ?? null,
            'submitted_for_approval' => $state['submitted_for_approval'] ?? null,
            'source_type' => $state['source_type'] ?? null,
        ], JSON_UNESCAPED_UNICODE));
    }

    /**
     * Verify WRITE succeeded by re-reading live state.
     *
     * @param array<string, mixed> $action
     * @param array<string, mixed> $execResult
     * @return array{verified: bool, incomplete: bool, new_state: array|null, message: string}
     */
    public static function verifyAction(array $action, array $execResult, ProcurementAgentContext $ctx): array
    {
        $tool = (string) ($action['tool'] ?? '');
        if (empty($execResult['success'])) {
            return [
                'verified' => false,
                'incomplete' => false,
                'new_state' => null,
                'message' => 'execution_failed',
            ];
        }

        if ($tool === 'create_draft_purchase_request') {
            $id = (int) ($execResult['data']['id'] ?? 0);
            if ($id < 1) {
                return ['verified' => false, 'incomplete' => true, 'new_state' => null, 'message' => 'verification_incomplete_missing_id'];
            }
            $state = self::readStateSnapshot('update_purchase_request', ['id' => $id], $ctx);
            $ok = is_array($state) && !empty($state['exists']) && (string) ($state['status'] ?? '') === 'draft';
            return [
                'verified' => $ok,
                'incomplete' => !$ok,
                'new_state' => $state,
                'message' => $ok ? 'verified_draft_created' : 'verification_incomplete',
            ];
        }

        $id = (int) ($action['arguments']['id'] ?? $execResult['data']['id'] ?? 0);
        $state = self::readStateSnapshot($tool, ['id' => $id], $ctx);
        if ($state === null) {
            return ['verified' => false, 'incomplete' => true, 'new_state' => null, 'message' => 'verification_incomplete'];
        }

        $status = (string) ($state['status'] ?? '');
        $ok = match ($tool) {
            'cancel_purchase_request' => $status === 'cancelled',
            'submit_purchase_request' => in_array($status, ['submitted', 'pending', 'in_approval'], true) || $status !== 'draft',
            'update_purchase_request' => !empty($state['exists']),
            'submit_journal_for_approval' => !empty($state['exists'])
                && $status === 'draft'
                && !empty($state['submitted_for_approval']),
            default => !empty($execResult['success']),
        };

        return [
            'verified' => $ok,
            'incomplete' => !$ok,
            'new_state' => $state,
            'message' => $ok ? 'verified' : 'verification_incomplete',
        ];
    }

    public static function wasAlreadyExecuted(ProcurementAgentContext $ctx, string $requestId, string $confirmKey): bool
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
                   AND tool_name IN (
                     'create_draft_purchase_request','update_purchase_request','cancel_purchase_request','submit_purchase_request',
                     'submit_journal_for_approval'
                   )
                 ORDER BY id DESC LIMIT 30"
            );
            $stmt->execute([
                'cid' => $ctx->companyId,
                'uid' => $ctx->userId,
                'rid' => $requestId,
            ]);
            foreach ($stmt->fetchAll() as $row) {
                $input = json_decode((string) ($row['input_json'] ?? ''), true);
                if (is_array($input) && (string) ($input['_idempotency_key'] ?? '') === $confirmKey) {
                    return true;
                }
            }
        } catch (\Throwable $e) {
            return false;
        }
        return false;
    }

    /**
     * @param array<string, mixed>|null $intelligence
     * @return array<string, mixed>|null
     */
    private static function suggestLineFromIntelligence(?array $intelligence): ?array
    {
        if (!is_array($intelligence)) {
            return null;
        }
        foreach (($intelligence['findings'] ?? []) as $f) {
            if (!is_array($f)) {
                continue;
            }
            $code = (string) ($f['code'] ?? '');
            if (!str_contains($code, 'shortfall') && !str_contains($code, 'low_stock')) {
                continue;
            }
            $ev = is_array($f['evidence'] ?? null) ? $f['evidence'] : [];
            $name = (string) ($ev['item_name'] ?? $ev['item_code'] ?? $f['finding'] ?? '');
            if ($name === '') {
                continue;
            }
            $qty = (float) ($ev['shortfall_qty'] ?? $ev['quantity'] ?? 1);
            if ($qty <= 0) {
                $qty = 1;
            }
            return [
                'item_name' => mb_substr($name, 0, 190),
                'quantity' => $qty,
                'unit_price' => 0,
                'unit' => 'pcs',
            ];
        }
        return null;
    }

    private static function extractId(string $message): int
    {
        if (preg_match('/(?:#|id\s*[:=]?\s*|رقم\s*)(\d{1,10})/ui', $message, $m)) {
            return (int) $m[1];
        }
        if (preg_match('/\b(\d{2,10})\b/u', $message, $m)) {
            return (int) $m[1];
        }
        return 0;
    }

    private static function extractTitle(string $message): string
    {
        if (preg_match('/(?:title|عنوان)\s*[:=]\s*[\"\']?([^\"\'\n]{3,120})/ui', $message, $m)) {
            return trim($m[1]);
        }
        if (preg_match('/طلب\s*شراء(?:\s*ل)?\s*(.+)$/u', $message, $m)) {
            $t = trim($m[1]);
            $t = preg_replace('/[؟?].*$/u', '', $t) ?? $t;
            return mb_substr(trim($t), 0, 120);
        }
        return '';
    }

    private static function isAr(ProcurementAgentContext $ctx): bool
    {
        return $ctx->normalizedLocale() === 'ar';
    }

    private static function match(string $message, string $pattern): bool
    {
        return $message !== '' && preg_match($pattern, $message) === 1;
    }

    /**
     * Format action response AR/EN.
     *
     * @param array<string, mixed> $plan
     * @param list<array<string, mixed>> $results
     * @param list<array<string, mixed>> $pending
     */
    public static function formatActionResponse(
        array $plan,
        array $results,
        array $pending,
        ProcurementAgentContext $ctx,
        bool $partial = false
    ): string {
        $ar = self::isAr($ctx);
        $lines = [];

        if ($pending !== []) {
            $lines[] = $ar
                ? 'خطة إجراء تتطلب تأكيداً قبل التنفيذ (التوصية ليست تنفيذاً):'
                : 'Action plan requires confirmation before execution (recommendation is not execution):';
            foreach ($pending as $i => $p) {
                $n = $i + 1;
                $summary = (string) (($p['impact_preview']['summary'] ?? null) ?: ($p['tool'] ?? ''));
                $lines[] = $ar
                    ? "{$n}) {$summary} — الصلاحية: " . (string) ($p['permission'] ?? '') . " — التأكيد مطلوب"
                    : "{$n}) {$summary} — permission: " . (string) ($p['permission'] ?? '') . " — confirmation required";
            }
        }

        if ($results !== []) {
            $lines[] = $partial
                ? ($ar ? 'تنفيذ جزئي (تم إيقاف السلسلة بعد فشل/إيقاف):' : 'Partial execution (chain stopped after failure/stop):')
                : ($ar ? 'نتائج التنفيذ:' : 'Execution results:');
            foreach ($results as $i => $r) {
                $n = $i + 1;
                $ok = !empty($r['success']);
                $ver = is_array($r['verification'] ?? null) ? $r['verification'] : [];
                $lines[] = $ar
                    ? "{$n}) " . (string) ($r['tool'] ?? '') . ' → ' . ($ok ? 'نجاح' : 'فشل')
                    : "{$n}) " . (string) ($r['tool'] ?? '') . ' → ' . ($ok ? 'success' : 'failed');
                if (!empty($ver['incomplete'])) {
                    $lines[] = $ar
                        ? '   التحقق غير مكتمل — لا يُعلن نجاحاً نهائياً.'
                        : '   Verification incomplete — not declared as final PASS.';
                } elseif (!empty($ver['verified'])) {
                    $lines[] = $ar ? '   تم التحقق من الحالة بعد التنفيذ.' : '   Post-action state verified.';
                }
                if (!empty($r['error_code'])) {
                    $lines[] = ($ar ? '   خطأ: ' : '   Error: ') . (string) $r['error_code'];
                }
            }
        }

        foreach (($plan['unsupported'] ?? []) as $u) {
            if (!is_array($u)) {
                continue;
            }
            $lines[] = $ar
                ? 'عملية غير متاحة عبر الوكيل: ' . (string) ($u['requested'] ?? '') . ' (' . (string) ($u['reason'] ?? '') . ')'
                : 'Action unavailable via agent: ' . (string) ($u['requested'] ?? '') . ' (' . (string) ($u['reason'] ?? '') . ')';
        }
        foreach (($plan['recommendations'] ?? []) as $rec) {
            if (!is_array($rec)) {
                continue;
            }
            $lines[] = ($ar ? 'توصية (بدون تنفيذ): ' : 'Recommendation (no write): ') . (string) ($rec['message'] ?? '');
        }

        if ($lines === []) {
            $lines[] = $ar
                ? 'لا يوجد إجراء كتابة مدعوم لهذا الطلب.'
                : 'No supported write action for this request.';
        }

        $lines[] = $ar
            ? 'ملاحظة: لا تنفيذ تلقائي. كل كتابة تمر عبر الصلاحية والسياسة والتأكيد والتدقيق.'
            : 'Note: no auto-execution. Every write requires authorization, policy, confirmation, and audit.';

        return implode("\n", $lines);
    }
}
