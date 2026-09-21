<?php
declare(strict_types=1);

namespace Rateb\App\Services;

/**
 * Cross-domain intent detection + READ plan builder for Unified ErpAgent.
 * Not an agent — planning helper only. Uses active domains/tools from registries.
 * Phase 13: decision-support intents + minimal required-domain selection.
 */
final class ErpOrchestrationPlanner
{
    /**
     * @return array{
     *     mode: 'single'|'cross',
     *     domains: list<string>,
     *     signals: array<string, bool>,
     *     write_intent: bool,
     *     primary: string|null,
     *     intent_kind: string,
     *     decision_support: bool,
     *     minimal: bool
     * }
     */
    public static function detectIntent(string $message, ProcurementAgentContext $ctx, ?string $explicitDomain = null): array
    {
        $explicit = strtolower(trim((string) $explicitDomain));
        if ($explicit !== '' && ErpDomainRegistry::isActive($explicit)) {
            $domain = ErpDomainRegistry::resolve($explicit);
            if ($domain !== null && $ctx->moduleEnabled((string) $domain['module'])) {
                return [
                    'mode' => 'single',
                    'domains' => [$explicit],
                    'signals' => [$explicit => true],
                    'write_intent' => self::hasWriteIntent($message),
                    'primary' => $explicit,
                    'intent_kind' => 'query',
                    'decision_support' => false,
                    'minimal' => true,
                ];
            }
        }

        $decisionSupport = self::hasDecisionIntent($message);
        $executiveIntent = self::hasExecutiveIntent($message);
        $proactiveIntent = self::hasProactiveIntent($message);
        $learningIntent = self::hasLearningIntent($message);
        $memoryIntent = self::hasMemoryIntent($message);
        if ($proactiveIntent || $learningIntent || $memoryIntent) {
            $executiveIntent = true;
        }
        $signals = [
            ErpDomainRegistry::DOMAIN_EXECUTIVE => $executiveIntent || $proactiveIntent || $learningIntent || $memoryIntent,
            ErpDomainRegistry::DOMAIN_ACCOUNTING => self::match(
                $message,
                '/(accounting|financial|finance|receivable|payable|invoice|invoices|payment|payments|journal|ledger|vat|tax|chart\s*of\s*accounts|محاسبة|مالي|مالية|مستحقات|ذمم|فاتورة|فواتير|دفعة|دفعات|قيد|قيود|ضريبة|قيمة\s*مضافة|دليل\s*الحسابات|الوضع\s*المالي|مخاطر\s*مالية|سيولة)/ui'
            ),
            ErpDomainRegistry::DOMAIN_LOGISTICS => self::match(
                $message,
                '/(logistics|shipment|shipments|delivery|deliveries|trip|trips|fleet|tracking|dispatch|شحن|شحنات|تسليم|توصيل|رحلة|رحلات|سائق|أسطول|تتبع|لوجست|اللوجست|عمليات\s*التسليم)/ui'
            ),
            ErpDomainRegistry::DOMAIN_CRM => self::match(
                $message,
                '/(crm|customer|customers|lead|leads|opportunity|opportunities|contact|contacts|follow[\s-]?up|عميل|عملاء|العميل|عملاءنا|فرصة|فرص|عميل\s*محتمل|متابعة\s*العميل|يحتاجون\s*متابعة)/ui'
            ),
            ErpDomainRegistry::DOMAIN_SALES => self::match(
                $message,
                '/(sales|pos|مبيعات|بيع|نقطة\s*البيع|طلب\s*بيع|طلبات\s*البيع|أوامر\s*البيع|أمر\s*بيع)/ui'
            ),
            ErpDomainRegistry::DOMAIN_SUPPLIERS => self::match(
                $message,
                '/(supplier|suppliers|مورد|موردين|موردون|المورد|الموردين)/ui'
            ),
            ErpDomainRegistry::DOMAIN_INVENTORY => self::match(
                $message,
                '/(inventory|warehouse|stock|sku|مخزون|مستودع|صنف|أصناف|حركة|حركات|نقص\s*مخزون|انخفاض\s*المخزون|غير\s*كاف)/ui'
            ),
            ErpDomainRegistry::DOMAIN_PROCUREMENT => self::match(
                $message,
                '/(procurement|purchase\s*request|purchase\s*order|مشتريات|طلب\s*شراء|طلبات\s*الشراء|أوامر\s*شراء|أمر\s*شراء|متأخر|تأخير)/ui'
            ),
            ErpDomainRegistry::DOMAIN_HR => self::match(
                $message,
                '/(hr|human\s*resources|employee|employees|workforce|attendance|leave|موارد\s*بشرية|موظف|موظفين|الحضور|إجازة|اجازة)/ui'
            ),
            ErpDomainRegistry::DOMAIN_RECRUITMENT => self::match(
                $message,
                '/(recruitment|recruit|candidate|candidates|interview|hiring|توظيف|مرشح|مرشحين|مقابلة|مقابلات|استقدام)/ui'
            ),
            ErpDomainRegistry::DOMAIN_PROJECTS => self::match(
                $message,
                '/(project|projects|milestone|task|tasks|مشروع|مشاريع|مهمة|مهام)/ui'
            ),
            ErpDomainRegistry::DOMAIN_CONTRACTS => self::match(
                $message,
                '/(contract|contracts|renewal|عقد|عقود|تجديد\s*عقد)/ui'
            ),
            ErpDomainRegistry::DOMAIN_ASSETS => self::match(
                $message,
                '/(asset|assets|eam|maintenance|أصل|أصول|صيانة)/ui'
            ),
            ErpDomainRegistry::DOMAIN_PAYROLL => self::match(
                $message,
                '/(payroll|salary|salaries|payslip|رواتب|راتب|مسير|قسيمة\s*راتب|منصة\s*الرواتب)/ui'
            ),
            ErpDomainRegistry::DOMAIN_MANUFACTURING => self::match(
                $message,
                '/(manufacturing|production\s*order|bom|تصنيع|إنتاج|أمر\s*تصنيع)/ui'
            ),
            ErpDomainRegistry::DOMAIN_QUALITY => self::match(
                $message,
                '/(quality|qms|ncr|nonconform|inspection|جودة|عدم\s*مطابقة|فحص|منصة\s*الجودة)/ui'
            ),
            ErpDomainRegistry::DOMAIN_APPROVALS => self::match(
                $message,
                '/(approval|approvals|workflow|eap|موافقة|موافقات|سير\s*عمل|اعتماد)/ui'
            ),
            ErpDomainRegistry::DOMAIN_MARKETPLACE => self::match(
                $message,
                '/(marketplace|service\s*market|سوق\s*الخدمات|مقدم\s*خدمة)/ui'
            ),
            ErpDomainRegistry::DOMAIN_NOTIFICATIONS => self::match(
                $message,
                '/(notification|notifications|إشعار|إشعارات|تنبيه|تنبيهات)/ui'
            ),
            ErpDomainRegistry::DOMAIN_BI => self::match(
                $message,
                '/(business\s*intelligence|\bbi\b|kpi|analytics|ذكاء\s*الأعمال|مؤشر|مؤشرات)/ui'
            ),
            ErpDomainRegistry::DOMAIN_WEBSITE => self::match(
                $message,
                '/(website|cms|blog|موقع|محتوى\s*تسويقي|صفحات\s*الموقع)/ui'
            ),
        ];

        // Decision phrases that mention "follow up" should not alone force CRM unless customer cues exist.
        if ($decisionSupport && !$signals[ErpDomainRegistry::DOMAIN_CRM]
            && self::match($message, '/(ماذا\s*يجب|ما\s*أهم|أين\s*توجد|what\s+should|top\s*issues|follow\s*now)/ui')
        ) {
            // keep CRM false unless explicit customer terms already matched
        }

        $domains = [];
        foreach ($signals as $domainId => $hit) {
            if (!$hit || !ErpDomainRegistry::isActive($domainId)) {
                continue;
            }
            $meta = ErpDomainRegistry::resolve($domainId);
            if ($meta === null || !$ctx->moduleEnabled((string) $meta['module'])) {
                continue;
            }
            $domains[] = $domainId;
        }

        $crossCue = self::match(
            $message,
            '/(cross[\s-]?domain|across\s+domains|end[\s-]?to[\s-]?end|impact|linked|correlate|between|and\s+inventory|and\s+procurement|commercial|operational|financial|والمخزون|والمشتريات|والمورد|والمبيعات|والعملاء|والشحن|والتسليم|والمحاسبة|والمالية|تأثير|مرتبط|ارتباط|بسبب|عبر\s*المجالات|حلل\s*حالة|أهم\s*المشاكل|نقص\s*مخزون\s*مرتبط|متأثرة|top\s*issues|غير\s*كاف|سلسلة\s*تجارية|من\s*العميل\s*حتى|نهاية\s*إلى\s*نهاية|الوضع\s*المالي)/ui'
        );

        $writeIntent = self::hasWriteIntent($message);
        $mode = ($decisionSupport || $executiveIntent || count($domains) >= 2 || ($crossCue && count($domains) >= 1)) ? 'cross' : 'single';
        $minimal = true;

        if ($executiveIntent) {
            // Expand to entitled operational domains for KPI coverage (bounded).
            foreach ([
                ErpDomainRegistry::DOMAIN_SALES,
                ErpDomainRegistry::DOMAIN_INVENTORY,
                ErpDomainRegistry::DOMAIN_PROCUREMENT,
                ErpDomainRegistry::DOMAIN_ACCOUNTING,
                ErpDomainRegistry::DOMAIN_CRM,
                ErpDomainRegistry::DOMAIN_SUPPLIERS,
                ErpDomainRegistry::DOMAIN_LOGISTICS,
            ] as $id) {
                if (in_array($id, $domains, true)) {
                    continue;
                }
                $meta = ErpDomainRegistry::resolve($id);
                if ($meta !== null && $ctx->moduleEnabled((string) $meta['module'])) {
                    $domains[] = $id;
                }
                if (count($domains) >= 6) {
                    break;
                }
            }
            if (!in_array(ErpDomainRegistry::DOMAIN_EXECUTIVE, $domains, true)
                && ErpDomainRegistry::isActive(ErpDomainRegistry::DOMAIN_EXECUTIVE)
            ) {
                array_unshift($domains, ErpDomainRegistry::DOMAIN_EXECUTIVE);
            }
            $mode = 'cross';
        }

        if ($mode === 'cross' && count($domains) < 2) {
            // Expand only the minimum companion domains needed — never dump all domains blindly.
            $fallbackOrder = self::minimalFallbackOrder($domains, $decisionSupport);
            foreach ($fallbackOrder as $fallbackId) {
                if (in_array($fallbackId, $domains, true)) {
                    continue;
                }
                if (!ErpDomainRegistry::isActive($fallbackId)) {
                    continue;
                }
                $meta = ErpDomainRegistry::resolve($fallbackId);
                if ($meta !== null && $ctx->moduleEnabled((string) $meta['module'])) {
                    $domains[] = $fallbackId;
                }
                if (count($domains) >= 2) {
                    break;
                }
            }
            if (count($domains) < 2) {
                $mode = $decisionSupport ? 'cross' : 'single';
                if ($decisionSupport) {
                    foreach (ErpDomainRegistry::activeDomainIds() as $id) {
                        $meta = ErpDomainRegistry::resolve($id);
                        if ($meta !== null && $ctx->moduleEnabled((string) $meta['module']) && !in_array($id, $domains, true)) {
                            $domains[] = $id;
                        }
                        if (count($domains) >= 3) {
                            break;
                        }
                    }
                }
                if (count($domains) < 2) {
                    $mode = 'single';
                }
            }
        }

        // Decision overview with no domain keywords: scan a small operational core only.
        if ($decisionSupport && $domains === []) {
            $mode = 'cross';
            foreach ([
                ErpDomainRegistry::DOMAIN_SALES,
                ErpDomainRegistry::DOMAIN_INVENTORY,
                ErpDomainRegistry::DOMAIN_PROCUREMENT,
                ErpDomainRegistry::DOMAIN_LOGISTICS,
            ] as $id) {
                if (!ErpDomainRegistry::isActive($id)) {
                    continue;
                }
                $meta = ErpDomainRegistry::resolve($id);
                if ($meta !== null && $ctx->moduleEnabled((string) $meta['module'])) {
                    $domains[] = $id;
                }
            }
            $minimal = true;
        }

        if ($mode === 'single' && $domains === []) {
            $domains = [ErpDomainRegistry::defaultDomainId()];
        }

        $primary = $domains[0] ?? ErpDomainRegistry::defaultDomainId();
        if ($mode === 'cross' && in_array(ErpDomainRegistry::DOMAIN_EXECUTIVE, $domains, true)) {
            $primary = ErpDomainRegistry::DOMAIN_EXECUTIVE;
        } elseif ($mode === 'cross' && in_array(ErpDomainRegistry::DOMAIN_ACCOUNTING, $domains, true)) {
            $primary = ErpDomainRegistry::DOMAIN_ACCOUNTING;
        } elseif ($mode === 'cross' && in_array(ErpDomainRegistry::DOMAIN_LOGISTICS, $domains, true)) {
            $primary = ErpDomainRegistry::DOMAIN_LOGISTICS;
        } elseif ($mode === 'cross' && in_array(ErpDomainRegistry::DOMAIN_CRM, $domains, true)) {
            $primary = ErpDomainRegistry::DOMAIN_CRM;
        } elseif ($mode === 'cross' && in_array(ErpDomainRegistry::DOMAIN_SALES, $domains, true)) {
            $primary = ErpDomainRegistry::DOMAIN_SALES;
        } elseif ($mode === 'cross' && in_array(ErpDomainRegistry::DOMAIN_SUPPLIERS, $domains, true)) {
            $primary = ErpDomainRegistry::DOMAIN_SUPPLIERS;
        } elseif ($mode === 'cross' && in_array(ErpDomainRegistry::DOMAIN_INVENTORY, $domains, true)) {
            $primary = ErpDomainRegistry::DOMAIN_INVENTORY;
        }

        $intentKind = 'query';
        if ($executiveIntent) {
            $intentKind = 'executive';
        } elseif ($decisionSupport) {
            $intentKind = 'decision';
        } elseif ($mode === 'cross') {
            $intentKind = 'analysis';
        }

        return [
            'mode' => $mode,
            'domains' => array_values(array_unique($domains)),
            'signals' => $signals,
            'write_intent' => $writeIntent,
            'primary' => $primary,
            'intent_kind' => $intentKind,
            'decision_support' => $decisionSupport || $executiveIntent,
            'minimal' => $minimal,
            'executive' => $executiveIntent,
            'proactive' => $proactiveIntent,
            'learning' => $learningIntent,
            'memory' => $memoryIntent,
        ];
    }

