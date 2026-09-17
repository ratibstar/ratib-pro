<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\SystemSetting;

/**
 * Operational Learning & Continuous Optimization (not an Agent / not ML).
 * Evidence → metrics → deterministic weights → better recommendation ordering.
 * Never modifies governance, security, permissions, code, or .env.
 */
final class ErpOperationalLearningLayer
{
    public const OUTCOME_SUCCESS = 'SUCCESS';
    public const OUTCOME_PARTIAL = 'PARTIAL_SUCCESS';
    public const OUTCOME_NO_EFFECT = 'NO_EFFECT';
    public const OUTCOME_NEGATIVE = 'NEGATIVE_OUTCOME';
    public const OUTCOME_FAILED = 'FAILED';
    public const OUTCOME_UNKNOWN = 'UNKNOWN';

    public const SUFFICIENT = 'SUFFICIENT';
    public const LIMITED = 'LIMITED';
    public const INSUFFICIENT = 'INSUFFICIENT';

    public const EFFECTIVE = 'EFFECTIVE';
    public const INEFFECTIVE = 'INEFFECTIVE';
    public const EFFECTIVENESS_UNKNOWN = 'UNKNOWN';

    private const MIN_PATTERN_OCCURRENCES = 3;
    private const MIN_LEARN_SAMPLES = 3;

    /** @var array<string, mixed> */
    private static array $memo = [];

    public static function clearMemo(): void
    {
        self::$memo = [];
    }

    /**
     * Record post-execution outcome (execution ≠ business success).
     *
     * @param array<string, mixed> $action
     * @param array<string, mixed> $execResult
     * @param array<string, mixed> $verification
     * @param array<string, mixed> $meta request_id, warning_id, recommendation, kpi_code
     * @return array<string, mixed>
     */
    public static function recordActionOutcome(
        ProcurementAgentContext $ctx,
        array $action,
        array $execResult,
        array $verification,
        array $meta = []
    ): array {
        $companyId = (int) $ctx->companyId;
        if ($companyId < 1) {
            return ['success' => false, 'error' => 'tenant_mismatch', 'learned' => false];
        }

        $tool = (string) ($action['tool'] ?? '');
        $domain = (string) ($action['domain'] ?? ErpToolRegistry::domainForTool($tool) ?? 'procurement');
        $requestId = (string) ($meta['request_id'] ?? '');
        $actionId = (string) ($meta['action_id'] ?? ('act_' . substr(sha1($requestId . '|' . $tool . '|' . microtime(true)), 0, 12)));

        $execOk = !empty($execResult['success']);
        $verified = !empty($verification['verified']);
        $classification = self::classifyImmediateOutcome($execOk, $verified, $verification);

        $outcome = [
            'outcome_id' => 'out_' . substr(sha1($companyId . '|' . $actionId . '|' . $classification), 0, 16),
            'request_id' => $requestId !== '' ? $requestId : null,
            'action_id' => $actionId,
            'user_id' => (int) $ctx->userId,
            'company_id' => $companyId,
            'domain' => $domain,
            'tool' => $tool,
            'action_type' => ErpActionPlanner::classifyTool($tool),
            'record' => self::safeRecordRef($action, $execResult, $verification),
            'previous_state' => self::sanitizeState($action['previous_state'] ?? null),
            'new_state' => self::sanitizeState($verification['new_state'] ?? null),
            'verification' => [
                'verified' => $verified,
                'incomplete' => !empty($verification['incomplete']),
                'message' => (string) ($verification['message'] ?? ''),
            ],
            'execution_success' => $execOk,
            'outcome_status' => $classification,
            'business_outcome' => self::OUTCOME_UNKNOWN, // measured later when evidence available
            'outcome_timestamp' => date('Y-m-d H:i:s'),
            'related_kpi' => $meta['kpi_code'] ?? null,
            'related_warning_id' => $meta['warning_id'] ?? null,
            'related_recommendation' => $meta['recommendation'] ?? ($action['recommended_action'] ?? null),
            'blocked' => !empty($meta['blocked']),
            'data_sufficiency' => self::INSUFFICIENT,
            'learned' => false,
        ];

        $store = self::loadStore($companyId);
        $fp = self::outcomeFingerprint($outcome);
        if (isset($store['outcomes'][$fp])) {
            return [
                'success' => true,
                'duplicate' => true,
                'outcome' => $store['outcomes'][$fp],
                'learned' => false,
            ];
        }

        $store['outcomes'][$fp] = $outcome;
        $signal = self::buildLearningSignal($companyId, 'action_executed', $outcome, [
            'execution_success' => $execOk,
            'verified' => $verified,
            'classification' => $classification,
        ]);
        if ($classification === self::OUTCOME_FAILED || !empty($meta['blocked'])) {
            $signal = self::buildLearningSignal($companyId, !empty($meta['blocked']) ? 'action_blocked' : 'action_failed', $outcome, [
                'classification' => $classification,
            ]);
        } elseif ($verified) {
            $signal = self::buildLearningSignal($companyId, 'action_verified', $outcome, [
                'classification' => $classification,
            ]);
        }
        self::appendSignal($store, $signal);

        // Warning linkage
        if (!empty($meta['warning_id'])) {
            self::touchWarningEffectiveness($store, (string) $meta['warning_id'], $outcome);
        }
        if (!empty($meta['recommendation'])) {
            self::touchRecommendationStats($store, (string) $meta['recommendation'], $domain, $tool, 'accepted_or_executed');
        }

        $learn = self::maybeLearn($store, $companyId);
        self::saveStore($companyId, $store);

        return [
            'success' => true,
            'duplicate' => false,
            'outcome' => $outcome,
            'signal' => $signal,
            'learned' => !empty($learn['learned']),
            'learning_audit' => $learn['audit'] ?? [],
            'auto_execute' => false,
            'governance_immutable' => true,
        ];
    }

