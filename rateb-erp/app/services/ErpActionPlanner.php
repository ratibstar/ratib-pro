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

    public const PHASE_COLLECTING = 'COLLECTING';
    public const PHASE_PENDING_CONFIRMATION = 'PENDING_CONFIRMATION';
    public const PHASE_CONFIRMED = 'CONFIRMED';
    public const PHASE_AUTHORIZED = 'AUTHORIZED';
    public const PHASE_EXECUTING = 'EXECUTING';
    public const PHASE_EXECUTED = 'EXECUTED';
    public const PHASE_VERIFIED = 'VERIFIED';
    public const PHASE_CANCELLED = 'CANCELLED';
    public const PHASE_FAILED = 'FAILED';
    public const PHASE_STALE = 'STALE';

    /** @deprecated keep reading legacy session values */
    private const PHASE_LEGACY_AWAITING = 'awaiting_confirmation';
    private const PHASE_LEGACY_EXECUTED = 'executed';
    private const PHASE_LEGACY_COLLECTING = 'collecting';

    /** @var list<string> */
    private const WRITE_TOOLS = [
        'create_draft_purchase_request',
        'update_purchase_request',
        'cancel_purchase_request',
        'submit_purchase_request',
        'submit_journal_for_approval',
        'create_inventory_item',
        'create_employee',
        'create_supplier',
        'create_crm_lead',
        'create_crm_followup',
        'create_customer',
        'create_project',
        'create_asset',
        'create_recruitment_candidate',
        'create_contract',
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
            '/(أنشئ|إنشاء|أضف|اضف|ضيف|إضافة|عدّل|عدل|ألغ|الغ|حدّث|حدث|أرسل|ارسل|submit|create|update|cancel|add|prepare\s+for\s+delivery|جهز|متابعة\s*للعميل|طلب\s*شراء)/ui'
        ) && (
            ErpOrchestrationPlanner::hasWriteIntent($message)
            || self::match($message, '/(طلب\s*شراء|purchase\s*request|مخزون|صنف|موظف|موظفين|مورد|موردين|موارد\s*بشرية|حساب|مستخدم|عميل|فرصة|متابعة|مشروع|أصل|عقد|مرشح|lead|customer|project|asset|contract|candidate|inventory|employee|supplier|hr|user\s*account|حالة\s*الطلب|متابعة|تسليم|delivery)/ui')
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
        // Never create login accounts / passwords via AI (Online ERP remains auth authority).
        if (self::match($message, '/(حساب\s*(مستخدم|دخول)|مستخدم\s*(جديد)?|user\s*account|login|كلمة\s*مرور|باسورد|password|credentials)/ui')
            && self::match($message, '/(أضف|اضف|ضيف|إضافة|أنشئ|إنشاء|create|add)/ui')
        ) {
            $unsupported[] = [
                'requested' => 'create_user_account_with_password',
                'domain' => 'users',
                'reason' => 'agent_cannot_create_login_credentials',
                'class' => self::CLASS_RECOMMENDATION,
            ];
            $recommendations[] = [
                'message' => self::isAr($ctx)
                    ? "لا يمكن إنشاء حساب دخول أو كلمة مرور عبر RATEB AI.\n"
                        . "استخدم إدارة المستخدمين في لوحة التحكم لإضافة حسابات الدخول.\n"
                        . "لإضافة سجل موظف (موارد بشرية) اكتب مثلاً: أضف موظف أحمد العتيبي"
                    : "RATEB AI cannot create login accounts or passwords.\n"
                        . "Use Users administration in the control panel for login accounts.\n"
                        . "To add an HR employee record write e.g.: add employee Ahmed",
                'domain' => 'users',
                'class' => self::CLASS_RECOMMENDATION,
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
        if (self::match($message, '/(أنشئ|إنشاء|أضف|اضف|ضيف|إضافة|create|add).{0,40}(طلب\s*شراء|purchase\s*request|مشتريات)/ui')
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

        // Inventory create
        if (self::match($message, '/(أضف|اضف|ضيف|إضافة|أنشئ|إنشاء|create|add).{0,40}(مخزون|صنف|منتج|inventory|item|sku)|ضيف\s*مخزون|أضف\s*مخزون/ui')) {
            $itemName = self::extractInventoryItemName($message);
            if ($itemName === '') {
                $recommendations[] = [
                    'message' => self::isAr($ctx)
                        ? 'لإضافة مخزون اكتب مثلاً: أضف مخزون رز كمية 20'
                        : 'To add inventory write e.g.: add inventory rice qty 20',
                    'domain' => 'inventory',
                    'class' => self::CLASS_RECOMMENDATION,
                ];
            } else {
                $qty = self::extractQuantity($message);
                $args = [
                    'item_name' => $itemName,
                    'quantity' => $qty,
                    'unit' => 'pcs',
                    'status' => 'active',
                    'notes' => mb_substr(trim($message), 0, 500),
                ];
                $actions[] = self::makeAction('create_inventory_item', $args, $ctx, 'create_inventory_from_chat');
                $domains[] = 'inventory';
            }
        }

        // Employee / HR create (موظف or موارد بشرية)
        if (self::match($message, '/(أضف|اضف|ضيف|إضافة|أنشئ|إنشاء|create|add).{0,40}(موظف|موظفين|الموارد\s*البشرية|موارد\s*بشرية|employee|employees|\bhr\b)/ui')
            || self::match($message, '/ضيف\s*(الموارد\s*البشرية|موارد\s*بشرية)/ui')
        ) {
            // Skip if this was already handled as user-account+password (above)
            $isLoginCreate = self::match($message, '/(حساب|مستخدم|كلمة\s*مرور|password|user\s*account)/ui');
            if (!$isLoginCreate) {
                $empName = self::extractPersonName($message);
                if ($empName === '') {
                    // Try extract after "موارد بشرية" / bare HR add
                    if (preg_match('/(?:الموارد\s*البشرية|موارد\s*بشرية|hr)\s+(.+)$/ui', $message, $mHr)) {
                        $t = trim($mHr[1]);
                        $t = preg_replace('/\s*(من\s*عندك|تجربه|تجربة|اختبار|test).*$/ui', '', $t) ?? $t;
                        $empName = mb_substr(trim($t, " \t\"'«»"), 0, 120);
                        if (self::match($empName, '/^(من\s*عندك|تجربه|تجربة|اختبار|test)$/ui')) {
                            $empName = '';
                        }
                    }
                }
                if ($empName === '') {
                    $recommendations[] = [
                        'message' => self::isAr($ctx)
                            ? 'لإضافة موظف في الموارد البشرية اكتب مثلاً: أضف موظف أحمد العتيبي'
                            : 'To add an HR employee write e.g.: add employee Ahmed',
                        'domain' => 'hr',
                        'class' => self::CLASS_RECOMMENDATION,
                    ];
                } else {
                    $args = [
                        'name' => $empName,
                        'status' => 'active',
                        'notes' => mb_substr(trim($message), 0, 500),
                    ];
                    $actions[] = self::makeAction('create_employee', $args, $ctx, 'create_employee_from_chat');
                    $domains[] = 'hr';
                }
            }
        }

        // Supplier create
        if (self::match($message, '/(أضف|اضف|ضيف|إضافة|أنشئ|إنشاء|create|add).{0,40}(مورد|موردين|supplier|suppliers)/ui')) {
            $supName = self::extractSupplierName($message);
            if ($supName === '') {
                $recommendations[] = [
                    'message' => self::isAr($ctx)
                        ? 'لإضافة مورد اكتب مثلاً: أضف مورد شركة النور'
                        : 'To add a supplier write e.g.: add supplier Al-Noor Co',
                    'domain' => 'suppliers',
                    'class' => self::CLASS_RECOMMENDATION,
                ];
            } else {
                $args = [
                    'name' => $supName,
                    'status' => 'active',
                    'notes' => mb_substr(trim($message), 0, 500),
                ];
                $actions[] = self::makeAction('create_supplier', $args, $ctx, 'create_supplier_from_chat');
                $domains[] = 'suppliers';
            }
        }

        // CRM lead
        if (self::match($message, '/(أضف|اضف|ضيف|إضافة|أنشئ|إنشاء|create|add).{0,40}(فرصة|عميل\s*محتمل|ليد|lead)/ui')) {
            $title = self::extractTitleLike($message, '/(?:فرصة|عميل\s*محتمل|ليد|lead)\s+(.+)$/ui');
            if ($title === '') {
                $recommendations[] = [
                    'message' => self::isAr($ctx)
                        ? 'لإضافة فرصة/عميل محتمل اكتب مثلاً: أضف فرصة شركة الأمل'
                        : 'To add a CRM lead write e.g.: add lead Al-Amal Co',
                    'domain' => 'crm',
                    'class' => self::CLASS_RECOMMENDATION,
                ];
            } else {
                $actions[] = self::makeAction('create_crm_lead', [
                    'title' => $title,
                    'contact_name' => $title,
                    'notes' => mb_substr(trim($message), 0, 500),
                ], $ctx, 'create_crm_lead_from_chat');
                $domains[] = 'crm';
            }
        }

        // CRM follow-up
        if (self::match($message, '/(أضف|اضف|ضيف|إضافة|أنشئ|إنشاء|create|add).{0,40}(متابعة|follow[\s-]?up)|متابعة\s*للعميل/ui')) {
            $subject = self::extractTitleLike($message, '/(?:متابعة|follow[\s-]?up)\s+(.+)$/ui');
            if ($subject === '') {
                $subject = self::isAr($ctx) ? 'متابعة عميل' : 'Customer follow-up';
            }
            $actions[] = self::makeAction('create_crm_followup', [
                'subject' => $subject,
                'notes' => mb_substr(trim($message), 0, 500),
            ], $ctx, 'create_crm_followup_from_chat');
            $domains[] = 'crm';
        }

        // Customer
        if (self::match($message, '/(أضف|اضف|ضيف|إضافة|أنشئ|إنشاء|create|add).{0,40}(عميل|زبون|customer)(?!\s*محتمل)/ui')
            && !self::match($message, '/(فرصة|ليد|lead|محتمل)/ui')
        ) {
            $custName = self::extractTitleLike($message, '/(?:عميل|زبون|customer)\s+(.+)$/ui');
            if ($custName === '') {
                $recommendations[] = [
                    'message' => self::isAr($ctx)
                        ? 'لإضافة عميل اكتب مثلاً: أضف عميل مؤسسة النور'
                        : 'To add a customer write e.g.: add customer Al-Noor Est',
                    'domain' => 'crm',
                    'class' => self::CLASS_RECOMMENDATION,
                ];
            } else {
                $actions[] = self::makeAction('create_customer', [
                    'name' => $custName,
                    'notes' => mb_substr(trim($message), 0, 500),
                ], $ctx, 'create_customer_from_chat');
                $domains[] = 'crm';
            }
        }

        // Project
        if (self::match($message, '/(أضف|اضف|ضيف|إضافة|أنشئ|إنشاء|create|add).{0,40}(مشروع|project)/ui')) {
            $projName = self::extractTitleLike($message, '/(?:مشروع|project)\s+(.+)$/ui');
            if ($projName === '') {
                $recommendations[] = [
                    'message' => self::isAr($ctx)
                        ? 'لإضافة مشروع اكتب مثلاً: أضف مشروع تطوير المتجر'
                        : 'To add a project write e.g.: add project Store upgrade',
                    'domain' => 'projects',
                    'class' => self::CLASS_RECOMMENDATION,
                ];
            } else {
                $actions[] = self::makeAction('create_project', [
                    'name' => $projName,
                    'notes' => mb_substr(trim($message), 0, 500),
                ], $ctx, 'create_project_from_chat');
                $domains[] = 'projects';
            }
        }

        // Asset
        if (self::match($message, '/(أضف|اضف|ضيف|إضافة|أنشئ|إنشاء|create|add).{0,40}(أصل|اصول|أصول|asset)/ui')) {
            $assetName = self::extractTitleLike($message, '/(?:أصل|اصول|أصول|asset)\s+(.+)$/ui');
            if ($assetName === '') {
                $recommendations[] = [
                    'message' => self::isAr($ctx)
                        ? 'لإضافة أصل اكتب مثلاً: أضف أصل طابعة ليزر'
                        : 'To add an asset write e.g.: add asset laser printer',
                    'domain' => 'assets',
                    'class' => self::CLASS_RECOMMENDATION,
                ];
            } else {
                $actions[] = self::makeAction('create_asset', [
                    'name' => $assetName,
                    'notes' => mb_substr(trim($message), 0, 500),
                ], $ctx, 'create_asset_from_chat');
                $domains[] = 'assets';
            }
        }

        // Recruitment candidate
        if (self::match($message, '/(أضف|اضف|ضيف|إضافة|أنشئ|إنشاء|create|add).{0,40}(مرشح|مرشحة|candidate)/ui')) {
            $candName = self::extractPersonName($message);
            if ($candName === '') {
                $candName = self::extractTitleLike($message, '/(?:مرشح|مرشحة|candidate)\s+(.+)$/ui');
            }
            if ($candName === '') {
                $recommendations[] = [
                    'message' => self::isAr($ctx)
                        ? 'لإضافة مرشح اكتب مثلاً: أضف مرشح سارة أحمد'
                        : 'To add a candidate write e.g.: add candidate Sara Ahmed',
                    'domain' => 'recruitment',
                    'class' => self::CLASS_RECOMMENDATION,
                ];
            } else {
                $actions[] = self::makeAction('create_recruitment_candidate', [
                    'full_name' => $candName,
                    'notes' => mb_substr(trim($message), 0, 500),
                ], $ctx, 'create_candidate_from_chat');
                $domains[] = 'recruitment';
            }
        }

        // Contract
        if (self::match($message, '/(أضف|اضف|ضيف|إضافة|أنشئ|إنشاء|create|add).{0,40}(عقد|contracts?)/ui')
            && !self::match($message, '/(توظيف|employment)/ui')
        ) {
            $ctTitle = self::extractTitleLike($message, '/(?:عقد|contract)\s+(.+)$/ui');
            if ($ctTitle === '') {
                $recommendations[] = [
                    'message' => self::isAr($ctx)
                        ? 'لإضافة عقد اكتب مثلاً: أضف عقد صيانة سنوية'
                        : 'To add a contract write e.g.: add contract annual maintenance',
                    'domain' => 'contracts',
                    'class' => self::CLASS_RECOMMENDATION,
                ];
            } else {
                $actions[] = self::makeAction('create_contract', [
                    'title' => $ctTitle,
                    'status' => 'draft',
                ], $ctx, 'create_contract_from_chat');
                $domains[] = 'contracts';
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
                'message' => self::isAr($ctx)
                    ? "هذا الإجراء غير متاح عبر الوكيل حالياً.\n"
                        . "يمكنك مثلاً:\n"
                        . "• أضف طلب شراء مستلزمات مكتبية\n"
                        . "• أضف مخزون رز كمية 20\n"
                        . "• أضف موظف أحمد العتيبي\n"
                        . "• أضف مورد شركة النور\n"
                        . "• أضف عميل مؤسسة النور\n"
                        . "• أضف فرصة شركة الأمل\n"
                        . "• أضف مشروع تطوير المتجر\n"
                        . "• أضف أصل طابعة ليزر\n"
                        . "• أضف مرشح سارة أحمد\n"
                        . "• أضف عقد صيانة سنوية"
                    : "This write action is not available via the agent yet.\n"
                        . "You can try e.g.:\n"
                        . "• add a purchase request for office supplies\n"
                        . "• add inventory rice qty 20\n"
                        . "• add employee Ahmed\n"
                        . "• add supplier Al-Noor Co\n"
                        . "• add customer Al-Noor Est\n"
                        . "• add lead Al-Amal Co\n"
                        . "• add project Store upgrade\n"
                        . "• add asset laser printer\n"
                        . "• add candidate Sara Ahmed\n"
                        . "• add contract annual maintenance",
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
    private static function makeAction(
        string $tool,
        array $arguments,
        ProcurementAgentContext $ctx,
        string $purpose,
        string $actionId = ''
    ): array {
        $class = self::classifyTool($tool);
        $meta = ErpToolRegistry::getTool($tool)
            ?? AccountingToolRegistry::getTool($tool)
            ?? ProcurementToolRegistry::getTool($tool)
            ?? [];
        $domain = ErpToolRegistry::domainForTool($tool)
            ?? (string) ($meta['domain'] ?? ($tool === 'submit_journal_for_approval' ? 'accounting' : 'procurement'));
        $state = self::readStateSnapshot($tool, $arguments, $ctx);
        $confirmKey = self::confirmKey($tool, $arguments, $actionId);

        $row = [
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
        if ($actionId !== '') {
            $row['action_id'] = $actionId;
        }
        return $row;
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
            '/(أنشئ|إنشاء|انشئ|أضف|اضف|ضيف|إضافة|اعمل|اعملوا|سو[يى]|create|make|add).{0,40}(طلب\s*شراء|purchase\s*request|مشتريات)|طلب\s*شراء|purchase\s*request/ui'
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

    public static function normalizePhase(string $phase): string
    {
        $p = strtoupper(trim($phase));
        return match ($p) {
            'AWAITING_CONFIRMATION', 'PENDING_CONFIRMATION' => self::PHASE_PENDING_CONFIRMATION,
            'COLLECTING' => self::PHASE_COLLECTING,
            'CONFIRMED' => self::PHASE_CONFIRMED,
            'AUTHORIZED' => self::PHASE_AUTHORIZED,
            'EXECUTING' => self::PHASE_EXECUTING,
            'EXECUTED' => self::PHASE_EXECUTED,
            'VERIFIED' => self::PHASE_VERIFIED,
            'CANCELLED' => self::PHASE_CANCELLED,
            'FAILED' => self::PHASE_FAILED,
            'STALE' => self::PHASE_STALE,
            default => $phase,
        };
    }

    /**
     * @param array<string, mixed> $pending
     * @return list<array{action_id:string,confirm_key:string,record_id:int}>
     */
    public static function verifiedActionsList(array $pending): array
    {
        $out = [];
        $raw = is_array($pending['verified_actions'] ?? null) ? $pending['verified_actions'] : [];
        foreach ($raw as $row) {
            if (!is_array($row)) {
                continue;
            }
            $aid = (string) ($row['action_id'] ?? '');
            $ck = (string) ($row['confirm_key'] ?? '');
            $rid = (int) ($row['record_id'] ?? 0);
            if ($aid === '' || $ck === '' || $rid < 1) {
                continue;
            }
            $out[] = ['action_id' => $aid, 'confirm_key' => $ck, 'record_id' => $rid];
        }
        return $out;
    }

    /**
     * True only when THIS action_id was verified with a real record — not merely proposed/confirmed.
     * PENDING_CONFIRMATION / CONFIRMED / AUTHORIZED / EXECUTING are never treated as executed.
     *
     * @param array<string, mixed> $pending
     * @param list<string> $confirmedWrites
     */
    public static function isVerifiedDuplicateAttempt(array $pending, array $confirmedWrites, string $message): bool
    {
        $phase = self::normalizePhase((string) ($pending['phase'] ?? ''));
        // Open proposal / in-flight confirm must always be allowed to execute
        if (!empty($pending['confirmations']) && is_array($pending['confirmations'])) {
            if (in_array($phase, [
                self::PHASE_PENDING_CONFIRMATION,
                self::PHASE_CONFIRMED,
                self::PHASE_AUTHORIZED,
                self::PHASE_EXECUTING,
                self::PHASE_COLLECTING,
                self::PHASE_LEGACY_AWAITING,
                self::PHASE_LEGACY_COLLECTING,
                self::PHASE_FAILED,
                '',
            ], true) || $phase === '') {
                return false;
            }
        }
        $verified = self::verifiedActionsList($pending);
        // Without proof of a real record id, never claim "already executed"
        if ($verified === []) {
            return false;
        }
        if (!in_array($phase, [self::PHASE_VERIFIED, self::PHASE_EXECUTED, self::PHASE_LEGACY_EXECUTED], true)) {
            return false;
        }
        $actionId = (string) ($pending['action_id'] ?? '');
        foreach ($verified as $v) {
            if ($actionId !== '' && $v['action_id'] !== $actionId) {
                continue;
            }
            if ($v['record_id'] < 1) {
                continue;
            }
            if ($confirmedWrites !== [] && in_array($v['confirm_key'], $confirmedWrites, true)) {
                return true;
            }
            // Bare confirmation phrase against the same verified action_id
            if ($confirmedWrites === [] && self::isConfirmationPhrase($message)
                && ($actionId === '' || $v['action_id'] === $actionId)
            ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Attach inventory_id when a catalog match exists. If catalog has rows and name unmatched → unknown_item.
     *
     * @param array<string, mixed> $draft
     * @return array<string, mixed>
     */
    public static function attachInventoryToDraft(array $draft, ProcurementAgentContext $ctx, bool $ar): array
    {
        $args = is_array($draft['arguments'] ?? null) ? $draft['arguments'] : [];
        $lines = is_array($args['line_items'] ?? null) ? $args['line_items'] : [];
        if ($lines === []) {
            return $draft;
        }
        $companyId = (int) $ctx->companyId;
        if ($companyId < 1 || !$ctx->moduleEnabled('inventory')) {
            return $draft;
        }
        try {
            $db = Database::connection();
            $count = (int) $db->query(
                'SELECT COUNT(*) FROM rateb_inventory WHERE company_id = ' . (int) $companyId
            )->fetchColumn();
            if ($count < 1) {
                // Empty catalog — allow free-text description lines (cannot validate)
                return $draft;
            }
            $resolved = [];
            foreach ($lines as $line) {
                if (!is_array($line)) {
                    continue;
                }
                $name = trim((string) ($line['item_name'] ?? $line['description'] ?? ''));
                if ($name === '') {
                    continue;
                }
                if (!empty($line['inventory_id'])) {
                    $resolved[] = $line;
                    continue;
                }
                $stmt = $db->prepare(
                    'SELECT id, item_name, sku, unit, unit_cost
                     FROM rateb_inventory
                     WHERE company_id = :cid
                       AND (item_name = :exact OR item_name LIKE :like OR sku = :sku)
                     ORDER BY (item_name = :exact2) DESC, id ASC
                     LIMIT 1'
                );
                $stmt->execute([
                    'cid' => $companyId,
                    'exact' => $name,
                    'exact2' => $name,
                    'sku' => $name,
                    'like' => '%' . $name . '%',
                ]);
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                if (!$row) {
                    $draft['unknown_item'] = true;
                    $draft['unknown_item_message'] = $ar
                        ? ('الصنف «' . $name . '» غير موجود في المخزون. اختر صنفاً صحيحاً من النظام.')
                        : ('Item «' . $name . '» was not found in inventory. Choose a valid catalog item.');
                    $draft['ready'] = false;
                    return $draft;
                }
                $line['inventory_id'] = (int) $row['id'];
                $line['item_name'] = (string) ($row['item_name'] ?? $name);
                if (empty($line['description'])) {
                    $line['description'] = (string) ($row['item_name'] ?? $name);
                }
                if (empty($line['sku']) && !empty($row['sku'])) {
                    $line['sku'] = (string) $row['sku'];
                }
                if ((empty($line['unit']) || $line['unit'] === 'each') && !empty($row['unit'])) {
                    $line['unit'] = (string) $row['unit'];
                }
                // Only fill price from catalog when user did not provide one
                if (!isset($line['unit_price']) || (float) $line['unit_price'] <= 0) {
                    // keep 0 unless user provided — do not invent price from cost unless explicit
                }
                $resolved[] = $line;
            }
            if ($resolved !== []) {
                $args['line_items'] = $resolved;
                $draft['arguments'] = $args;
            }
        } catch (\Throwable $e) {
            // fail open for lookup errors — do not invent items
        }
        return $draft;
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

        // Department / cost center — only when the user explicitly provides it (never invent)
        if (preg_match('/(?:قسم|department)\s*[=:]?\s*([^\n,]+)/ui', $message, $m) === 1) {
            $dept = trim($m[1]);
            // Strip trailing priority / qty noise from capture
            $dept = trim(preg_replace('/\s*(?:بأولوية|اولوية|أولوية|priority).*$/ui', '', $dept) ?? $dept);
            if ($dept !== '') {
                $args['department'] = mb_substr($dept, 0, 120);
                $notes = trim($notes . "\nDepartment: " . $dept);
                $draft['department'] = $dept;
            }
        }

        // Optional unit price / tax from the utterance (never invent)
        $unitPrice = null;
        if (preg_match(
            '/(?:بسعر|بسعر\s*الوحدة|سعر\s*الوحدة|سعرها|سعره|سعر|price|unit[_\s-]?price)\s*[=:]?\s*(\d+(?:\.\d+)?)\s*(?:ريال|ر\.?\s*س\.?|sar|usd)?/ui',
            $message,
            $pm
        ) === 1) {
            $unitPrice = (float) $pm[1];
        }

        $taxRate = null;
        $taxNameHint = null;
        $excludingTax = true;
        if (self::match($message, '/(شامل\s*الضريبة|شامل\s*ل?ضريبة|tax\s*inclusive|inclusive\s*of\s*tax|including\s*tax)/ui')) {
            $excludingTax = false;
        } elseif (self::match($message, '/(غير\s*شامل|exclusive\s*of\s*tax|excluding\s*tax|بدون\s*شمول)/ui')) {
            $excludingTax = true;
        }
        if (self::match($message, '/(معفى|معفي|إعفاء|اعفاء|exempt)/ui')) {
            $taxNameHint = 'Exempt';
            $taxRate = 0.0;
        } elseif (self::match($message, '/(مبيعات\s*محلية|local\s*sales|بدون\s*ضريبة|zero\s*tax|no\s*tax|vat\s*0)/ui')) {
            $taxNameHint = 'Local Sales 0%';
            $taxRate = 0.0;
        } elseif (self::match($message, '/(ضريبة\s*القيمة\s*المضافة\s*5|vat\s*5\s*%?|ضريبة\s*5\s*%?)/ui')) {
            $taxNameHint = 'VAT 5%';
            $taxRate = 5.0;
        } elseif (self::match($message, '/(ضريبة\s*القيمة\s*المضافة|قيمة\s*مضافة|vat\s*15\s*%?|ضريبة\s*15\s*%?)/ui')) {
            $taxNameHint = 'VAT 15%';
            $taxRate = 15.0;
        } elseif (preg_match('/(?:ضريبة|tax|vat)\s*[=:]?\s*(\d+(?:\.\d+)?)\s*%?/ui', $message, $tm) === 1) {
            $taxRate = (float) $tm[1];
            $resolved = \Rateb\App\Helpers\LineItems::resolveTaxPreset(null, $taxRate);
            $taxNameHint = $resolved['tax_name'];
            $taxRate = (float) $resolved['tax_rate'];
        }
        $resolvedTax = \Rateb\App\Helpers\LineItems::resolveTaxPreset($taxNameHint, $taxRate);
        $defaultTaxRate = (float) $resolvedTax['tax_rate'];
        $defaultTaxName = (string) $resolvedTax['tax_name'];

        // Qty + unit + item(s) — "10 وحدات من بطاطس" / "بطاطس 66" / "بطاطس × 10 وأرز × 5"
        $item = null;
        $qty = null;
        $unit = 'unit';
        $msgTrim = trim($message);
        $msgForItem = preg_replace('/\s*(?:قسم|department)\s*[^\n,]*/ui', '', $msgTrim) ?? $msgTrim;
        $msgForItem = preg_replace('/\s*(?:بأولوية|اولوية|أولوية|priority)\s*\S+/ui', '', $msgForItem) ?? $msgForItem;
        $msgForItem = preg_replace(
            '/\s*(?:بسعر|بسعر\s*الوحدة|سعر\s*الوحدة|سعرها|سعره|سعر|price|unit[_\s-]?price)\s*[=:]?\s*\d+(?:\.\d+)?\s*(?:ريال|ر\.?\s*س\.?|sar|usd)?/ui',
            '',
            $msgForItem
        ) ?? $msgForItem;
        $msgForItem = preg_replace('/\s*\d+(?:\.\d+)?\s*(?:ريال|ر\.?\s*س\.?|sar|usd)\b/ui', '', $msgForItem) ?? $msgForItem;
        $msgForItem = preg_replace('/\s*(?:ضريبة|tax|vat|معفى|معفي|مبيعات\s*محلية|شامل\s*الضريبة|غير\s*شامل)[^\n,]*/ui', '', $msgForItem) ?? $msgForItem;
        $msgForItem = trim(preg_replace('/\s+/u', ' ', (string) $msgForItem) ?? '');
        $unitAlt = 'وحدات|وحدة|unit|units|pcs?|pieces?|قطعة|قطع|each|ea';
        $unitBlock = '/^(وحدات|وحدة|unit|units|pcs|piece|pieces|قطعة|قطع|each|ea|متوسط|متوسطة|متوسطه|عاجل|منخفض|طلب|شراء|مواد|department|قسم)$/ui';
        $itemBlock = '/^(ريال|سار|sar|usd|ضريبة|أولوية|اولوية|priority|بدون|شامل|غير)$/ui';

        $extractedLines = [];
        // Per-line prices from original: "بطاطس × 10 بسعر 5"
        $perLinePrices = [];
        if (preg_match_all(
            '/([\p{L}]{2,40})\s*[×xX*]\s*(\d+(?:\.\d+)?)\s*(?:بسعر|سعر|price)\s*[=:]?\s*(\d+(?:\.\d+)?)/ui',
            $message,
            $pmLines,
            PREG_SET_ORDER
        ) >= 1) {
            foreach ($pmLines as $pl) {
                $pn = trim(preg_replace('/^و/u', '', (string) ($pl[1] ?? '')) ?? '');
                if ($pn !== '' && !self::match($pn, $itemBlock)) {
                    $perLinePrices[mb_strtolower($pn)] = (float) $pl[3];
                }
            }
        }

        // Multi-item from price-stripped text only (never treat "5 ريال" as an item)
        if (preg_match_all(
            '/([\p{L}]{2,40})\s*[×xX*]\s*(\d+(?:\.\d+)?)|(\d+(?:\.\d+)?)\s*(?:' . $unitAlt . ')?\s*(?:من\s+)?([\p{L}]{2,40})\b/ui',
            $msgForItem,
            $mm,
            PREG_SET_ORDER
        ) >= 1) {
            foreach ($mm as $m) {
                $n = '';
                $q = 0.0;
                if (!empty($m[1]) && isset($m[2]) && $m[2] !== '') {
                    $n = trim($m[1]);
                    $q = (float) $m[2];
                } elseif (!empty($m[4]) && isset($m[3]) && $m[3] !== '') {
                    $n = trim($m[4]);
                    $q = (float) $m[3];
                }
                $n = trim(preg_replace('/^و/u', '', $n) ?? $n);
                if ($n === '' || $q <= 0 || self::match($n, $unitBlock) || self::match($n, $itemBlock)
                    || self::match($n, '/^(نعم|لا|انشئ|أنشئ|موافق|تأكيد|تجريبي|شراء|طلب)$/ui')
                ) {
                    continue;
                }
                $lineUnit = 'each';
                if (preg_match('/\d+(?:\.\d+)?\s*(' . $unitAlt . ')/ui', $m[0] ?? '', $um) === 1) {
                    $unitRaw = mb_strtolower(trim($um[1]));
                    $lineUnit = in_array($unitRaw, ['وحدات', 'وحدة', 'قطعة', 'قطع', 'ea', 'each', 'pcs', 'piece', 'pieces'], true)
                        ? 'each'
                        : mb_substr($unitRaw, 0, 30);
                }
                $linePrice = $perLinePrices[mb_strtolower($n)] ?? $unitPrice;
                $extractedLines[] = [
                    'item_name' => $n,
                    'description' => $n,
                    'quantity' => $q,
                    'unit' => $lineUnit,
                    'unit_price' => $linePrice !== null ? (float) $linePrice : 0.0,
                    'tax_rate' => $defaultTaxRate,
                    'tax_name' => $defaultTaxName,
                    'excluding_tax' => $excludingTax ? 1 : 0,
                ];
            }
        }

        if ($extractedLines === []) {
            if (preg_match('/(\d+(?:\.\d+)?)\s*(?:' . $unitAlt . ')?\s*(?:من\s+)?([\p{L}]{2,40})\b/ui', $msgForItem, $m) === 1
                && !self::match($m[2], $unitBlock) && !self::match($m[2], $itemBlock)
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
                && !self::match($m[1], $unitBlock) && !self::match($m[1], $itemBlock)
            ) {
                $item = trim($m[1]);
                $qty = (float) $m[2];
            }

            if ($item !== null && $qty !== null && $qty > 0
                && !self::match($item, '/^(نعم|لا|انشئ|أنشئ|موافق|تأكيد|تجريبي)$/ui')
            ) {
                $extractedLines[] = [
                    'item_name' => $item,
                    'description' => $item,
                    'quantity' => $qty,
                    'unit' => $unit !== '' && $unit !== 'unit' ? $unit : 'each',
                    'unit_price' => $unitPrice !== null ? $unitPrice : 0.0,
                    'tax_rate' => $defaultTaxRate,
                    'tax_name' => $defaultTaxName,
                    'excluding_tax' => $excludingTax ? 1 : 0,
                ];
            }
        }

        if ($extractedLines !== []) {
            // Deduplicate by item_name keeping last
            $byName = [];
            foreach ($extractedLines as $el) {
                $byName[mb_strtolower((string) $el['item_name'])] = $el;
            }
            $args['line_items'] = array_values($byName);
            $item = (string) ($args['line_items'][0]['item_name'] ?? $item);
            $qty = (float) ($args['line_items'][0]['quantity'] ?? $qty);
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

        $phase = self::normalizePhase((string) ($pending['phase'] ?? ''));
        $actionId = (string) ($pending['action_id'] ?? '');
        $verifiedActions = self::verifiedActionsList($pending);

        // Duplicate confirm ONLY when THIS action_id was actually verified (real write).
        // confirmation_required / pending / authorized are NOT execution.
        if (self::isVerifiedDuplicateAttempt($pending, $confirmedWrites, $message)) {
            return [
                'mode' => 'already_executed',
                'pending' => $pending,
                'confirmed_writes' => [],
                'action_plan' => null,
                'clear_pending' => false,
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
                    'pending' => array_merge($pending, ['phase' => self::PHASE_STALE]),
                    'confirmed_writes' => [],
                    'action_plan' => null,
                    'clear_pending' => false,
                    'response' => $ar
                        ? 'تغيّرت حالة الإجراء المعلق. أعد التخطيط ثم أكّد من جديد.'
                        : 'Pending action is stale. Please replan and confirm again.',
                ];
            }
            if ($matched !== []) {
                // Never block first confirm of a PENDING_CONFIRMATION action
                return self::buildConfirmTurn(
                    array_merge($pending, ['phase' => self::PHASE_CONFIRMED]),
                    $matched,
                    $ctx,
                    $ar
                );
            }
        }

        // Rejection of pending proposal
        if (self::isRejectionPhrase($message) && (
            !empty($pending['confirmations']) || !empty($pending['draft'])
            || in_array($phase, [self::PHASE_PENDING_CONFIRMATION, self::PHASE_COLLECTING, self::PHASE_LEGACY_AWAITING, self::PHASE_LEGACY_COLLECTING], true)
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
            $keys = [];
            foreach ($pending['confirmations'] as $c) {
                if (is_array($c) && (string) ($c['confirm_key'] ?? '') !== '') {
                    $keys[] = (string) $c['confirm_key'];
                }
            }
            return self::buildConfirmTurn(
                array_merge($pending, ['phase' => self::PHASE_CONFIRMED]),
                array_values(array_unique(array_merge($confirmedWrites, $keys))),
                $ctx,
                $ar
            );
        }

        // Re-surface existing proposal (prevent repeated replan on unrelated chatter)
        if (in_array($phase, [self::PHASE_PENDING_CONFIRMATION, self::PHASE_LEGACY_AWAITING], true)
            && !empty($pending['action_plan'])
            && !empty($pending['confirmations'])
            && !self::isCreatePurchaseRequestIntent($message)
            && !self::isConfirmationPhrase($message)
            && !self::isRejectionPhrase($message)
            && $confirmedWrites === []
        ) {
            if (self::match($message, '/(مثال\s*فقط|ليس\s*حقيق|مو\s*حقيق|ليس\s*للتنفيذ|dry\s*run|test\s*only|not\s*real|example\s*only)/ui')) {
                if (!is_array($pending['draft'] ?? null)) {
                    $pending['draft'] = [];
                }
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

            // New create intent with params (or replacing an awaiting proposal) → current request wins
            $freshStart = self::isCreatePurchaseRequestIntent($message)
                && (
                    self::messageHasPurchaseParams($message)
                    || in_array(self::normalizePhase((string) ($pending['phase'] ?? '')), [
                        self::PHASE_PENDING_CONFIRMATION,
                        self::PHASE_LEGACY_AWAITING,
                        self::PHASE_VERIFIED,
                        self::PHASE_EXECUTED,
                        self::PHASE_LEGACY_EXECUTED,
                    ], true)
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

            $draft = self::attachInventoryToDraft($draft, $ctx, $ar);
            if (!empty($draft['unknown_item'])) {
                $pending = [
                    'phase' => self::PHASE_COLLECTING,
                    'intent' => 'create_draft_purchase_request',
                    'draft' => $draft,
                    'company_id' => (int) $ctx->companyId,
                    'action_id' => 'act_' . bin2hex(random_bytes(6)),
                    'verified_actions' => [],
                    'executed_keys' => [],
                ];
                return [
                    'mode' => 'collect',
                    'pending' => $pending,
                    'confirmed_writes' => $confirmedWrites,
                    'action_plan' => null,
                    'clear_pending' => false,
                    'response' => (string) ($draft['unknown_item_message'] ?? (
                        $ar ? 'الصنف غير موجود في المخزون. اختر صنفاً صحيحاً.' : 'Item not found in inventory. Choose a valid item.'
                    )),
                ];
            }

            $pending = [
                'phase' => self::PHASE_COLLECTING,
                'intent' => 'create_draft_purchase_request',
                'draft' => $draft,
                'company_id' => (int) $ctx->companyId,
                'action_id' => ($freshStart || $prevActionId === '')
                    ? ('act_' . bin2hex(random_bytes(6)))
                    : $prevActionId,
                'verified_actions' => [],
                'executed_keys' => [],
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
            $actionId = (string) $pending['action_id'];
            $action = self::makeAction(
                'create_draft_purchase_request',
                $draft['arguments'],
                $ctx,
                'create_pr_from_conversation',
                $actionId
            );
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
                'action_id' => $actionId,
                'parameter_snapshot' => [
                    'title' => (string) ($action['arguments']['title'] ?? ''),
                    'priority' => (string) ($action['arguments']['priority'] ?? 'medium'),
                    'line_items' => is_array($action['arguments']['line_items'] ?? null) ? $action['arguments']['line_items'] : [],
                ],
            ];

            $pending['phase'] = self::PHASE_PENDING_CONFIRMATION;
            $pending['confirmations'] = [[
                'tool' => $action['tool'],
                'confirm_key' => $action['confirm_key'],
                'arguments' => $action['arguments'],
                'class' => $action['class'],
                'action_id' => $actionId,
            ]];
            $pending['action_plan'] = $plan;
            $pending['parameter_snapshot'] = $plan['parameter_snapshot'];
            $pending['verified_actions'] = [];
            $pending['executed_keys'] = [];

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
            $actionId = (string) ($pending['action_id'] ?? '');
            $action = self::makeAction(
                'create_draft_purchase_request',
                $args,
                $ctx,
                'create_pr_from_conversation',
                $actionId
            );
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
                'action_id' => $actionId,
                'parameter_snapshot' => is_array($pending['parameter_snapshot'] ?? null)
                    ? $pending['parameter_snapshot']
                    : [],
            ];
        } elseif (!empty($pending['confirmations']) && is_array($pending['confirmations'])) {
            // Rebuild plan from LLM/domain pending confirmations (session may lack action_plan).
            $actions = [];
            $domains = [];
            $actionId = (string) ($pending['action_id'] ?? '');
            foreach ($pending['confirmations'] as $c) {
                if (!is_array($c)) {
                    continue;
                }
                $tool = (string) ($c['tool'] ?? '');
                if ($tool === '') {
                    continue;
                }
                $args = is_array($c['arguments'] ?? null) ? $c['arguments'] : [];
                $action = self::makeAction($tool, $args, $ctx, 'confirm_pending_write', $actionId);
                $ck = (string) ($c['confirm_key'] ?? '');
                if ($ck !== '') {
                    $action['confirm_key'] = $ck;
                }
                if (!empty($c['permission'])) {
                    $action['permission'] = (string) $c['permission'];
                }
                $actions[] = $action;
                $d = (string) ($action['domain'] ?? '');
                if ($d !== '') {
                    $domains[] = $d;
                }
            }
            if ($actions !== []) {
                if ($actionId === '') {
                    $actionId = 'act_' . substr(sha1((string) json_encode(array_column($actions, 'confirm_key'))), 0, 12);
                }
                $plan = [
                    'intent' => (string) ($pending['intent'] ?? 'pending_write_confirmation'),
                    'domains' => array_values(array_unique($domains)),
                    'actions' => $actions,
                    'unsupported' => [],
                    'recommendations' => [],
                    'requires_confirmation' => true,
                    'evidence_first' => true,
                    'auto_execute' => false,
                    'from_pending_confirmations' => true,
                    'action_id' => $actionId,
                ];
            }
        }

        // Confirm path: CONFIRMED → AUTHORIZED (execution starts in ErpAgent)
        $pending['phase'] = self::PHASE_AUTHORIZED;

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
        if ($lines !== []) {
            $parts = [];
            foreach ($lines as $li) {
                if (!is_array($li)) {
                    continue;
                }
                $name = (string) ($li['item_name'] ?? $li['description'] ?? '');
                $qty = (string) ($li['quantity'] ?? '');
                if ($name === '') {
                    continue;
                }
                $parts[] = $ar
                    ? ("{$name}" . ($qty !== '' ? " × {$qty}" : ''))
                    : ("{$name}" . ($qty !== '' ? " × {$qty}" : ''));
            }
            if ($parts !== []) {
                $lineTxt = $ar
                    ? ("\n- الأصناف: " . implode('، ', $parts))
                    : ("\n- Items: " . implode(', ', $parts));
            }
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
            'create_inventory_item' => 'إضافة صنف مخزون',
            'create_employee' => 'إضافة موظف',
            'create_supplier' => 'إضافة مورد',
            'create_crm_lead' => 'إضافة فرصة / عميل محتمل',
            'create_crm_followup' => 'إضافة متابعة عميل',
            'create_customer' => 'إضافة عميل',
            'create_project' => 'إضافة مشروع',
            'create_asset' => 'إضافة أصل',
            'create_recruitment_candidate' => 'إضافة مرشح',
            'create_contract' => 'إضافة عقد',
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
            'create_inventory_item' => 'Add inventory item',
            'create_employee' => 'Add employee',
            'create_supplier' => 'Add supplier',
            'create_crm_lead' => 'Add CRM lead',
            'create_crm_followup' => 'Add CRM follow-up',
            'create_customer' => 'Add customer',
            'create_project' => 'Add project',
            'create_asset' => 'Add asset',
            'create_recruitment_candidate' => 'Add recruitment candidate',
            'create_contract' => 'Add contract',
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

    public static function confirmKey(string $tool, array $arguments, string $actionId = ''): string
    {
        // Bind idempotency to action_id when present so a new proposal never collides with a prior one.
        $payload = $arguments;
        if ($actionId !== '') {
            $payload = ['_action_id' => $actionId] + $arguments;
        }
        return $tool . ':' . md5((string) json_encode($payload, JSON_UNESCAPED_UNICODE));
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
        if ($tool === 'create_inventory_item') {
            return ['exists' => false, 'entity' => 'inventory_item', 'status' => null];
        }
        if ($tool === 'create_employee') {
            return ['exists' => false, 'entity' => 'employee', 'status' => null];
        }
        if ($tool === 'create_supplier') {
            return ['exists' => false, 'entity' => 'supplier', 'status' => null];
        }
        if (in_array($tool, [
            'create_crm_lead', 'create_crm_followup', 'create_customer',
            'create_project', 'create_asset', 'create_recruitment_candidate', 'create_contract',
        ], true)) {
            return ['exists' => false, 'entity' => $tool, 'status' => null];
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
            if ($ok) {
                // Backend financials must match header after real write
                $items = \Rateb\App\Helpers\LineItems::loadPurchaseRequestItems($id);
                $agg = $items !== []
                    ? \Rateb\App\Helpers\LineItems::aggregateTotals($items)
                    : ['subtotal' => 0.0, 'tax' => 0.0, 'total' => 0.0];
                $headerTotal = (float) ($state['total_estimated'] ?? $execResult['data']['total_estimated'] ?? 0);
                if (abs($headerTotal - (float) $agg['total']) > 0.009) {
                    return [
                        'verified' => false,
                        'incomplete' => true,
                        'new_state' => array_merge($state, ['financials' => $agg]),
                        'message' => 'verification_incomplete_totals_mismatch',
                    ];
                }
                $state['financials'] = $agg;
            }
            return [
                'verified' => $ok,
                'incomplete' => !$ok,
                'new_state' => $state,
                'message' => $ok ? 'verified_draft_created' : 'verification_incomplete',
            ];
        }

        if ($tool === 'create_inventory_item') {
            $id = (int) ($execResult['data']['id'] ?? 0);
            if ($id < 1) {
                return ['verified' => false, 'incomplete' => true, 'new_state' => null, 'message' => 'verification_incomplete_missing_id'];
            }
            try {
                $rows = (new \Rateb\App\Models\Inventory())->query(
                    'SELECT id, item_code, item_name, status, quantity FROM rateb_inventory
                     WHERE id = :id AND company_id = :cid LIMIT 1',
                    ['id' => $id, 'cid' => (int) $ctx->companyId]
                );
                $row = $rows[0] ?? null;
                $ok = is_array($row);
                return [
                    'verified' => $ok,
                    'incomplete' => !$ok,
                    'new_state' => $ok ? ['exists' => true, 'entity' => 'inventory_item', 'id' => $id] + $row : null,
                    'message' => $ok ? 'verified_inventory_created' : 'verification_incomplete',
                ];
            } catch (\Throwable $e) {
                return ['verified' => false, 'incomplete' => true, 'new_state' => null, 'message' => 'verification_incomplete'];
            }
        }

        if ($tool === 'create_employee') {
            $id = (int) ($execResult['data']['id'] ?? 0);
            if ($id < 1) {
                return ['verified' => false, 'incomplete' => true, 'new_state' => null, 'message' => 'verification_incomplete_missing_id'];
            }
            try {
                $rows = (new \Rateb\App\Models\Employee())->query(
                    'SELECT id, employee_code, name, status FROM rateb_employees
                     WHERE id = :id AND company_id = :cid LIMIT 1',
                    ['id' => $id, 'cid' => (int) $ctx->companyId]
                );
                $row = $rows[0] ?? null;
                $ok = is_array($row);
                return [
                    'verified' => $ok,
                    'incomplete' => !$ok,
                    'new_state' => $ok ? ['exists' => true, 'entity' => 'employee', 'id' => $id] + $row : null,
                    'message' => $ok ? 'verified_employee_created' : 'verification_incomplete',
                ];
            } catch (\Throwable $e) {
                return ['verified' => false, 'incomplete' => true, 'new_state' => null, 'message' => 'verification_incomplete'];
            }
        }

        if ($tool === 'create_supplier') {
            $id = (int) ($execResult['data']['id'] ?? 0);
            if ($id < 1) {
                return ['verified' => false, 'incomplete' => true, 'new_state' => null, 'message' => 'verification_incomplete_missing_id'];
            }
            try {
                $rows = (new \Rateb\App\Models\Supplier())->query(
                    'SELECT id, code, name, status FROM rateb_suppliers
                     WHERE id = :id AND company_id = :cid LIMIT 1',
                    ['id' => $id, 'cid' => (int) $ctx->companyId]
                );
                $row = $rows[0] ?? null;
                $ok = is_array($row);
                return [
                    'verified' => $ok,
                    'incomplete' => !$ok,
                    'new_state' => $ok ? ['exists' => true, 'entity' => 'supplier', 'id' => $id] + $row : null,
                    'message' => $ok ? 'verified_supplier_created' : 'verification_incomplete',
                ];
            } catch (\Throwable $e) {
                return ['verified' => false, 'incomplete' => true, 'new_state' => null, 'message' => 'verification_incomplete'];
            }
        }

        $verifyMap = [
            'create_crm_lead' => ['table' => 'rateb_crm_leads', 'cols' => 'id, lead_no, title, status', 'msg' => 'verified_crm_lead_created'],
            'create_crm_followup' => ['table' => 'rateb_crm_tasks', 'cols' => 'id, subject, status', 'msg' => 'verified_crm_followup_created'],
            'create_customer' => ['table' => 'rateb_customers', 'cols' => 'id, code, name, is_active', 'msg' => 'verified_customer_created'],
            'create_project' => ['table' => 'rateb_projects', 'cols' => 'id, project_no, name, status', 'msg' => 'verified_project_created'],
            'create_asset' => ['table' => 'rateb_eam_assets', 'cols' => 'id, asset_no, name, status', 'msg' => 'verified_asset_created'],
            'create_recruitment_candidate' => ['table' => 'rateb_recruitment_candidates', 'cols' => 'id, candidate_no, full_name, status', 'msg' => 'verified_candidate_created'],
            'create_contract' => ['table' => 'rateb_contracts', 'cols' => 'id, contract_no, title, status', 'msg' => 'verified_contract_created'],
        ];
        if (isset($verifyMap[$tool])) {
            $id = (int) ($execResult['data']['id'] ?? 0);
            if ($id < 1) {
                return ['verified' => false, 'incomplete' => true, 'new_state' => null, 'message' => 'verification_incomplete_missing_id'];
            }
            $meta = $verifyMap[$tool];
            try {
                $model = new \Rateb\App\Models\Customer();
                $rows = $model->query(
                    'SELECT ' . $meta['cols'] . ' FROM ' . $meta['table']
                    . ' WHERE id = :id AND company_id = :cid LIMIT 1',
                    ['id' => $id, 'cid' => (int) $ctx->companyId]
                );
                $row = $rows[0] ?? null;
                $ok = is_array($row);
                return [
                    'verified' => $ok,
                    'incomplete' => !$ok,
                    'new_state' => $ok ? ['exists' => true, 'entity' => $tool, 'id' => $id] + $row : null,
                    'message' => $ok ? $meta['msg'] : 'verification_incomplete',
                ];
            } catch (\Throwable $e) {
                return ['verified' => false, 'incomplete' => true, 'new_state' => null, 'message' => 'verification_incomplete'];
            }
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
                "SELECT id, input_json, output_json FROM rateb_agent_audit_events
                 WHERE company_id = :cid AND user_id = :uid AND request_id = :rid
                   AND status = 'success'
                   AND tool_name IN (
                     'create_draft_purchase_request','update_purchase_request','cancel_purchase_request','submit_purchase_request',
                     'submit_journal_for_approval','create_inventory_item','create_employee','create_supplier',
                     'create_crm_lead','create_crm_followup','create_customer','create_project','create_asset',
                     'create_recruitment_candidate','create_contract'
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
                $output = json_decode((string) ($row['output_json'] ?? ''), true);
                if (!is_array($input) || (string) ($input['_idempotency_key'] ?? '') !== $confirmKey) {
                    continue;
                }
                // Only treat as executed when audit proves a successful write event
                $eventType = is_array($output) ? (string) ($output['event_type'] ?? '') : '';
                if ($eventType !== '' && $eventType !== 'action_success') {
                    continue;
                }
                $verified = is_array($output['verification'] ?? null) ? $output['verification'] : [];
                if ($verified !== [] && empty($verified['verified'])) {
                    continue;
                }
                return true;
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

    private static function extractInventoryItemName(string $message): string
    {
        if (preg_match('/(?:اسم|name)\s*[:=]\s*[\"\']?([^\"\'\n]{2,120})/ui', $message, $m)) {
            return mb_substr(trim($m[1]), 0, 120);
        }
        if (preg_match('/(?:ضيف|أضف|اضف|إضافة|أنشئ|إنشاء|add|create)\s+(?:مخزون|صنف|منتج|inventory|item)\s+(.+)$/ui', $message, $m)) {
            $t = trim($m[1]);
            $t = preg_replace('/\s*(كمية|qty|quantity|عدد|unit|وحدة).*$/ui', '', $t) ?? $t;
            $t = trim($t, " \t\"'«»");
            return mb_substr($t, 0, 120);
        }
        if (preg_match('/(?:مخزون|صنف|منتج|item)\s+(?:باسم\s+)?[\"\']?([^\"\'\n]{2,80})/ui', $message, $m)) {
            return mb_substr(trim($m[1]), 0, 120);
        }
        return '';
    }

    private static function extractPersonName(string $message): string
    {
        if (preg_match('/(?:اسم|name)\s*[:=]\s*[\"\']?([^\"\'\n]{2,120})/ui', $message, $m)) {
            return mb_substr(trim($m[1]), 0, 120);
        }
        if (preg_match('/(?:ضيف|أضف|اضف|إضافة|أنشئ|إنشاء|add|create)\s+(?:موظف|موظفة|employee)\s+(.+)$/ui', $message, $m)) {
            $t = trim($m[1]);
            $t = preg_replace('/\s*(بريد|email|جوال|phone|هاتف).*$/ui', '', $t) ?? $t;
            return mb_substr(trim($t, " \t\"'«»"), 0, 120);
        }
        return '';
    }

    private static function extractSupplierName(string $message): string
    {
        if (preg_match('/(?:اسم|name)\s*[:=]\s*[\"\']?([^\"\'\n]{2,120})/ui', $message, $m)) {
            return mb_substr(trim($m[1]), 0, 120);
        }
        if (preg_match('/(?:ضيف|أضف|اضف|إضافة|أنشئ|إنشاء|add|create)\s+(?:مورد|موردين|supplier)\s+(.+)$/ui', $message, $m)) {
            $t = trim($m[1]);
            $t = preg_replace('/\s*(بريد|email|جوال|phone|هاتف).*$/ui', '', $t) ?? $t;
            return mb_substr(trim($t, " \t\"'«»"), 0, 120);
        }
        return '';
    }

    /** Extract trailing name/title after a domain keyword; strips test filler. */
    private static function extractTitleLike(string $message, string $pattern): string
    {
        if (preg_match($pattern, $message, $m)) {
            $t = trim($m[1]);
            $t = preg_replace('/\s*(من\s*عندك|تجربه|تجربة|اختبار|test).*$/ui', '', $t) ?? $t;
            $t = trim($t, " \t\"'«»");
            if ($t === '' || self::match($t, '/^(من\s*عندك|تجربه|تجربة|اختبار|test)$/ui')) {
                return '';
            }
            return mb_substr($t, 0, 120);
        }
        return '';
    }

    private static function extractQuantity(string $message): float
    {
        if (preg_match('/(?:كمية|qty|quantity|عدد)\s*[:=]?\s*(\d+(?:[.,]\d+)?)/ui', $message, $m)) {
            return max(0, (float) str_replace(',', '.', $m[1]));
        }
        if (preg_match('/\b(\d+(?:[.,]\d+)?)\s*(?:قطعة|حبة|وحدة|pcs|kg|كجم)?\b/ui', $message, $m)) {
            // Avoid matching years like 2026
            $n = (float) str_replace(',', '.', $m[1]);
            if ($n >= 1900 && $n <= 2100) {
                return 0.0;
            }
            return max(0, $n);
        }
        return 0.0;
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
                    $savedItems = is_array($data['line_items'] ?? null) ? $data['line_items'] : [];
                    $snap = is_array($plan['parameter_snapshot'] ?? null) ? $plan['parameter_snapshot'] : [];
                    $args = is_array($plan['actions'][0]['arguments'] ?? null) ? $plan['actions'][0]['arguments'] : [];
                    $prio = self::labelPriority((string) ($data['priority'] ?? $snap['priority'] ?? $args['priority'] ?? 'medium'), $ar);
                    $fin = is_array($data['financials'] ?? null)
                        ? $data['financials']
                        : ($savedItems !== [] ? \Rateb\App\Helpers\LineItems::aggregateTotals($savedItems) : null);
                    $currency = (string) ($data['currency'] ?? 'SAR');
                    $line0 = is_array($savedItems[0] ?? null) ? $savedItems[0]
                        : (is_array($snap['line_items'][0] ?? null) ? $snap['line_items'][0]
                        : (is_array($args['line_items'][0] ?? null) ? $args['line_items'][0] : []));
                    $item = (string) ($line0['item_name'] ?? $line0['description'] ?? '');
                    $qty = (string) ($line0['quantity'] ?? '');
                    $unitPrice = (string) ($line0['unit_price'] ?? '');
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
                        if ($unitPrice !== '' && (float) $unitPrice > 0) {
                            $lines[] = 'سعر الوحدة: ' . number_format((float) $unitPrice, 2) . ' ' . $currency;
                        }
                        $lines[] = 'الأولوية: ' . $prio;
                        if (is_array($fin)) {
                            $lines[] = 'المبلغ قبل الضريبة: ' . number_format((float) ($fin['subtotal'] ?? 0), 2) . ' ' . $currency;
                            $lines[] = 'قيمة الضريبة: ' . number_format((float) ($fin['tax'] ?? 0), 2) . ' ' . $currency;
                            $lines[] = 'الإجمالي: ' . number_format((float) ($fin['total'] ?? $data['total_estimated'] ?? 0), 2) . ' ' . $currency;
                        }
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
                        if ($unitPrice !== '' && (float) $unitPrice > 0) {
                            $lines[] = 'Unit price: ' . number_format((float) $unitPrice, 2) . ' ' . $currency;
                        }
                        $lines[] = 'Priority: ' . $prio;
                        if (is_array($fin)) {
                            $lines[] = 'Amount before tax: ' . number_format((float) ($fin['subtotal'] ?? 0), 2) . ' ' . $currency;
                            $lines[] = 'Tax amount: ' . number_format((float) ($fin['tax'] ?? 0), 2) . ' ' . $currency;
                            $lines[] = 'Total: ' . number_format((float) ($fin['total'] ?? $data['total_estimated'] ?? 0), 2) . ' ' . $currency;
                        }
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
            $requested = (string) ($u['requested'] ?? '');
            $lines[] = ($ar ? 'عملية غير متاحة عبر الوكيل: ' : 'Action unavailable via agent: ')
                . self::labelRecommendationKey($requested, $ar);
        }
        foreach (($plan['recommendations'] ?? []) as $rec) {
            if (!is_array($rec)) {
                continue;
            }
            $msg = (string) ($rec['message'] ?? '');
            if ($msg === '' || str_contains($msg, 'controlled_test')) {
                continue;
            }
            // Already localized prose (contains spaces / Arabic) — show as-is.
            $shown = self::labelRecommendationKey($msg, $ar);
            $lines[] = ($ar ? 'توصية: ' : 'Recommendation: ') . $shown;
        }

        if ($lines === []) {
            $lines[] = $ar
                ? 'لا يوجد إجراء كتابة مدعوم لهذا الطلب.'
                : 'No supported write action for this request.';
        }

        return implode("\n", $lines);
    }

    /**
     * Map internal recommendation/unsupported keys to user-facing AR/EN text.
     */
    public static function labelRecommendationKey(string $key, bool $ar): string
    {
        $key = trim($key);
        if ($key === '') {
            return '';
        }
        // Already human prose (Arabic or multi-word sentence)
        if (preg_match('/[\x{0600}-\x{06FF}]/u', $key) === 1 || str_contains($key, ' ') || str_contains($key, "\n")) {
            // Still remap known snake_case if somehow mixed — otherwise return as-is when has space/newline/Arabic
            if (!preg_match('/^[a-z0-9_]+$/i', $key)) {
                return $key;
            }
        }
        $mapAr = [
            'requested_action_not_mapped_to_registered_write_tool' => 'هذا الإجراء غير متاح عبر الوكيل حالياً.',
            'create_user_account_with_password' => 'إنشاء حساب دخول / كلمة مرور',
            'agent_cannot_create_login_credentials' => 'لا يمكن إنشاء بيانات الدخول عبر الوكيل',
            'crm_followup_write_not_available_via_agent' => 'إنشاء متابعة عميل غير متاح عبر الوكيل',
            'create_crm_followup' => 'متابعة عميل',
            'update_logistics_status' => 'تحديث حالة الشحن',
            'direct_accounting_mutation' => 'تعديل محاسبي مباشر',
            'accounting_write_requires_existing_workflow_tool' => 'الكتابة المحاسبية تتم عبر مسار الموافقة الحالي فقط',
            'update_sales_order_status' => 'تحديث حالة طلب البيع',
            'submit_requires_journal_id' => 'أرسل رقم القيد، مثلاً: أرسل القيد 12 للموافقة',
            'submit_requires_purchase_request_id' => 'أرسل رقم طلب الشراء، مثلاً: أرسل طلب الشراء 5 للموافقة',
            'cancel_requires_purchase_request_id' => 'ألغِ برقم طلب الشراء، مثلاً: ألغ طلب الشراء 5',
            'update_requires_purchase_request_id' => 'عدّل برقم طلب الشراء، مثلاً: عدّل طلب الشراء 5',
        ];
        $mapEn = [
            'requested_action_not_mapped_to_registered_write_tool' => 'This write action is not available via the agent yet.',
            'create_user_account_with_password' => 'create login account / password',
            'agent_cannot_create_login_credentials' => 'login credentials cannot be created via the agent',
            'crm_followup_write_not_available_via_agent' => 'CRM follow-up create is not available via the agent',
            'create_crm_followup' => 'CRM follow-up',
            'update_logistics_status' => 'update shipment status',
            'direct_accounting_mutation' => 'direct accounting mutation',
            'accounting_write_requires_existing_workflow_tool' => 'accounting writes require the existing approval workflow tool',
            'update_sales_order_status' => 'update sales order status',
            'submit_requires_journal_id' => 'Include the journal id, e.g.: submit journal 12 for approval',
            'submit_requires_purchase_request_id' => 'Include the purchase request id, e.g.: submit purchase request 5',
            'cancel_requires_purchase_request_id' => 'Include the purchase request id, e.g.: cancel purchase request 5',
            'update_requires_purchase_request_id' => 'Include the purchase request id, e.g.: update purchase request 5',
        ];
        $map = $ar ? $mapAr : $mapEn;
        return $map[$key] ?? $key;
    }
}