    /**
     * @param list<string> $already
     * @return list<string>
     */
    private static function minimalFallbackOrder(array $already, bool $decisionSupport): array
    {
        if ($decisionSupport) {
            return [
                ErpDomainRegistry::DOMAIN_SALES,
                ErpDomainRegistry::DOMAIN_INVENTORY,
                ErpDomainRegistry::DOMAIN_PROCUREMENT,
                ErpDomainRegistry::DOMAIN_ACCOUNTING,
                ErpDomainRegistry::DOMAIN_LOGISTICS,
                ErpDomainRegistry::DOMAIN_CRM,
                ErpDomainRegistry::DOMAIN_SUPPLIERS,
            ];
        }
        if (in_array(ErpDomainRegistry::DOMAIN_ACCOUNTING, $already, true)) {
            return [
                ErpDomainRegistry::DOMAIN_SALES,
                ErpDomainRegistry::DOMAIN_PROCUREMENT,
                ErpDomainRegistry::DOMAIN_SUPPLIERS,
                ErpDomainRegistry::DOMAIN_INVENTORY,
            ];
        }
        if (in_array(ErpDomainRegistry::DOMAIN_LOGISTICS, $already, true)) {
            return [
                ErpDomainRegistry::DOMAIN_SALES,
                ErpDomainRegistry::DOMAIN_INVENTORY,
                ErpDomainRegistry::DOMAIN_CRM,
                ErpDomainRegistry::DOMAIN_ACCOUNTING,
            ];
        }
        if (in_array(ErpDomainRegistry::DOMAIN_CRM, $already, true)) {
            return [
                ErpDomainRegistry::DOMAIN_SALES,
                ErpDomainRegistry::DOMAIN_INVENTORY,
                ErpDomainRegistry::DOMAIN_ACCOUNTING,
            ];
        }
        if (in_array(ErpDomainRegistry::DOMAIN_SALES, $already, true)) {
            return [
                ErpDomainRegistry::DOMAIN_INVENTORY,
                ErpDomainRegistry::DOMAIN_PROCUREMENT,
                ErpDomainRegistry::DOMAIN_ACCOUNTING,
            ];
        }
        return [
            ErpDomainRegistry::DOMAIN_ACCOUNTING,
            ErpDomainRegistry::DOMAIN_LOGISTICS,
            ErpDomainRegistry::DOMAIN_CRM,
            ErpDomainRegistry::DOMAIN_SALES,
            ErpDomainRegistry::DOMAIN_PROCUREMENT,
            ErpDomainRegistry::DOMAIN_INVENTORY,
            ErpDomainRegistry::DOMAIN_SUPPLIERS,
        ];
    }

