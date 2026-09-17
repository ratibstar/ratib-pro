<?php
declare(strict_types=1);

namespace Rateb\App\Services;

/**
 * Unified ERP Intelligence & Decision Layer (not an agent).
 * Evidence-first correlation / risk / priority / decision support over confirmed live tool results only.
 */
final class ErpIntelligenceLayer
{
    /**
     * Enrich base cross-domain intelligence with findings, evidence, conflicts, and decisions.
     *
     * @param array<string, mixed> $base
     * @param list<array{tool: string, domain: string, purpose: string, data: mixed}> $confirmed
     * @param list<array{tool: string, domain: string, purpose: string, error_code: string}> $failed
     * @param array<string, mixed> $intent
     * @return array<string, mixed>
     */
    public static function enrich(array $base, array $confirmed, array $failed, array $intent, ProcurementAgentContext $ctx): array
    {
        $findings = [];
        $evidence = [];
        $conflicts = [];
        $correlations = [];
        $incompleteChains = [];
        $decisions = [];

        $byPurpose = [];
        foreach ($confirmed as $row) {
            if (!is_array($row)) {
                continue;
            }
            $purpose = (string) ($row['purpose'] ?? '');
            if ($purpose !== '') {
                $byPurpose[$purpose] = $row['data'] ?? null;
            }
            $tool = (string) ($row['tool'] ?? '');
            $domain = (string) ($row['domain'] ?? '');
            $data = $row['data'] ?? null;
            if (!is_array($data)) {
                continue;
            }
            $source = (string) ($data['data_source'] ?? '');
            if ($source !== '' && $source !== 'live_tenant') {
                continue;
            }
            $evidence[] = self::evidenceRef($domain, $tool, $purpose, $data);
        }

        self::extractFromLogisticsE2e($byPurpose['logistics_e2e_core'] ?? null, $findings, $correlations, $incompleteChains, $conflicts);
        self::extractFromExecutive($byPurpose['executive_intelligence_core'] ?? null, $findings, $correlations);
        self::extractFromProactive($byPurpose['proactive_early_warning_core'] ?? null, $findings, $correlations);
        self::extractFromFinancialIntelligence($byPurpose['financial_intelligence_core'] ?? null, $findings, $correlations, $incompleteChains);
        self::extractFromAccounting($byPurpose['accounting_intelligence'] ?? null, $findings);
        self::extractFromCrmCommercial($byPurpose['crm_commercial_core'] ?? null, $findings, $correlations, $incompleteChains);
        self::extractFromSalesCross($byPurpose['sales_cross_domain_core'] ?? null, $findings, $correlations, $incompleteChains);
        self::extractFromSupplierCross($byPurpose['cross_domain_core'] ?? null, $findings, $correlations);
        self::extractFromInventory($byPurpose['inventory_intelligence'] ?? null, $byPurpose['inventory_procurement_links'] ?? null, $findings, $incompleteChains);
        self::extractFromLogistics($byPurpose['logistics_intelligence'] ?? null, $findings);
        self::extractFromCrm($byPurpose['crm_intelligence'] ?? null, $findings);
        self::extractFromSalesLinks($byPurpose['sales_inventory_links'] ?? null, $byPurpose['sales_procurement_links'] ?? null, $findings, $incompleteChains, $conflicts);

        // Merge risks already detected in base (still evidence-backed)
        foreach (($base['risks'] ?? []) as $risk) {
            if (!is_array($risk)) {
                continue;
            }
            $findings[] = [
                'code' => (string) ($risk['code'] ?? 'risk'),
                'finding' => (string) ($risk['label'] ?? $risk['code'] ?? ''),
                'source_domain' => (string) ($risk['domain'] ?? ''),
                'urgency' => self::normalizeUrgency((string) ($risk['urgency'] ?? 'medium')),
                'status' => (string) ($risk['status'] ?? ''),
                'evidence' => array_filter([
                    'label' => $risk['label'] ?? null,
                    'shortfall_qty' => $risk['shortfall_qty'] ?? null,
                    'quantity' => $risk['quantity'] ?? null,
                    'expected_date' => $risk['expected_date'] ?? null,
                    'amount' => $risk['amount'] ?? null,
                ], static fn($v) => $v !== null && $v !== ''),
                'reason' => 'confirmed_live_tool_signal',
            ];
        }

        $findings = self::dedupeRows($findings, static function (array $r): string {
            return ((string) ($r['code'] ?? '')) . '|' . ((string) ($r['finding'] ?? '')) . '|' . ((string) ($r['source_domain'] ?? ''));
        });
        $conflicts = self::dedupeRows($conflicts, static function (array $r): string {
            return ((string) ($r['code'] ?? '')) . '|' . ((string) ($r['description'] ?? ''));
        });
        $incompleteChains = self::dedupeRows($incompleteChains, static function (array $r): string {
            return ((string) ($r['code'] ?? '')) . '|' . ((string) ($r['confirmed'] ?? '')) . '|' . ((string) ($r['missing'] ?? ''));
        });

        $priorities = self::prioritize($findings, is_array($base['priorities'] ?? null) ? $base['priorities'] : []);
        $decisions = self::buildDecisions($findings, $incompleteChains, $conflicts, $priorities, $failed);

        $dataSufficient = $confirmed !== [] && (
            $findings !== []
            || $correlations !== []
            || ((int) count($base['metrics'] ?? []) > 0)
        );

        $out = $base;
        $out['intelligence_layer'] = 'unified_decision_v1';
        $out['data_source'] = 'live_tenant';
        $out['evidence_first'] = true;
        $out['data_sufficient'] = $dataSufficient;
        $out['findings'] = array_slice($findings, 0, 30);
        $out['evidence'] = array_slice($evidence, 0, 40);
        $out['conflicts'] = array_slice($conflicts, 0, 20);
        $out['correlations'] = array_slice($correlations, 0, 20);
        $out['incomplete_chains'] = array_slice($incompleteChains, 0, 20);
        $out['priorities'] = $priorities;
        $out['decisions'] = array_slice($decisions, 0, 15);
        $out['partial_failures'] = $failed;
        $out['intent_kind'] = (string) ($intent['intent_kind'] ?? 'analysis');
        $out['decision_support'] = !empty($intent['decision_support']);
        $out['notes'] = array_values(array_unique(array_merge(
            is_array($base['notes'] ?? null) ? $base['notes'] : [],
            [
                'only_confirmed_live_data_included',
                'no_invented_numbers_or_relations',
                'recommendations_are_not_writes',
                'insufficient_data_reported_when_empty',
            ]
        )));

        if (!$dataSufficient) {
            $out['insufficient_data_message'] = 'insufficient_live_evidence_for_full_analysis';
        }

        // Phase 21: CURRENT DATA + RELEVANT OPERATIONAL MEMORY (never full history)
        $memoryHints = [
            'open_warning_codes' => [],
            'resolved_warning_ids' => [],
        ];
        foreach ($findings as $f) {
            if (is_array($f) && (string) ($f['code'] ?? '') !== '') {
                $memoryHints['open_warning_codes'][] = (string) $f['code'];
            }
        }
        $memory = ErpOperationalMemoryLayer::forIntelligence($ctx, $intent, $memoryHints);
        $out['operational_memory'] = $memory;
        $out['notes'] = array_values(array_unique(array_merge(
            is_array($out['notes'] ?? null) ? $out['notes'] : [],
            [
                'current_data_plus_relevant_operational_memory',
                'historical_memory_not_current_fact',
                'llm_cannot_write_memory',
            ]
        )));
        // Merge memory conflicts as historical (current state wins)
        foreach (($memory['conflicts'] ?? []) as $mc) {
            if (!is_array($mc)) {
                continue;
            }
            $out['conflicts'][] = [
                'code' => 'stale_memory_conflict',
                'description' => (string) ($mc['reason'] ?? 'historical_conflicts_with_current'),
                'resolution' => (string) ($mc['resolution'] ?? 'prefer_current_state'),
                'memory_id' => $mc['memory_id'] ?? null,
            ];
        }
        $out['conflicts'] = array_slice($out['conflicts'], 0, 20);

        return $out;
    }

