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

    /**
     * Build a write action from a conversational draft (public for continuation).
     *
     * @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    public static function actionFromDraft(string $tool, array $arguments, ProcurementAgentContext $ctx, string $purpose): array
    {
        return self::makeAction($tool, $arguments, $ctx, $purpose);
    }

    public static function isConfirmationPhrase(string $message): bool
    {
        $m = trim($message);
        if ($m === '') {
            return false;
        }
        // Short confirmations only — avoid matching long "أنشئ طلب شراء ..." as bare confirm
        if (mb_strlen($m) > 40) {
            return false;
        }
        return self::match(
            $m,
            '/^(نعم|موافق|تأكيد|أكد|اكد|أكمل|اكمل|نفذ|نفّذ|انشئ|أنشئ|انشاء|إنشاء|تنفيذ|confirm|yes|ok|okay|go|proceed|do\s*it|create|execute)$/ui'
        ) || self::match($m, '/^(نعم[,.]?\s*)?(انشئ|أنشئ|نفذ|نفّذ|أكمل|اكمل|موافق|تأكيد)\s*!*$/ui');
    }

    public static function isRejectionPhrase(string $message): bool
    {
        $m = trim($message);
        if ($m === '' || mb_strlen($m) > 40) {
            return false;
        }
        return self::match($m, '/^(لا|الغاء|إلغاء|توقف|ألغ|الغ|cancel|no|stop|abort|nevermind|never\s*mind)$/ui');
    }

    public static function isCreatePurchaseRequestIntent(string $message): bool
    {
        return self::match(
            $message,
            '/(أنشئ|إنشاء|انشئ|اعمل|اعملوا|سو[يى]|create|make).{0,40}(طلب\s*شراء|purchase\s*request)|طلب\s*شراء|purchase\s*request/ui'
        );
    }

    /**
     * Session-backed pending action/confirmation state (tenant+user scoped key).
     *
     * @return array<string, mixed>
     */
    public static function loadPendingState(string $scopeKey): array
    {
        if ($scopeKey === '') {
            return [];
        }
        $raw = \Rateb\App\Core\SessionManager::get('rateb_ai_pending_' . md5($scopeKey), null);
        return is_array($raw) ? $raw : [];
    }

    /**
     * @param array<string, mixed> $state
     */
    public static function savePendingState(string $scopeKey, array $state): void
    {
        if ($scopeKey === '') {
            return;
        }
        $state['updated_at'] = date('Y-m-d H:i:s');
        \Rateb\App\Core\SessionManager::set('rateb_ai_pending_' . md5($scopeKey), $state);
    }

    public static function clearPendingState(string $scopeKey): void
    {
        if ($scopeKey === '') {
            return;
        }
        \Rateb\App\Core\SessionManager::set('rateb_ai_pending_' . md5($scopeKey), null);
    }

    /**
     * Extract / merge purchase-request draft fields from a user utterance.
     * Current message wins over any prior draft values when it supplies a field.
     *
     * @param array<string, mixed> $draft
     * @return array<string, mixed>
     */
    public static function mergePurchaseRequestDraft(string $message, array $draft = []): array
    {
        $args = is_array($draft['arguments'] ?? null) ? $draft['arguments'] : [];
        $notes = (string) ($args['notes'] ?? '');
        $dryRun = !empty($draft['dry_run'])
            || self::match(
                $message,
                '/(مثال\s*فقط|ليس\s*حقيق|مو\s*حقيق|ليس\s*للتنفيذ|dry\s*run|test\s*only|not\s*real|example\s*only)/ui'
            );
        // "طلب شراء تجريبي" = real draft write — never treat تجريبي alone as dry-run skip

        // Priority — current message wins (بأولوية متوسطة / عاجل / …)
        if (self::match($message, '/(?:بأولوية|اولوية|أولوية|priority)\s*(عاجل|عالية|عالي|high|urgent)/ui')
            || self::match($message, '/(عاجل|high|urgent)/ui')
        ) {
            $args['priority'] = 'high';
        } elseif (self::match($message, '/(?:بأولوية|اولوية|أولوية|priority)\s*(متوسط|متوسطة|متوسطه|medium)/ui')
            || self::match($message, '/(متوسط|متوسطة|متوسطه|medium)/ui')
        ) {
            $args['priority'] = 'medium';
        } elseif (self::match($message, '/(?:بأولوية|اولوية|أولوية|priority)\s*(منخفض|منخفضة|منخفضه|low)/ui')
            || self::match($message, '/(منخفض|منخفضة|منخفضه|low)/ui')
        ) {
            $args['priority'] = 'low';
        }

        // Department / cost center
        if (preg_match('/(?:قسم|department)\s*[=:]?\s*([^\n,]+)/ui', $message, $m) === 1) {
            $dept = trim($m[1]);
            $args['department'] = mb_substr($dept, 0, 120);
            $notes = trim($notes . "\nDepartment: " . $dept);
            $draft['department'] = $dept;
        } elseif (preg_match('/قسم\s+(المشتريات|مشتريات)/ui', $message) === 1) {
            $args['department'] = 'المشتريات';
            $notes = trim($notes . "\nDepartment: المشتريات");
            $draft['department'] = 'المشتريات';
        }

        // Qty + unit + item — "10 وحدات من بطاطس" / "بطاطس 66"
        $item = null;
        $qty = null;
        $unit = 'unit';
        $msgTrim = trim($message);
        $msgForItem = preg_replace('/\s*(?:قسم|department)\s*[^\n,]*/ui', '', $msgTrim) ?? $msgTrim;
        $msgForItem = preg_replace('/\s*(?:بأولوية|اولوية|أولوية|priority)\s*\S+/ui', '', $msgForItem) ?? $msgForItem;
        $msgForItem = preg_replace('/\b(المشتريات|مشتريات)\b/ui', '', $msgForItem) ?? $msgForItem;
        $msgForItem = trim(preg_replace('/\s+/u', ' ', (string) $msgForItem) ?? '');
        $unitAlt = 'وحدات|وحدة|unit|units|pcs?|pieces?|قطعة|قطع|each|ea';
        $unitBlock = '/^(وحدات|وحدة|unit|units|pcs|piece|pieces|قطعة|قطع|each|ea|متوسط|متوسطة|متوسطه|عاجل|منخفض|طلب|شراء|مواد|department|قسم)$/ui';

        if (preg_match('/(\d+(?:\.\d+)?)\s*(?:' . $unitAlt . ')?\s*(?:من\s+)?([\p{L}]{2,40})\b/ui', $msgForItem, $m) === 1
            && !self::match($m[2], $unitBlock)
        ) {
            $qty = (float) $m[1];
            $item = trim($m[2]);
            if (preg_match('/\d+(?:\.\d+)?\s*(' . $unitAlt . ')/ui', $msgForItem, $um) === 1) {
                $unitRaw = mb_strtolower(trim($um[1]));
                $unit = in_array($unitRaw, ['وحدات', 'وحدة', 'قطعة', 'قطع', 'ea', 'each', 'pcs', 'piece', 'pieces'], true)
                    ? 'unit'
                    : mb_substr($unitRaw, 0, 30);
            }
        } elseif (preg_match('/([\p{L}]{2,40})\s+(\d+(?:\.\d+)?)\s*$/u', $msgForItem, $m) === 1
            && !self::match($m[1], $unitBlock)
        ) {
            $item = trim($m[1]);
            $qty = (float) $m[2];
        }

        if ($item !== null && $qty !== null && $qty > 0
            && !self::match($item, '/^(نعم|لا|انشئ|أنشئ|موافق|تأكيد|تجريبي)$/ui')
        ) {
            $args['line_items'] = [[
                'item_name' => $item,
                'description' => $item,
                'quantity' => $qty,
                'unit' => $unit !== '' && $unit !== 'unit' ? $unit : 'each',
                'unit_price' => 0,
                'tax_rate' => 15,
                'tax_name' => 'VAT 15%',
                'excluding_tax' => 1,
            ]];
        }

        // Title — prefer item-based; avoid "شراء تجريبي لشراء …"
        if (preg_match('/(?:عنوان|title)\s*[=:]?\s*(.+)$/ui', $message, $m) === 1) {
            $args['title'] = mb_substr(trim($m[1]), 0, 120);
        } elseif ($item !== null && $item !== '') {
            $args['title'] = 'شراء ' . $item;
        } elseif (self::match($message, '/طلب\s*شراء\s*تجريبي/ui')) {
            $args['title'] = 'طلب شراء تجريبي';
        } elseif (self::match($message, '/شراء\s+مواد/ui')) {
            $args['title'] = 'شراء مواد';
        } elseif (empty($args['title']) && self::isCreatePurchaseRequestIntent($message)) {
            $t = self::extractTitle($message);
            if ($t !== '' && !self::match($t, '/^تجريبي\s*لشراء/ui')) {
                $args['title'] = mb_substr($t, 0, 120);
            } else {
                $args['title'] = 'طلب شراء';
            }
        }

        // Supplier name as note only (never invent supplier_id)
        if (preg_match('/(?:مورد|supplier)\s*[=:]?\s*(.+)$/ui', $message, $m) === 1) {
            $notes = trim($notes . "\nSupplier hint: " . trim($m[1]));
            $draft['supplier_hint'] = trim($m[1]);
        } elseif (self::match($message, '/(روح\s*من\s*عندك|من\s*عندك|مثال)/ui') && $dryRun) {
            $notes = trim($notes . "\nSupplier hint: test example (not real)");
            $draft['supplier_hint'] = 'test_example';
        }

        // Free-form person names mid-collection → notes (not fake supplier IDs)
        if (!empty($draft['collecting']) && preg_match('/^[\p{L}\s]{3,40}$/u', trim($message)) === 1
            && !self::isConfirmationPhrase($message)
            && !self::isRejectionPhrase($message)
            && !self::isCreatePurchaseRequestIntent($message)
            && empty($item)
        ) {
            $notes = trim($notes . "\nContact/name: " . trim($message));
        }

        if ($dryRun) {
            // Soft note only — still creates a real draft; never invents supplier_id
            $notes = trim($notes . "\n[test_draft_note]");
        }
        if ($notes !== '') {
            $args['notes'] = mb_substr($notes, 0, 500);
        }
        if (empty($args['priority'])) {
            $args['priority'] = 'medium';
        }
        if (empty($args['title'])) {
            $args['title'] = $item !== null && $item !== '' ? ('شراء ' . $item) : 'طلب شراء';
        }

        $missing = [];
        if (empty($args['title'])) {
            $missing[] = 'title';
        }

        $draft['tool'] = 'create_draft_purchase_request';
        $draft['arguments'] = $args;
        // Keep flag for notes/UX only — Confirm always executes real tool
        $draft['dry_run'] = false;
        $draft['test_draft_note'] = $dryRun || !empty($draft['test_draft_note']);
        $draft['missing'] = $missing;
        $draft['collecting'] = true;
        $draft['ready'] = $missing === [];

        return $draft;
    }

    /** True when the utterance itself carries qty/item/priority (complete enough to replace prior draft). */
    public static function messageHasPurchaseParams(string $message): bool
    {
        return self::match($message, '/\d+/u')
            && (
                self::match($message, '/(?:من\s+)?[\p{L}]{2,40}/u')
                || self::match($message, '/(بطاطس|أرز|رز|مواد|صنف)/ui')
            );
    }

    /**
     * Resolve confirmation / rejection / draft continuation for a chat turn.
     *
     * @param list<array{role:string,content:string}> $history
     * @param array<string, mixed> $pending
     * @param list<string> $confirmedWrites
     * @return array{
     *     mode: string,
     *     pending: array<string,mixed>,
     *     confirmed_writes: list<string>,
     *     action_plan: array<string,mixed>|null,
     *     clear_pending: bool,
     *     response: string|null
     * }
     */
    public static function resolveConversationTurn(
        string $message,
        ProcurementAgentContext $ctx,
        array $history,
        array $pending,
        array $confirmedWrites
    ): array {
        $ar = self::isAr($ctx);
        $pending = is_array($pending) ? $pending : [];
        $confirmedWrites = array_values(array_filter(array_map('strval', $confirmedWrites), static fn($k) => $k !== ''));

        // Tenant isolation on pending action
        if (!empty($pending['company_id']) && (int) $pending['company_id'] !== (int) $ctx->companyId) {
            return [
                'mode' => 'stale',
                'pending' => [],
                'confirmed_writes' => [],
                'action_plan' => null,
                'clear_pending' => true,
                'response' => $ar
                    ? 'تعذر تأكيد إجراء من شركة أخرى.'
                    : 'Cannot confirm an action from another company.',
            ];
        }

        $executedPrior = is_array($pending['executed_keys'] ?? null) ? $pending['executed_keys'] : [];
        if ($confirmedWrites !== [] && $executedPrior !== [] && array_intersect($confirmedWrites, $executedPrior) !== []) {
            return [
                'mode' => 'already_executed',
                'pending' => [],
                'confirmed_writes' => [],
                'action_plan' => null,
                'clear_pending' => true,
                'response' => $ar
                    ? 'تم تنفيذ هذا الإجراء مسبقاً. لم يُنشأ سجل مكرر.'
                    : 'This action was already executed. No duplicate record was created.',
            ];
        }
        if (self::isConfirmationPhrase($message) && $executedPrior !== [] && empty($pending['confirmations'])) {
            return [
                'mode' => 'already_executed',
                'pending' => [],
                'confirmed_writes' => [],
                'action_plan' => null,
                'clear_pending' => true,
                'response' => $ar
                    ? 'تم تنفيذ هذا الإجراء مسبقاً. لم يُنشأ سجل مكرر.'
                    : 'This action was already executed. No duplicate record was created.',
            ];
        }

        // Explicit confirmed_writes from UI button → execute pending snapshot (never replan)
        if ($confirmedWrites !== [] && !empty($pending['confirmations']) && is_array($pending['confirmations'])) {
            $pendingKeys = [];
            foreach ($pending['confirmations'] as $c) {
                if (is_array($c) && (string) ($c['confirm_key'] ?? '') !== '') {
                    $pendingKeys[] = (string) $c['confirm_key'];
                }
            }
            $matched = array_values(array_intersect($confirmedWrites, $pendingKeys));
            if ($matched === [] && $pendingKeys !== []) {
                return [
                    'mode' => 'stale',
                    'pending' => $pending,
                    'confirmed_writes' => [],
                    'action_plan' => null,
                    'clear_pending' => false,
                    'response' => $ar
                        ? 'تغيّرت حالة الإجراء المعلق. أعد التخطيط ثم أكّد من جديد.'
                        : 'Pending action is stale. Please replan and confirm again.',
                ];
            }
            if ($matched !== []) {
                $executed = is_array($pending['executed_keys'] ?? null) ? $pending['executed_keys'] : [];
                if (array_intersect($matched, $executed) !== []) {
                    return [
                        'mode' => 'already_executed',
                        'pending' => [],
                        'confirmed_writes' => [],
                        'action_plan' => null,
                        'clear_pending' => true,
                        'response' => $ar
                            ? 'تم تنفيذ هذا الإجراء مسبقاً. لم يُنشأ سجل مكرر.'
                            : 'This action was already executed. No duplicate record was created.',
                    ];
                }
                return self::buildConfirmTurn($pending, $matched, $ctx, $ar);
            }
        }

        // Rejection of pending proposal
        if (self::isRejectionPhrase($message) && (
            !empty($pending['confirmations']) || !empty($pending['draft'])
        )) {
            return [
                'mode' => 'reject',
                'pending' => [],
                'confirmed_writes' => [],
                'action_plan' => null,
                'clear_pending' => true,
                'response' => $ar
                    ? 'تم إلغاء الإجراء المعلق. لم يتم تنفيذ أي كتابة.'
                    : 'Pending action cancelled. No write was executed.',
            ];
        }

        // Confirmation phrase of pending proposal
        if (self::isConfirmationPhrase($message) && !empty($pending['confirmations']) && is_array($pending['confirmations'])) {
            $executed = is_array($pending['executed_keys'] ?? null) ? $pending['executed_keys'] : [];
            $keys = [];
            foreach ($pending['confirmations'] as $c) {
                if (is_array($c) && (string) ($c['confirm_key'] ?? '') !== '') {
                    $keys[] = (string) $c['confirm_key'];
                }
            }
            if ($keys !== [] && array_intersect($keys, $executed) !== []) {
                return [
                    'mode' => 'already_executed',
                    'pending' => [],
                    'confirmed_writes' => [],
                    'action_plan' => null,
                    'clear_pending' => true,
                    'response' => $ar
                        ? 'تم تنفيذ هذا الإجراء مسبقاً. لم يُنشأ سجل مكرر.'
                        : 'This action was already executed. No duplicate record was created.',
                ];
            }
            return self::buildConfirmTurn($pending, array_values(array_unique(array_merge($confirmedWrites, $keys))), $ctx, $ar);
        }

        // Re-surface existing proposal (prevent repeated replan on unrelated chatter)
        if (($pending['phase'] ?? '') === 'awaiting_confirmation'
            && !empty($pending['action_plan'])
            && !empty($pending['confirmations'])
            && !self::isCreatePurchaseRequestIntent($message)
            && !self::isConfirmationPhrase($message)
            && !self::isRejectionPhrase($message)
            && $confirmedWrites === []
        ) {
            // Allow marking controlled test while proposal is open
            if (self::match($message, '/(مثال\s*فقط|ليس\s*حقيق|مو\s*حقيق|ليس\s*للتنفيذ|dry\s*run|test\s*only|not\s*real|example\s*only)/ui')) {
                if (!is_array($pending['draft'] ?? null)) {
                    $pending['draft'] = [];
                }
                // Metadata only — confirm still executes real create_draft_purchase_request
                $pending['draft']['dry_run_note'] = true;
                $args = is_array($pending['draft']['arguments'] ?? null) ? $pending['draft']['arguments'] : [];
                $notes = trim((string) ($args['notes'] ?? '') . "\n[test_draft_note]");
                $args['notes'] = mb_substr($notes, 0, 500);
                $pending['draft']['arguments'] = $args;
            }
            return [
                'mode' => 'propose',
                'pending' => $pending,
                'confirmed_writes' => [],
                'action_plan' => is_array($pending['action_plan']) ? $pending['action_plan'] : null,
                'clear_pending' => false,
                'response' => self::formatProposalResponse($pending, $ar),
            ];
        }

            // Start / continue PR draft collection
        $hasDraft = !empty($pending['draft']['collecting']);
        if ($hasDraft || self::isCreatePurchaseRequestIntent($message)) {
            $prevKey = (string) ($pending['confirmations'][0]['confirm_key'] ?? '');
            $prevActionId = (string) ($pending['action_id'] ?? '');
            $prevExecuted = is_array($pending['executed_keys'] ?? null) ? $pending['executed_keys'] : [];

            // New create intent with params (or replacing an awaiting proposal) → current request wins
            $freshStart = self::isCreatePurchaseRequestIntent($message)
                && (
                    self::messageHasPurchaseParams($message)
                    || ($pending['phase'] ?? '') === 'awaiting_confirmation'
                    || !empty($pending['confirmations'])
                );
            if ($freshStart) {
                $draft = self::mergePurchaseRequestDraft($message, []);
            } else {
                $draft = self::mergePurchaseRequestDraft(
                    $message,
                    is_array($pending['draft'] ?? null) ? $pending['draft'] : []
                );
                // Merge history only while collecting incomplete crumbs — never onto a fresh complete request
                if (empty($draft['ready']) || empty($draft['arguments']['line_items'])) {
                    foreach (array_reverse($history) as $msg) {
                        if (!is_array($msg) || ($msg['role'] ?? '') !== 'user') {
                            continue;
                        }
                        $content = trim((string) ($msg['content'] ?? ''));
                        if ($content === '' || $content === $message) {
                            continue;
                        }
                        if (self::isConfirmationPhrase($content) || self::isRejectionPhrase($content)) {
                            continue;
                        }
                        if (self::isCreatePurchaseRequestIntent($content) && self::messageHasPurchaseParams($content)) {
                            continue; // do not leak prior complete requests
                        }
                        $draft = self::mergePurchaseRequestDraft($content, $draft);
                    }
                }
            }

            $pending = [
                'phase' => 'collecting',
                'intent' => 'create_draft_purchase_request',
                'draft' => $draft,
                'company_id' => (int) $ctx->companyId,
                'action_id' => ($freshStart || $prevActionId === '')
                    ? ('act_' . bin2hex(random_bytes(6)))
                    : $prevActionId,
                'executed_keys' => $freshStart ? [] : $prevExecuted,
            ];

            if (empty($draft['ready'])) {
                $ask = [];
                if (in_array('title', $draft['missing'] ?? [], true)) {
                    $ask[] = $ar ? 'عنوان طلب الشراء' : 'purchase request title';
                }
                return [
                    'mode' => 'collect',
                    'pending' => $pending,
                    'confirmed_writes' => $confirmedWrites,
                    'action_plan' => null,
                    'clear_pending' => false,
                    'response' => $ar
                        ? ('أكمل بيانات طلب الشراء. المتبقي: ' . implode('، ', $ask)
                            . (empty($draft['arguments']['line_items']) ? ' (اختياري: الصنف والكمية)' : '')
                            . '.')
                        : ('Continue the purchase request. Still needed: ' . implode(', ', $ask)
                            . (empty($draft['arguments']['line_items']) ? ' (optional: item and quantity)' : '')
                            . '.'),
                ];
            }

            // Ready → propose snapshot (do not execute yet)
            $action = self::makeAction(
                'create_draft_purchase_request',
                $draft['arguments'],
                $ctx,
                'create_pr_from_conversation'
            );
            $action['action_id'] = (string) $pending['action_id'];
            $plan = [
                'intent' => 'create_draft_purchase_request',
                'domains' => ['procurement'],
                'actions' => [$action],
                'unsupported' => [],
                'recommendations' => [],
                'requires_confirmation' => true,
                'evidence_first' => true,
                'auto_execute' => false,
                'from_conversation' => true,
                'action_id' => (string) $pending['action_id'],
                'parameter_snapshot' => [
                    'title' => (string) ($action['arguments']['title'] ?? ''),
                    'priority' => (string) ($action['arguments']['priority'] ?? 'medium'),
                    'line_items' => is_array($action['arguments']['line_items'] ?? null) ? $action['arguments']['line_items'] : [],
                ],
            ];

            $pending['phase'] = 'awaiting_confirmation';
            $pending['confirmations'] = [[
                'tool' => $action['tool'],
                'confirm_key' => $action['confirm_key'],
                'arguments' => $action['arguments'],
                'class' => $action['class'],
                'action_id' => (string) $pending['action_id'],
            ]];
            $pending['action_plan'] = $plan;
            $pending['parameter_snapshot'] = $plan['parameter_snapshot'];

            return [
                'mode' => 'propose',
                'pending' => $pending,
                'confirmed_writes' => $confirmedWrites,
                'action_plan' => $plan,
                'clear_pending' => false,
                'response' => self::formatProposalResponse($pending, $ar),
                'same_snapshot' => ($prevKey !== '' && $prevKey === (string) $action['confirm_key']),
            ];
        }

        return [
            'mode' => 'passthrough',
            'pending' => $pending,
            'confirmed_writes' => $confirmedWrites,
            'action_plan' => null,
            'clear_pending' => false,
            'response' => null,
        ];
    }

    /**
     * @param array<string, mixed> $pending
     * @param list<string> $keys
     * @return array<string, mixed>
     */
    private static function buildConfirmTurn(array $pending, array $keys, ProcurementAgentContext $ctx, bool $ar): array
    {
        // Confirm always executes the pending snapshot via ActionPlanner/Governance.
        // dry_run is metadata only (no fake supplier ids) — never a controlled_test substitute.
        $plan = null;
        if (!empty($pending['action_plan']) && is_array($pending['action_plan'])) {
            $plan = $pending['action_plan'];
            // Strip dry-run recommendation noise from user-facing plan copy
            if (isset($plan['recommendations']) && is_array($plan['recommendations'])) {
                $plan['recommendations'] = array_values(array_filter(
                    $plan['recommendations'],
                    static function ($rec): bool {
                        if (!is_array($rec)) {
                            return false;
                        }
                        $msg = (string) ($rec['message'] ?? '');
                        return $msg !== '' && !str_contains($msg, 'controlled_test');
                    }
                ));
            }
        } elseif (!empty($pending['draft']['ready']) || !empty($pending['parameter_snapshot'])) {
            $args = is_array($pending['draft']['arguments'] ?? null)
                ? $pending['draft']['arguments']
                : [];
            if ($args === [] && is_array($pending['parameter_snapshot'] ?? null)) {
                $snap = $pending['parameter_snapshot'];
                $args = [
                    'title' => (string) ($snap['title'] ?? 'طلب شراء'),
                    'priority' => (string) ($snap['priority'] ?? 'medium'),
                    'line_items' => is_array($snap['line_items'] ?? null) ? $snap['line_items'] : [],
                ];
            }
            $action = self::makeAction(
                'create_draft_purchase_request',
                $args,
                $ctx,
                'create_pr_from_conversation'
            );
            $action['action_id'] = (string) ($pending['action_id'] ?? '');
            $plan = [
                'intent' => (string) ($pending['intent'] ?? 'create_draft_purchase_request'),
                'domains' => ['procurement'],
                'actions' => [$action],
                'unsupported' => [],
                'recommendations' => [],
                'requires_confirmation' => true,
                'evidence_first' => true,
                'auto_execute' => false,
                'from_conversation' => true,
                'action_id' => (string) ($pending['action_id'] ?? ''),
                'parameter_snapshot' => is_array($pending['parameter_snapshot'] ?? null)
                    ? $pending['parameter_snapshot']
                    : [],
            ];
        }

        return [
            'mode' => 'confirm',
            'pending' => $pending,
            'confirmed_writes' => array_values(array_unique($keys)),
            'action_plan' => $plan,
            'clear_pending' => false,
            'response' => null,
        ];
    }

    /**
     * @param array<string, mixed> $pending
     */
    private static function formatProposalResponse(array $pending, bool $ar): string
    {
        $snap = is_array($pending['parameter_snapshot'] ?? null)
            ? $pending['parameter_snapshot']
            : (is_array($pending['action_plan']['parameter_snapshot'] ?? null)
                ? $pending['action_plan']['parameter_snapshot']
                : []);
        $args = is_array($pending['action_plan']['actions'][0]['arguments'] ?? null)
            ? $pending['action_plan']['actions'][0]['arguments']
            : (is_array($pending['draft']['arguments'] ?? null) ? $pending['draft']['arguments'] : []);
        $summary = (string) ($snap['title'] ?? $args['title'] ?? '');
        $prioRaw = (string) ($snap['priority'] ?? $args['priority'] ?? 'medium');
        $prio = self::labelPriority($prioRaw, $ar);
        $lines = is_array($snap['line_items'] ?? null) ? $snap['line_items']
            : (is_array($args['line_items'] ?? null) ? $args['line_items'] : []);
        $lineTxt = '';
        if ($lines !== [] && is_array($lines[0])) {
            $name = (string) ($lines[0]['item_name'] ?? $lines[0]['description'] ?? '');
            $qty = (string) ($lines[0]['quantity'] ?? '');
            $lineTxt = $ar
                ? ("\n- الصنف: {$name}\n- الكمية: {$qty}")
                : ("\n- Item: {$name}\n- Quantity: {$qty}");
        }
        return $ar
            ? ("سأقوم بإنشاء مسودة طلب شراء:\n- العنوان: {$summary}\n- الأولوية: {$prio}{$lineTxt}\n\nللتأكيد اضغط تأكيد أو اكتب: نعم / موافق\nللإلغاء: لا")
            : ("I will create a draft purchase request:\n- Title: {$summary}\n- Priority: {$prio}{$lineTxt}\n\nConfirm with the Confirm button or: yes / ok\nCancel with: no");
    }

    public static function labelPriority(string $priority, bool $ar): string
    {
        $p = strtolower(trim($priority));
        if (!$ar) {
            return match ($p) {
                'high' => 'High',
                'low' => 'Low',
                default => 'Medium',
            };
        }
        return match ($p) {
            'high' => 'عالية',
            'low' => 'منخفضة',
            default => 'متوسطة',
        };
    }

    public static function labelTool(string $tool, bool $ar): string
    {
        $key = 'ai_tool_' . $tool;
        $tr = __($key);
        if (is_string($tr) && $tr !== '' && $tr !== $key) {
            return $tr;
        }
        return $tool;
    }

    public static function labelGovToken(string $token, bool $ar): string
    {
        $mapAr = [
            'LOW' => 'منخفضة',
            'MEDIUM' => 'متوسطة',
            'HIGH' => 'عالية',
            'CRITICAL' => 'حرجة',
            'READ_ONLY' => 'قراءة فقط',
            'ANALYSIS_ONLY' => 'تحليل فقط',
            'CONFIRMED_WRITE' => 'كتابة بعد التأكيد',
            'APPROVED_WRITE' => 'كتابة بعد الموافقة',
            'controlled_autonomy_disabled_by_default' => 'الاستقلالية المتحكم بها موقوفة افتراضيًا',
            'confirmation_still_required' => 'ما زال التأكيد مطلوبًا',
            'confirmation_policy_blocks_unattended_write' => 'سياسة التأكيد تمنع الكتابة غير المراقبة',
            'sensitive_actions_never_autonomous' => 'الإجراءات الحساسة لا تُنفَّذ تلقائيًا',
            'empty_autonomy_allowlist' => 'قائمة الاستقلالية فارغة',
            'tool_not_in_autonomy_allowlist' => 'الأداة غير مدرجة في قائمة الاستقلالية',
            'confirmation_required' => 'يتطلب تأكيد المستخدم',
            'controlled_test' => 'اختبار مضبوط',
            'create_draft_purchase_request' => 'إنشاء مسودة طلب شراء',
            'duplicate_action' => 'إجراء مكرر',
            'tool_exception' => 'تعذر تنفيذ الأداة',
            'verification_incomplete' => 'التحقق غير مكتمل',
        ];
        $mapEn = [
            'LOW' => 'Low',
            'MEDIUM' => 'Medium',
            'HIGH' => 'High',
            'CRITICAL' => 'Critical',
            'READ_ONLY' => 'Read only',
            'ANALYSIS_ONLY' => 'Analysis only',
            'CONFIRMED_WRITE' => 'Confirmed write',
            'APPROVED_WRITE' => 'Approved write',
            'controlled_autonomy_disabled_by_default' => 'Controlled autonomy disabled by default',
            'confirmation_still_required' => 'Confirmation still required',
            'confirmation_policy_blocks_unattended_write' => 'Confirmation policy blocks unattended write',
            'sensitive_actions_never_autonomous' => 'Sensitive actions are never autonomous',
            'empty_autonomy_allowlist' => 'Autonomy allowlist is empty',
            'tool_not_in_autonomy_allowlist' => 'Tool is not on the autonomy allowlist',
            'confirmation_required' => 'User confirmation required',
            'controlled_test' => 'Controlled test',
            'create_draft_purchase_request' => 'Create draft purchase request',
            'duplicate_action' => 'Duplicate action',
            'tool_exception' => 'Tool execution failed',
            'verification_incomplete' => 'Verification incomplete',
        ];
        $key = trim($token);
        if ($ar) {
            return $mapAr[$key] ?? $mapAr[strtoupper($key)] ?? $mapAr[strtolower($key)] ?? $key;
        }
        return $mapEn[$key] ?? $mapEn[strtoupper($key)] ?? $mapEn[strtolower($key)] ?? $key;
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
                $tool = (string) ($p['tool'] ?? '');
                $summary = (string) (($p['impact_preview']['summary'] ?? null) ?: self::labelTool($tool, $ar));
                $lines[] = $ar
                    ? "{$n}) {$summary} — التأكيد مطلوب"
                    : "{$n}) {$summary} — confirmation required";
            }
        }

        if ($results !== []) {
            $lines[] = $partial
                ? ($ar ? 'تنفيذ جزئي (تم إيقاف السلسلة بعد فشل/إيقاف):' : 'Partial execution (chain stopped after failure/stop):')
                : ($ar ? 'نتائج التنفيذ:' : 'Execution results:');
            foreach ($results as $i => $r) {
                $n = $i + 1;
                $ok = !empty($r['success']);
                $tool = (string) ($r['tool'] ?? '');
                $toolLabel = self::labelTool($tool, $ar);
                $ver = is_array($r['verification'] ?? null) ? $r['verification'] : [];
                $data = is_array($r['data'] ?? null) ? $r['data'] : [];

                if ($ok && $tool === 'create_draft_purchase_request') {
                    $reqNo = (string) ($data['request_no'] ?? '');
                    $id = (string) ($data['id'] ?? '');
                    $savedTitle = (string) ($data['title'] ?? '');
                    $savedPrio = self::labelPriority((string) ($data['priority'] ?? 'medium'), $ar);
                    $savedItems = is_array($data['line_items'] ?? null) ? $data['line_items'] : [];
                    $snap = is_array($plan['parameter_snapshot'] ?? null) ? $plan['parameter_snapshot'] : [];
                    $args = is_array($plan['actions'][0]['arguments'] ?? null) ? $plan['actions'][0]['arguments'] : [];
                    $prio = self::labelPriority((string) ($data['priority'] ?? $snap['priority'] ?? $args['priority'] ?? 'medium'), $ar);
                    $line0 = is_array($savedItems[0] ?? null) ? $savedItems[0]
                        : (is_array($snap['line_items'][0] ?? null) ? $snap['line_items'][0]
                        : (is_array($args['line_items'][0] ?? null) ? $args['line_items'][0] : []));
                    $item = (string) ($line0['item_name'] ?? $line0['description'] ?? '');
                    $qty = (string) ($line0['quantity'] ?? '');
                    if ($ar) {
                        $lines[] = 'تم إنشاء مسودة طلب الشراء بنجاح.';
                        if ($reqNo !== '') {
                            $lines[] = 'رقم الطلب: ' . $reqNo;
                        } elseif ($id !== '') {
                            $lines[] = 'المعرّف: ' . $id;
                        }
                        if ($savedTitle !== '') {
                            $lines[] = 'العنوان: ' . $savedTitle;
                        }
                        if ($item !== '') {
                            $lines[] = 'الصنف: ' . $item;
                        }
                        if ($qty !== '') {
                            $lines[] = 'الكمية: ' . $qty;
                        }
                        $lines[] = 'الأولوية: ' . $prio;
                        $lines[] = !empty($ver['verified'])
                            ? 'التحقق: تم التحقق من الحالة بعد التنفيذ.'
                            : 'التحقق: قيد المراجعة.';
                    } else {
                        $lines[] = 'Draft purchase request created successfully.';
                        if ($reqNo !== '') {
                            $lines[] = 'Request number: ' . $reqNo;
                        } elseif ($id !== '') {
                            $lines[] = 'ID: ' . $id;
                        }
                        if ($savedTitle !== '') {
                            $lines[] = 'Title: ' . $savedTitle;
                        }
                        if ($item !== '') {
                            $lines[] = 'Item: ' . $item;
                        }
                        if ($qty !== '') {
                            $lines[] = 'Quantity: ' . $qty;
                        }
                        $lines[] = 'Priority: ' . $prio;
                        $lines[] = !empty($ver['verified'])
                            ? 'Verification: post-action state verified.'
                            : 'Verification: pending review.';
                    }
                    continue;
                }

                $lines[] = $ar
                    ? "{$n}) {$toolLabel} → " . ($ok ? 'نجاح' : 'فشل')
                    : "{$n}) {$toolLabel} → " . ($ok ? 'success' : 'failed');
                if (!empty($ver['incomplete'])) {
                    $lines[] = $ar
                        ? '   التحقق غير مكتمل — لا يُعلن نجاحاً نهائياً.'
                        : '   Verification incomplete — not declared as final PASS.';
                } elseif (!empty($ver['verified'])) {
                    $lines[] = $ar ? '   تم التحقق من الحالة بعد التنفيذ.' : '   Post-action state verified.';
                }
                if (!empty($r['error_code'])) {
                    $lines[] = ($ar ? '   خطأ: ' : '   Error: ') . self::labelGovToken((string) $r['error_code'], $ar);
                }
            }
        }

        foreach (($plan['unsupported'] ?? []) as $u) {
            if (!is_array($u)) {
                continue;
            }
            $lines[] = $ar
                ? 'عملية غير متاحة عبر الوكيل: ' . (string) ($u['requested'] ?? '')
                : 'Action unavailable via agent: ' . (string) ($u['requested'] ?? '');
        }
        foreach (($plan['recommendations'] ?? []) as $rec) {
            if (!is_array($rec)) {
                continue;
            }
            $msg = (string) ($rec['message'] ?? '');
            if ($msg === '' || str_contains($msg, 'controlled_test')) {
                continue;
            }
            $lines[] = ($ar ? 'توصية: ' : 'Recommendation: ') . $msg;
        }

        if ($lines === []) {
            $lines[] = $ar
                ? 'لا يوجد إجراء كتابة مدعوم لهذا الطلب.'
                : 'No supported write action for this request.';
        }

        return implode("\n", $lines);
    }
}