    /**
     * Build a READ/ANALYSIS plan from existing tools only (no invented tools, no writes).
     * Decision intents prefer core intelligence tools (minimal set).
     *
     * @param array{mode: string, domains: list<string>, decision_support?: bool, intent_kind?: string} $intent
     * @return list<array{tool: string, arguments: array, domain: string, purpose: string}>
     */
    public static function buildPlan(array $intent, ProcurementAgentContext $ctx, int $maxSteps = 8): array
    {
        $domains = $intent['domains'] ?? [];
        if (!is_array($domains)) {
            return [];
        }
        $decision = !empty($intent['decision_support']) || (($intent['intent_kind'] ?? '') === 'decision');
        $maxSteps = max(1, min(12, $decision ? min($maxSteps, 6) : $maxSteps));
        $plan = [];
        $seen = [];
        $seenPurposes = [];

        $add = static function (string $tool, array $args, string $purpose) use (&$plan, &$seen, &$seenPurposes, $maxSteps, $ctx): void {
            if (count($plan) >= $maxSteps) {
                return;
            }
            if (isset($seenPurposes[$purpose])) {
                return; // loop / duplicate purpose prevention
            }
            $domain = ErpDomainRegistry::domainForTool($tool);
            if ($domain === null || !ErpDomainRegistry::isActive($domain)) {
                return;
            }
            $meta = ErpDomainRegistry::resolve($domain);
            if ($meta === null || !$ctx->moduleEnabled((string) $meta['module'])) {
                return;
            }
            $toolMeta = ErpToolRegistry::getTool($tool);
            if ($toolMeta === null || !empty($toolMeta['write'])) {
                return;
            }
            $key = $tool . '|' . md5((string) json_encode($args, JSON_UNESCAPED_UNICODE));
            if (isset($seen[$key])) {
                return;
            }
            $seen[$key] = true;
            $seenPurposes[$purpose] = true;
            $plan[] = [
                'tool' => $tool,
                'arguments' => $args,
                'domain' => $domain,
                'purpose' => $purpose,
            ];
        };

        $has = static fn(string $id): bool => in_array($id, $domains, true);
        $limit = $decision ? 8 : 10;
        $executive = (($intent['intent_kind'] ?? '') === 'executive') || !empty($intent['executive']);
        $proactive = !empty($intent['proactive']);
        $learning = !empty($intent['learning']);
        $memory = !empty($intent['memory']);

        // Prefer one core correlation tool first.
        if ($executive || $has(ErpDomainRegistry::DOMAIN_EXECUTIVE)) {
            if ($memory) {
                $add('get_relevant_operational_context', ['limit' => $limit], 'operational_memory_core');
            }
            if ($learning) {
                $add('analyze_operational_learning', ['limit' => $limit], 'operational_learning_core');
            }
            if ($proactive) {
                $add('scan_early_warnings', ['limit' => $limit], 'proactive_early_warning_core');
            }
            $add('analyze_executive_intelligence', ['limit' => $limit], 'executive_intelligence_core');
            if ($executive) {
                return $plan;
            }
        }

        if ($has(ErpDomainRegistry::DOMAIN_ACCOUNTING)
            && ($has(ErpDomainRegistry::DOMAIN_SALES)
                || $has(ErpDomainRegistry::DOMAIN_PROCUREMENT)
                || $has(ErpDomainRegistry::DOMAIN_SUPPLIERS)
                || $has(ErpDomainRegistry::DOMAIN_INVENTORY)
                || $has(ErpDomainRegistry::DOMAIN_LOGISTICS)
                || $decision)
        ) {
            $add('analyze_financial_intelligence', ['limit' => $limit], 'financial_intelligence_core');
        }

        if ($has(ErpDomainRegistry::DOMAIN_LOGISTICS)
            && ($has(ErpDomainRegistry::DOMAIN_CRM)
                || $has(ErpDomainRegistry::DOMAIN_SALES)
                || $has(ErpDomainRegistry::DOMAIN_INVENTORY)
                || $decision)
            && !$has(ErpDomainRegistry::DOMAIN_ACCOUNTING)
        ) {
            $add('analyze_logistics_end_to_end', ['limit' => $limit], 'logistics_e2e_core');
        }

        if ($has(ErpDomainRegistry::DOMAIN_CRM)
            && ($has(ErpDomainRegistry::DOMAIN_SALES) || $has(ErpDomainRegistry::DOMAIN_INVENTORY))
            && !$has(ErpDomainRegistry::DOMAIN_LOGISTICS)
            && !$has(ErpDomainRegistry::DOMAIN_ACCOUNTING)
        ) {
            $add('analyze_crm_commercial_intelligence', ['limit' => $limit], 'crm_commercial_core');
        }

        if ($has(ErpDomainRegistry::DOMAIN_SALES)
            && $has(ErpDomainRegistry::DOMAIN_INVENTORY)
            && ($has(ErpDomainRegistry::DOMAIN_PROCUREMENT) || $has(ErpDomainRegistry::DOMAIN_SUPPLIERS) || $decision)
            && !$has(ErpDomainRegistry::DOMAIN_CRM)
            && !$has(ErpDomainRegistry::DOMAIN_LOGISTICS)
            && !$has(ErpDomainRegistry::DOMAIN_ACCOUNTING)
        ) {
            $add('analyze_sales_cross_domain', ['limit' => $limit], 'sales_cross_domain_core');
        }

        if ($has(ErpDomainRegistry::DOMAIN_SUPPLIERS)
            && $has(ErpDomainRegistry::DOMAIN_PROCUREMENT)
            && $has(ErpDomainRegistry::DOMAIN_INVENTORY)
            && !$has(ErpDomainRegistry::DOMAIN_SALES)
            && !$has(ErpDomainRegistry::DOMAIN_CRM)
            && !$has(ErpDomainRegistry::DOMAIN_LOGISTICS)
            && !$has(ErpDomainRegistry::DOMAIN_ACCOUNTING)
        ) {
            $add('analyze_supplier_cross_domain', ['limit' => $limit], 'cross_domain_core');
        }

        if ($decision) {
            // Minimal companion reads for decision support — avoid full link fan-out.
            if ($has(ErpDomainRegistry::DOMAIN_ACCOUNTING) && !isset($seenPurposes['financial_intelligence_core'])) {
                $add('analyze_accounting', ['limit' => $limit], 'accounting_intelligence');
            }
            if ($has(ErpDomainRegistry::DOMAIN_LOGISTICS) && !isset($seenPurposes['logistics_e2e_core']) && !$has(ErpDomainRegistry::DOMAIN_ACCOUNTING)) {
                $add('analyze_logistics', ['limit' => $limit], 'logistics_intelligence');
            }
            if ($has(ErpDomainRegistry::DOMAIN_CRM) && !$has(ErpDomainRegistry::DOMAIN_LOGISTICS) && !$has(ErpDomainRegistry::DOMAIN_ACCOUNTING)) {
                $add('analyze_crm', ['limit' => $limit], 'crm_intelligence');
            }
            if ($has(ErpDomainRegistry::DOMAIN_SALES) && !$has(ErpDomainRegistry::DOMAIN_LOGISTICS) && !$has(ErpDomainRegistry::DOMAIN_ACCOUNTING) && !isset($seenPurposes['sales_cross_domain_core'])) {
                $add('analyze_sales', ['limit' => $limit], 'sales_intelligence');
            }
            if ($has(ErpDomainRegistry::DOMAIN_INVENTORY) && !$has(ErpDomainRegistry::DOMAIN_LOGISTICS) && !$has(ErpDomainRegistry::DOMAIN_ACCOUNTING)) {
                $add('analyze_inventory', ['limit' => $limit], 'inventory_intelligence');
            }
            if ($has(ErpDomainRegistry::DOMAIN_PROCUREMENT) && !$has(ErpDomainRegistry::DOMAIN_LOGISTICS) && !$has(ErpDomainRegistry::DOMAIN_ACCOUNTING)) {
                $add('list_pending_approvals', ['limit' => $limit], 'pending_approvals');
                $add('get_procurement_operational_guidance', ['limit' => $limit], 'operational_guidance');
            }
            if ($has(ErpDomainRegistry::DOMAIN_SUPPLIERS) && !$has(ErpDomainRegistry::DOMAIN_LOGISTICS) && !$has(ErpDomainRegistry::DOMAIN_ACCOUNTING) && !isset($seenPurposes['cross_domain_core'])) {
                $add('analyze_suppliers', ['limit' => $limit], 'supplier_intelligence');
            }
            return $plan;
        }

        if ($has(ErpDomainRegistry::DOMAIN_ACCOUNTING)) {
            $add('analyze_accounting', ['limit' => $limit], 'accounting_intelligence');
            $add('get_accounting_operational_guidance', ['limit' => $limit], 'accounting_guidance');
            if ($has(ErpDomainRegistry::DOMAIN_SALES) && !isset($seenPurposes['financial_intelligence_core'])) {
                $add('get_accounting_sales_links', ['limit' => $limit], 'accounting_sales_links');
            }
            if ($has(ErpDomainRegistry::DOMAIN_PROCUREMENT) && !isset($seenPurposes['financial_intelligence_core'])) {
                $add('get_accounting_procurement_links', ['limit' => $limit], 'accounting_procurement_links');
            }
            if ($has(ErpDomainRegistry::DOMAIN_SUPPLIERS) && !isset($seenPurposes['financial_intelligence_core'])) {
                $add('get_accounting_supplier_links', ['limit' => $limit], 'accounting_supplier_links');
            }
            if ($has(ErpDomainRegistry::DOMAIN_INVENTORY) && !isset($seenPurposes['financial_intelligence_core'])) {
                $add('get_accounting_inventory_links', ['limit' => $limit], 'accounting_inventory_links');
            }
            if ($has(ErpDomainRegistry::DOMAIN_LOGISTICS) && !isset($seenPurposes['financial_intelligence_core'])) {
                $add('get_accounting_logistics_links', ['limit' => $limit], 'accounting_logistics_links');
            }
        }

        if ($has(ErpDomainRegistry::DOMAIN_LOGISTICS) && !$has(ErpDomainRegistry::DOMAIN_ACCOUNTING)) {
            $add('analyze_logistics', ['limit' => $limit], 'logistics_intelligence');
            $add('get_logistics_operational_guidance', ['limit' => $limit], 'logistics_guidance');
            if ($has(ErpDomainRegistry::DOMAIN_CRM)) {
                $add('get_logistics_crm_links', ['limit' => $limit], 'logistics_crm_links');
            }
            if ($has(ErpDomainRegistry::DOMAIN_SALES)) {
                $add('get_logistics_sales_links', ['limit' => $limit], 'logistics_sales_links');
            }
            if ($has(ErpDomainRegistry::DOMAIN_INVENTORY)) {
                $add('get_logistics_inventory_links', ['limit' => $limit], 'logistics_inventory_links');
            }
            if ($has(ErpDomainRegistry::DOMAIN_PROCUREMENT)) {
                $add('get_logistics_procurement_links', ['limit' => $limit], 'logistics_procurement_links');
            }
            if ($has(ErpDomainRegistry::DOMAIN_SUPPLIERS)) {
                $add('get_logistics_supplier_links', ['limit' => $limit], 'logistics_supplier_links');
            }
        }

        if ($has(ErpDomainRegistry::DOMAIN_CRM) && !$has(ErpDomainRegistry::DOMAIN_LOGISTICS)) {
            $add('analyze_crm', ['limit' => $limit], 'crm_intelligence');
            $add('get_crm_operational_guidance', ['limit' => $limit], 'crm_guidance');
            if ($has(ErpDomainRegistry::DOMAIN_SALES)) {
                $add('get_crm_sales_links', ['limit' => $limit], 'crm_sales_links');
            }
            if ($has(ErpDomainRegistry::DOMAIN_INVENTORY)) {
                $add('get_crm_inventory_links', ['limit' => $limit], 'crm_inventory_links');
            }
            if ($has(ErpDomainRegistry::DOMAIN_PROCUREMENT)) {
                $add('get_crm_procurement_links', ['limit' => $limit], 'crm_procurement_links');
            }
            if ($has(ErpDomainRegistry::DOMAIN_SUPPLIERS)) {
                $add('get_crm_supplier_links', ['limit' => $limit], 'crm_supplier_links');
            }
        }

        if ($has(ErpDomainRegistry::DOMAIN_SALES) && !$has(ErpDomainRegistry::DOMAIN_LOGISTICS)) {
            $add('analyze_sales', ['limit' => $limit], 'sales_intelligence');
            $add('get_sales_operational_guidance', ['limit' => $limit], 'sales_guidance');
            if ($has(ErpDomainRegistry::DOMAIN_INVENTORY) && !$has(ErpDomainRegistry::DOMAIN_CRM)) {
                $add('get_sales_inventory_links', ['limit' => $limit, 'shortfall_only' => true], 'sales_inventory_links');
            }
            if ($has(ErpDomainRegistry::DOMAIN_PROCUREMENT) && !$has(ErpDomainRegistry::DOMAIN_CRM)) {
                $add('get_sales_procurement_links', ['limit' => $limit], 'sales_procurement_links');
            }
            if ($has(ErpDomainRegistry::DOMAIN_SUPPLIERS) && !$has(ErpDomainRegistry::DOMAIN_CRM)) {
                $add('get_sales_supplier_links', ['limit' => $limit], 'sales_supplier_links');
            }
        }

        if ($has(ErpDomainRegistry::DOMAIN_SUPPLIERS) && !$has(ErpDomainRegistry::DOMAIN_LOGISTICS)) {
            $add('analyze_suppliers', ['limit' => $limit], 'supplier_intelligence');
            if ($has(ErpDomainRegistry::DOMAIN_PROCUREMENT)) {
                $add('get_supplier_procurement_links', ['limit' => $limit, 'open_only' => false], 'supplier_procurement_links');
            }
            if ($has(ErpDomainRegistry::DOMAIN_INVENTORY)) {
                $add('get_supplier_inventory_links', ['limit' => $limit], 'supplier_inventory_links');
            }
        }

        if ($has(ErpDomainRegistry::DOMAIN_INVENTORY) && !$has(ErpDomainRegistry::DOMAIN_LOGISTICS)) {
            $add('analyze_inventory', ['limit' => $limit], 'inventory_intelligence');
            if ($has(ErpDomainRegistry::DOMAIN_PROCUREMENT)) {
                $add('get_inventory_procurement_links', ['limit' => $limit, 'low_stock_only' => true], 'inventory_procurement_links');
            }
        }

        if ($has(ErpDomainRegistry::DOMAIN_PROCUREMENT) && !$has(ErpDomainRegistry::DOMAIN_LOGISTICS)) {
            $add('analyze_advanced_procurement_operations', ['limit' => $limit], 'procurement_operations');
            $add('list_pending_approvals', ['limit' => $limit], 'pending_approvals');
            $add('get_procurement_operational_guidance', ['limit' => $limit], 'operational_guidance');
        }

        return $plan;
    }