    /**
     * Later business-outcome measurement from live evidence (not execution success).
     *
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public static function measureBusinessOutcome(ProcurementAgentContext $ctx, string $outcomeId, array $options = []): array
    {
        $companyId = (int) $ctx->companyId;
        if ($companyId < 1) {
            return ['success' => false, 'error' => 'tenant_mismatch'];
        }
        $store = self::loadStore($companyId);
        $foundFp = null;
        $outcome = null;
        foreach (($store['outcomes'] ?? []) as $fp => $row) {
            if (is_array($row) && ((string) ($row['outcome_id'] ?? '') === $outcomeId || (string) ($row['action_id'] ?? '') === $outcomeId)) {
                $foundFp = (string) $fp;
                $outcome = $row;
                break;
            }
        }
        if ($outcome === null) {
            return ['success' => false, 'error' => 'outcome_not_found'];
        }

        $business = self::OUTCOME_UNKNOWN;
        $evidence = [];
        $sufficiency = self::INSUFFICIENT;

        $warningId = (string) ($outcome['related_warning_id'] ?? $options['warning_id'] ?? '');
        if ($warningId !== '') {
            $warnings = ErpProactiveEarlyWarningLayer::listWarnings($companyId, true);
            foreach ($warnings as $w) {
                if (!is_array($w)) {
                    continue;
                }
                if ((string) ($w['warning_id'] ?? '') !== $warningId) {
                    continue;
                }
                $st = (string) ($w['status'] ?? '');
                $evidence['warning_status'] = $st;
                if ($st === ErpProactiveEarlyWarningLayer::STATUS_RESOLVED) {
                    $business = self::OUTCOME_SUCCESS;
                    $sufficiency = self::LIMITED;
                } elseif (in_array($st, [ErpProactiveEarlyWarningLayer::STATUS_NEW, ErpProactiveEarlyWarningLayer::STATUS_IN_PROGRESS], true)) {
                    $business = self::OUTCOME_NO_EFFECT;
                    $sufficiency = self::LIMITED;
                }
                break;
            }
        }

        // KPI delta if provided with before/after evidence
        $before = $options['kpi_before'] ?? null;
        $after = $options['kpi_after'] ?? null;
        if (is_numeric($before) && is_numeric($after)) {
            $evidence['kpi_before'] = (float) $before;
            $evidence['kpi_after'] = (float) $after;
            $delta = (float) $after - (float) $before;
            $evidence['kpi_delta'] = $delta;
            $direction = (string) ($options['improve_direction'] ?? 'up');
            $improved = $direction === 'down' ? ($delta < 0) : ($delta > 0);
            $worsened = $direction === 'down' ? ($delta > 0) : ($delta < 0);
            if (abs($delta) < 0.00001) {
                $business = self::OUTCOME_NO_EFFECT;
            } elseif ($improved) {
                $business = self::OUTCOME_SUCCESS;
            } elseif ($worsened) {
                $business = self::OUTCOME_NEGATIVE;
            }
            $sufficiency = self::SUFFICIENT;
        }

        if ($business === self::OUTCOME_UNKNOWN && empty($evidence)) {
            return [
                'success' => true,
                'outcome_status' => self::OUTCOME_UNKNOWN,
                'business_outcome' => self::OUTCOME_UNKNOWN,
                'data_sufficiency' => self::INSUFFICIENT,
                'learned' => false,
                'note' => 'DO NOT LEARN — INSUFFICIENT EVIDENCE',
            ];
        }

        $outcome['business_outcome'] = $business;
        $outcome['business_evidence'] = $evidence;
        $outcome['data_sufficiency'] = $sufficiency;
        $outcome['measured_at'] = date('Y-m-d H:i:s');
        $store['outcomes'][$foundFp] = $outcome;

        $sigCode = match ($business) {
            self::OUTCOME_SUCCESS => 'action_produced_expected_result',
            self::OUTCOME_NO_EFFECT => 'action_produced_no_measurable_effect',
            self::OUTCOME_NEGATIVE => 'action_produced_negative_outcome',
            self::OUTCOME_PARTIAL => 'action_partial_success',
            default => 'action_outcome_unknown',
        };
        $signal = self::buildLearningSignal($companyId, $sigCode, $outcome, $evidence, $sufficiency);
        self::appendSignal($store, $signal);

        if (!empty($outcome['related_recommendation'])) {
            $eff = $business === self::OUTCOME_SUCCESS ? self::EFFECTIVE
                : ($business === self::OUTCOME_UNKNOWN ? self::EFFECTIVENESS_UNKNOWN : self::INEFFECTIVE);
            self::touchRecommendationStats(
                $store,
                (string) $outcome['related_recommendation'],
                (string) ($outcome['domain'] ?? ''),
                (string) ($outcome['tool'] ?? ''),
                'measured',
                $eff
            );
        }
        if ($warningId !== '') {
            self::touchWarningEffectiveness($store, $warningId, $outcome, $business);
        }

        $learn = ['learned' => false, 'audit' => []];
        if ($sufficiency !== self::INSUFFICIENT) {
            $learn = self::maybeLearn($store, $companyId);
        }
        self::saveStore($companyId, $store);

        return [
            'success' => true,
            'outcome' => $outcome,
            'business_outcome' => $business,
            'data_sufficiency' => $sufficiency,
            'signal' => $signal,
            'learned' => !empty($learn['learned']),
            'learning_audit' => $learn['audit'] ?? [],
        ];
    }

    /**
     * Forecast → actual feedback (does not mutate forecast model).
     *
     * @param array<string, mixed> $forecastItem
     * @return array<string, mixed>
     */
    public static function recordForecastFeedback(
        ProcurementAgentContext $ctx,
        array $forecastItem,
        float $actualValue,
        ?string $period = null
    ): array {
        $companyId = (int) $ctx->companyId;
        if ($companyId < 1) {
            return ['success' => false, 'error' => 'tenant_mismatch'];
        }
        if (empty($forecastItem['available']) || !isset($forecastItem['prediction'])) {
            return [
                'success' => true,
                'status' => self::EFFECTIVENESS_UNKNOWN,
                'data_sufficiency' => self::INSUFFICIENT,
                'note' => 'DO NOT LEARN — forecast unavailable',
                'learned' => false,
            ];
        }
        $predicted = (float) $forecastItem['prediction'];
        $diff = $actualValue - $predicted;
        $err = abs($predicted) > 0.00001 ? abs($diff / abs($predicted)) : null;
        $row = [
            'forecast_code' => (string) ($forecastItem['code'] ?? 'forecast'),
            'predicted' => $predicted,
            'actual' => $actualValue,
            'difference' => $diff,
            'error_ratio' => $err,
            'period' => $period ?? ($forecastItem['period_used'] ?? null),
            'basis' => $forecastItem['basis'] ?? null,
            'is_fact' => false,
            'recorded_at' => date('Y-m-d H:i:s'),
            'company_id' => $companyId,
            'data_sufficiency' => $err === null ? self::LIMITED : self::SUFFICIENT,
        ];
        $store = self::loadStore($companyId);
        $key = sha1($companyId . '|' . $row['forecast_code'] . '|' . (string) $row['period']);
        if (isset($store['forecast_feedback'][$key])) {
            return ['success' => true, 'duplicate' => true, 'feedback' => $store['forecast_feedback'][$key], 'learned' => false];
        }
        $store['forecast_feedback'][$key] = $row;
        $signal = self::buildLearningSignal($companyId, 'forecast_accuracy_observed', [
            'outcome_id' => 'fc_' . substr($key, 0, 12),
            'domain' => 'executive',
            'tool' => 'get_executive_forecast',
        ], $row, (string) $row['data_sufficiency']);
        self::appendSignal($store, $signal);
        // Soft weight: prefer forecasts with lower historical error for display ordering only
        $code = (string) $row['forecast_code'];
        $prev = (float) ($store['weights']['forecast_display'][$code] ?? 1.0);
        $new = $prev;
        if ($err !== null && (string) $row['data_sufficiency'] === self::SUFFICIENT) {
            $new = max(0.2, min(2.0, $prev * ($err < 0.2 ? 1.05 : ($err > 0.5 ? 0.9 : 1.0))));
        }
        $audit = self::setWeight($store, 'forecast_display', $code, $prev, $new, 'forecast_error_feedback', $signal['signal_id'] ?? '');
        self::saveStore($companyId, $store);
        return [
            'success' => true,
            'feedback' => $row,
            'signal' => $signal,
            'learning_audit' => $audit,
            'model_mutated' => false,
            'learned' => $audit !== [],
        ];
    }