    /**
     * @param array<string, mixed>|null $data
     * @param list<array<string, mixed>> $findings
     * @param list<array<string, mixed>> $correlations
     * @param list<array<string, mixed>> $incomplete
     * @param list<array<string, mixed>> $conflicts
     */
    private static function extractFromLogisticsE2e($data, array &$findings, array &$correlations, array &$incomplete, array &$conflicts): void
    {
        if (!is_array($data) || (($data['data_source'] ?? '') !== 'live_tenant' && !isset($data['chain']))) {
            return;
        }
        foreach (($data['chain'] ?? []) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $salesId = (int) ($row['sales_order_id'] ?? 0);
            $log = is_array($row['logistics'] ?? null) ? $row['logistics'] : [];
            $inv = is_array($row['inventory_links'] ?? null) ? $row['inventory_links'] : [];
            $proc = is_array($row['procurement_links'] ?? null) ? $row['procurement_links'] : [];
            $sup = is_array($row['supplier_links'] ?? null) ? $row['supplier_links'] : [];

            $correlations[] = [
                'code' => 'commercial_logistics_chain',
                'source_domain' => 'logistics',
                'customer_id' => (int) ($row['customer_id'] ?? 0),
                'sales_order_id' => $salesId,
                'order_no' => (string) ($row['order_no'] ?? ''),
                'shipment_id' => (int) ($log['shipment_id'] ?? 0),
                'shipment_status' => (string) ($log['shipment_status'] ?? ''),
                'inventory_link_count' => count($inv),
                'procurement_link_count' => count($proc),
                'supplier_link_count' => count($sup),
                'confirmed_segments' => array_values(array_filter([
                    $salesId > 0 ? 'sales' : null,
                    $inv !== [] ? 'inventory' : null,
                    $proc !== [] ? 'procurement' : null,
                    $sup !== [] ? 'suppliers' : null,
                    ((int) ($log['shipment_id'] ?? 0) > 0 || (int) ($log['delivery_order_id'] ?? 0) > 0) ? 'logistics' : null,
                ])),
            ];

            $hasShortfall = false;
            foreach ($inv as $il) {
                if (!empty($il['is_shortfall'])) {
                    $hasShortfall = true;
                    $findings[] = [
                        'code' => 'logistics_linked_stock_shortfall',
                        'finding' => 'sales_order_linked_to_logistics_has_inventory_shortfall',
                        'source_domain' => 'logistics',
                        'urgency' => 'high',
                        'entity' => [
                            'sales_order_id' => $salesId,
                            'shipment_id' => (int) ($il['shipment_id'] ?? $log['shipment_id'] ?? 0),
                            'inventory_id' => (int) ($il['inventory_id'] ?? 0),
                        ],
                        'status' => (string) ($log['shipment_status'] ?? ''),
                        'evidence' => [
                            'demand_qty' => $il['demand_qty'] ?? null,
                            'stock_qty' => $il['stock_qty'] ?? null,
                            'shortfall_qty' => $il['shortfall_qty'] ?? null,
                        ],
                        'reason' => 'live_inventory_qty_below_linked_sales_demand',
                    ];
                }
            }

            if ($hasShortfall && $proc === []) {
                $incomplete[] = [
                    'code' => 'shortfall_without_procurement',
                    'confirmed' => 'sales+inventory_shortfall+logistics',
                    'missing' => 'procurement',
                    'sales_order_id' => $salesId,
                    'reason' => 'no_purchase_request_or_order_linked_via_inventory_id',
                ];
            }

            $shipStatus = strtolower((string) ($log['shipment_status'] ?? ''));
            $salesStatus = '';
            // Prefer status from sales_links sibling if present on same row
            if (isset($row['sales_status'])) {
                $salesStatus = strtolower((string) $row['sales_status']);
            }
            if ($salesId > 0 && in_array($shipStatus, ['created', 'picked', 'packed', 'shipped', 'out_for_delivery', 'failed'], true)) {
                $findings[] = [
                    'code' => 'open_logistics_on_sales_order',
                    'finding' => 'sales_order_has_incomplete_logistics_fulfillment',
                    'source_domain' => 'logistics',
                    'urgency' => in_array($shipStatus, ['failed', 'out_for_delivery'], true) ? 'high' : 'medium',
                    'entity' => [
                        'sales_order_id' => $salesId,
                        'shipment_id' => (int) ($log['shipment_id'] ?? 0),
                        'tracking_number' => (string) ($log['tracking_number'] ?? ''),
                    ],
                    'status' => $shipStatus,
                    'evidence' => ['shipment_status' => $shipStatus, 'order_no' => (string) ($row['order_no'] ?? '')],
                    'reason' => 'shipment_status_not_delivered',
                ];
            }
            // Conflict only when sales is explicitly completed/paid AND logistics still open (both from live fields).
            if ($salesId > 0
                && $salesStatus !== ''
                && in_array($salesStatus, ['completed', 'paid', 'closed', 'done', 'delivered'], true)
                && in_array($shipStatus, ['created', 'picked', 'packed', 'shipped', 'out_for_delivery', 'failed'], true)
            ) {
                $conflicts[] = [
                    'code' => 'sales_complete_logistics_open',
                    'description' => 'sales_status_complete_but_logistics_fulfillment_open',
                    'domains' => 'sales,logistics',
                    'sales_order_id' => $salesId,
                    'sales_status' => $salesStatus,
                    'shipment_status' => $shipStatus,
                    'evidence' => [
                        'order_no' => (string) ($row['order_no'] ?? ''),
                        'shipment_id' => (int) ($log['shipment_id'] ?? 0),
                    ],
                ];
            }
        }

        foreach (($data['follow_up'] ?? []) as $fu) {
            if (!is_array($fu)) {
                continue;
            }
            $findings[] = [
                'code' => (string) ($fu['code'] ?? 'logistics_follow_up'),
                'finding' => (string) ($fu['message'] ?? $fu['code'] ?? ''),
                'source_domain' => 'logistics',
                'urgency' => self::normalizeUrgency((string) ($fu['urgency'] ?? 'medium')),
                'entity' => array_filter([
                    'shipment_id' => $fu['shipment_id'] ?? null,
                    'inventory_id' => $fu['inventory_id'] ?? null,
                ]),
                'status' => '',
                'evidence' => $fu,
                'reason' => 'logistics_e2e_follow_up_from_live_data',
            ];
        }
    }