    public static function hasWriteIntent(string $message): bool
    {
        return self::match(
            $message,
            '/(create|update|cancel|submit|write|أنشئ|إنشاء|عدّل|عدل|ألغ|الغ|أرسل|ارسل|تنفيذ\s*كتابة)/ui'
        );
    }

    public static function hasDecisionIntent(string $message): bool
    {
        return self::match(
            $message,
            '/(ماذا\s*يجب\s*أن\s*أتابع|ما\s*أهم\s*المشاكل|أين\s*توجد\s*المشاكل|ماذا\s*أتابع\s*الآن|أولويات\s*التشغيل|دعم\s*القرار|what\s+should\s+i\s+(follow|do|prioritize)|top\s*(operational\s*)?(issues|problems|priorities)|what\s+to\s+follow\s+now|decision\s+support|operational\s+priorities|where\s+are\s+the\s+(operational\s+)?(issues|problems))/ui'
        );
    }

    public static function hasProactiveIntent(string $message): bool
    {
        return self::match(
            $message,
            '/(مسح\s*استباقي|تحذير\s*مبكر|تنبيه\s*مبكر|التحذيرات\s*الحالية|افحص\s*الوضع|افحص\s*الحالة|امسح\s*الوضع|ملخص\s*استباقي|إنذارات|انذارات|early\s*warning|proactive\s*scan|scan\s+current\s+state|scan\s+the\s+(company|system)|current\s+warnings|proactive\s+digest|what\s+should\s+i\s+watch|watch\s+list)/ui'
        );
    }