    /**
     * User feedback signal (not alone a business outcome).
     *
     * @return array<string, mixed>
     */
    public static function recordUserFeedback(
        ProcurementAgentContext $ctx,
        string $targetType,
        string $targetId,
        string $feedback
    ): array {
        $companyId = (int) $ctx->companyId;
        $feedback = strtolower(trim($feedback));
        $allowed = ['useful', 'not_useful', 'action_taken', 'action_not_taken'];
        if ($companyId < 1 || !in_array($feedback, $allowed, true) || $targetId === '') {
            return ['success' => false, 'error' => 'invalid_feedback'];
        }
        $store = self::loadStore($companyId);
        $row = [
            'feedback_id' => 'fb_' . substr(sha1($companyId . '|' . $targetType . '|' . $targetId . '|' . $feedback . '|' . date('Ymd')), 0, 12),
            'target_type' => $targetType,
            'target_id' => $targetId,
            'feedback' => $feedback,
            'user_id' => (int) $ctx->userId,
            'company_id' => $companyId,
            'at' => date('Y-m-d H:i:s'),
            'alone_not_business_outcome' => true,
        ];
        $fp = sha1($row['feedback_id']);
        if (isset($store['feedback'][$fp])) {
            return [
                'success' => true,
                'duplicate' => true,
                'feedback' => $store['feedback'][$fp],
                'business_outcome' => null,
            ];
        }
        $store['feedback'][$fp] = $row;
        $signal = self::buildLearningSignal($companyId, 'recommendation_' . $feedback, [
            'outcome_id' => $row['feedback_id'],
            'domain' => 'executive',
            'tool' => 'record_recommendation_feedback',
            'related_recommendation' => $targetId,
        ], $row, self::LIMITED);
        self::appendSignal($store, $signal);
        if ($targetType === 'recommendation') {
            self::touchRecommendationStats($store, $targetId, 'executive', '', $feedback);
        }
        self::saveStore($companyId, $store);
        return ['success' => true, 'feedback' => $row, 'signal' => $signal, 'business_outcome' => null];
    }

