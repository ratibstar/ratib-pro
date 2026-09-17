<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\Database;

/**
 * RATEB Agent Control Tower — aggregation only (not an Agent / not a BI engine).
 * Reuses Executive, Proactive, Learning, Governance layers. No direct ERP writes.
 */
final class ErpControlTowerLayer
{
    /** @var array<string, mixed> */
    private static array $memo = [];

    public static function clearMemo(): void
    {
        self::$memo = [];
    }

    /**
     * Unified operational snapshot for /admin/ai Control Tower.
     *
     * @return array<string, mixed>
     */
    public static function snapshot(ProcurementAgentContext $ctx, int $limit = 12): array
    {
        $companyId = (int) $ctx->companyId;
        if ($companyId < 1) {
            return self::emptySnapshot('tenant_mismatch');
        }

        $limit = max(5, min(30, $limit));
        $memoKey = 'snap:' . $companyId . ':' . $limit . ':' . (int) $ctx->userId;
        if (isset(self::$memo[$memoKey])) {
            return self::$memo[$memoKey];
        }

        $t0 = microtime(true);

        // Reuse existing layers (shared memos where available)
        $exec = ErpExecutiveIntelligenceLayer::build($ctx, ['intent_kind' => 'executive'], $limit);
        $warnings = ErpProactiveEarlyWarningLayer::listWarnings($companyId, false);
        $closedWarnings = ErpProactiveEarlyWarningLayer::listWarnings($companyId, true);
        $learning = ErpOperationalLearningLayer::analyze($ctx, $limit);
        $effectiveness = self::agentEffectiveness($companyId);
        $recs = is_array($exec['recommended_actions'] ?? null) ? $exec['recommended_actions'] : [];
        $recs = ErpOperationalLearningLayer::optimizeRecommendationOrder($companyId, $recs);

        $kpis = [];
        foreach (array_slice($exec['kpis']['items'] ?? [], 0, $limit) as $k) {
            if (!is_array($k)) {
                continue;
            }
            $kpis[] = [
                'code' => (string) ($k['code'] ?? ''),
                'label' => (string) ($k['label'] ?? $k['code'] ?? ''),
                'value' => $k['value'] ?? null,
                'unit' => (string) ($k['unit'] ?? ''),
                'available' => !empty($k['available']),
                'status' => (string) ($k['status'] ?? ''),
                'evidence' => self::normalizeEvidence($k['evidence'] ?? [], (string) ($k['domain'] ?? ''), 'kpi'),
                'domain' => (string) ($k['domain'] ?? ''),
            ];
        }

        $risks = [];
        foreach (array_slice($exec['risks'] ?? [], 0, $limit) as $r) {
            if (!is_array($r)) {
                continue;
            }
            $risks[] = [
                'code' => (string) ($r['code'] ?? ''),
                'priority' => (string) ($r['priority'] ?? ''),
                'impact' => (string) ($r['impact'] ?? ''),
                'domain' => (string) ($r['source_domain'] ?? ''),
                'recommended_action' => (string) ($r['recommended_action'] ?? ''),
                'evidence' => self::normalizeEvidence($r['evidence'] ?? [], (string) ($r['source_domain'] ?? ''), 'risk'),
                'decision_summary' => (string) ($r['code'] ?? ''),
            ];
        }

        $warningCenter = [];
        foreach (array_slice($warnings, 0, $limit) as $w) {
            if (!is_array($w)) {
                continue;
            }
            $detected = (string) ($w['detected_at'] ?? '');
            $ageHours = null;
            if ($detected !== '' && strtotime($detected) !== false) {
                $ageHours = (int) floor((time() - strtotime($detected)) / 3600);
            }
            $warningCenter[] = [
                'warning_id' => (string) ($w['warning_id'] ?? ''),
                'signal' => (string) ($w['signal'] ?? ''),
                'domain' => (string) ($w['domain'] ?? ''),
                'severity' => (string) ($w['severity'] ?? ''),
                'priority' => (string) ($w['priority'] ?? ''),
                'status' => (string) ($w['status'] ?? ''),
                'impact' => (string) ($w['impact'] ?? ''),
                'recommended_action' => (string) ($w['recommended_action'] ?? ''),
                'detected_at' => $detected,
                'age_hours' => $ageHours,
                'kind' => (string) ($w['kind'] ?? 'risk'),
                'evidence' => self::normalizeEvidence($w['evidence'] ?? [], (string) ($w['domain'] ?? ''), 'warning'),
                'related_action' => null,
                'decision_summary' => (string) ($w['signal'] ?? ''),
            ];
        }

        $forecasts = [];
        foreach (array_slice($exec['forecasts']['items'] ?? [], 0, $limit) as $f) {
            if (!is_array($f)) {
                continue;
            }
            $forecasts[] = [
                'code' => (string) ($f['code'] ?? ''),
                'available' => !empty($f['available']),
                'prediction' => $f['prediction'] ?? null,
                'status' => (string) ($f['status'] ?? ''),
                'basis' => $f['basis'] ?? null,
                'period_used' => $f['period_used'] ?? null,
                'is_fact' => false,
                'evidence' => self::normalizeEvidence([
                    'basis' => $f['basis'] ?? null,
                    'period_used' => $f['period_used'] ?? null,
                    'available' => !empty($f['available']),
                ], 'executive', 'forecast'),
                'decision_summary' => !empty($f['available'])
                    ? (string) ($f['code'] ?? 'forecast')
                    : 'FORECAST UNAVAILABLE — INSUFFICIENT HISTORICAL DATA',
            ];
        }

        $opportunities = [];
        foreach (array_slice($learning['patterns'] ?? [], 0, 5) as $p) {
            // learning patterns are not opportunities — skip
        }
        // Opportunities from last proactive scan store via open warnings kind=opportunity
        foreach ($closedWarnings as $w) {
            if (!is_array($w) || ($w['kind'] ?? '') !== 'opportunity') {
                continue;
            }
            if (in_array(($w['status'] ?? ''), ['RESOLVED', 'DISMISSED'], true)) {
                continue;
            }
            $opportunities[] = [
                'code' => (string) ($w['signal'] ?? ''),
                'domain' => (string) ($w['domain'] ?? ''),
                'priority' => (string) ($w['priority'] ?? ''),
                'recommended_action' => (string) ($w['recommended_action'] ?? ''),
                'evidence' => self::normalizeEvidence($w['evidence'] ?? [], (string) ($w['domain'] ?? ''), 'opportunity'),
                'decision_summary' => (string) ($w['opportunity'] ?? $w['signal'] ?? ''),
            ];
            if (count($opportunities) >= $limit) {
                break;
            }
        }
        // Also from executive pack if present via recommended opportunities in summary
        foreach (($exec['executive_summary']['OPPORTUNITIES'] ?? []) as $o) {
            if (!is_array($o)) {
                continue;
            }
            $opportunities[] = [
                'code' => (string) ($o['code'] ?? ''),
                'domain' => 'executive',
                'priority' => 'MEDIUM',
                'recommended_action' => '',
                'evidence' => self::normalizeEvidence($o['evidence'] ?? [], 'executive', 'opportunity'),
                'decision_summary' => (string) ($o['description'] ?? $o['code'] ?? ''),
            ];
            if (count($opportunities) >= $limit) {
                break;
            }
        }

        $recommendations = [];
        foreach (array_slice($recs, 0, $limit) as $r) {
            if (!is_array($r)) {
                continue;
            }
            $recommendations[] = [
                'insight' => (string) ($r['insight'] ?? $r['code'] ?? ''),
                'recommended_action' => (string) ($r['recommended_action'] ?? ''),
                'domain' => (string) ($r['domain'] ?? ''),
                'priority' => (string) ($r['priority'] ?? ''),
                'requires_confirmation' => true,
                'auto_execute' => false,
                'evidence' => self::normalizeEvidence($r['evidence'] ?? ['bridge' => 'ErpActionPlanner'], (string) ($r['domain'] ?? ''), 'recommendation'),
                'decision_summary' => (string) ($r['recommended_action'] ?? ''),
                'lifecycle' => 'PROPOSED',
            ];
        }

        $outcomes = [];
        foreach (array_slice($learning['outcomes'] ?? [], -$limit) as $o) {
            if (!is_array($o)) {
                continue;
            }
            $outcomes[] = [
                'outcome_id' => (string) ($o['outcome_id'] ?? ''),
                'action_id' => (string) ($o['action_id'] ?? ''),
                'tool' => (string) ($o['tool'] ?? ''),
                'domain' => (string) ($o['domain'] ?? ''),
                'outcome_status' => (string) ($o['outcome_status'] ?? ''),
                'business_outcome' => (string) ($o['business_outcome'] ?? 'UNKNOWN'),
                'verification' => $o['verification'] ?? null,
                'related_warning_id' => $o['related_warning_id'] ?? null,
                'related_recommendation' => $o['related_recommendation'] ?? null,
                'timestamp' => (string) ($o['outcome_timestamp'] ?? ''),
                'evidence' => self::normalizeEvidence([
                    'verification' => $o['verification'] ?? null,
                    'execution_success' => $o['execution_success'] ?? null,
                ], (string) ($o['domain'] ?? ''), (string) ($o['tool'] ?? 'action')),
                'lifecycle' => self::actionLifecycleFromOutcome($o),
            ];
        }

        $actions = [
            'proposed' => $recommendations,
            'pending_confirmation' => [], // request-scoped; not persisted globally
            'executed' => array_values(array_filter($outcomes, static fn($x) => ($x['lifecycle'] ?? '') === 'VERIFIED' || ($x['lifecycle'] ?? '') === 'EXECUTED')),
            'blocked' => array_values(array_filter($outcomes, static fn($x) => ($x['outcome_status'] ?? '') === 'FAILED' && !empty(($learning['signals'] ?? [])))),
            'failed' => array_values(array_filter($outcomes, static fn($x) => ($x['outcome_status'] ?? '') === 'FAILED')),
            'partial' => array_values(array_filter($outcomes, static fn($x) => ($x['outcome_status'] ?? '') === 'PARTIAL_SUCCESS')),
            'verified' => array_values(array_filter($outcomes, static fn($x) => !empty($x['verification']['verified']))),
            'note' => 'writes_require_action_planner_governance_confirmation',
            'direct_writes' => false,
        ];

        $currentState = [
            'as_of' => date('Y-m-d H:i:s'),
            'company_id' => $companyId,
            'kpi_count' => count($kpis),
            'critical_risks' => count(array_filter($risks, static fn($r) => in_array(($r['priority'] ?? ''), ['CRITICAL', 'HIGH'], true))),
            'open_warnings' => count($warningCenter),
            'forecasts_available' => (int) ($exec['forecasts']['available_count'] ?? 0),
            'recommendations' => count($recommendations),
            'outcomes' => count($outcomes),
            'data_source' => 'live_tenant',
        ];

        $pack = [
            'data_source' => 'live_tenant',
            'company_id' => $companyId,
            'as_of' => date('Y-m-d H:i:s'),
            'current_state' => $currentState,
            'overview' => $currentState,
            'kpis' => $kpis,
            'risks' => $risks,
            'warnings' => $warningCenter,
            'opportunities' => array_slice($opportunities, 0, $limit),
            'forecasts' => $forecasts,
            'recommendations' => $recommendations,
            'actions' => $actions,
            'outcomes' => $outcomes,
            'learning' => [
                'signals' => array_slice(array_values($learning['signals'] ?? []), -$limit),
                'recommendation_effectiveness' => array_slice(array_values($learning['recommendation_effectiveness'] ?? []), 0, $limit),
                'warning_effectiveness' => array_slice(array_values($learning['warning_effectiveness'] ?? []), 0, $limit),
                'forecast_feedback' => array_slice(array_values($learning['forecast_feedback'] ?? []), 0, $limit),
                'patterns' => $learning['patterns'] ?? [],
                'optimization' => $learning['optimization'] ?? [],
                'data_sufficiency' => $learning['data_sufficiency'] ?? 'INSUFFICIENT',
                'governance_immutable' => true,
            ],
            'operational_memory' => ErpOperationalMemoryLayer::towerSection($ctx, $limit),
            'agent_effectiveness' => $effectiveness,
            'executive_summary' => $exec['executive_summary'] ?? [],
            'explainability' => [
                'format' => 'decision_summary_evidence_impact_recommended_action',
                'hidden_chain_of_thought' => false,
            ],
            'security' => [
                'tenant_scoped' => true,
                'direct_writes' => false,
                'requires_action_planner' => true,
                'requires_governance' => true,
                'requires_confirmation' => true,
            ],
            'observability' => [
                'duration_ms' => (int) round((microtime(true) - $t0) * 1000),
                'domains' => $exec['domains'] ?? [],
            ],
            'auto_execute' => false,
        ];

        return self::$memo[$memoKey] = $pack;
    }