    public static function hasLearningIntent(string $message): bool
    {
        return self::match(
            $message,
            '/(تعلم\s*تشغيلي|فعالية\s*التوصيات|فعالية\s*التحذيرات|دقة\s*التوقع|تحسين\s*مستمر|نتائج\s*الإجراءات|operational\s+learning|recommendation\s+effectiveness|warning\s+effectiveness|forecast\s+accuracy|continuous\s+optimization|action\s+outcomes|learning\s+signals)/ui'
        );
    }

    public static function hasMemoryIntent(string $message): bool
    {
        return self::match(
            $message,
            '/(ذاكرة\s*تشغيلية|سياق\s*تشغيلي|القرارات\s*السابقة|الإجراءات\s*السابقة|النتائج\s*السابقة|أنماط\s*متكررة|سياق\s*ذي\s*صلة|operational\s+memory|operational\s+context|previous\s+decisions|previous\s+actions|previous\s+outcomes|recurring\s+patterns|relevant\s+context)/ui'
        );
    }

    public static function hasWorkflowIntent(string $message): bool
    {
        return self::match(
            $message,
            '/(سير\s*عمل|خطة\s*متعددة|خطوات\s*متعددة|نفّذ\s*خطة|workflow|multi[\s-]?step|step\s*by\s*step|execute\s+plan|create\s+and\s+submit|أنشئ\s*وأرسل|انشئ\s*وارسل)/ui'
        ) || (
            self::hasWriteIntent($message)
            && self::match($message, '/(ثم|بعدها|بعد\s*ذلك|and\s+then|then\s+submit|ثم\s*أرسل|ثم\s*ارسل)/ui')
        );
    }

    public static function hasExecutiveIntent(string $message): bool
    {
        // Keep narrow — do not steal Accounting "financial position" / domain correlation queries.
        return self::match(
            $message,
            '/(ملخص\s*الشركة|ملخص\s*تنفيذي|أهم\s*المخاطر\s*(اليوم)?|وضع\s*المبيعات|وضع\s*السيولة\s*والمستحقات|ماذا\s*يحدث\s*في\s*الشركة|أين\s*المشكلة|ما\s*أهم\s*شيء\s*الآن|الاتجاه\s*المتوقع|قارن\s*المبيعات\s*بالمشتريات|يحتاج\s*تدخلي|executive\s+summary|company\s+summary|what\s+is\s+happening\s+in\s+the\s+company|top\s+risks\s+(today|now)|executive\s+kpis?|executive\s+forecast|executive\s+intelligence|what\s+should\s+i\s+focus\s+on|compare\s+sales\s+(to|with|and)\s+(procurement|inventory|purchases))/ui'
        ) || self::hasProactiveIntent($message) || self::hasLearningIntent($message) || self::hasMemoryIntent($message);
    }

    private static function match(string $message, string $pattern): bool
    {
        return $message !== '' && preg_match($pattern, $message) === 1;
    }
}