    /**
     * Full learning insights pack (tenant-scoped).
     *
     * @return array<string, mixed>
     */
    public static function analyze(ProcurementAgentContext $ctx, int $limit = 20): array
    {
        $companyId = (int) $ctx->companyId;
        if ($companyId < 1) {
            return ['data_source' => 'live_tenant', 'error' => 'tenant_mismatch'];
        }
        $memoKey = 'analyze:' . $companyId . ':' . $limit;
        if (isset(self::$memo[$memoKey])) {
            return self::$memo[$memoKey];
        }
        $store = self::loadStore($companyId);
        $patterns = self::detectPatterns($store);
        $root = self::rootCauseSupport($patterns, $store);
        $pack = [
            'data_source' => 'live_tenant',
            'company_id' => $companyId,
            'as_of' => date('Y-m-d H:i:s'),
            'outcomes' => array_slice(array_values($store['outcomes'] ?? []), -$limit),
            'signals' => array_slice(array_values($store['signals'] ?? []), -$limit),
            'recommendation_effectiveness' => array_values($store['recommendations'] ?? []),
            'warning_effectiveness' => array_values($store['warnings'] ?? []),
            'forecast_feedback' => array_values($store['forecast_feedback'] ?? []),
            'patterns' => $patterns,
            'root_cause_support' => $root,
            'weights' => $store['weights'] ?? [],
            'learning_audit' => array_slice(array_values($store['learning_audit'] ?? []), -$limit),
            'optimization' => [
                'recommendation_order_boosts' => $store['weights']['recommendation'] ?? [],
                'warning_relevance_boosts' => $store['weights']['warning'] ?? [],
                'forecast_display_boosts' => $store['weights']['forecast_display'] ?? [],
                'governance_immutable' => true,
                'security_immutable' => true,
                'self_modification' => false,
            ],
            'data_sufficiency' => self::overallSufficiency($store),
            'auto_execute' => false,
            'deterministic' => true,
            'llm_cannot_modify_learning' => true,
        ];
        return self::$memo[$memoKey] = $pack;
    }

    /**
     * Reorder recommendations by historical effectiveness (never bypasses auth).
     *
     * @param list<array<string, mixed>> $recommendations
     * @return list<array<string, mixed>>
     */
    public static function optimizeRecommendationOrder(int $companyId, array $recommendations): array
    {
        if ($companyId < 1 || $recommendations === []) {
            return $recommendations;
        }
        $store = self::loadStore($companyId);
        $weights = is_array($store['weights']['recommendation'] ?? null) ? $store['weights']['recommendation'] : [];
        $scored = [];
        foreach ($recommendations as $i => $rec) {
            if (!is_array($rec)) {
                continue;
            }
            $key = (string) ($rec['recommended_action'] ?? $rec['code'] ?? $rec['insight'] ?? $i);
            $w = (float) ($weights[$key] ?? 1.0);
            $prio = self::priorityScore((string) ($rec['priority'] ?? 'MEDIUM'));
            $scored[] = ['score' => ($w * 10.0) + $prio, 'rec' => $rec];
        }
        usort($scored, static fn($a, $b) => $b['score'] <=> $a['score']);
        return array_map(static fn($x) => $x['rec'], $scored);
    }