    /**
     * @return array<string, mixed>
     */
    public static function agentEffectiveness(int $companyId): array
    {
        if ($companyId < 1) {
            return ['data_source' => 'live_tenant', 'available' => false];
        }
        try {
            $db = Database::connection();
            $stmt = $db->prepare(
                "SELECT
                    COUNT(*) AS requests,
                    SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) AS success_n,
                    SUM(CASE WHEN status = 'error' THEN 1 ELSE 0 END) AS errors,
                    SUM(CASE WHEN status = 'denied' THEN 1 ELSE 0 END) AS blocked,
                    SUM(CASE WHEN tool_name LIKE 'analyze_%' OR tool_name LIKE 'get_%' OR tool_name LIKE 'list_%' OR tool_name LIKE 'search_%' THEN 1 ELSE 0 END) AS reads_analyses,
                    SUM(CASE WHEN tool_name IN (
                        'create_draft_purchase_request','update_purchase_request','cancel_purchase_request',
                        'submit_purchase_request','submit_journal_for_approval'
                    ) THEN 1 ELSE 0 END) AS writes,
                    AVG(NULLIF(duration_ms, 0)) AS avg_duration_ms
                 FROM rateb_agent_audit_events
                 WHERE company_id = :cid
                   AND created_at >= (NOW() - INTERVAL 7 DAY)"
            );
            $stmt->execute(['cid' => $companyId]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];

            $recStmt = $db->prepare(
                "SELECT COUNT(*) FROM rateb_agent_audit_events
                 WHERE company_id = :cid AND created_at >= (NOW() - INTERVAL 7 DAY)
                   AND (tool_name LIKE '%guidance%' OR tool_name LIKE '%priorities%' OR tool_name LIKE '%action_bridge%'
                        OR tool_name = 'intelligence_analysis')"
            );
            $recStmt->execute(['cid' => $companyId]);
            $recommendations = (int) $recStmt->fetchColumn();

            $verStmt = $db->prepare(
                "SELECT COUNT(*) FROM rateb_agent_audit_events
                 WHERE company_id = :cid AND created_at >= (NOW() - INTERVAL 7 DAY)
                   AND status = 'success'
                   AND tool_name IN (
                        'create_draft_purchase_request','update_purchase_request','cancel_purchase_request',
                        'submit_purchase_request','submit_journal_for_approval'
                   )"
            );
            $verStmt->execute(['cid' => $companyId]);
            $verifiedWrites = (int) $verStmt->fetchColumn();

            return [
                'data_source' => 'live_tenant',
                'period' => '7d',
                'available' => true,
                'requests' => (int) ($row['requests'] ?? 0),
                'reads_analyses' => (int) ($row['reads_analyses'] ?? 0),
                'recommendations' => $recommendations,
                'writes' => (int) ($row['writes'] ?? 0),
                'verified_actions' => $verifiedWrites,
                'blocked_actions' => (int) ($row['blocked'] ?? 0),
                'failures' => (int) ($row['errors'] ?? 0),
                'success' => (int) ($row['success_n'] ?? 0),
                'avg_duration_ms' => isset($row['avg_duration_ms']) ? (float) $row['avg_duration_ms'] : null,
                'duplicate_suppression' => null, // surfaced from proactive scans when available
                'evidence' => [
                    'domain' => 'executive',
                    'source' => 'rateb_agent_audit_events',
                    'timestamp' => date('Y-m-d H:i:s'),
                    'confidence' => 'SUFFICIENT',
                ],
            ];
        } catch (\Throwable $e) {
            return [
                'data_source' => 'live_tenant',
                'available' => false,
                'error' => 'audit_unavailable',
                'evidence' => [
                    'domain' => 'executive',
                    'source' => 'rateb_agent_audit_events',
                    'status' => 'INSUFFICIENT EVIDENCE',
                ],
            ];
        }
    }

    /**
     * @param mixed $evidence
     * @return array<string, mixed>
     */
    private static function normalizeEvidence($evidence, string $domain, string $source): array
    {
        $ev = is_array($evidence) ? $evidence : [];
        return [
            'domain' => $domain !== '' ? $domain : (string) ($ev['domain'] ?? 'executive'),
            'source' => (string) ($ev['source'] ?? $source),
            'tool' => (string) ($ev['tool'] ?? $source),
            'record' => $ev['record'] ?? ($ev['related_records'] ?? null),
            'timestamp' => (string) ($ev['as_of'] ?? $ev['timestamp'] ?? date('Y-m-d H:i:s')),
            'confidence' => $ev['confidence'] ?? null,
            'data_sufficiency' => $ev['data_sufficiency'] ?? ($ev['status'] ?? null),
            'details' => $ev,
        ];
    }

    /**
     * @param array<string, mixed> $o
     */
    private static function actionLifecycleFromOutcome(array $o): string
    {
        if (!empty($o['blocked'])) {
            return 'BLOCKED';
        }
        if (($o['outcome_status'] ?? '') === 'FAILED') {
            return 'FAILED';
        }
        if (($o['outcome_status'] ?? '') === 'PARTIAL_SUCCESS') {
            return 'PARTIAL';
        }
        if (!empty($o['verification']['verified'])) {
            return 'VERIFIED';
        }
        if (!empty($o['execution_success'])) {
            return 'EXECUTED';
        }
        return 'PROPOSED';
    }

    /**
     * @return array<string, mixed>
     */
    private static function emptySnapshot(string $reason): array
    {
        return [
            'data_source' => 'live_tenant',
            'error' => $reason,
            'current_state' => [],
            'kpis' => [],
            'risks' => [],
            'warnings' => [],
            'forecasts' => [],
            'recommendations' => [],
            'actions' => ['direct_writes' => false],
            'outcomes' => [],
            'learning' => [],
            'agent_effectiveness' => ['available' => false],
            'auto_execute' => false,
            'security' => ['tenant_scoped' => true, 'direct_writes' => false],
        ];
    }
}
