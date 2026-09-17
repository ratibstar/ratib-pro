<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\SystemSetting;

/**
 * Proactive Operations & Early-Warning Engine (not an Agent).
 * Continuous signals → detection → evidence → risk/opportunity → priority → warning → recommendation.
 * Detection ≠ Execution. Persistence via tenant-scoped SystemSetting + optional notifications.
 */
final class ErpProactiveEarlyWarningLayer
{
    public const STATUS_NEW = 'NEW';
    public const STATUS_ACKNOWLEDGED = 'ACKNOWLEDGED';
    public const STATUS_IN_PROGRESS = 'IN_PROGRESS';
    public const STATUS_RESOLVED = 'RESOLVED';
    public const STATUS_DISMISSED = 'DISMISSED';

    public const SEVERITY_LOW = 'LOW';
    public const SEVERITY_MEDIUM = 'MEDIUM';
    public const SEVERITY_HIGH = 'HIGH';
    public const SEVERITY_CRITICAL = 'CRITICAL';

    public const TRIGGER_TYPE = 'agent_early_warning';
    public const ENTITY_TYPE = 'agent_early_warning';
    public const DIGEST_ENTITY_TYPE = 'agent_early_warning_digest';

    /** @var array<string, mixed> */
    private static array $memo = [];

    public static function clearMemo(): void
    {
        self::$memo = [];
    }

    /**
     * On-demand / cron scan — READ only; never auto-writes ERP records.
     *
     * @param array<string, mixed> $options trigger|persist|notify|limit|request_id
     * @return array<string, mixed>
     */
    public static function scan(ProcurementAgentContext $ctx, array $options = []): array
    {
        $companyId = (int) $ctx->companyId;
        if ($companyId < 1) {
            return self::emptyScan('tenant_mismatch');
        }

        $limit = max(1, min(40, (int) ($options['limit'] ?? 20)));
        $trigger = (string) ($options['trigger'] ?? 'on_demand');
        $persist = array_key_exists('persist', $options) ? (bool) $options['persist'] : true;
        $notify = !empty($options['notify']);
        $requestId = (string) ($options['request_id'] ?? '');
        $t0 = microtime(true);

        $memoKey = 'scan:' . $companyId . ':' . md5((string) json_encode([$limit, $trigger, $persist]));
        if (isset(self::$memo[$memoKey])) {
            return self::$memo[$memoKey];
        }

        $scanId = 'ews_' . $companyId . '_' . date('YmdHis') . '_' . substr(bin2hex(random_bytes(3)), 0, 6);
        ErpExecutiveIntelligenceLayer::clearMemo();
        $pack = ErpExecutiveIntelligenceLayer::build($ctx, ['intent_kind' => 'executive', 'proactive' => true], $limit);

        $signals = self::detectSignals($pack);
        $opportunities = self::detectOpportunities($pack);
        // Memory improves recurrence tagging only when current evidence already exists
        $signals = self::annotateRecurringFromMemory($ctx, $signals);
        $warnings = self::signalsToWarnings($companyId, $signals, $opportunities);

        $store = self::loadStore($companyId);
        $dedup = self::mergeDedupAndLifecycle($store, $warnings, $scanId);
        $mergedWarnings = $dedup['warnings'];
        $created = $dedup['created'];
        $suppressed = $dedup['suppressed'];
        $resolved = $dedup['resolved'];
        $updated = $dedup['updated'];

        if ($persist) {
            $store['warnings'] = $mergedWarnings;
            $store['last_scan_id'] = $scanId;
            $store['last_scan_at'] = date('Y-m-d H:i:s');
            $store['last_trigger'] = $trigger;
            self::saveStore($companyId, $store);
        }

        $open = array_values(array_filter(
            $mergedWarnings,
            static fn($w) => is_array($w) && !in_array(($w['status'] ?? ''), [self::STATUS_RESOLVED, self::STATUS_DISMISSED], true)
        ));
        usort($open, static function (array $a, array $b): int {
            return self::severityRank((string) ($b['severity'] ?? '')) <=> self::severityRank((string) ($a['severity'] ?? ''));
        });

        $digest = null;
        if ($notify || $trigger === 'cron') {
            $digest = self::maybeSendDigest($ctx, $open, $store, $scanId, $notify || $trigger === 'cron');
            if ($persist && is_array($digest) && !empty($digest['sent'])) {
                $store = self::loadStore($companyId);
                $store['last_digest_at'] = date('Y-m-d H:i:s');
                $store['last_digest_fingerprint'] = (string) ($digest['fingerprint'] ?? '');
                self::saveStore($companyId, $store);
            }
        }

        $durationMs = (int) round((microtime(true) - $t0) * 1000);
        $result = [
            'data_source' => 'live_tenant',
            'scan_id' => $scanId,
            'request_id' => $requestId !== '' ? $requestId : null,
            'trigger' => $trigger,
            'company_id' => $companyId,
            'as_of' => date('Y-m-d H:i:s'),
            'domains_scanned' => $pack['domains'] ?? [],
            'signals' => array_slice($signals, 0, $limit),
            'opportunities' => array_slice($opportunities, 0, $limit),
            'warnings' => array_slice($open, 0, $limit),
            'warnings_created' => $created,
            'duplicates_suppressed' => $suppressed,
            'warnings_resolved' => $resolved,
            'warnings_updated' => $updated,
            'recommendations' => array_slice(array_map(
                static fn($w) => [
                    'warning_id' => $w['warning_id'] ?? '',
                    'recommended_action' => $w['recommended_action'] ?? '',
                    'priority' => $w['priority'] ?? '',
                    'auto_execute' => false,
                ],
                $open
            ), 0, $limit),
            'digest' => $digest,
            'auto_execute' => false,
            'controlled_autonomy' => false,
            'observability' => [
                'scan_id' => $scanId,
                'trigger' => $trigger,
                'tenant' => $companyId,
                'domains_scanned' => count($pack['domains'] ?? []),
                'signals_detected' => count($signals),
                'warnings_created' => $created,
                'duplicates_suppressed' => $suppressed,
                'opportunities' => count($opportunities),
                'duration_ms' => $durationMs,
            ],
            'explainability' => [
                'evidence_first' => true,
                'hidden_chain_of_thought' => false,
                'format' => 'operational_evidence_decision_summary',
            ],
            'stale_protection' => [
                'requires_reread' => true,
                'requires_revalidation' => true,
                'requires_new_confirmation_for_writes' => true,
            ],
        ];

        return self::$memo[$memoKey] = $result;
    }