    /**
     * Assert learning cannot mutate governance/security (for tests + runtime guard).
     */
    public static function assertNoSelfModification(): array
    {
        return [
            'modifies_php' => false,
            'modifies_policies' => false,
            'modifies_permissions' => false,
            'modifies_governance' => false,
            'modifies_approvals' => false,
            'modifies_tenant_isolation' => false,
            'modifies_schema' => false,
            'modifies_env' => false,
            'modifies_llm_config' => false,
            'learning_scope' => 'operational_decision_ordering_only',
        ];
    }

    private static function classifyImmediateOutcome(bool $execOk, bool $verified, array $verification): string
    {
        if (!$execOk) {
            return self::OUTCOME_FAILED;
        }
        if (!empty($verification['incomplete']) || !$verified) {
            return self::OUTCOME_PARTIAL;
        }
        // Verified execution — business success still UNKNOWN until measured
        return self::OUTCOME_UNKNOWN;
    }

    /**
     * @param array<string, mixed> $store
     * @return array{learned: bool, audit: list<array<string, mixed>>}
     */
    private static function maybeLearn(array &$store, int $companyId): array
    {
        $audit = [];
        $recs = is_array($store['recommendations'] ?? null) ? $store['recommendations'] : [];
        foreach ($recs as $key => $row) {
            if (!is_array($row)) {
                continue;
            }
            $samples = (int) ($row['measured_count'] ?? 0);
            $effective = (int) ($row['effective_count'] ?? 0);
            $ineffective = (int) ($row['ineffective_count'] ?? 0);
            if ($samples < self::MIN_LEARN_SAMPLES) {
                continue; // DO NOT LEARN
            }
            $rate = $samples > 0 ? ($effective / $samples) : 0.0;
            $prev = (float) ($store['weights']['recommendation'][$key] ?? 1.0);
            $new = $prev;
            if ($rate >= 0.66) {
                $new = min(2.0, $prev * 1.08);
            } elseif ($rate <= 0.33 && $ineffective > 0) {
                $new = max(0.25, $prev * 0.92);
            } else {
                continue;
            }
            if (abs($new - $prev) < 0.001) {
                continue;
            }
            $a = self::setWeight($store, 'recommendation', (string) $key, $prev, $new, 'recommendation_effectiveness', (string) ($row['last_signal_id'] ?? ''));
            if ($a !== []) {
                $audit[] = $a;
            }
        }

        // Pattern-based warning relevance
        $patterns = self::detectPatterns($store);
        foreach ($patterns as $p) {
            if (($p['data_sufficiency'] ?? '') === self::INSUFFICIENT) {
                continue;
            }
            $code = (string) ($p['code'] ?? '');
            if ($code === '') {
                continue;
            }
            $prev = (float) ($store['weights']['warning'][$code] ?? 1.0);
            $new = min(2.0, $prev * 1.05);
            $a = self::setWeight($store, 'warning', $code, $prev, $new, 'repeated_pattern', (string) ($p['code'] ?? ''));
            if ($a !== []) {
                $audit[] = $a;
            }
        }

        return ['learned' => $audit !== [], 'audit' => $audit];
    }

    /**
     * @param array<string, mixed> $store
     * @return list<array<string, mixed>>
     */
    private static function detectPatterns(array $store): array
    {
        $counts = [];
        foreach (($store['outcomes'] ?? []) as $o) {
            if (!is_array($o)) {
                continue;
            }
            $tool = (string) ($o['tool'] ?? '');
            $domain = (string) ($o['domain'] ?? '');
            $status = (string) ($o['outcome_status'] ?? '');
            $rec = (string) ($o['related_recommendation'] ?? '');
            $warn = (string) ($o['related_warning_id'] ?? '');
            foreach ([
                'tool:' . $tool,
                'domain_fail:' . $domain . ':' . $status,
                'rec:' . $rec,
                'warn:' . $warn,
            ] as $k) {
                if ($k === 'tool:' || str_ends_with($k, ':') || str_contains($k, '::')) {
                    continue;
                }
                $counts[$k] = ($counts[$k] ?? 0) + 1;
            }
        }
        foreach (($store['warnings'] ?? []) as $w) {
            if (!is_array($w)) {
                continue;
            }
            $sig = (string) ($w['signal'] ?? $w['warning_id'] ?? '');
            if ($sig === '') {
                continue;
            }
            $occ = (int) ($w['occurrences'] ?? 1);
            $counts['repeated_warning:' . $sig] = max($counts['repeated_warning:' . $sig] ?? 0, $occ);
        }

        $out = [];
        foreach ($counts as $code => $n) {
            if ($n < self::MIN_PATTERN_OCCURRENCES) {
                continue;
            }
            $out[] = [
                'code' => $code,
                'occurrences' => $n,
                'data_sufficiency' => $n >= 5 ? self::SUFFICIENT : self::LIMITED,
                'confidence' => $n >= 5 ? 'medium' : 'low',
                'evidence' => ['count' => $n, 'min_threshold' => self::MIN_PATTERN_OCCURRENCES],
            ];
        }
        usort($out, static fn($a, $b) => ($b['occurrences'] ?? 0) <=> ($a['occurrences'] ?? 0));
        return array_slice($out, 0, 20);
    }