    /**
     * @param array<string, mixed>|null $data
     * @param list<array<string, mixed>> $findings
     * @param list<array<string, mixed>> $correlations
     * @param list<array<string, mixed>> $incomplete
     */
    private static function extractFromCrmCommercial($data, array &$findings, array &$correlations, array &$incomplete): void
    {
        if (!is_array($data)) {
            return;
        }
        foreach (($data['inventory_links'] ?? []) as $link) {
            if (!is_array($link)) {
                continue;
            }
            $findings[] = [
                'code' => 'customer_sales_stock_shortfall',
                'finding' => 'customer_sales_demand_exceeds_stock',
                'source_domain' => 'crm',
                'urgency' => 'high',
                'entity' => [
                    'customer_id' => (int) ($link['customer_id'] ?? 0),
                    'inventory_id' => (int) ($link['inventory_id'] ?? 0),
                ],
                'status' => '',
                'evidence' => [
                    'customer_name' => $link['customer_name'] ?? null,
                    'item_name' => $link['item_name'] ?? null,
                    'shortfall_qty' => $link['shortfall_qty'] ?? null,
                ],
                'reason' => 'live_pos_demand_vs_inventory_qty',
            ];
            $correlations[] = [
                'code' => 'crm_sales_inventory',
                'source_domain' => 'crm',
                'customer_id' => (int) ($link['customer_id'] ?? 0),
                'inventory_id' => (int) ($link['inventory_id'] ?? 0),
                'confirmed_segments' => ['crm', 'sales', 'inventory'],
            ];
        }
        foreach (($data['procurement_links'] ?? []) as $link) {
            if (!is_array($link)) {
                continue;
            }
            $hasProc = !empty($link['purchase_requests']) || !empty($link['purchase_orders']);
            if (!$hasProc && !empty($link['inventory_id'])) {
                $incomplete[] = [
                    'code' => 'crm_shortfall_without_procurement',
                    'confirmed' => 'crm+sales+inventory',
                    'missing' => 'procurement',
                    'inventory_id' => (int) ($link['inventory_id'] ?? 0),
                    'reason' => 'no_linked_pr_or_po_for_shortfall_item',
                ];
            }
        }
    }

