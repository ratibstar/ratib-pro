<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\Inventory;

/**
 * Executive Intelligence Layer (not an agent / not a BI engine).
 * Evidence-first KPIs, trends, risks, priorities, forecasts, and executive summaries
 * over live domain tool/service data only. LLM never supplies numbers.
 */
final class ErpExecutiveIntelligenceLayer
{
    public const PRIORITY_CRITICAL = 'CRITICAL';
    public const PRIORITY_HIGH = 'HIGH';
    public const PRIORITY_MEDIUM = 'MEDIUM';
    public const PRIORITY_LOW = 'LOW';

    public const TREND_INCREASING = 'increasing';
    public const TREND_DECREASING = 'decreasing';
    public const TREND_STABLE = 'stable';
    public const TREND_VOLATILE = 'volatile';
    public const TREND_INSUFFICIENT = 'insufficient_data';

    /** @var array<string, mixed> */
    private static array $memo = [];

    public static function clearMemo(): void
    {
        self::$memo = [];
    }

    /**
     * Full executive pack from live tenant evidence.
     *
     * @param array<string, mixed> $intent
     * @return array<string, mixed>
     */
    public static function build(ProcurementAgentContext $ctx, array $intent = [], int $limit = 15): array
    {
        $companyId = (int) $ctx->companyId;
        if ($companyId < 1) {
            return self::emptyPack('tenant_mismatch');
        }

        $memoKey = 'build:' . $companyId . ':' . md5((string) json_encode([$intent, $limit]));
        if (isset(self::$memo[$memoKey])) {
            return self::$memo[$memoKey];
        }

        $domains = self::entitledDomains($ctx);
        $kpis = self::collectKpis($ctx, $domains, $limit);
        $trends = self::analyzeTrends($ctx, $kpis, $domains);
        $correlations = self::buildCorrelations($kpis, $domains);
        $risks = self::detectRisks($kpis, $correlations, $domains);
        $priorities = self::prioritize($risks, $kpis);
        $forecasts = self::buildForecasts($ctx, $kpis, $trends, $domains);
        $summary = self::buildExecutiveSummary($kpis, $trends, $risks, $priorities, $forecasts, $correlations);
        $actions = self::recommendedActions($priorities, $risks);
        $explain = self::explainabilityPack($kpis, $trends, $risks, $forecasts);

        $pack = [
            'data_source' => 'live_tenant',
            'as_of' => date('Y-m-d H:i:s'),
            'company_id' => $companyId,
            'domains' => $domains,
            'evidence_first' => true,
            'auto_execute' => false,
            'kpis' => $kpis,
            'trends' => $trends,
            'correlations' => $correlations,
            'risks' => array_slice($risks, 0, $limit),
            'priorities' => array_slice($priorities, 0, $limit),
            'forecasts' => $forecasts,
            'executive_summary' => $summary,
            'recommended_actions' => array_slice($actions, 0, $limit),
            'explainability' => $explain,
            'decision_support' => [
                'top_priority' => $priorities[0] ?? null,
                'top_risk' => $risks[0] ?? null,
                'follow_now' => array_slice(array_map(
                    static fn($p) => (string) ($p['title'] ?? $p['code'] ?? ''),
                    $priorities
                ), 0, 5),
                'insufficient_data' => empty($kpis['items']),
            ],
            'data_sufficient' => !empty($kpis['items']),
            'notes' => empty($kpis['items'])
                ? ['INSUFFICIENT EVIDENCE']
                : ['numbers_from_live_services_only', 'llm_not_number_source'],
            'operational_memory' => ErpOperationalMemoryLayer::forIntelligence($ctx, $intent, [
                'open_warning_codes' => array_values(array_filter(array_map(
                    static fn($r) => is_array($r) ? (string) ($r['code'] ?? '') : '',
                    $risks
                ))),
            ]),
        ];

        return self::$memo[$memoKey] = $pack;
    }

    /**
     * @return list<string>
     */
    public static function entitledDomains(ProcurementAgentContext $ctx): array
    {
        $out = [];
        foreach (ErpDomainRegistry::activeDomainIds() as $id) {
            $meta = ErpDomainRegistry::resolve($id);
            if ($meta === null) {
                continue;
            }
            if ($ctx->moduleEnabled((string) $meta['module'])) {
                $out[] = $id;
            }
        }
        return $out;
    }