    /**
     * @param list<array<string, mixed>> $patterns
     * @param array<string, mixed> $store
     * @return list<array<string, mixed>>
     */
    private static function rootCauseSupport(array $patterns, array $store): array
    {
        $out = [];
        foreach ($patterns as $p) {
            if (!is_array($p)) {
                continue;
            }
            $code = (string) ($p['code'] ?? '');
            $factors = [];
            if (str_contains($code, 'inventory') || str_contains($code, 'stock')) {
                $factors[] = 'possible_inventory_replenishment_gap';
                $factors[] = 'possible_sales_demand_pressure';
            }
            if (str_contains($code, 'supplier') || str_contains($code, 'procurement')) {
                $factors[] = 'possible_supplier_or_approval_delay';
            }
            if (str_contains($code, 'receivable') || str_contains($code, 'accounting')) {
                $factors[] = 'possible_collections_followup_gap';
            }
            if (str_contains($code, 'logistics')) {
                $factors[] = 'possible_delivery_bottleneck';
            }
            if (str_contains($code, 'crm') || str_contains($code, 'customer')) {
                $factors[] = 'possible_followup_gap';
            }
            if ($factors === []) {
                $factors[] = 'possible_operational_process_gap';
            }
            $out[] = [
                'pattern' => $code,
                'possible_causes' => $factors,
                'confirmed_cause' => null,
                'evidence' => $p['evidence'] ?? [],
                'note' => 'possible_cause_not_confirmed',
            ];
        }
        return array_slice($out, 0, 15);
    }

    /**
     * @param array<string, mixed> $store
     * @param array<string, mixed> $outcome
     */
    private static function touchWarningEffectiveness(array &$store, string $warningId, array $outcome, ?string $business = null): void
    {
        if ($warningId === '') {
            return;
        }
        $row = is_array($store['warnings'][$warningId] ?? null) ? $store['warnings'][$warningId] : [
            'warning_id' => $warningId,
            'occurrences' => 0,
            'actions' => 0,
            'resolved_after_action' => 0,
            'unresolved_after_action' => 0,
        ];
        $row['occurrences'] = (int) ($row['occurrences'] ?? 0) + 1;
        $row['actions'] = (int) ($row['actions'] ?? 0) + 1;
        $row['last_action_at'] = date('Y-m-d H:i:s');
        $row['last_outcome_id'] = $outcome['outcome_id'] ?? null;
        if ($business === self::OUTCOME_SUCCESS) {
            $row['resolved_after_action'] = (int) ($row['resolved_after_action'] ?? 0) + 1;
            $row['time_to_resolution_hint'] = 'measured_via_warning_status';
        } elseif ($business === self::OUTCOME_NO_EFFECT || $business === self::OUTCOME_NEGATIVE) {
            $row['unresolved_after_action'] = (int) ($row['unresolved_after_action'] ?? 0) + 1;
        }
        $row['effectiveness'] = self::EFFECTIVENESS_UNKNOWN;
        $samples = (int) ($row['resolved_after_action'] ?? 0) + (int) ($row['unresolved_after_action'] ?? 0);
        if ($samples >= self::MIN_LEARN_SAMPLES) {
            $rate = ((int) $row['resolved_after_action']) / max(1, $samples);
            $row['effectiveness'] = $rate >= 0.5 ? self::EFFECTIVE : self::INEFFECTIVE;
            $row['data_sufficiency'] = $samples >= 5 ? self::SUFFICIENT : self::LIMITED;
        } else {
            $row['data_sufficiency'] = self::INSUFFICIENT;
        }
        $store['warnings'][$warningId] = $row;
    }

