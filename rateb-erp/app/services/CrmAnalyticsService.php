<?php

declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\CrmConversion;
use Rateb\App\Models\CrmLead;
use Rateb\App\Models\CrmOpportunity;
use Rateb\App\Models\CrmStageTransition;

/**
 * Phase 5 — CRM analytics (read-only). CAC is placeholder (no spend source).
 */
final class CrmAnalyticsService
{
    /**
     * @return array{accuracy: array<string,mixed>, periods_compared: int}
     */
    public function revenueForecastAccuracy(int $limit = 12): array
    {
        $report = (new CrmForecastEngineService())->accuracyReport($limit);

        return [
            'accuracy' => $report,
            'periods_compared' => (int) (is_array($report) ? count($report) : 0),
        ];
    }

    /**
     * Average days from opportunity create → won.
     *
     * @return array{avg_days: float, sample_size: int, median_days: float}
     */
    public function salesCycleDuration(): array
    {
        $companyId = CrmSupport::requireCompanyId();
        $rows = (new CrmOpportunity())->query(
            "SELECT DATEDIFF(updated_at, created_at) AS days
             FROM rateb_crm_opportunities
             WHERE company_id = :cid AND deleted_at IS NULL AND workflow_status = 'won'
               AND created_at IS NOT NULL AND updated_at IS NOT NULL
             ORDER BY updated_at DESC LIMIT 200",
            ['cid' => $companyId]
        );
        $days = [];
        foreach (is_array($rows) ? $rows : [] as $r) {
            $days[] = max(0, (int) ($r['days'] ?? 0));
        }
        $n = count($days);
        if ($n === 0) {
            return ['avg_days' => 0.0, 'sample_size' => 0, 'median_days' => 0.0];
        }
        sort($days);
        $avg = array_sum($days) / $n;
        $mid = (int) floor(($n - 1) / 2);
        $median = $n % 2 === 1 ? (float) $days[$mid] : (($days[$mid] + $days[$mid + 1]) / 2);

        return [
            'avg_days' => round($avg, 1),
            'sample_size' => $n,
            'median_days' => round($median, 1),
        ];
    }

    /**
     * CAC placeholders — marketing spend not available in CRM (no Accounting).
     *
     * @return array{cac_placeholder: null, note: string, customers_acquired: int, leads_total: int}
     */
    public function customerAcquisitionCostPlaceholders(): array
    {
        $companyId = CrmSupport::requireCompanyId();
        $acquired = (int) (((new CrmConversion())->queryOne(
            "SELECT COUNT(*) AS c FROM rateb_crm_conversions
             WHERE company_id = :cid AND conversion_type = 'quotation_to_customer'",
            ['cid' => $companyId]
        )['c'] ?? 0));
        $leads = (int) (((new CrmLead())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_crm_leads WHERE company_id = :cid AND deleted_at IS NULL',
            ['cid' => $companyId]
        )['c'] ?? 0));

        return [
            'cac_placeholder' => null,
            'note' => 'CAC requires marketing spend source — not wired (Accounting/Marketplace out of scope).',
            'customers_acquired' => $acquired,
            'leads_total' => $leads,
        ];
    }

    /**
     * @return list<array{owner_user_id:int,open_count:int,won_count:int,lost_count:int,open_amount:float,won_amount:float,win_rate:float}>
     */
    public function repPerformance(): array
    {
        $rows = (new CrmOpportunity())->query(
            "SELECT COALESCE(owner_user_id, 0) AS owner_user_id,
                    SUM(CASE WHEN workflow_status = 'open' THEN 1 ELSE 0 END) AS open_count,
                    SUM(CASE WHEN workflow_status = 'won' THEN 1 ELSE 0 END) AS won_count,
                    SUM(CASE WHEN workflow_status = 'lost' THEN 1 ELSE 0 END) AS lost_count,
                    COALESCE(SUM(CASE WHEN workflow_status = 'open' THEN amount ELSE 0 END),0) AS open_amount,
                    COALESCE(SUM(CASE WHEN workflow_status = 'won' THEN amount ELSE 0 END),0) AS won_amount
             FROM rateb_crm_opportunities
             WHERE company_id = :cid AND deleted_at IS NULL
             GROUP BY COALESCE(owner_user_id, 0)
             ORDER BY won_amount DESC, open_amount DESC
             LIMIT 50",
            ['cid' => CrmSupport::requireCompanyId()]
        );
        $out = [];
        foreach (is_array($rows) ? $rows : [] as $r) {
            $won = (int) ($r['won_count'] ?? 0);
            $lost = (int) ($r['lost_count'] ?? 0);
            $closed = $won + $lost;
            $out[] = [
                'owner_user_id' => (int) ($r['owner_user_id'] ?? 0),
                'open_count' => (int) ($r['open_count'] ?? 0),
                'won_count' => $won,
                'lost_count' => $lost,
                'open_amount' => (float) ($r['open_amount'] ?? 0),
                'won_amount' => (float) ($r['won_amount'] ?? 0),
                'win_rate' => $closed > 0 ? round($won / $closed, 3) : 0.0,
            ];
        }

        return $out;
    }

    /**
     * Pipeline velocity: weighted amount moved / average stage days.
     *
     * @return array{transitions_30d:int,avg_stage_days:float,open_weighted:float,velocity_score:float}
     */
    public function pipelineVelocity(?int $pipelineId = null): array
    {
        $companyId = CrmSupport::requireCompanyId();
        $params = ['cid' => $companyId];
        $pipeFilter = '';
        if ($pipelineId !== null && $pipelineId > 0) {
            $pipeFilter = ' AND pipeline_id = :pid';
            $params['pid'] = $pipelineId;
        }
        $tr = (new CrmStageTransition())->queryOne(
            "SELECT COUNT(*) AS c, AVG(duration_seconds) AS avg_sec
             FROM rateb_crm_stage_transitions
             WHERE company_id = :cid AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) {$pipeFilter}",
            $params
        );
        $weighted = (float) (((new CrmOpportunity())->queryOne(
            "SELECT COALESCE(SUM(amount * probability_percent / 100),0) AS w
             FROM rateb_crm_opportunities
             WHERE company_id = :cid AND deleted_at IS NULL AND workflow_status = 'open' "
            . ($pipelineId !== null && $pipelineId > 0 ? 'AND pipeline_id = :pid' : ''),
            $params
        )['w'] ?? 0));
        $avgDays = round(((float) ($tr['avg_sec'] ?? 0)) / 86400, 2);
        $transitions = (int) ($tr['c'] ?? 0);
        $velocity = $avgDays > 0
            ? round($weighted / $avgDays, 2)
            : ($transitions > 0 ? round($weighted, 2) : 0.0);

        return [
            'transitions_30d' => $transitions,
            'avg_stage_days' => $avgDays,
            'open_weighted' => round($weighted, 2),
            'velocity_score' => $velocity,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function dashboard(?int $pipelineId = null): array
    {
        return [
            'forecast_accuracy' => $this->revenueForecastAccuracy(),
            'sales_cycle' => $this->salesCycleDuration(),
            'cac' => $this->customerAcquisitionCostPlaceholders(),
            'rep_performance' => $this->repPerformance(),
            'pipeline_velocity' => $this->pipelineVelocity($pipelineId),
            'pipeline_health' => (new CrmPipelineHealthService())->healthScore($pipelineId),
            'bottlenecks' => (new CrmPipelineHealthService())->bottleneckAnalysis($pipelineId),
            'retention' => (new CrmRetentionService())->summary(),
        ];
    }
}