    /**
     * @param list<string> $domains
     * @return array<string, mixed>
     */
    public static function collectKpis(ProcurementAgentContext $ctx, array $domains, int $limit = 20): array
    {
        $companyId = (int) $ctx->companyId;
        $items = [];
        $evidence = [];

        $add = static function (
            string $code,
            string $domain,
            string $label,
            $value,
            string $unit,
            string $source,
            array $calc,
            bool $available = true
        ) use (&$items, &$evidence): void {
            $row = [
                'code' => $code,
                'domain' => $domain,
                'label' => $label,
                'value' => $available ? $value : null,
                'unit' => $unit,
                'available' => $available,
                'status' => $available ? 'ok' : 'INSUFFICIENT EVIDENCE',
                'evidence' => [
                    'domain' => $domain,
                    'source' => $source,
                    'calculation' => $calc,
                    'as_of' => date('Y-m-d'),
                ],
            ];
            $items[] = $row;
            $evidence[] = $row['evidence'];
        };

        // Baseline analytics (existing service)
        try {
            $base = (new ErpAnalyticsService())->companyKpi($companyId);
            if (in_array('procurement', $domains, true)) {
                $add('purchase_requests', 'procurement', 'Purchase requests', (int) ($base['purchase_requests'] ?? 0), 'count', 'ErpAnalyticsService::companyKpi', ['field' => 'purchase_requests']);
                $add('purchase_orders', 'procurement', 'Purchase orders', (int) ($base['purchase_orders'] ?? 0), 'count', 'ErpAnalyticsService::companyKpi', ['field' => 'purchase_orders']);
                $add('pending_workflows', 'procurement', 'Pending approvals', (int) ($base['pending_workflows'] ?? 0), 'count', 'ErpAnalyticsService::companyKpi', ['field' => 'pending_workflows']);
            }
            if (in_array('inventory', $domains, true)) {
                $add('inventory_value', 'inventory', 'Inventory value', (float) ($base['inventory_value'] ?? 0), 'amount', 'ErpAnalyticsService::companyKpi', ['field' => 'inventory_value']);
                $add('low_stock', 'inventory', 'Low stock items', (int) ($base['low_stock'] ?? $base['low_stock_items'] ?? 0), 'count', 'ErpAnalyticsService::companyKpi', ['field' => 'low_stock']);
            }
            if (in_array('suppliers', $domains, true)) {
                $add('suppliers', 'suppliers', 'Suppliers', (int) ($base['suppliers'] ?? 0), 'count', 'ErpAnalyticsService::companyKpi', ['field' => 'suppliers']);
            }
        } catch (\Throwable $e) {
            // leave domain KPIs to tool fallbacks
        }

        if (in_array('sales', $domains, true) && $ctx->can('pos.view')) {
            $sales = SalesToolExecutor::execute('analyze_sales', ['limit' => $limit], $ctx);
            $data = is_array($sales['data'] ?? null) ? $sales['data'] : [];
            $summary = is_array($data['summary'] ?? null) ? $data['summary'] : [];
            if (!empty($sales['success'])) {
                $add('sales_order_count', 'sales', 'Sales orders (sample)', (int) ($summary['order_count'] ?? $summary['sample_count'] ?? count($data['orders'] ?? [])), 'count', 'SalesToolExecutor::analyze_sales', ['summary' => array_keys($summary)]);
                $add('sales_open_count', 'sales', 'Open sales orders', (int) ($summary['open_count'] ?? $summary['open_order_count'] ?? 0), 'count', 'SalesToolExecutor::analyze_sales', ['field' => 'open_count']);
                if (isset($summary['total_amount']) || isset($summary['revenue_total'])) {
                    $add('sales_volume', 'sales', 'Sales volume', (float) ($summary['total_amount'] ?? $summary['revenue_total'] ?? 0), 'amount', 'SalesToolExecutor::analyze_sales', ['field' => 'total_amount']);
                } else {
                    // Derive from live order totals when present
                    $orders = is_array($data['orders'] ?? null) ? $data['orders'] : (is_array($data['recent_orders'] ?? null) ? $data['recent_orders'] : []);
                    $sum = 0.0;
                    foreach ($orders as $o) {
                        $sum += (float) ($o['total'] ?? 0);
                    }
                    if ($orders !== []) {
                        $add('sales_volume', 'sales', 'Sales volume (sample)', $sum, 'amount', 'SalesToolExecutor::analyze_sales', ['agg' => 'sum(order.total)', 'n' => count($orders)]);
                    }
                }
            }
        }

        if (in_array('crm', $domains, true) && $ctx->can('crm.view')) {
            $crm = CrmToolExecutor::execute('analyze_crm', ['limit' => $limit], $ctx);
            $data = is_array($crm['data'] ?? null) ? $crm['data'] : [];
            $summary = is_array($data['summary'] ?? null) ? $data['summary'] : [];
            if (!empty($crm['success'])) {
                $add('crm_customers', 'crm', 'CRM customers', (int) ($summary['customer_count'] ?? 0), 'count', 'CrmToolExecutor::analyze_crm', ['field' => 'customer_count']);
                $add('crm_followups', 'crm', 'CRM follow-ups', (int) ($summary['followup_count'] ?? $summary['open_followup_count'] ?? count($data['follow_up'] ?? [])), 'count', 'CrmToolExecutor::analyze_crm', ['field' => 'followups']);
                $add('crm_at_risk', 'crm', 'At-risk customers', (int) ($summary['at_risk_count'] ?? count($data['at_risk_customers'] ?? [])), 'count', 'CrmToolExecutor::analyze_crm', ['field' => 'at_risk']);
            }
        }

        if (in_array('inventory', $domains, true) && $ctx->can('inventory.manage')) {
            $inv = InventoryToolExecutor::execute('analyze_inventory', ['limit' => $limit], $ctx);
            $data = is_array($inv['data'] ?? null) ? $inv['data'] : [];
            $summary = is_array($data['summary'] ?? null) ? $data['summary'] : [];
            if (!empty($inv['success'])) {
                if (!self::hasKpi($items, 'low_stock')) {
                    $add('low_stock', 'inventory', 'Low stock items', (int) ($summary['low_stock_count'] ?? count($data['low_stock'] ?? [])), 'count', 'InventoryToolExecutor::analyze_inventory', ['field' => 'low_stock_count']);
                }
                $add('stock_movements', 'inventory', 'Recent stock movements', (int) ($summary['movement_count'] ?? 0), 'count', 'InventoryToolExecutor::analyze_inventory', ['field' => 'movement_count']);
            }
        }

        if (in_array('logistics', $domains, true) && $ctx->can('logistics.view')) {
            $log = LogisticsToolExecutor::execute('analyze_logistics', ['limit' => $limit], $ctx);
            $data = is_array($log['data'] ?? null) ? $log['data'] : [];
            $summary = is_array($data['summary'] ?? null) ? $data['summary'] : [];
            if (!empty($log['success'])) {
                $add('logistics_open_shipments', 'logistics', 'Open shipments', (int) ($summary['open_shipment_count'] ?? 0), 'count', 'LogisticsToolExecutor::analyze_logistics', ['field' => 'open_shipment_count']);
                $add('logistics_delayed', 'logistics', 'Delayed shipments', (int) ($summary['delayed_shipment_count'] ?? 0), 'count', 'LogisticsToolExecutor::analyze_logistics', ['field' => 'delayed_shipment_count']);
            }
        }

        if (in_array('accounting', $domains, true) && $ctx->can('accounting.view')) {
            $acct = AccountingToolExecutor::execute('analyze_accounting', ['limit' => $limit], $ctx);
            $data = is_array($acct['data'] ?? null) ? $acct['data'] : [];
            $totals = is_array($data['totals'] ?? null) ? $data['totals'] : [];
            $cfo = is_array($data['cfo_metrics'] ?? null) ? $data['cfo_metrics'] : [];
            $summary = is_array($data['summary'] ?? null) ? $data['summary'] : [];
            if (!empty($acct['success'])) {
                $add('ar_open', 'accounting', 'Receivables open', (float) ($totals['ar_open'] ?? $cfo['ar_open'] ?? 0), 'amount', 'AccountingToolExecutor::analyze_accounting', ['field' => 'ar_open']);
                $add('ap_open', 'accounting', 'Payables open', (float) ($totals['ap_open'] ?? $cfo['ap_open'] ?? 0), 'amount', 'AccountingToolExecutor::analyze_accounting', ['field' => 'ap_open']);
                $add('overdue_receivables', 'accounting', 'Overdue receivables', (int) ($totals['overdue_receivable_count'] ?? count($data['overdue_receivables'] ?? [])), 'count', 'AccountingToolExecutor::analyze_accounting', ['field' => 'overdue_receivable_count']);
                $add('outstanding_payables', 'accounting', 'Outstanding payables', (int) ($totals['outstanding_payable_count'] ?? count($data['outstanding_payables'] ?? [])), 'count', 'AccountingToolExecutor::analyze_accounting', ['field' => 'outstanding_payable_count']);
                if (isset($cfo['cash_position'])) {
                    $add('cash_position', 'accounting', 'Cash position', (float) $cfo['cash_position'], 'amount', 'AccountingService::cfoMetrics', ['field' => 'cash_position']);
                }
                if (isset($cfo['revenue_ytd'])) {
                    $add('revenue_ytd', 'accounting', 'Revenue YTD', (float) $cfo['revenue_ytd'], 'amount', 'AccountingService::cfoMetrics', ['field' => 'revenue_ytd']);
                }
                if (isset($summary['payments_total'])) {
                    $add('payments_total', 'accounting', 'Payments total', (float) $summary['payments_total'], 'amount', 'AccountingService::financialSummary', ['field' => 'payments_total']);
                }
            }
        }

        if (in_array('suppliers', $domains, true) && ($ctx->can('suppliers.manage') || $ctx->can('procurement.view'))) {
            $sup = SupplierToolExecutor::execute('analyze_suppliers', ['limit' => $limit], $ctx);
            $data = is_array($sup['data'] ?? null) ? $sup['data'] : [];
            $summary = is_array($data['summary'] ?? null) ? $data['summary'] : [];
            if (!empty($sup['success']) && !self::hasKpi($items, 'suppliers')) {
                $add('suppliers', 'suppliers', 'Suppliers', (int) ($summary['supplier_count'] ?? 0), 'count', 'SupplierToolExecutor::analyze_suppliers', ['field' => 'supplier_count']);
            }
        }

        return [
            'data_source' => 'live_tenant',
            'items' => $items,
            'evidence' => $evidence,
            'count' => count($items),
            'domains' => $domains,
        ];
    }

