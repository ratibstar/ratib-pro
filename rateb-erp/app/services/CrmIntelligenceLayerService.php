<?php

declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\CrmOpportunity;
use Rateb\App\Models\CrmScoreHistory;
use Rateb\App\Models\Customer;

/**
 * Phase 9 — Internal CRM intelligence layer (rules/heuristics only; no external AI/ML).
 */
final class CrmIntelligenceLayerService
{
    /**
     * @return array<string, mixed>
     */
    public function analyze(?int $pipelineId = null): array
    {
        $companyId = CrmSupport::requireCompanyId();
        $result = [
            'scoring_evolution' => $this->opportunityScoringEvolution(),
            'sales_trends' => $this->salesTrendDetection(),
            'customer_risk_signals' => $this->customerRiskSignals(),
            'pipeline_anomalies' => $this->pipelineAnomalyDetection($pipelineId),
        ];
        if (class_exists(AuditService::class)) {
            (new AuditService())->log('crm.intelligence.calculate', 'crm_intelligence_layer', null, [
                'pipeline_id' => $pipelineId,
                'anomaly_count' => count($result['pipeline_anomalies']),
                'risk_count' => count($result['customer_risk_signals']),
            ]);
        }

        return $result;
    }

    /** @return list<array<string, mixed>> */
    public function opportunityScoringEvolution(int $limit = 40): array
    {
        $rows = (new CrmScoreHistory())->query(
            "SELECT entity_id, score_type, from_value, to_value, created_at, meta_json
             FROM rateb_crm_score_history
             WHERE company_id = :cid AND entity_type = 'opportunity'
               AND score_type IN ('intelligence_score','engagement_score')
             ORDER BY id DESC LIMIT " . max(1, min(100, $limit)),
            ['cid' => CrmSupport::requireCompanyId()]
        );
        $out = [];
        foreach (is_array($rows) ? $rows : [] as $r) {
            $from = (float) ($r['from_value'] ?? 0);
            $to = (float) ($r['to_value'] ?? 0);
            $out[] = [
                'opportunity_id' => (int) ($r['entity_id'] ?? 0),
                'score_type' => (string) ($r['score_type'] ?? ''),
                'from' => $from,
                'to' => $to,
                'delta' => round($to - $from, 2),
                'trend' => $to > $from ? 'up' : ($to < $from ? 'down' : 'flat'),
                'at' => (string) ($r['created_at'] ?? ''),
            ];
        }

        return $out;
    }

    /** @return array<string, mixed> */
    public function salesTrendDetection(): array
    {
        try {
            $trends = (new CrmRevenueIntelligenceService())->historicalTrendAnalysis(
                date('Y-m-d', strtotime('-90 days')),
                date('Y-m-d')
            );
        } catch (\Throwable $e) {
            $trends = [];
        }
        $points = [];
        if (isset($trends['points']) && is_array($trends['points'])) {
            $points = $trends['points'];
        } elseif (is_array($trends) && (isset($trends[0]) || $trends === [])) {
            $points = $trends;
        }
        $direction = 'stable';
        if (count($points) >= 2) {
            $first = (float) (($points[0]['won_amount'] ?? $points[0]['amount'] ?? 0));
            $last = (float) (($points[count($points) - 1]['won_amount'] ?? $points[count($points) - 1]['amount'] ?? 0));
            if ($last > $first * 1.1) {
                $direction = 'growing';
            } elseif ($last < $first * 0.9) {
                $direction = 'declining';
            }
        }

        return ['direction' => $direction, 'series' => $points];
    }