    /**
     * @param array<string, mixed>|null $data
     * @param list<array<string, mixed>> $findings
     * @param list<array<string, mixed>> $correlations
     * @param list<array<string, mixed>> $incomplete
     */
    private static function extractFromSalesCross($data, array &$findings, array &$correlations, array &$incomplete): void
    {
        if (!is_array($data)) {
            return;
        }
        foreach (($data['inventory_links'] ?? []) as $link) {
            if (!is_array($link) || empty($link['is_shortfall'])) {
                continue;
            }
            $findings[] = [
                'code' => 'sales_stock_shortfall',
                'finding' => 'sales_demand_exceeds_available_stock',
                'source_domain' => 'sales',
                'urgency' => 'high',
                'entity' => ['inventory_id' => (int) ($link['inventory_id'] ?? 0)],
                'evidence' => [
                    'item_code' => $link['item_code'] ?? null,
                    'shortfall_qty' => $link['shortfall_qty'] ?? null,
                ],
                'reason' => 'live_sales_lines_vs_inventory',
            ];
            $correlations[] = [
                'code' => 'sales_inventory',
                'source_domain' => 'sales',
                'inventory_id' => (int) ($link['inventory_id'] ?? 0),
                'confirmed_segments' => ['sales', 'inventory'],
            ];
        }
        foreach (($data['procurement_links'] ?? []) as $link) {
            if (!is_array($link)) {
                continue;
            }
            if (empty($link['purchase_requests']) && empty($link['purchase_orders']) && !empty($link['is_shortfall'])) {
                $incomplete[] = [
                    'code' => 'sales_shortfall_without_procurement',
                    'confirmed' => 'sales+inventory_shortfall',
                    'missing' => 'procurement',
                    'inventory_id' => (int) ($link['inventory_id'] ?? 0),
                    'reason' => 'no_linked_procurement_record',
                ];
            }
        }
    }

    /**
     * @param array<string, mixed>|null $data
     * @param list<array<string, mixed>> $findings
     * @param list<array<string, mixed>> $correlations
     */
    private static function extractFromSupplierCross($data, array &$findings, array &$correlations): void
    {
        if (!is_array($data)) {
            return;
        }
        foreach (($data['overdue_operations'] ?? []) as $op) {
            if (!is_array($op)) {
                continue;
            }
            $findings[] = [
                'code' => 'overdue_supplier_po',
                'finding' => 'overdue_purchase_order_needs_follow_up',
                'source_domain' => 'suppliers',
                'urgency' => 'high',
                'entity' => [
                    'supplier_id' => (int) ($op['supplier_id'] ?? 0),
                    'purchase_order_id' => (int) ($op['id'] ?? 0),
                ],
                'status' => (string) ($op['status'] ?? ''),
                'evidence' => [
                    'order_no' => $op['order_no'] ?? null,
                    'expected_date' => $op['expected_date'] ?? null,
                    'supplier_name' => $op['supplier_name'] ?? null,
                ],
                'reason' => 'po_expected_date_passed_and_still_open',
            ];
            $correlations[] = [
                'code' => 'supplier_procurement',
                'source_domain' => 'suppliers',
                'confirmed_segments' => ['suppliers', 'procurement'],
            ];
        }
    }

    /**
     * @param array<string, mixed>|null $inv
     * @param array<string, mixed>|null $links
     * @param list<array<string, mixed>> $findings
     * @param list<array<string, mixed>> $incomplete
     */
    private static function extractFromInventory($inv, $links, array &$findings, array &$incomplete): void
    {
        if (is_array($inv)) {
            foreach (($inv['low_stock'] ?? []) as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $findings[] = [
                    'code' => 'low_stock',
                    'finding' => 'low_stock_item_needs_review',
                    'source_domain' => 'inventory',
                    'urgency' => 'high',
                    'entity' => ['inventory_id' => (int) ($item['id'] ?? $item['inventory_id'] ?? 0)],
                    'evidence' => [
                        'item_code' => $item['item_code'] ?? null,
                        'quantity' => $item['quantity'] ?? null,
                        'reorder_level' => $item['reorder_level'] ?? null,
                    ],
                    'reason' => 'quantity_at_or_below_reorder_or_low_stock_rule',
                ];
            }
        }
        if (is_array($links)) {
            foreach (($links['links'] ?? []) as $link) {
                if (!is_array($link)) {
                    continue;
                }
                if (!empty($link['is_low_stock']) && empty($link['purchase_requests']) && empty($link['purchase_orders'])) {
                    $incomplete[] = [
                        'code' => 'low_stock_without_procurement',
                        'confirmed' => 'inventory_low_stock',
                        'missing' => 'procurement',
                        'inventory_id' => (int) ($link['inventory_id'] ?? 0),
                        'reason' => 'no_open_pr_or_po_for_low_stock_item',
                    ];
                }
            }
        }
    }

    /**
     * @param array<string, mixed>|null $data
     * @param list<array<string, mixed>> $findings
     * @param list<array<string, mixed>> $correlations
     */
    private static function extractFromExecutive($data, array &$findings, array &$correlations): void
    {
        if (!is_array($data)) {
            return;
        }
        foreach (($data['risks'] ?? []) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $findings[] = [
                'code' => (string) ($row['code'] ?? 'executive_risk'),
                'finding' => (string) ($row['code'] ?? 'executive_risk'),
                'source_domain' => (string) ($row['source_domain'] ?? 'executive'),
                'urgency' => self::normalizeUrgency((string) ($row['priority'] ?? 'medium')),
                'evidence' => is_array($row['evidence'] ?? null) ? $row['evidence'] : [],
                'reason' => 'executive_intelligence_live_risk',
            ];
        }
        foreach (($data['correlations'] ?? []) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $correlations[] = [
                'code' => (string) ($row['code'] ?? 'executive_correlation'),
                'source_domain' => 'executive',
                'domains' => is_array($row['domains'] ?? null) ? $row['domains'] : [],
                'relation' => (string) ($row['relation'] ?? 'correlated with'),
                'evidence' => $row['evidence'] ?? [],
                'reason' => 'executive_cross_domain_evidence',
            ];
        }
    }