    /**
     * @param array<string, mixed> $store
     */
    private static function touchRecommendationStats(
        array &$store,
        string $recommendation,
        string $domain,
        string $tool,
        string $event,
        ?string $effectiveness = null
    ): void {
        if ($recommendation === '') {
            return;
        }
        $row = is_array($store['recommendations'][$recommendation] ?? null) ? $store['recommendations'][$recommendation] : [
            'recommendation' => $recommendation,
            'domain' => $domain,
            'tool' => $tool,
            'accepted' => 0,
            'rejected' => 0,
            'executed' => 0,
            'measured_count' => 0,
            'effective_count' => 0,
            'ineffective_count' => 0,
        ];
        if ($event === 'useful' || $event === 'action_taken' || $event === 'accepted_or_executed') {
            $row['accepted'] = (int) ($row['accepted'] ?? 0) + 1;
            if ($event === 'accepted_or_executed' || $event === 'action_taken') {
                $row['executed'] = (int) ($row['executed'] ?? 0) + 1;
            }
        } elseif ($event === 'not_useful' || $event === 'action_not_taken') {
            $row['rejected'] = (int) ($row['rejected'] ?? 0) + 1;
        } elseif ($event === 'measured' && $effectiveness !== null) {
            $row['measured_count'] = (int) ($row['measured_count'] ?? 0) + 1;
            if ($effectiveness === self::EFFECTIVE) {
                $row['effective_count'] = (int) ($row['effective_count'] ?? 0) + 1;
            } elseif ($effectiveness === self::INEFFECTIVE) {
                $row['ineffective_count'] = (int) ($row['ineffective_count'] ?? 0) + 1;
            }
            $row['last_effectiveness'] = $effectiveness;
        }
        $samples = (int) ($row['measured_count'] ?? 0);
        if ($samples >= self::MIN_LEARN_SAMPLES) {
            $rate = ((int) ($row['effective_count'] ?? 0)) / max(1, $samples);
            $row['effectiveness'] = $rate >= 0.5 ? self::EFFECTIVE : self::INEFFECTIVE;
            $row['data_sufficiency'] = $samples >= 5 ? self::SUFFICIENT : self::LIMITED;
        } else {
            $row['effectiveness'] = self::EFFECTIVENESS_UNKNOWN;
            $row['data_sufficiency'] = self::INSUFFICIENT;
        }
        $row['updated_at'] = date('Y-m-d H:i:s');
        $store['recommendations'][$recommendation] = $row;
    }

    /**
     * @param array<string, mixed> $store
     * @return array<string, mixed>
     */
    private static function setWeight(
        array &$store,
        string $bucket,
        string $key,
        float $prev,
        float $new,
        string $reason,
        string $sourceSignal
    ): array {
        if ($key === '' || abs($new - $prev) < 0.0001) {
            return [];
        }
        if (!isset($store['weights']) || !is_array($store['weights'])) {
            $store['weights'] = [];
        }
        if (!isset($store['weights'][$bucket]) || !is_array($store['weights'][$bucket])) {
            $store['weights'][$bucket] = [];
        }
        $store['weights'][$bucket][$key] = round($new, 4);
        $entry = [
            'learning_signal_id' => $sourceSignal !== '' ? $sourceSignal : ('ls_' . substr(sha1($bucket . $key . microtime(true)), 0, 10)),
            'tenant' => (int) ($store['company_id'] ?? 0),
            'bucket' => $bucket,
            'metric' => $key,
            'previous_value' => $prev,
            'new_value' => round($new, 4),
            'reason' => $reason,
            'timestamp' => date('Y-m-d H:i:s'),
            'governance_changed' => false,
            'security_changed' => false,
        ];
        if (!isset($store['learning_audit']) || !is_array($store['learning_audit'])) {
            $store['learning_audit'] = [];
        }
        $store['learning_audit'][] = $entry;
        // Bound audit size
        if (count($store['learning_audit']) > 200) {
            $store['learning_audit'] = array_slice($store['learning_audit'], -200);
        }
        return $entry;
    }

    /**
     * @param array<string, mixed> $outcomeLike
     * @param array<string, mixed> $evidence
     * @return array<string, mixed>
     */
    private static function buildLearningSignal(
        int $companyId,
        string $code,
        array $outcomeLike,
        array $evidence = [],
        string $sufficiency = self::LIMITED
    ): array {
        return [
            'signal_id' => 'ls_' . substr(sha1($companyId . '|' . $code . '|' . ($outcomeLike['outcome_id'] ?? '') . '|' . date('YmdHis')), 0, 14),
            'code' => $code,
            'company_id' => $companyId,
            'source_action' => $outcomeLike['action_id'] ?? null,
            'source_outcome' => $outcomeLike['outcome_id'] ?? null,
            'domain' => $outcomeLike['domain'] ?? null,
            'tool' => $outcomeLike['tool'] ?? null,
            'evidence' => $evidence,
            'data_sufficiency' => $sufficiency,
            'confidence' => $sufficiency === self::SUFFICIENT ? 'medium' : ($sufficiency === self::LIMITED ? 'low' : 'none'),
            'observation_period' => date('Y-m-d'),
            'at' => date('Y-m-d H:i:s'),
            'do_not_learn' => $sufficiency === self::INSUFFICIENT,
        ];
    }