    /**
     * @param array<string, mixed> $kpis
     * @param list<string> $domains
     * @return list<array<string, mixed>>
     */
    public static function analyzeTrends(ProcurementAgentContext $ctx, array $kpis, array $domains): array
    {
        $companyId = (int) $ctx->companyId;
        $trends = [];
        $series = self::loadHistoricalSeries($companyId, $domains);

        foreach ($series as $code => $points) {
            if (count($points) < 2) {
                $trends[] = [
                    'code' => $code,
                    'trend' => self::TREND_INSUFFICIENT,
                    'current' => $points[0]['value'] ?? null,
                    'previous' => null,
                    'change' => null,
                    'change_pct' => null,
                    'evidence' => ['points' => count($points), 'status' => 'INSUFFICIENT EVIDENCE'],
                    'confidence' => 'low',
                    'availability' => 'insufficient_data',
                ];
                continue;
            }
            $current = (float) $points[0]['value'];
            $previous = (float) $points[1]['value'];
            $change = $current - $previous;
            $changePct = abs($previous) > 0.00001 ? ($change / abs($previous)) * 100.0 : null;
            $vals = array_map(static fn($p) => (float) $p['value'], array_slice($points, 0, 6));
            $trend = self::classifyTrend($vals, $changePct);
            $trends[] = [
                'code' => $code,
                'trend' => $trend,
                'current' => $current,
                'previous' => $previous,
                'change' => $change,
                'change_pct' => $changePct !== null ? round($changePct, 2) : null,
                'period' => [
                    'current' => $points[0]['period'] ?? null,
                    'previous' => $points[1]['period'] ?? null,
                ],
                'evidence' => [
                    'series' => array_slice($points, 0, 6),
                    'source' => $points[0]['source'] ?? 'live_query',
                ],
                'confidence' => count($points) >= 3 ? 'medium' : 'low',
                'availability' => 'available',
            ];
        }

        // If no historical series, mark key KPIs insufficient rather than inventing trends
        if ($trends === []) {
            foreach (array_slice($kpis['items'] ?? [], 0, 8) as $kpi) {
                if (!is_array($kpi) || empty($kpi['available'])) {
                    continue;
                }
                $trends[] = [
                    'code' => (string) ($kpi['code'] ?? ''),
                    'trend' => self::TREND_INSUFFICIENT,
                    'current' => $kpi['value'] ?? null,
                    'previous' => null,
                    'change' => null,
                    'change_pct' => null,
                    'evidence' => ['status' => 'INSUFFICIENT EVIDENCE', 'reason' => 'no_comparable_historical_period'],
                    'confidence' => 'none',
                    'availability' => 'insufficient_data',
                ];
            }
        }

        return $trends;
    }