    /**
     * @param array<string, mixed>|null $data
     * @param list<array<string, mixed>> $findings
     * @param list<array<string, mixed>> $correlations
     */
    private static function extractFromProactive($data, array &$findings, array &$correlations): void
    {
        if (!is_array($data)) {
            return;
        }
        foreach (($data['warnings'] ?? []) as $row) {
            if (!is_array($row) || empty($row['evidence'])) {
                continue;
            }
            $findings[] = [
                'code' => (string) ($row['signal'] ?? $row['warning_id'] ?? 'early_warning'),
                'finding' => (string) ($row['signal'] ?? 'early_warning'),
                'source_domain' => (string) ($row['domain'] ?? 'executive'),
                'urgency' => self::normalizeUrgency((string) ($row['severity'] ?? $row['priority'] ?? 'medium')),
                'evidence' => is_array($row['evidence'] ?? null) ? $row['evidence'] : [],
                'reason' => 'proactive_early_warning_live_signal',
                'status' => (string) ($row['status'] ?? ''),
            ];
            if (!empty($row['cross_domain'])) {
                $correlations[] = [
                    'code' => (string) ($row['signal'] ?? 'proactive_cross'),
                    'source_domain' => (string) ($row['domain'] ?? 'executive'),
                    'relation' => 'associated with',
                    'evidence' => $row['evidence'] ?? [],
                    'reason' => 'proactive_cross_domain_signal',
                ];
            }
        }
        foreach (($data['opportunities'] ?? []) as $row) {
            if (!is_array($row) || empty($row['evidence'])) {
                continue;
            }
            $findings[] = [
                'code' => (string) ($row['code'] ?? 'opportunity'),
                'finding' => (string) ($row['opportunity'] ?? $row['code'] ?? 'opportunity'),
                'source_domain' => (string) ($row['domain'] ?? 'executive'),
                'urgency' => self::normalizeUrgency((string) ($row['priority'] ?? 'low')),
                'evidence' => is_array($row['evidence'] ?? null) ? $row['evidence'] : [],
                'reason' => 'proactive_opportunity_live_evidence',
            ];
        }
    }

    /**
     * @param array<string, mixed>|null $data
     * @param list<array<string, mixed>> $findings
     * @param list<array<string, mixed>> $correlations
     * @param list<array<string, mixed>> $incomplete
     */
    private static function extractFromFinancialIntelligence($data, array &$findings, array &$correlations, array &$incomplete): void
    {
        if (!is_array($data)) {
            return;
        }
        foreach (($data['correlations'] ?? []) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $correlations[] = [
                'code' => (string) ($row['code'] ?? 'financial_correlation'),
                'source_domain' => 'accounting',
                'domains' => is_array($row['domains'] ?? null) ? $row['domains'] : [],
                'evidence_count' => (int) ($row['evidence_count'] ?? 0),
                'mismatch_count' => (int) ($row['mismatch_count'] ?? $row['payment_gap_count'] ?? 0),
                'reason' => 'live_financial_cross_domain_evidence',
            ];
        }
        foreach (($data['risks'] ?? []) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $findings[] = [
                'code' => (string) ($row['code'] ?? 'financial_risk'),
                'finding' => (string) ($row['code'] ?? 'financial_risk'),
                'source_domain' => 'accounting',
                'urgency' => self::normalizeUrgency((string) ($row['urgency'] ?? 'medium')),
                'entity' => array_filter([
                    'invoice_id' => $row['invoice_id'] ?? null,
                    'purchase_order_id' => $row['purchase_order_id'] ?? null,
                    'amount' => $row['amount'] ?? null,
                    'status' => $row['status'] ?? null,
                ], static fn($v) => $v !== null && $v !== ''),
                'evidence' => $row,
                'reason' => 'live_accounting_financial_risk_signal',
            ];
        }
        $salesMismatches = (int) (($data['sales_links']['summary']['mismatch_count'] ?? 0));
        if ($salesMismatches > 0) {
            $incomplete[] = [
                'code' => 'sales_accounting_ledger_gap',
                'confirmed' => 'sales_invoices',
                'missing' => 'posted_journal',
                'count' => $salesMismatches,
                'reason' => 'invoice_without_posted_journal_evidence',
            ];
        }
    }

    /**
     * @param array<string, mixed>|null $data
     * @param list<array<string, mixed>> $findings
     */
    private static function extractFromAccounting($data, array &$findings): void
    {
        if (!is_array($data)) {
            return;
        }
        foreach (($data['overdue_receivables'] ?? []) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $findings[] = [
                'code' => 'overdue_receivable',
                'finding' => 'overdue_invoice_needs_collection',
                'source_domain' => 'accounting',
                'urgency' => 'high',
                'entity' => [
                    'invoice_id' => (int) ($row['invoice_id'] ?? 0),
                    'amount' => (float) ($row['total_amount'] ?? 0),
                ],
                'status' => (string) ($row['status'] ?? ''),
                'evidence' => [
                    'due_date' => $row['due_date'] ?? null,
                    'journal_id' => $row['journal_id'] ?? null,
                ],
                'reason' => 'live_ar_overdue_status_or_past_due_date',
            ];
        }
        foreach (($data['outstanding_payables'] ?? []) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $findings[] = [
                'code' => 'outstanding_payable',
                'finding' => 'supplier_payable_outstanding',
                'source_domain' => 'accounting',
                'urgency' => 'medium',
                'entity' => [
                    'purchase_order_id' => (int) ($row['purchase_order_id'] ?? 0),
                    'supplier_id' => (int) ($row['supplier_id'] ?? 0),
                    'amount' => (float) ($row['outstanding'] ?? 0),
                ],
                'evidence' => [
                    'total_amount' => $row['total_amount'] ?? null,
                    'paid_amount' => $row['paid_amount'] ?? null,
                    'journal_id' => $row['journal_id'] ?? null,
                ],
                'reason' => 'posted_payable_exceeds_paid_amount',
            ];
        }
    }