    /** @return list<array<string, mixed>> */
    public function customerRiskSignals(int $limit = 25): array
    {
        $rows = (new Customer())->query(
            "SELECT id, name, crm_health_score, crm_renewal_risk, crm_at_risk, crm_last_interaction_at, crm_owner_user_id
             FROM rateb_customers
             WHERE company_id = :cid
               AND (crm_renewal_risk IN ('high','critical') OR crm_at_risk = 1 OR COALESCE(crm_health_score,100) < 40)
             ORDER BY COALESCE(crm_health_score,0) ASC
             LIMIT " . max(1, min(50, $limit)),
            ['cid' => CrmSupport::requireCompanyId()]
        );
        $out = [];
        foreach (is_array($rows) ? $rows : [] as $r) {
            $signals = [];
            if (in_array((string) ($r['crm_renewal_risk'] ?? ''), ['high', 'critical'], true)) {
                $signals[] = 'renewal_risk';
            }
            if (!empty($r['crm_at_risk'])) {
                $signals[] = 'at_risk_flag';
            }
            if ((int) ($r['crm_health_score'] ?? 100) < 40) {
                $signals[] = 'low_health';
            }
            if (!empty($r['crm_last_interaction_at'])) {
                $days = (int) floor((time() - strtotime((string) $r['crm_last_interaction_at'])) / 86400);
                if ($days >= 30) {
                    $signals[] = 'stale_engagement';
                }
            } else {
                $signals[] = 'no_interaction';
            }
            $out[] = array_merge($r, ['signals' => $signals]);
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    public function pipelineAnomalyDetection(?int $pipelineId = null, int $limit = 25): array
    {
        $companyId = CrmSupport::requireCompanyId();
        $params = ['cid' => $companyId];
        $filter = '';
        if ($pipelineId !== null && $pipelineId > 0) {
            $filter = ' AND pipeline_id = :pid';
            $params['pid'] = $pipelineId;
        }
        $avgRow = (new CrmOpportunity())->queryOne(
            "SELECT AVG(amount) AS avg_amount FROM rateb_crm_opportunities
             WHERE company_id = :cid AND deleted_at IS NULL AND workflow_status = 'open' {$filter}
               AND amount > 0",
            $params
        );
        $avg = (float) ($avgRow['avg_amount'] ?? 0);
        $thresholds = (new CrmGovernanceService())->setting('intelligence_thresholds', [
            'anomaly_amount_multiplier' => 3,
            'score_drop_alert' => 20,
        ]);
        $mult = max(1.5, (float) ($thresholds['anomaly_amount_multiplier'] ?? 3));
        $anomalies = [];

        $big = (new CrmOpportunity())->query(
            "SELECT id, name, amount, probability_percent, intelligence_score, engagement_score, risk_level, is_stale, owner_user_id
             FROM rateb_crm_opportunities
             WHERE company_id = :cid AND deleted_at IS NULL AND workflow_status = 'open' {$filter}
               AND amount > :thr
             ORDER BY amount DESC LIMIT " . max(1, min(50, $limit)),
            array_merge($params, ['thr' => max($avg * $mult, 1)])
        );
        foreach (is_array($big) ? $big : [] as $r) {
            $anomalies[] = array_merge($r, ['anomaly' => 'outlier_amount', 'baseline_avg' => round($avg, 2)]);
        }

        $staleHigh = (new CrmOpportunity())->query(
            "SELECT id, name, amount, probability_percent, intelligence_score, engagement_score, risk_level, is_stale, owner_user_id
             FROM rateb_crm_opportunities
             WHERE company_id = :cid AND deleted_at IS NULL AND workflow_status = 'open' {$filter}
               AND is_stale = 1 AND probability_percent >= 60
             ORDER BY amount DESC LIMIT 15",
            $params
        );
        foreach (is_array($staleHigh) ? $staleHigh : [] as $r) {
            $anomalies[] = array_merge($r, ['anomaly' => 'stale_high_probability']);
        }

        $dropAlert = (float) ($thresholds['score_drop_alert'] ?? 20);
        foreach ($this->opportunityScoringEvolution(30) as $ev) {
            if (($ev['delta'] ?? 0) <= -$dropAlert) {
                $anomalies[] = [
                    'id' => $ev['opportunity_id'],
                    'anomaly' => 'score_drop',
                    'delta' => $ev['delta'],
                    'score_type' => $ev['score_type'],
                ];
            }
        }

        return array_slice($anomalies, 0, $limit);
    }
}