    /**
     * Cron-safe scan for one company (tenant isolated).
     *
     * @return array<string, mixed>
     */
    public static function runCronForCompany(int $companyId): array
    {
        if ($companyId < 1) {
            return self::emptyScan('tenant_mismatch');
        }
        $ctx = self::systemContext($companyId);
        if ($ctx === null) {
            return self::emptyScan('context_unavailable');
        }
        return self::scan($ctx, [
            'trigger' => 'cron',
            'persist' => true,
            'notify' => true,
            'limit' => 20,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function listWarnings(int $companyId, bool $includeClosed = false): array
    {
        if ($companyId < 1) {
            return [];
        }
        $store = self::loadStore($companyId);
        $out = [];
        foreach (($store['warnings'] ?? []) as $w) {
            if (!is_array($w)) {
                continue;
            }
            $st = (string) ($w['status'] ?? '');
            if (!$includeClosed && in_array($st, [self::STATUS_RESOLVED, self::STATUS_DISMISSED], true)) {
                continue;
            }
            $out[] = $w;
        }
        usort($out, static function (array $a, array $b): int {
            return self::severityRank((string) ($b['severity'] ?? '')) <=> self::severityRank((string) ($a['severity'] ?? ''));
        });
        return $out;
    }

    /**
     * Update warning lifecycle status (not an ERP business write).
     *
     * @return array<string, mixed>
     */
    public static function updateStatus(int $companyId, string $warningId, string $status): array
    {
        $allowed = [
            self::STATUS_ACKNOWLEDGED,
            self::STATUS_IN_PROGRESS,
            self::STATUS_RESOLVED,
            self::STATUS_DISMISSED,
        ];
        $status = strtoupper(trim($status));
        if ($companyId < 1 || $warningId === '' || !in_array($status, $allowed, true)) {
            return ['success' => false, 'error' => 'invalid_status_update'];
        }
        $store = self::loadStore($companyId);
        $found = false;
        foreach (($store['warnings'] ?? []) as $fp => $w) {
            if (!is_array($w)) {
                continue;
            }
            if ((string) ($w['warning_id'] ?? '') === $warningId || (string) $fp === $warningId) {
                $store['warnings'][$fp]['status'] = $status;
                $store['warnings'][$fp]['updated_at'] = date('Y-m-d H:i:s');
                $found = true;
                break;
            }
        }
        if (!$found) {
            return ['success' => false, 'error' => 'warning_not_found'];
        }
        self::saveStore($companyId, $store);
        return ['success' => true, 'warning_id' => $warningId, 'status' => $status, 'auto_execute' => false];
    }

    /**
     * Re-read + re-validate before acting on a warning (stale protection).
     *
     * @return array<string, mixed>
     */
    public static function revalidateWarning(ProcurementAgentContext $ctx, string $warningId): array
    {
        $companyId = (int) $ctx->companyId;
        $fresh = self::scan($ctx, [
            'trigger' => 'stale_revalidate',
            'persist' => true,
            'notify' => false,
            'limit' => 25,
        ]);
        $match = null;
        foreach (($fresh['warnings'] ?? []) as $w) {
            if (is_array($w) && (string) ($w['warning_id'] ?? '') === $warningId) {
                $match = $w;
                break;
            }
        }
        if ($match === null) {
            return [
                'valid' => false,
                'stale' => true,
                'reason' => 'warning_no_longer_evidenced',
                'requires_new_confirmation' => true,
                'action_blocked' => true,
                'scan_id' => $fresh['scan_id'] ?? null,
            ];
        }
        return [
            'valid' => true,
            'stale' => false,
            'warning' => $match,
            'requires_new_confirmation' => true,
            'action_blocked' => false,
            'scan_id' => $fresh['scan_id'] ?? null,
            'recommended_action' => $match['recommended_action'] ?? '',
            'auto_execute' => false,
        ];
    }

    /**
     * Bridge warning → ErpActionPlanner (never auto-executes).
     *
     * @return array<string, mixed>
     */
    public static function actionBridge(ProcurementAgentContext $ctx, string $warningId): array
    {
        $re = self::revalidateWarning($ctx, $warningId);
        if (empty($re['valid'])) {
            return [
                'success' => false,
                'stale' => true,
                'action_plan' => null,
                'auto_execute' => false,
                'requires_governance' => true,
                'reason' => $re['reason'] ?? 'stale',
            ];
        }
        $w = is_array($re['warning'] ?? null) ? $re['warning'] : [];
        $code = (string) ($w['signal'] ?? $w['risk'] ?? '');
        $message = 'Review operational priority';
        if (str_contains($code, 'inventory') || str_contains($code, 'stock')) {
            $message = 'Create a purchase request for missing stock';
        } elseif (str_contains($code, 'procurement') || str_contains($code, 'approval')) {
            $message = 'List pending approvals';
        } elseif (str_contains($code, 'receivable') || str_contains($code, 'invoice')) {
            $message = 'Review overdue invoices';
        } elseif (str_contains($code, 'crm') || str_contains($code, 'customer') || str_contains($code, 'follow')) {
            $message = 'Follow up at-risk customers';
        } elseif (str_contains($code, 'logistics') || str_contains($code, 'delivery')) {
            $message = 'Follow up delayed shipments';
        }
        $plan = ErpActionPlanner::buildActionPlan($message, $ctx, [
            'incomplete_chains' => [['code' => 'proactive_warning', 'warning_id' => $warningId]],
        ]);
        return [
            'success' => true,
            'insight' => $w,
            'recommended_action' => $w['recommended_action'] ?? '',
            'action_plan' => $plan,
            'auto_execute' => false,
            'requires_governance' => true,
            'requires_confirmation' => true,
            'stale_protection' => true,
            'revalidated' => true,
        ];
    }

    public static function formatDigest(array $scan, ProcurementAgentContext $ctx): string
    {
        $ar = str_starts_with(strtolower((string) ($ctx->locale ?? 'en')), 'ar');
        $warnings = is_array($scan['warnings'] ?? null) ? $scan['warnings'] : [];
        $crit = 0;
        $high = 0;
        foreach ($warnings as $w) {
            $p = strtoupper((string) ($w['priority'] ?? ''));
            if ($p === self::SEVERITY_CRITICAL) {
                $crit++;
            } elseif ($p === self::SEVERITY_HIGH) {
                $high++;
            }
        }
        $ops = count($scan['opportunities'] ?? []);
        if ($ar) {
            return "ملخص استباقي\nتحذيرات حرجة: {$crit}\nعالية: {$high}\nفرص: {$ops}\nلا يتم تنفيذ أي إجراء تلقائيًا.";
        }
        return "Proactive digest\nCritical warnings: {$crit}\nHigh: {$high}\nOpportunities: {$ops}\nNo action is auto-executed.";
    }

    /**
     * @param array<string, mixed> $pack
     * @return list<array<string, mixed>>
     */
    private static function detectSignals(array $pack): array
    {
        $signals = [];
        $kpis = is_array($pack['kpis']['items'] ?? null) ? $pack['kpis']['items'] : [];
        $by = [];
        foreach ($kpis as $k) {
            if (is_array($k) && !empty($k['available'])) {
                $by[(string) ($k['code'] ?? '')] = $k;
            }
        }
        $trends = is_array($pack['trends'] ?? null) ? $pack['trends'] : [];
        $byTrend = [];
        foreach ($trends as $t) {
            if (is_array($t)) {
                $byTrend[(string) ($t['code'] ?? '')] = $t;
            }
        }

        $push = static function (
            string $code,
            string $domain,
            string $severity,
            string $impact,
            array $evidence,
            string $action,
            bool $cross = false
        ) use (&$signals): void {
            $signals[] = [
                'code' => $code,
                'domain' => $domain,
                'severity' => $severity,
                'impact' => $impact,
                'evidence' => $evidence,
                'recommended_action' => $action,
                'cross_domain' => $cross,
                'kind' => 'risk',
            ];
        };

        // Map executive risks → signals (evidence already present)
        foreach (($pack['risks'] ?? []) as $r) {
            if (!is_array($r) || empty($r['evidence'])) {
                continue;
            }
            $sev = self::mapPriorityToSeverity((string) ($r['priority'] ?? self::SEVERITY_MEDIUM));
            $push(
                (string) ($r['code'] ?? 'risk'),
                (string) ($r['source_domain'] ?? 'executive'),
                $sev,
                (string) ($r['impact'] ?? 'operational'),
                is_array($r['evidence']) ? $r['evidence'] : [],
                (string) ($r['recommended_action'] ?? 'review'),
                ($r['impact'] ?? '') === 'cross_domain'
            );
        }

        // Sales decline / increase from trends
        $salesTrend = $byTrend['sales_volume'] ?? $byTrend['sales_order_count'] ?? null;
        if (is_array($salesTrend) && ($salesTrend['trend'] ?? '') === 'decreasing'
            && ($salesTrend['change_pct'] ?? null) !== null
            && abs((float) $salesTrend['change_pct']) >= 15.0
        ) {
            $push('unusual_sales_decline', 'sales', self::SEVERITY_HIGH, 'commercial', [
                'trend' => $salesTrend['trend'],
                'change_pct' => $salesTrend['change_pct'],
                'current' => $salesTrend['current'] ?? null,
                'previous' => $salesTrend['previous'] ?? null,
            ], 'review_sales_pipeline');
        }
        if (is_array($salesTrend) && ($salesTrend['trend'] ?? '') === 'increasing'
            && ($salesTrend['change_pct'] ?? null) !== null
            && (float) $salesTrend['change_pct'] >= 15.0
        ) {
            // also opportunity — keep as signal for cross-domain stockout risk
            $low = (int) ($by['low_stock']['value'] ?? 0);
            if ($low > 0) {
                $push('sales_increase_inventory_pressure', 'sales', self::SEVERITY_HIGH, 'cross_domain', [
                    'sales_change_pct' => $salesTrend['change_pct'],
                    'low_stock' => $low,
                    'relation' => 'associated with',
                ], 'align_inventory_with_rising_sales_demand', true);
            }
        }

        $openSales = (int) ($by['sales_open_count']['value'] ?? 0);
        $lowStock = (int) ($by['low_stock']['value'] ?? 0);
        if ($openSales > 0 && $lowStock > 0 && !self::hasSignal($signals, 'cross_domain_operational_risk')
            && !self::hasSignal($signals, 'sales_increase_inventory_pressure')
        ) {
            $push('stockout_risk_cross', 'inventory', self::SEVERITY_HIGH, 'cross_domain', [
                'sales_open_count' => $openSales,
                'low_stock' => $lowStock,
                'relation' => 'associated with',
            ], 'prepare_procurement_for_sales_demand', true);
        }

        $followups = (int) ($by['crm_followups']['value'] ?? 0);
        $atRisk = (int) ($by['crm_at_risk']['value'] ?? 0);
        if ($followups > 0 && $atRisk > 0 && !self::hasSignal($signals, 'customer_followup_risk')) {
            $push('crm_inactivity_followup', 'crm', self::SEVERITY_MEDIUM, 'commercial', [
                'crm_followups' => $followups,
                'crm_at_risk' => $atRisk,
                'relation' => 'associated with',
            ], 'contact_inactive_or_at_risk_customers', true);
        }

        return $signals;
    }

    /**
     * @param array<string, mixed> $pack
     * @return list<array<string, mixed>>
     */
    private static function detectOpportunities(array $pack): array
    {
        $ops = [];
        $trends = is_array($pack['trends'] ?? null) ? $pack['trends'] : [];
        $kpis = is_array($pack['kpis']['items'] ?? null) ? $pack['kpis']['items'] : [];
        $by = [];
        foreach ($kpis as $k) {
            if (is_array($k) && !empty($k['available'])) {
                $by[(string) ($k['code'] ?? '')] = $k;
            }
        }

        foreach ($trends as $t) {
            if (!is_array($t) || ($t['trend'] ?? '') !== 'increasing') {
                continue;
            }
            if (($t['change_pct'] ?? null) === null || (float) $t['change_pct'] < 10.0) {
                continue;
            }
            $code = (string) ($t['code'] ?? '');
            $ops[] = [
                'code' => 'opportunity_' . $code,
                'domain' => self::domainForKpiCode($code),
                'opportunity' => 'rising_' . $code,
                'severity' => self::SEVERITY_MEDIUM,
                'priority' => ErpExecutiveIntelligenceLayer::PRIORITY_MEDIUM,
                'impact' => 'positive',
                'evidence' => [
                    'trend' => $t['trend'],
                    'change_pct' => $t['change_pct'],
                    'current' => $t['current'] ?? null,
                    'previous' => $t['previous'] ?? null,
                    'period' => $t['period'] ?? null,
                ],
                'recommended_action' => 'monitor_and_capitalize_on_' . $code,
                'kind' => 'opportunity',
            ];
        }

        $low = (int) ($by['low_stock']['value'] ?? 0);
        $openSales = (int) ($by['sales_open_count']['value'] ?? 0);
        if ($low === 0 && $openSales > 0 && isset($by['inventory_value'])) {
            $ops[] = [
                'code' => 'opportunity_inventory_aligned',
                'domain' => 'inventory',
                'opportunity' => 'inventory_availability_aligned_with_demand',
                'severity' => self::SEVERITY_LOW,
                'priority' => ErpExecutiveIntelligenceLayer::PRIORITY_LOW,
                'impact' => 'operational_efficiency',
                'evidence' => [
                    'low_stock' => 0,
                    'sales_open_count' => $openSales,
                    'inventory_value' => $by['inventory_value']['value'] ?? null,
                ],
                'recommended_action' => 'maintain_inventory_service_levels',
                'kind' => 'opportunity',
            ];
        }

        return $ops;
    }

    /**
     * Tag signals with recurring history when current evidence already exists.
     * Never creates a warning from memory alone.
     *
     * @param list<array<string, mixed>> $signals
     * @return list<array<string, mixed>>
     */
    private static function annotateRecurringFromMemory(ProcurementAgentContext $ctx, array $signals): array
    {
        if ($signals === []) {
            return $signals;
        }
        try {
            $mem = ErpOperationalMemoryLayer::forIntelligence($ctx, ['proactive' => true, 'intent_kind' => 'executive']);
        } catch (\Throwable $e) {
            return $signals;
        }
        $patternCodes = [];
        $failedOutcomes = [];
        foreach (($mem['relevant_memory'] ?? []) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $type = (string) ($item['type'] ?? '');
            $title = strtolower((string) ($item['title'] ?? ''));
            if ($type === 'pattern' || !empty($item['recurring'])) {
                $patternCodes[$title] = true;
            }
            if ($type === 'outcome' && in_array(strtoupper((string) ($item['outcome'] ?? '')), ['FAILED', 'NEGATIVE_OUTCOME', 'NO_EFFECT'], true)) {
                $failedOutcomes[$title] = true;
            }
            if ($type === 'warning') {
                $patternCodes[strtolower((string) ($item['title'] ?? ''))] = true;
            }
        }
        foreach ($signals as &$s) {
            if (!is_array($s) || empty($s['evidence'])) {
                continue;
            }
            $code = strtolower((string) ($s['code'] ?? ''));
            $recurring = isset($patternCodes[$code]);
            foreach (array_keys($patternCodes) as $pc) {
                if ($pc !== '' && ($code === $pc || str_contains($pc, $code) || str_contains($code, $pc))) {
                    $recurring = true;
                    break;
                }
            }
            if ($recurring) {
                $s['recurring'] = true;
                $s['memory_note'] = 'recurring_pattern_with_current_evidence';
                // Mild severity nudge only when already HIGH/MEDIUM with current evidence
                if (($s['severity'] ?? '') === self::SEVERITY_MEDIUM) {
                    $s['severity'] = self::SEVERITY_HIGH;
                }
            }
        }
        unset($s);
        return $signals;
    }

    /**
     * @param list<array<string, mixed>> $signals
     * @param list<array<string, mixed>> $opportunities
     * @return list<array<string, mixed>>
     */
    private static function signalsToWarnings(int $companyId, array $signals, array $opportunities): array
    {
        $out = [];
        foreach ($signals as $s) {
            if (!is_array($s) || empty($s['evidence'])) {
                continue;
            }
            $fp = self::fingerprint($companyId, (string) $s['code'], $s['evidence']);
            $sev = (string) ($s['severity'] ?? self::SEVERITY_MEDIUM);
            $out[] = [
                'warning_id' => 'ew_' . substr($fp, 0, 16),
                'fingerprint' => $fp,
                'domain' => (string) ($s['domain'] ?? ''),
                'signal' => (string) ($s['code'] ?? ''),
                'evidence' => $s['evidence'],
                'detected_at' => date('Y-m-d H:i:s'),
                'impact' => (string) ($s['impact'] ?? ''),
                'risk' => (string) ($s['code'] ?? ''),
                'severity' => $sev,
                'priority' => $sev,
                'related_records' => self::relatedFromEvidence($s['evidence']),
                'recommended_action' => (string) ($s['recommended_action'] ?? ''),
                'status' => self::STATUS_NEW,
                'kind' => 'risk',
                'cross_domain' => !empty($s['cross_domain']),
                'recurring' => !empty($s['recurring']),
                'memory_note' => $s['memory_note'] ?? null,
                'company_id' => $companyId,
            ];
        }
        foreach ($opportunities as $o) {
            if (!is_array($o) || empty($o['evidence'])) {
                continue;
            }
            $fp = self::fingerprint($companyId, (string) $o['code'], $o['evidence']);
            $sev = (string) ($o['severity'] ?? self::SEVERITY_LOW);
            $out[] = [
                'warning_id' => 'ew_' . substr($fp, 0, 16),
                'fingerprint' => $fp,
                'domain' => (string) ($o['domain'] ?? ''),
                'signal' => (string) ($o['code'] ?? ''),
                'evidence' => $o['evidence'],
                'detected_at' => date('Y-m-d H:i:s'),
                'impact' => (string) ($o['impact'] ?? 'positive'),
                'risk' => null,
                'opportunity' => (string) ($o['opportunity'] ?? ''),
                'severity' => $sev,
                'priority' => (string) ($o['priority'] ?? $sev),
                'related_records' => self::relatedFromEvidence($o['evidence']),
                'recommended_action' => (string) ($o['recommended_action'] ?? ''),
                'status' => self::STATUS_NEW,
                'kind' => 'opportunity',
                'cross_domain' => false,
                'company_id' => $companyId,
            ];
        }
        return $out;
    }

    /**
     * @param array<string, mixed> $store
     * @param list<array<string, mixed>> $incoming
     * @return array{warnings: array<string, array>, created: int, suppressed: int, resolved: int, updated: int}
     */
    private static function mergeDedupAndLifecycle(array $store, array $incoming, string $scanId): array
    {
        /** @var array<string, array<string, mixed>> $existing */
        $existing = [];
        foreach (($store['warnings'] ?? []) as $k => $w) {
            if (is_array($w)) {
                $fp = (string) ($w['fingerprint'] ?? $k);
                $existing[$fp] = $w;
            }
        }

        $created = 0;
        $suppressed = 0;
        $updated = 0;
        $seen = [];

        foreach ($incoming as $w) {
            $fp = (string) ($w['fingerprint'] ?? '');
            if ($fp === '') {
                continue;
            }
            $seen[$fp] = true;
            if (isset($existing[$fp])) {
                $prevStatus = (string) ($existing[$fp]['status'] ?? self::STATUS_NEW);
                if (in_array($prevStatus, [self::STATUS_RESOLVED, self::STATUS_DISMISSED], true)) {
                    // Re-open only if evidence returned
                    $w['status'] = self::STATUS_NEW;
                    $w['warning_id'] = (string) ($existing[$fp]['warning_id'] ?? $w['warning_id']);
                    $w['reopened_at'] = date('Y-m-d H:i:s');
                    $existing[$fp] = $w;
                    $created++;
                } else {
                    // Dedup: refresh evidence/priority, keep status & warning_id
                    $existing[$fp]['evidence'] = $w['evidence'];
                    $existing[$fp]['severity'] = $w['severity'];
                    $existing[$fp]['priority'] = $w['priority'];
                    $existing[$fp]['impact'] = $w['impact'];
                    $existing[$fp]['recommended_action'] = $w['recommended_action'];
                    $existing[$fp]['related_records'] = $w['related_records'];
                    $existing[$fp]['updated_at'] = date('Y-m-d H:i:s');
                    $existing[$fp]['last_scan_id'] = $scanId;
                    $suppressed++;
                    $updated++;
                }
            } else {
                $w['last_scan_id'] = $scanId;
                $existing[$fp] = $w;
                $created++;
            }
        }

        $resolved = 0;
        foreach ($existing as $fp => $w) {
            if (!empty($seen[$fp])) {
                continue;
            }
            $st = (string) ($w['status'] ?? '');
            if (in_array($st, [self::STATUS_RESOLVED, self::STATUS_DISMISSED], true)) {
                continue;
            }
            // Cause gone → RESOLVED from evidence
            $existing[$fp]['status'] = self::STATUS_RESOLVED;
            $existing[$fp]['resolved_at'] = date('Y-m-d H:i:s');
            $existing[$fp]['resolve_reason'] = 'evidence_no_longer_present';
            $existing[$fp]['updated_at'] = date('Y-m-d H:i:s');
            $resolved++;
        }

        return [
            'warnings' => $existing,
            'created' => $created,
            'suppressed' => $suppressed,
            'resolved' => $resolved,
            'updated' => $updated,
        ];
    }

    /**
     * @param list<array<string, mixed>> $open
     * @param array<string, mixed> $store
     * @return array<string, mixed>|null
     */
    private static function maybeSendDigest(
        ProcurementAgentContext $ctx,
        array $open,
        array $store,
        string $scanId,
        bool $forceAttempt
    ): ?array {
        if (!$forceAttempt) {
            return null;
        }
        $companyId = (int) $ctx->companyId;
        $critical = [];
        $high = [];
        $opps = [];
        foreach ($open as $w) {
            if (!is_array($w)) {
                continue;
            }
            $p = strtoupper((string) ($w['priority'] ?? ''));
            if (($w['kind'] ?? '') === 'opportunity') {
                $opps[] = $w;
                continue;
            }
            if ($p === self::SEVERITY_CRITICAL) {
                $critical[] = $w;
            } elseif ($p === self::SEVERITY_HIGH) {
                $high[] = $w;
            }
        }

        if ($critical === [] && $high === [] && $opps === []) {
            return ['sent' => false, 'reason' => 'nothing_to_digest'];
        }

        $fpParts = [];
        foreach (array_merge($critical, $high, array_slice($opps, 0, 5)) as $w) {
            $fpParts[] = (string) ($w['fingerprint'] ?? $w['warning_id'] ?? '');
        }
        sort($fpParts);
        $digestFp = sha1($companyId . '|digest|' . implode(',', $fpParts));

        $lastAt = (string) ($store['last_digest_at'] ?? '');
        $lastFp = (string) ($store['last_digest_fingerprint'] ?? '');
        if ($lastFp === $digestFp) {
            return ['sent' => false, 'reason' => 'duplicate_digest', 'fingerprint' => $digestFp];
        }
        if ($lastAt !== '' && strtotime($lastAt) !== false && (time() - strtotime($lastAt)) < 20 * 3600) {
            // Periodic: at most ~daily unless fingerprints changed to more critical — still skip same-day spam
            if ($critical === []) {
                return ['sent' => false, 'reason' => 'digest_cooldown', 'fingerprint' => $digestFp];
            }
        }

        $ar = str_starts_with(strtolower((string) ($ctx->locale ?? 'en')), 'ar');
        $title = $ar ? 'ملخص تنبيهات RATEB الاستباقية' : 'RATEB proactive early-warning digest';
        $lines = [];
        $lines[] = $ar
            ? ('حرجة: ' . count($critical) . ' | عالية: ' . count($high) . ' | فرص: ' . count($opps))
            : ('Critical: ' . count($critical) . ' | High: ' . count($high) . ' | Opportunities: ' . count($opps));
        foreach (array_slice($critical, 0, 5) as $w) {
            $lines[] = '[CRITICAL] ' . (string) ($w['signal'] ?? '');
        }
        foreach (array_slice($high, 0, 5) as $w) {
            $lines[] = '[HIGH] ' . (string) ($w['signal'] ?? '');
        }
        $lines[] = $ar ? 'لا يُنفَّذ أي إجراء تلقائيًا.' : 'No action is auto-executed.';
        $message = implode("\n", $lines);

        $entityId = self::fingerprintToEntityId($digestFp);
        $notified = 0;
        try {
            $notified = (new NotificationService())->notifyCompany(
                $companyId,
                $title,
                $message,
                count($critical) > 0 ? 'warning' : 'info',
                self::TRIGGER_TYPE,
                self::DIGEST_ENTITY_TYPE,
                $entityId
            );
        } catch (\Throwable $e) {
            return ['sent' => false, 'reason' => 'notify_failed', 'fingerprint' => $digestFp, 'scan_id' => $scanId];
        }

        return [
            'sent' => $notified > 0,
            'fingerprint' => $digestFp,
            'notifications' => $notified,
            'scan_id' => $scanId,
            'critical_count' => count($critical),
            'high_count' => count($high),
            'opportunity_count' => count($opps),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function loadStore(int $companyId): array
    {
        $key = self::settingKey($companyId);
        try {
            $raw = (new SystemSetting())->get($key, '{}');
            $decoded = json_decode((string) $raw, true);
            if (!is_array($decoded)) {
                return ['warnings' => [], 'company_id' => $companyId];
            }
            if ((int) ($decoded['company_id'] ?? 0) !== $companyId && isset($decoded['company_id'])) {
                // Tenant isolation hard stop
                return ['warnings' => [], 'company_id' => $companyId];
            }
            $decoded['company_id'] = $companyId;
            if (!isset($decoded['warnings']) || !is_array($decoded['warnings'])) {
                $decoded['warnings'] = [];
            }
            return $decoded;
        } catch (\Throwable $e) {
            return ['warnings' => [], 'company_id' => $companyId];
        }
    }

    /**
     * @param array<string, mixed> $store
     */
    private static function saveStore(int $companyId, array $store): void
    {
        $store['company_id'] = $companyId;
        $key = self::settingKey($companyId);
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
                    'setting_group' => 'agent_early_warnings',
                ]);
            } else {
                $model->create([
                    'setting_key' => $key,
                    'setting_value' => $json,
                    'setting_group' => 'agent_early_warnings',
                ]);
            }
        } catch (\Throwable $e) {
            // persistence best-effort; scan result still returned in-memory
        }
    }

    private static function settingKey(int $companyId): string
    {
        return 'agent_early_warnings_c' . $companyId;
    }

    /**
     * @param array<string, mixed> $evidence
     */
    private static function fingerprint(int $companyId, string $code, array $evidence): string
    {
        $norm = $evidence;
        ksort($norm);
        // Drop volatile timestamps from fingerprint
        unset($norm['as_of'], $norm['detected_at'], $norm['updated_at']);
        return hash('sha256', $companyId . '|' . $code . '|' . json_encode($norm, JSON_UNESCAPED_UNICODE));
    }

    private static function fingerprintToEntityId(string $fp): int
    {
        $n = (int) sprintf('%u', crc32($fp));
        return $n > 0 ? $n : 1;
    }

    /**
     * @param array<string, mixed> $evidence
     * @return list<array<string, mixed>>
     */
    private static function relatedFromEvidence(array $evidence): array
    {
        $out = [];
        foreach ($evidence as $k => $v) {
            if (is_scalar($v)) {
                $out[] = ['field' => (string) $k, 'value' => $v];
            }
        }
        return array_slice($out, 0, 12);
    }

    /**
     * @param list<array<string, mixed>> $signals
     */
    private static function hasSignal(array $signals, string $code): bool
    {
        foreach ($signals as $s) {
            if (is_array($s) && ($s['code'] ?? '') === $code) {
                return true;
            }
        }
        return false;
    }

    private static function domainForKpiCode(string $code): string
    {
        if (str_starts_with($code, 'sales_')) {
            return 'sales';
        }
        if (str_starts_with($code, 'crm_')) {
            return 'crm';
        }
        if (str_contains($code, 'purchase') || str_contains($code, 'procurement')) {
            return 'procurement';
        }
        if (str_contains($code, 'stock') || str_contains($code, 'inventory')) {
            return 'inventory';
        }
        if (str_starts_with($code, 'ar_') || str_starts_with($code, 'ap_') || str_contains($code, 'receivable') || str_contains($code, 'payable') || str_contains($code, 'cash') || str_contains($code, 'revenue')) {
            return 'accounting';
        }
        if (str_starts_with($code, 'logistics_')) {
            return 'logistics';
        }
        if (str_contains($code, 'supplier')) {
            return 'suppliers';
        }
        return 'executive';
    }

    private static function mapPriorityToSeverity(string $p): string
    {
        $p = strtoupper($p);
        return match ($p) {
            self::SEVERITY_CRITICAL, 'CRITICAL' => self::SEVERITY_CRITICAL,
            self::SEVERITY_HIGH, 'HIGH' => self::SEVERITY_HIGH,
            self::SEVERITY_LOW, 'LOW' => self::SEVERITY_LOW,
            default => self::SEVERITY_MEDIUM,
        };
    }

    private static function severityRank(string $s): int
    {
        return match (strtoupper($s)) {
            self::SEVERITY_CRITICAL => 4,
            self::SEVERITY_HIGH => 3,
            self::SEVERITY_MEDIUM => 2,
            self::SEVERITY_LOW => 1,
            default => 0,
        };
    }

    private static function systemContext(int $companyId): ?ProcurementAgentContext
    {
        try {
            $ref = new \ReflectionClass(ProcurementAgentContext::class);
            $ctor = $ref->getConstructor();
            if ($ctor === null) {
                return null;
            }
            $ctor->setAccessible(true);
            $planLimits = new PlanLimitService();
            $modules = [];
            foreach (['procurement', 'suppliers', 'inventory', 'pos', 'crm', 'logistics', 'accounting', 'dashboard'] as $m) {
                if ($planLimits->companyHasModule($companyId, $m) || $m === 'dashboard') {
                    $modules[] = $m;
                }
            }
            // Ensure logistics/pos aliases if sales-related modules present
            if (!in_array('pos', $modules, true) && $planLimits->companyHasModule($companyId, 'pos')) {
                $modules[] = 'pos';
            }
            $ctx = $ref->newInstanceWithoutConstructor();
            $ctor->invoke(
                $ctx,
                0,
                $companyId,
                'en',
                'cron:proactive:' . $companyId,
                ['ai.view', 'dashboard.view', 'reports.view', 'accounting.view', 'procurement.view', 'inventory.manage', 'crm.view', 'logistics.view', 'pos.view', 'suppliers.manage'],
                true,
                $modules
            );
            return $ctx;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function emptyScan(string $reason): array
    {
        return [
            'data_source' => 'live_tenant',
            'error' => $reason,
            'signals' => [],
            'warnings' => [],
            'opportunities' => [],
            'auto_execute' => false,
            'observability' => ['error' => $reason],
        ];
    }
}