    /**
     * @param array<string, mixed>|null $data
     * @param list<array<string, mixed>> $findings
     */
    private static function extractFromLogistics($data, array &$findings): void
    {
        if (!is_array($data)) {
            return;
        }
        foreach (($data['delayed_shipments'] ?? []) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $findings[] = [
                'code' => 'delayed_shipment',
                'finding' => 'shipment_needs_follow_up',
                'source_domain' => 'logistics',
                'urgency' => 'high',
                'entity' => [
                    'shipment_id' => (int) ($row['id'] ?? 0),
                    'tracking_number' => (string) ($row['tracking_number'] ?? ''),
                    'customer_id' => (int) ($row['customer_id'] ?? 0),
                ],
                'status' => (string) ($row['status'] ?? ''),
                'evidence' => [
                    'dispatched_at' => $row['dispatched_at'] ?? null,
                    'created_at' => $row['created_at'] ?? null,
                    'customer_name' => $row['customer_name'] ?? null,
                ],
                'reason' => 'open_shipment_past_dispatch_or_age_threshold',
            ];
        }
    }

    /**
     * @param array<string, mixed>|null $data
     * @param list<array<string, mixed>> $findings
     */
    private static function extractFromCrm($data, array &$findings): void
    {
        if (!is_array($data)) {
            return;
        }
        foreach (($data['at_risk_customers'] ?? []) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $findings[] = [
                'code' => 'customer_at_risk',
                'finding' => 'customer_marked_at_risk',
                'source_domain' => 'crm',
                'urgency' => 'high',
                'entity' => ['customer_id' => (int) ($row['id'] ?? 0)],
                'status' => (string) ($row['crm_health_status'] ?? $row['crm_renewal_risk'] ?? ''),
                'evidence' => [
                    'code' => $row['code'] ?? null,
                    'name' => $row['name'] ?? null,
                    'crm_at_risk' => $row['crm_at_risk'] ?? null,
                ],
                'reason' => 'existing_crm_at_risk_or_health_status_fields',
            ];
        }
    }

    /**
     * @param array<string, mixed>|null $invLinks
     * @param array<string, mixed>|null $procLinks
     * @param list<array<string, mixed>> $findings
     * @param list<array<string, mixed>> $incomplete
     * @param list<array<string, mixed>> $conflicts
     */
    private static function extractFromSalesLinks($invLinks, $procLinks, array &$findings, array &$incomplete, array &$conflicts): void
    {
        if (is_array($invLinks)) {
            foreach (($invLinks['links'] ?? []) as $link) {
                if (!is_array($link) || empty($link['is_shortfall'])) {
                    continue;
                }
                $findings[] = [
                    'code' => 'sales_inventory_shortfall',
                    'finding' => 'sales_product_stock_insufficient',
                    'source_domain' => 'sales',
                    'urgency' => 'high',
                    'entity' => ['inventory_id' => (int) ($link['inventory_id'] ?? 0)],
                    'evidence' => [
                        'item_code' => $link['item_code'] ?? null,
                        'shortfall_qty' => $link['shortfall_qty'] ?? null,
                        'stock_qty' => $link['stock_qty'] ?? null,
                    ],
                    'reason' => 'live_shortfall_flag_from_sales_inventory_links',
                ];
            }
        }
        if (is_array($procLinks)) {
            foreach (($procLinks['links'] ?? []) as $link) {
                if (!is_array($link)) {
                    continue;
                }
                if (!empty($link['is_shortfall']) && empty($link['purchase_requests']) && empty($link['purchase_orders'])) {
                    $incomplete[] = [
                        'code' => 'sales_gap_no_procurement',
                        'confirmed' => 'sales+inventory_shortfall',
                        'missing' => 'procurement',
                        'inventory_id' => (int) ($link['inventory_id'] ?? 0),
                        'reason' => 'shortfall_confirmed_procurement_absent',
                    ];
                }
            }
        }
        // Conflicts require explicit dual-status evidence; none invented here.
        if ($conflicts === []) {
            // keep parameter used for signature compatibility with conflict collectors
        }
    }

    /**
     * @param list<array<string, mixed>> $findings
     * @param list<array<string, mixed>> $basePriorities
     * @return list<array<string, mixed>>
     */
    private static function prioritize(array $findings, array $basePriorities): array
    {
        $rank = ['high' => 3, 'medium' => 2, 'low' => 1];
        $rows = [];
        foreach ($findings as $f) {
            if (!is_array($f)) {
                continue;
            }
            $rows[] = [
                'code' => (string) ($f['code'] ?? 'priority'),
                'urgency' => self::normalizeUrgency((string) ($f['urgency'] ?? 'medium')),
                'domain' => (string) ($f['source_domain'] ?? ''),
                'message' => (string) ($f['finding'] ?? ''),
                'entity' => $f['entity'] ?? null,
                'evidence' => $f['evidence'] ?? null,
                'reason' => (string) ($f['reason'] ?? ''),
                'sort_basis' => 'urgency_then_existing_status_signals',
            ];
        }
        foreach ($basePriorities as $p) {
            if (!is_array($p)) {
                continue;
            }
            $rows[] = [
                'code' => (string) ($p['code'] ?? 'priority'),
                'urgency' => self::normalizeUrgency((string) ($p['urgency'] ?? 'medium')),
                'domain' => (string) ($p['domain'] ?? ''),
                'message' => (string) ($p['message'] ?? ''),
                'entity' => $p['entity'] ?? null,
                'evidence' => $p['evidence'] ?? null,
                'reason' => (string) ($p['reason'] ?? 'base_priority_from_confirmed_tools'),
                'sort_basis' => 'urgency_then_existing_status_signals',
            ];
        }
        usort($rows, static function (array $a, array $b) use ($rank): int {
            $ua = $rank[$a['urgency']] ?? 0;
            $ub = $rank[$b['urgency']] ?? 0;
            if ($ua !== $ub) {
                return $ub <=> $ua;
            }
            return strcmp((string) $a['code'], (string) $b['code']);
        });
        return self::dedupeRows($rows, static function (array $r): string {
            return ((string) ($r['code'] ?? '')) . '|' . ((string) ($r['message'] ?? '')) . '|' . ((string) ($r['domain'] ?? ''));
        });
    }