    /**
     * @param array<string, mixed> $kpis
     * @param list<string> $domains
     * @return list<array<string, mixed>>
     */
    public static function buildCorrelations(array $kpis, array $domains): array
    {
        $byCode = [];
        foreach (($kpis['items'] ?? []) as $item) {
            if (is_array($item) && !empty($item['available'])) {
                $byCode[(string) $item['code']] = $item;
            }
        }
        $out = [];
        $has = static fn(string $d): bool => in_array($d, $domains, true);

        if ($has('sales') && $has('accounting') && isset($byCode['sales_volume'], $byCode['ar_open'])) {
            $out[] = [
                'code' => 'sales_accounting',
                'domains' => ['sales', 'accounting'],
                'relation' => 'correlated with',
                'description' => 'sales_volume_associated_with_open_receivables',
                'evidence' => [
                    'sales_volume' => $byCode['sales_volume']['value'] ?? null,
                    'ar_open' => $byCode['ar_open']['value'] ?? null,
                ],
            ];
        }
        if ($has('sales') && $has('inventory') && isset($byCode['sales_open_count'], $byCode['low_stock'])) {
            $out[] = [
                'code' => 'sales_inventory',
                'domains' => ['sales', 'inventory'],
                'relation' => 'correlated with',
                'description' => 'open_sales_associated_with_low_stock_pressure',
                'evidence' => [
                    'sales_open_count' => $byCode['sales_open_count']['value'] ?? null,
                    'low_stock' => $byCode['low_stock']['value'] ?? null,
                ],
            ];
        }
        if ($has('inventory') && $has('procurement') && isset($byCode['low_stock'], $byCode['purchase_requests'])) {
            $out[] = [
                'code' => 'inventory_procurement',
                'domains' => ['inventory', 'procurement'],
                'relation' => 'associated with',
                'description' => 'low_stock_associated_with_purchase_request_activity',
                'evidence' => [
                    'low_stock' => $byCode['low_stock']['value'] ?? null,
                    'purchase_requests' => $byCode['purchase_requests']['value'] ?? null,
                ],
            ];
        }
        if ($has('procurement') && $has('suppliers') && $has('accounting') && isset($byCode['purchase_orders'], $byCode['ap_open'])) {
            $out[] = [
                'code' => 'procurement_supplier_accounting',
                'domains' => ['procurement', 'suppliers', 'accounting'],
                'relation' => 'correlated with',
                'description' => 'purchase_orders_associated_with_open_payables',
                'evidence' => [
                    'purchase_orders' => $byCode['purchase_orders']['value'] ?? null,
                    'ap_open' => $byCode['ap_open']['value'] ?? null,
                    'suppliers' => $byCode['suppliers']['value'] ?? null,
                ],
            ];
        }
        if ($has('sales') && $has('logistics') && $has('accounting') && isset($byCode['logistics_delayed'])) {
            $out[] = [
                'code' => 'sales_logistics_accounting',
                'domains' => ['sales', 'logistics', 'accounting'],
                'relation' => 'associated with',
                'description' => 'delayed_logistics_may_affect_sales_fulfillment_and_collections',
                'evidence' => [
                    'logistics_delayed' => $byCode['logistics_delayed']['value'] ?? null,
                    'sales_open_count' => $byCode['sales_open_count']['value'] ?? null,
                    'ar_open' => $byCode['ar_open']['value'] ?? null,
                ],
            ];
        }
        if ($has('crm') && $has('sales') && isset($byCode['crm_at_risk'], $byCode['sales_open_count'])) {
            $out[] = [
                'code' => 'crm_sales',
                'domains' => ['crm', 'sales'],
                'relation' => 'associated with',
                'description' => 'at_risk_customers_associated_with_open_sales_activity',
                'evidence' => [
                    'crm_at_risk' => $byCode['crm_at_risk']['value'] ?? null,
                    'sales_open_count' => $byCode['sales_open_count']['value'] ?? null,
                ],
            ];
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $kpis
     * @param list<array<string, mixed>> $correlations
     * @param list<string> $domains
     * @return list<array<string, mixed>>
     */
    public static function detectRisks(array $kpis, array $correlations, array $domains): array
    {
        $byCode = [];
        foreach (($kpis['items'] ?? []) as $item) {
            if (is_array($item) && !empty($item['available'])) {
                $byCode[(string) $item['code']] = $item;
            }
        }
        $risks = [];

        $push = static function (
            string $code,
            string $domain,
            string $priority,
            string $impact,
            array $evidence,
            string $action
        ) use (&$risks): void {
            $risks[] = [
                'code' => $code,
                'risk' => $code,
                'source_domain' => $domain,
                'affected_domain' => $domain,
                'priority' => $priority,
                'impact' => $impact,
                'evidence' => $evidence,
                'recommended_action' => $action,
            ];
        };

        $lowStock = (int) ($byCode['low_stock']['value'] ?? 0);
        if ($lowStock > 0) {
            $push('inventory_shortage_risk', 'inventory', $lowStock >= 10 ? self::PRIORITY_HIGH : self::PRIORITY_MEDIUM,
                'operational', ['low_stock' => $lowStock], 'review_inventory_and_procurement_need');
        }
        $delayed = (int) ($byCode['logistics_delayed']['value'] ?? 0);
        if ($delayed > 0) {
            $push('logistics_delay_risk', 'logistics', $delayed >= 5 ? self::PRIORITY_HIGH : self::PRIORITY_MEDIUM,
                'operational', ['delayed_shipments' => $delayed], 'follow_up_delayed_shipments');
        }
        $overdueAr = (int) ($byCode['overdue_receivables']['value'] ?? 0);
        $arOpen = (float) ($byCode['ar_open']['value'] ?? 0);
        if ($overdueAr > 0 || $arOpen > 0) {
            $prio = $overdueAr > 0 ? self::PRIORITY_HIGH : self::PRIORITY_MEDIUM;
            $push('receivables_risk', 'accounting', $prio, 'financial',
                ['overdue_receivables' => $overdueAr, 'ar_open' => $arOpen], 'review_collections_and_overdue_invoices');
        }
        $apOpen = (float) ($byCode['ap_open']['value'] ?? 0);
        $apCount = (int) ($byCode['outstanding_payables']['value'] ?? 0);
        if ($apOpen > 0 || $apCount > 0) {
            $push('payables_risk', 'accounting', $apCount >= 5 ? self::PRIORITY_HIGH : self::PRIORITY_MEDIUM,
                'financial', ['ap_open' => $apOpen, 'outstanding_payables' => $apCount], 'review_supplier_payables');
        }
        $atRisk = (int) ($byCode['crm_at_risk']['value'] ?? 0);
        if ($atRisk > 0) {
            $push('customer_followup_risk', 'crm', self::PRIORITY_MEDIUM, 'commercial',
                ['crm_at_risk' => $atRisk], 'follow_up_at_risk_customers');
        }
        $pending = (int) ($byCode['pending_workflows']['value'] ?? 0);
        if ($pending > 0) {
            $push('procurement_delay_risk', 'procurement', $pending >= 5 ? self::PRIORITY_HIGH : self::PRIORITY_MEDIUM,
                'operational', ['pending_workflows' => $pending], 'clear_pending_approvals');
        }
        if ($lowStock > 0 && (int) ($byCode['sales_open_count']['value'] ?? 0) > 0) {
            $push('cross_domain_operational_risk', 'sales', self::PRIORITY_HIGH, 'cross_domain',
                ['low_stock' => $lowStock, 'sales_open_count' => $byCode['sales_open_count']['value'] ?? 0, 'correlations' => count($correlations)],
                'align_sales_demand_with_inventory_and_procurement');
        }
        if ($arOpen > 0 && (int) ($byCode['sales_order_count']['value'] ?? 0) > 0) {
            $push('revenue_risk', 'accounting', self::PRIORITY_MEDIUM, 'financial',
                ['ar_open' => $arOpen, 'sales_order_count' => $byCode['sales_order_count']['value'] ?? 0],
                'monitor_revenue_collection_pipeline');
        }
        if ($apOpen > 0 && (int) ($byCode['suppliers']['value'] ?? 0) > 0) {
            $push('supplier_exposure', 'suppliers', self::PRIORITY_MEDIUM, 'financial',
                ['ap_open' => $apOpen, 'suppliers' => $byCode['suppliers']['value'] ?? 0],
                'review_supplier_exposure');
        }

        usort($risks, static function (array $a, array $b): int {
            return self::priorityRank((string) ($b['priority'] ?? '')) <=> self::priorityRank((string) ($a['priority'] ?? ''));
        });
        return $risks;
    }

    /**
     * @param list<array<string, mixed>> $risks
     * @param array<string, mixed> $kpis
     * @return list<array<string, mixed>>
     */
    public static function prioritize(array $risks, array $kpis): array
    {
        $priorities = [];
        foreach ($risks as $risk) {
            if (!is_array($risk)) {
                continue;
            }
            $priorities[] = [
                'code' => (string) ($risk['code'] ?? 'priority'),
                'title' => (string) ($risk['code'] ?? 'priority'),
                'priority' => (string) ($risk['priority'] ?? self::PRIORITY_MEDIUM),
                'domain' => (string) ($risk['source_domain'] ?? ''),
                'impact' => (string) ($risk['impact'] ?? ''),
                'urgency' => (string) ($risk['priority'] ?? self::PRIORITY_MEDIUM),
                'evidence' => $risk['evidence'] ?? [],
                'recommended_action' => (string) ($risk['recommended_action'] ?? ''),
                'cross_domain' => ($risk['impact'] ?? '') === 'cross_domain',
            ];
        }
        if ($priorities === [] && !empty($kpis['items'])) {
            $priorities[] = [
                'code' => 'monitor_operations',
                'title' => 'monitor_operations',
                'priority' => self::PRIORITY_LOW,
                'domain' => '',
                'impact' => 'informational',
                'urgency' => self::PRIORITY_LOW,
                'evidence' => ['kpi_count' => count($kpis['items'])],
                'recommended_action' => 'continue_monitoring_live_kpis',
                'cross_domain' => false,
            ];
        }
        usort($priorities, static function (array $a, array $b): int {
            return self::priorityRank((string) ($b['priority'] ?? '')) <=> self::priorityRank((string) ($a['priority'] ?? ''));
        });
        return $priorities;
    }

    /**
     * @param array<string, mixed> $kpis
     * @param list<array<string, mixed>> $trends
     * @param list<string> $domains
     * @return array<string, mixed>
     */
    public static function buildForecasts(ProcurementAgentContext $ctx, array $kpis, array $trends, array $domains): array
    {
        $out = [];
        $byTrend = [];
        foreach ($trends as $t) {
            if (is_array($t)) {
                $byTrend[(string) ($t['code'] ?? '')] = $t;
            }
        }

        $candidates = [
            'sales_volume' => 'sales_trend',
            'sales_order_count' => 'sales_trend',
            'low_stock' => 'inventory_demand',
            'purchase_orders' => 'procurement_demand',
            'ar_open' => 'receivable_trend',
            'ap_open' => 'payable_trend',
            'logistics_open_shipments' => 'operational_workload',
        ];

        foreach ($candidates as $kpiCode => $forecastCode) {
            $trend = $byTrend[$kpiCode] ?? null;
            if ($trend === null || ($trend['trend'] ?? '') === self::TREND_INSUFFICIENT
                || ($trend['availability'] ?? '') === 'insufficient_data'
                || $trend['previous'] === null
            ) {
                $out[] = [
                    'code' => $forecastCode,
                    'kpi' => $kpiCode,
                    'status' => 'FORECAST UNAVAILABLE — INSUFFICIENT HISTORICAL DATA',
                    'available' => false,
                    'prediction' => null,
                    'basis' => 'insufficient_historical_evidence',
                    'period_used' => null,
                    'is_fact' => false,
                ];
                continue;
            }
            $current = (float) ($trend['current'] ?? 0);
            $change = (float) ($trend['change'] ?? 0);
            // Simple linear continuation — not presented as fact
            $prediction = $current + $change;
            $out[] = [
                'code' => $forecastCode,
                'kpi' => $kpiCode,
                'status' => 'available',
                'available' => true,
                'prediction' => round($prediction, 2),
                'basis' => 'linear_continuation_of_last_two_comparable_periods',
                'period_used' => $trend['period'] ?? null,
                'evidence' => $trend['evidence'] ?? [],
                'confidence' => $trend['confidence'] ?? 'low',
                'is_fact' => false,
                'disclaimer' => 'prediction_not_fact',
            ];
        }

        return [
            'data_source' => 'live_tenant',
            'items' => $out,
            'available_count' => count(array_filter($out, static fn($r) => !empty($r['available']))),
            'unavailable_count' => count(array_filter($out, static fn($r) => empty($r['available']))),
        ];
    }

    /**
     * @param array<string, mixed> $kpis
     * @param list<array<string, mixed>> $trends
     * @param list<array<string, mixed>> $risks
     * @param list<array<string, mixed>> $priorities
     * @param array<string, mixed> $forecasts
     * @param list<array<string, mixed>> $correlations
     * @return array<string, mixed>
     */
    public static function buildExecutiveSummary(
        array $kpis,
        array $trends,
        array $risks,
        array $priorities,
        array $forecasts,
        array $correlations
    ): array {
        $current = [];
        foreach (array_slice($kpis['items'] ?? [], 0, 12) as $kpi) {
            if (!is_array($kpi) || empty($kpi['available'])) {
                continue;
            }
            $current[] = [
                'code' => $kpi['code'] ?? '',
                'label' => $kpi['label'] ?? '',
                'value' => $kpi['value'] ?? null,
                'domain' => $kpi['domain'] ?? '',
            ];
        }
        $changes = [];
        foreach ($trends as $t) {
            if (!is_array($t) || ($t['trend'] ?? '') === self::TREND_INSUFFICIENT) {
                continue;
            }
            $changes[] = [
                'code' => $t['code'] ?? '',
                'trend' => $t['trend'] ?? '',
                'change' => $t['change'] ?? null,
                'change_pct' => $t['change_pct'] ?? null,
            ];
        }
        $opportunities = [];
        foreach ($correlations as $c) {
            if (!is_array($c)) {
                continue;
            }
            if (($c['code'] ?? '') === 'sales_inventory' && (int) (($c['evidence']['low_stock'] ?? 0)) === 0) {
                $opportunities[] = [
                    'code' => 'fulfillment_capacity',
                    'description' => 'sales_demand_without_low_stock_pressure',
                    'evidence' => $c['evidence'] ?? [],
                ];
            }
        }
        if ($opportunities === [] && (int) (($kpis['count'] ?? 0)) > 0 && $risks === []) {
            $opportunities[] = [
                'code' => 'stable_operations',
                'description' => 'no_critical_risks_detected_from_live_evidence',
                'evidence' => ['kpi_count' => $kpis['count'] ?? 0],
            ];
        }

        return [
            'CURRENT STATE' => $current,
            'KEY CHANGES' => $changes,
            'RISKS' => array_map(static fn($r) => [
                'code' => $r['code'] ?? '',
                'priority' => $r['priority'] ?? '',
                'domain' => $r['source_domain'] ?? '',
            ], array_slice($risks, 0, 8)),
            'OPPORTUNITIES' => $opportunities,
            'PRIORITIES' => array_map(static fn($p) => [
                'code' => $p['code'] ?? '',
                'priority' => $p['priority'] ?? '',
                'action' => $p['recommended_action'] ?? '',
            ], array_slice($priorities, 0, 8)),
            'FORECAST' => array_slice($forecasts['items'] ?? [], 0, 8),
            'RECOMMENDED ACTIONS' => array_map(static fn($p) => (string) ($p['recommended_action'] ?? ''), array_slice($priorities, 0, 8)),
            'auto_execute' => false,
        ];
    }

    /**
     * @param list<array<string, mixed>> $priorities
     * @param list<array<string, mixed>> $risks
     * @return list<array<string, mixed>>
     */
    public static function recommendedActions(array $priorities, array $risks): array
    {
        $actions = [];
        foreach ($priorities as $p) {
            if (!is_array($p)) {
                continue;
            }
            $actions[] = [
                'insight' => (string) ($p['code'] ?? ''),
                'recommended_action' => (string) ($p['recommended_action'] ?? ''),
                'domain' => (string) ($p['domain'] ?? ''),
                'priority' => (string) ($p['priority'] ?? ''),
                'requires_confirmation' => true,
                'auto_execute' => false,
                'bridge' => 'ErpActionPlanner',
            ];
        }
        return $actions;
    }

    /**
     * @param array<string, mixed> $kpis
     * @param list<array<string, mixed>> $trends
     * @param list<array<string, mixed>> $risks
     * @param array<string, mixed> $forecasts
     * @return array<string, mixed>
     */
    public static function explainabilityPack(array $kpis, array $trends, array $risks, array $forecasts): array
    {
        return [
            'kpi_evidence' => array_map(static fn($k) => $k['evidence'] ?? [], array_slice($kpis['items'] ?? [], 0, 20)),
            'trend_evidence' => array_map(static fn($t) => [
                'code' => $t['code'] ?? '',
                'evidence' => $t['evidence'] ?? [],
                'confidence' => $t['confidence'] ?? null,
            ], array_slice($trends, 0, 12)),
            'risk_evidence' => array_map(static fn($r) => [
                'code' => $r['code'] ?? '',
                'evidence' => $r['evidence'] ?? [],
                'impact' => $r['impact'] ?? null,
            ], array_slice($risks, 0, 12)),
            'forecast_basis' => array_map(static fn($f) => [
                'code' => $f['code'] ?? '',
                'basis' => $f['basis'] ?? null,
                'period_used' => $f['period_used'] ?? null,
                'is_fact' => false,
            ], array_slice($forecasts['items'] ?? [], 0, 12)),
            'hidden_chain_of_thought' => false,
            'format' => 'operational_evidence_decision_summary',
        ];
    }

    public static function formatSummary(array $pack, ProcurementAgentContext $ctx): string
    {
        $ar = str_starts_with(strtolower((string) ($ctx->locale ?? 'en')), 'ar');
        $s = is_array($pack['executive_summary'] ?? null) ? $pack['executive_summary'] : [];
        $lines = [];
        $lines[] = $ar ? 'ملخص تنفيذي (أدلة حية فقط)' : 'Executive summary (live evidence only)';
        $lines[] = ($ar ? 'الوضع الحالي: ' : 'CURRENT STATE: ') . count($s['CURRENT STATE'] ?? []) . ($ar ? ' مؤشر' : ' indicators');
        $risks = $s['RISKS'] ?? [];
        $lines[] = ($ar ? 'المخاطر: ' : 'RISKS: ') . (count($risks) > 0 ? count($risks) : ($ar ? 'لا مخاطر حرجة مثبتة' : 'no critical evidenced risks'));
        $pri = $s['PRIORITIES'] ?? [];
        if ($pri !== []) {
            $top = $pri[0];
            $lines[] = ($ar ? 'أولوية الآن: ' : 'Top priority: ') . (string) ($top['code'] ?? '');
        }
        $fcAvail = (int) ($pack['forecasts']['available_count'] ?? 0);
        $lines[] = ($ar ? 'التوقعات المتاحة: ' : 'Available forecasts: ') . $fcAvail;
        $lines[] = $ar
            ? 'لا يتم تنفيذ أي إجراء تلقائيًا من الملخص التنفيذي.'
            : 'No action is auto-executed from the executive summary.';
        return implode("\n", $lines);
    }

    /**
     * Bridge insight → action plan (no auto-write).
     *
     * @return array<string, mixed>
     */
    public static function actionBridge(array $pack, ProcurementAgentContext $ctx): array
    {
        $priorities = is_array($pack['priorities'] ?? null) ? $pack['priorities'] : [];
        $top = $priorities[0] ?? null;
        $message = '';
        if (is_array($top)) {
            $code = (string) ($top['code'] ?? '');
            if (str_contains($code, 'inventory') || str_contains($code, 'shortage')) {
                $message = 'Create a purchase request for missing stock';
            } elseif (str_contains($code, 'procurement')) {
                $message = 'List pending approvals';
            }
        }
        $plan = $message !== ''
            ? ErpActionPlanner::buildActionPlan($message, $ctx, [
                'incomplete_chains' => !empty($pack['risks']) ? [['code' => 'executive_priority']] : [],
            ])
            : [
                'actions' => [],
                'unsupported' => [],
                'recommendations' => [[
                    'message' => 'executive_insight_requires_manual_follow_up',
                    'domain' => is_array($top) ? (string) ($top['domain'] ?? '') : '',
                ]],
                'requires_confirmation' => false,
                'auto_execute' => false,
            ];
        return [
            'insight' => $top,
            'recommended_action' => is_array($top) ? ($top['recommended_action'] ?? '') : '',
            'action_plan' => $plan,
            'auto_execute' => false,
            'requires_governance' => true,
        ];
    }

    /**
     * @param list<float> $vals newest first
     */
    private static function classifyTrend(array $vals, ?float $changePct): string
    {
        if (count($vals) < 2) {
            return self::TREND_INSUFFICIENT;
        }
        $diffs = [];
        for ($i = 0; $i < count($vals) - 1; $i++) {
            $diffs[] = $vals[$i] - $vals[$i + 1];
        }
        $up = 0;
        $down = 0;
        foreach ($diffs as $d) {
            if ($d > 0) {
                $up++;
            } elseif ($d < 0) {
                $down++;
            }
        }
        $mean = array_sum($vals) / max(1, count($vals));
        $variance = 0.0;
        foreach ($vals as $v) {
            $variance += ($v - $mean) ** 2;
        }
        $variance /= max(1, count($vals));
        $cv = abs($mean) > 0.00001 ? sqrt($variance) / abs($mean) : 0.0;
        if ($cv > 0.35 && count($vals) >= 3) {
            return self::TREND_VOLATILE;
        }
        if ($changePct === null || abs($changePct) < 5.0) {
            return self::TREND_STABLE;
        }
        if ($changePct >= 5.0 && $up >= $down) {
            return self::TREND_INCREASING;
        }
        if ($changePct <= -5.0 && $down >= $up) {
            return self::TREND_DECREASING;
        }
        return self::TREND_STABLE;
    }

    /**
     * @param list<string> $domains
     * @return array<string, list<array{period:string,value:float|int,source:string}>>
     */
    private static function loadHistoricalSeries(int $companyId, array $domains): array
    {
        $model = new Inventory();
        $series = [];
        try {
            if (in_array('sales', $domains, true)) {
                $rows = $model->query(
                    "SELECT DATE_FORMAT(created_at, '%Y-%m') AS period, COUNT(*) AS c, COALESCE(SUM(total),0) AS total
                     FROM rateb_pos_orders WHERE company_id = :cid
                     GROUP BY period ORDER BY period DESC LIMIT 6",
                    ['cid' => $companyId]
                );
                $series['sales_order_count'] = [];
                $series['sales_volume'] = [];
                foreach ($rows as $r) {
                    $series['sales_order_count'][] = ['period' => (string) $r['period'], 'value' => (int) $r['c'], 'source' => 'rateb_pos_orders'];
                    $series['sales_volume'][] = ['period' => (string) $r['period'], 'value' => (float) $r['total'], 'source' => 'rateb_pos_orders'];
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }
        try {
            if (in_array('procurement', $domains, true)) {
                $rows = $model->query(
                    "SELECT DATE_FORMAT(order_date, '%Y-%m') AS period, COUNT(*) AS c
                     FROM rateb_purchase_orders WHERE company_id = :cid AND order_date IS NOT NULL
                     GROUP BY period ORDER BY period DESC LIMIT 6",
                    ['cid' => $companyId]
                );
                $series['purchase_orders'] = [];
                foreach ($rows as $r) {
                    $series['purchase_orders'][] = ['period' => (string) $r['period'], 'value' => (int) $r['c'], 'source' => 'rateb_purchase_orders'];
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }
        try {
            if (in_array('accounting', $domains, true)) {
                $rows = $model->query(
                    "SELECT DATE_FORMAT(issued_at, '%Y-%m') AS period, COALESCE(SUM(total_amount),0) AS total
                     FROM rateb_invoices WHERE company_id = :cid AND status IN ('sent','overdue','draft','paid')
                       AND issued_at IS NOT NULL
                     GROUP BY period ORDER BY period DESC LIMIT 6",
                    ['cid' => $companyId]
                );
                $series['ar_open'] = [];
                foreach ($rows as $r) {
                    $series['ar_open'][] = ['period' => (string) $r['period'], 'value' => (float) $r['total'], 'source' => 'rateb_invoices'];
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }
        return $series;
    }

    private static function hasKpi(array $items, string $code): bool
    {
        foreach ($items as $item) {
            if (is_array($item) && ($item['code'] ?? '') === $code) {
                return true;
            }
        }
        return false;
    }

    private static function priorityRank(string $p): int
    {
        return match (strtoupper($p)) {
            self::PRIORITY_CRITICAL => 4,
            self::PRIORITY_HIGH => 3,
            self::PRIORITY_MEDIUM => 2,
            self::PRIORITY_LOW => 1,
            default => 0,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private static function emptyPack(string $reason): array
    {
        return [
            'data_source' => 'live_tenant',
            'evidence_first' => true,
            'auto_execute' => false,
            'kpis' => ['items' => [], 'count' => 0],
            'trends' => [],
            'correlations' => [],
            'risks' => [],
            'priorities' => [],
            'forecasts' => ['items' => [], 'available_count' => 0, 'unavailable_count' => 0],
            'executive_summary' => [],
            'recommended_actions' => [],
            'explainability' => [],
            'decision_support' => ['insufficient_data' => true],
            'data_sufficient' => false,
            'error' => $reason,
            'notes' => ['INSUFFICIENT EVIDENCE'],
        ];
    }
}