    /**
     * @param array<string, mixed> $store
     * @param array<string, mixed> $signal
     */
    private static function appendSignal(array &$store, array $signal): void
    {
        if (!isset($store['signals']) || !is_array($store['signals'])) {
            $store['signals'] = [];
        }
        $id = (string) ($signal['signal_id'] ?? '');
        if ($id !== '' && isset($store['signals'][$id])) {
            return;
        }
        if ($id !== '') {
            $store['signals'][$id] = $signal;
        } else {
            $store['signals'][] = $signal;
        }
        if (count($store['signals']) > 300) {
            $store['signals'] = array_slice($store['signals'], -300, null, true);
        }
    }

    /**
     * @param array<string, mixed> $outcome
     */
    private static function outcomeFingerprint(array $outcome): string
    {
        return sha1(implode('|', [
            (string) ($outcome['company_id'] ?? ''),
            (string) ($outcome['request_id'] ?? ''),
            (string) ($outcome['action_id'] ?? ''),
            (string) ($outcome['tool'] ?? ''),
            (string) ($outcome['outcome_status'] ?? ''),
        ]));
    }

    /**
     * @param array<string, mixed> $action
     * @param array<string, mixed> $execResult
     * @param array<string, mixed> $verification
     * @return array<string, mixed>
     */
    private static function safeRecordRef(array $action, array $execResult, array $verification): array
    {
        $id = (int) ($action['arguments']['id'] ?? $execResult['data']['id'] ?? $verification['new_state']['id'] ?? 0);
        return [
            'id' => $id > 0 ? $id : null,
            'type' => (string) ($action['tool'] ?? ''),
        ];
    }

    /**
     * @param mixed $state
     * @return array<string, mixed>|null
     */
    private static function sanitizeState($state): ?array
    {
        if (!is_array($state)) {
            return null;
        }
        $out = [];
        foreach ($state as $k => $v) {
            $key = strtolower((string) $k);
            if (str_contains($key, 'password') || str_contains($key, 'token') || str_contains($key, 'secret')
                || str_contains($key, 'api_key') || str_contains($key, 'authorization')) {
                continue;
            }
            if (is_scalar($v) || $v === null) {
                $out[(string) $k] = $v;
            }
        }
        return $out;
    }

    private static function priorityScore(string $p): float
    {
        return match (strtoupper($p)) {
            'CRITICAL' => 4.0,
            'HIGH' => 3.0,
            'MEDIUM' => 2.0,
            'LOW' => 1.0,
            default => 1.5,
        };
    }

    /**
     * @param array<string, mixed> $store
     */
    private static function overallSufficiency(array $store): string
    {
        $n = count($store['outcomes'] ?? []);
        if ($n >= 10) {
            return self::SUFFICIENT;
        }
        if ($n >= self::MIN_LEARN_SAMPLES) {
            return self::LIMITED;
        }
        return self::INSUFFICIENT;
    }

    /**
     * @return array<string, mixed>
     */
    private static function loadStore(int $companyId): array
    {
        $key = 'agent_operational_learning_c' . $companyId;
        try {
            $raw = (new SystemSetting())->get($key, '{}');
            $decoded = json_decode((string) $raw, true);
            if (!is_array($decoded)) {
                return self::emptyStore($companyId);
            }
            if (isset($decoded['company_id']) && (int) $decoded['company_id'] !== $companyId) {
                return self::emptyStore($companyId);
            }
            $decoded['company_id'] = $companyId;
            foreach (['outcomes', 'signals', 'recommendations', 'warnings', 'forecast_feedback', 'feedback', 'weights', 'learning_audit'] as $k) {
                if (!isset($decoded[$k]) || !is_array($decoded[$k])) {
                    $decoded[$k] = [];
                }
            }
            return $decoded;
        } catch (\Throwable $e) {
            return self::emptyStore($companyId);
        }
    }

    /**
     * @param array<string, mixed> $store
     */
    private static function saveStore(int $companyId, array $store): void
    {
        $store['company_id'] = $companyId;
        // Bound outcomes
        if (count($store['outcomes'] ?? []) > 250) {
            $store['outcomes'] = array_slice($store['outcomes'], -250, null, true);
        }
        $key = 'agent_operational_learning_c' . $companyId;
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
                    'setting_group' => 'agent_operational_learning',
                ]);
            } else {
                $model->create([
                    'setting_key' => $key,
                    'setting_value' => $json,
                    'setting_group' => 'agent_operational_learning',
                ]);
            }
        } catch (\Throwable $e) {
            // best-effort
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function emptyStore(int $companyId): array
    {
        return [
            'company_id' => $companyId,
            'outcomes' => [],
            'signals' => [],
            'recommendations' => [],
            'warnings' => [],
            'forecast_feedback' => [],
            'feedback' => [],
            'weights' => [],
            'learning_audit' => [],
        ];
    }
}