    /**
     * @param list<array<string, mixed>> $findings
     * @param list<array<string, mixed>> $incomplete
     * @param list<array<string, mixed>> $conflicts
     * @param list<array<string, mixed>> $priorities
     * @param list<array<string, mixed>> $failed
     * @return list<array<string, mixed>>
     */
    private static function buildDecisions(array $findings, array $incomplete, array $conflicts, array $priorities, array $failed): array
    {
        $out = [];
        $n = 0;
        foreach ($priorities as $p) {
            if ($n >= 12) {
                break;
            }
            if (!is_array($p)) {
                continue;
            }
            $out[] = [
                'finding' => (string) ($p['message'] ?? $p['code'] ?? ''),
                'evidence' => $p['evidence'] ?? ['code' => $p['code'] ?? null, 'domain' => $p['domain'] ?? null],
                'impact' => self::impactForCode((string) ($p['code'] ?? ''), (string) ($p['domain'] ?? '')),
                'recommended_next_step' => self::recommendForCode((string) ($p['code'] ?? ''), (string) ($p['domain'] ?? '')),
                'write_required' => false,
                'auto_execute' => false,
                'source_domain' => (string) ($p['domain'] ?? ''),
                'urgency' => self::normalizeUrgency((string) ($p['urgency'] ?? 'medium')),
            ];
            $n++;
        }
        foreach ($incomplete as $inc) {
            if ($n >= 15) {
                break;
            }
            if (!is_array($inc)) {
                continue;
            }
            $out[] = [
                'finding' => 'incomplete_cross_domain_chain',
                'evidence' => $inc,
                'impact' => 'operation_may_stall_at_missing_segment',
                'recommended_next_step' => 'review_missing_segment_without_assuming_delay: ' . (string) ($inc['missing'] ?? ''),
                'write_required' => false,
                'auto_execute' => false,
                'source_domain' => (string) ($inc['confirmed'] ?? ''),
                'urgency' => 'medium',
            ];
            $n++;
        }
        foreach ($conflicts as $c) {
            if ($n >= 18) {
                break;
            }
            if (!is_array($c)) {
                continue;
            }
            $out[] = [
                'finding' => (string) ($c['description'] ?? $c['code'] ?? 'conflict'),
                'evidence' => $c,
                'impact' => 'cross_domain_status_conflict',
                'recommended_next_step' => 'reconcile_statuses_using_live_records',
                'write_required' => false,
                'auto_execute' => false,
                'source_domain' => (string) ($c['domains'] ?? ''),
                'urgency' => 'high',
            ];
            $n++;
        }
        if ($failed !== [] && $out === []) {
            $out[] = [
                'finding' => 'partial_domain_failure',
                'evidence' => ['failed' => $failed],
                'impact' => 'analysis_incomplete',
                'recommended_next_step' => 'retry_failed_domain_or_review_permissions',
                'write_required' => false,
                'auto_execute' => false,
                'source_domain' => (string) ($failed[0]['domain'] ?? ''),
                'urgency' => 'medium',
            ];
        }
        if ($out === [] && $findings === []) {
            $out[] = [
                'finding' => 'no_urgent_operational_exceptions_from_live_data',
                'evidence' => ['data_source' => 'live_tenant'],
                'impact' => 'none_detected',
                'recommended_next_step' => 'continue_monitoring_open_operations',
                'write_required' => false,
                'auto_execute' => false,
                'source_domain' => '',
                'urgency' => 'low',
            ];
        }
        return $out;
    }

    private static function impactForCode(string $code, string $domain): string
    {
        if (str_contains($code, 'shortfall') || str_contains($code, 'low_stock')) {
            return 'fulfillment_and_customer_delivery_at_risk';
        }
        if (str_contains($code, 'overdue') || str_contains($code, 'delayed')) {
            return 'downstream_operations_may_wait_on_this_item';
        }
        if (str_contains($code, 'approval')) {
            return 'procurement_workflow_blocked_pending_approval';
        }
        if (str_contains($code, 'at_risk')) {
            return 'customer_relationship_requires_follow_up';
        }
        return $domain !== '' ? $domain . '_operation_requires_attention' : 'operational_attention_required';
    }

    private static function recommendForCode(string $code, string $domain): string
    {
        if (str_contains($code, 'shortfall') || str_contains($code, 'low_stock')) {
            return 'review_stock_and_consider_procurement_after_confirmation';
        }
        if (str_contains($code, 'overdue')) {
            return 'follow_up_open_purchase_order_with_supplier';
        }
        if (str_contains($code, 'delayed') || str_contains($code, 'logistics')) {
            return 'follow_up_open_shipment_or_delivery_status';
        }
        if (str_contains($code, 'approval')) {
            return 'review_pending_approvals_in_procurement';
        }
        if (str_contains($code, 'at_risk')) {
            return 'review_customer_follow_up_in_crm';
        }
        return 'review_live_records_in_' . ($domain !== '' ? $domain : 'relevant_domain');
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private static function evidenceRef(string $domain, string $tool, string $purpose, array $data): array
    {
        $refs = [];
        foreach (['links', 'chain', 'delayed_shipments', 'low_stock', 'at_risk_customers', 'follow_up', 'open_operations', 'overdue_operations'] as $key) {
            if (isset($data[$key]) && is_array($data[$key])) {
                $refs[$key . '_count'] = count($data[$key]);
            }
        }
        if (isset($data['summary']) && is_array($data['summary'])) {
            $refs['summary_keys'] = array_keys($data['summary']);
        }
        return [
            'source_domain' => $domain,
            'tool' => $tool,
            'purpose' => $purpose,
            'data_source' => (string) ($data['data_source'] ?? 'live_tenant'),
            'refs' => $refs,
        ];
    }

    private static function normalizeUrgency(string $urgency): string
    {
        $u = strtolower(trim($urgency));
        return in_array($u, ['high', 'medium', 'low'], true) ? $u : 'medium';
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param callable(array): string $keyFn
     * @return list<array<string, mixed>>
     */
    private static function dedupeRows(array $rows, callable $keyFn): array
    {
        $out = [];
        $seen = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $k = $keyFn($row);
            if (isset($seen[$k])) {
                continue;
            }
            $seen[$k] = true;
            $out[] = $row;
        }
        return array_slice($out, 0, 40);
    }

    /**
     * Format decision-oriented user response (AR/EN) from enriched intelligence.
     */
    public static function formatDecisionSummary(array $intelligence, ProcurementAgentContext $ctx): string
    {
        $ar = $ctx->normalizedLocale() === 'ar';
        $lines = [];

        if (empty($intelligence['data_sufficient'])) {
            $lines[] = $ar
                ? 'البيانات الحية غير كافية لإكمال التحليل الموحّد. تُعرض النتائج المؤكدة فقط.'
                : 'Live data is insufficient for a complete unified analysis. Only confirmed results are shown.';
        } else {
            $lines[] = $ar
                ? 'دعم القرار التشغيلي (أدلة حية فقط — التوصية ليست تنفيذاً):'
                : 'Operational decision support (live evidence only — recommendations are not writes):';
        }

        $domains = $intelligence['domains'] ?? [];
        if (is_array($domains) && $domains !== []) {
            $lines[] = ($ar ? 'المجالات المستخدمة: ' : 'Domains used: ') . implode(', ', $domains);
        }

        $failed = is_array($intelligence['partial_failures'] ?? null) ? $intelligence['partial_failures'] : [];
        if ($failed !== []) {
            $failedDomains = [];
            foreach ($failed as $f) {
                if (is_array($f) && !empty($f['domain'])) {
                    $failedDomains[(string) $f['domain']] = true;
                }
            }
            if ($failedDomains !== []) {
                $lines[] = ($ar ? 'مجالات متعذّرة: ' : 'Failed domains: ') . implode(', ', array_keys($failedDomains));
            }
        }

        $decisions = is_array($intelligence['decisions'] ?? null) ? $intelligence['decisions'] : [];
        if ($decisions !== []) {
            $lines[] = $ar ? 'الأولويات / ماذا تتابع الآن:' : 'Priorities / what to follow now:';
            foreach (array_slice($decisions, 0, 8) as $i => $d) {
                if (!is_array($d)) {
                    continue;
                }
                $n = $i + 1;
                $lines[] = $ar
                    ? "{$n}) النتيجة: " . (string) ($d['finding'] ?? '')
                    : "{$n}) Finding: " . (string) ($d['finding'] ?? '');
                $ev = $d['evidence'] ?? null;
                if (is_array($ev)) {
                    $bits = [];
                    foreach ($ev as $ek => $evv) {
                        if (is_scalar($evv) && (string) $evv !== '') {
                            $bits[] = $ek . '=' . $evv;
                        }
                    }
                    if ($bits !== []) {
                        $lines[] = ($ar ? '   الدليل: ' : '   Evidence: ') . implode(', ', array_slice($bits, 0, 6));
                    }
                }
                $lines[] = ($ar ? '   الأثر: ' : '   Impact: ') . (string) ($d['impact'] ?? '');
                $lines[] = ($ar ? '   الخطوة المقترحة: ' : '   Recommended next step: ') . (string) ($d['recommended_next_step'] ?? '');
            }
        }

        $incomplete = is_array($intelligence['incomplete_chains'] ?? null) ? $intelligence['incomplete_chains'] : [];
        if ($incomplete !== []) {
            $lines[] = $ar ? 'سلاسل مؤكدة جزئياً (بدون افتراض تأخير للجزء المفقود):' : 'Partially confirmed chains (missing segment not assumed delayed):';
            foreach (array_slice($incomplete, 0, 5) as $inc) {
                if (!is_array($inc)) {
                    continue;
                }
                $lines[] = '- ' . (string) ($inc['confirmed'] ?? '') . ' → missing: ' . (string) ($inc['missing'] ?? '');
            }
        }

        $conflicts = is_array($intelligence['conflicts'] ?? null) ? $intelligence['conflicts'] : [];
        if ($conflicts !== []) {
            $lines[] = $ar ? 'تعارضات مؤكدة:' : 'Confirmed conflicts:';
            foreach (array_slice($conflicts, 0, 5) as $c) {
                if (!is_array($c)) {
                    continue;
                }
                $lines[] = '- ' . (string) ($c['description'] ?? $c['code'] ?? '');
            }
        }

        $lines[] = $ar
            ? 'ملاحظة: لا يتم تنفيذ أي كتابة تلقائياً. أي إجراء كتابة يحتاج تأكيداً وصلاحية.'
            : 'Note: no write is auto-executed. Any write action still requires confirmation and authorization.';

        return implode("\n", $lines);
    }
}
