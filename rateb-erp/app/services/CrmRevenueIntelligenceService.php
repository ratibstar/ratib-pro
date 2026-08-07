<?php

declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\CrmConversion;
use Rateb\App\Models\CrmLead;
use Rateb\App\Models\CrmOpportunity;
use Rateb\App\Models\CrmOpportunityOutcome;
use Rateb\App\Models\CrmRevenueEvent;

/**
 * Phase 7 — Revenue intelligence (no Accounting / Invoice lifecycle).
 */
final class CrmRevenueIntelligenceService
{
    /**
     * @return array<string, mixed>
     */
    public function dashboard(?int $pipelineId = null, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        return [
            'pipeline' => $this->revenuePipelineAnalytics($pipelineId),
            'trends' => $this->historicalTrendAnalysis($dateFrom, $dateTo),
            'win_loss' => $this->winLossIntelligence($pipelineId),
            'funnel' => $this->conversionFunnelAnalytics(),
            'sales_cycle' => $this->salesCycleAnalytics(),
        ];
    }

    /**
     * @return array{open_amount:float,weighted_amount:float,won_amount:float,tracked_revenue:float,by_stage:list<array<string,mixed>>}
     */
    public function revenuePipelineAnalytics(?int $pipelineId = null): array
    {
        $fc = (new CrmForecastEngineService())->compute($pipelineId);
        $tracked = 0.0;
        try {
            $tracked = (float) (((new CrmRevenueEvent())->queryOne(
                'SELECT COALESCE(SUM(amount),0) AS v FROM rateb_crm_revenue_events WHERE company_id = :cid',
                ['cid' => CrmSupport::requireCompanyId()]
            )['v'] ?? 0));
        } catch (\Throwable $e) {
            $tracked = 0.0;
        }

        return [
            'open_amount' => (float) ($fc['open_amount'] ?? 0),
            'weighted_amount' => (float) ($fc['weighted_amount'] ?? 0),
            'won_amount' => (float) ($fc['won_amount'] ?? 0),
            'tracked_revenue' => $tracked,
            'by_stage' => $fc['by_stage'] ?? [],
        ];
    }

    /**
     * Monthly historical won/lost/open trends (last 12 months by default).
     *
     * @return list<array{period_key:string,won_amount:float,lost_amount:float,open_created:int}>
     */
    public function historicalTrendAnalysis(?string $dateFrom = null, ?string $dateTo = null): array
    {
        $companyId = CrmSupport::requireCompanyId();
        $from = $dateFrom ?: date('Y-m-01', strtotime('-11 months'));
        $to = $dateTo ?: date('Y-m-d');
        $rows = (new CrmOpportunity())->query(
            "SELECT DATE_FORMAT(updated_at, '%Y-%m') AS period_key,
                    COALESCE(SUM(CASE WHEN workflow_status = 'won' THEN amount ELSE 0 END),0) AS won_amount,
                    COALESCE(SUM(CASE WHEN workflow_status = 'lost' THEN amount ELSE 0 END),0) AS lost_amount,
                    SUM(CASE WHEN workflow_status = 'open' THEN 1 ELSE 0 END) AS open_created
             FROM rateb_crm_opportunities
             WHERE company_id = :cid AND deleted_at IS NULL
               AND updated_at BETWEEN :from AND :to
             GROUP BY DATE_FORMAT(updated_at, '%Y-%m')
             ORDER BY period_key ASC",
            ['cid' => $companyId, 'from' => $from . ' 00:00:00', 'to' => $to . ' 23:59:59']
        );
        $out = [];
        foreach (is_array($rows) ? $rows : [] as $r) {
            $out[] = [
                'period_key' => (string) ($r['period_key'] ?? ''),
                'won_amount' => (float) ($r['won_amount'] ?? 0),
                'lost_amount' => (float) ($r['lost_amount'] ?? 0),
                'open_created' => (int) ($r['open_created'] ?? 0),
            ];
        }

        return $out;
    }

    /**
     * @return array{won_count:int,lost_count:int,win_rate:float,top_loss_reasons:list<array{reason:string,count:int,amount:float}>}
     */
    public function winLossIntelligence(?int $pipelineId = null): array
    {
        $companyId = CrmSupport::requireCompanyId();
        $params = ['cid' => $companyId];
        $pipe = '';
        if ($pipelineId !== null && $pipelineId > 0) {
            $pipe = ' AND o.pipeline_id = :pid';
            $params['pid'] = $pipelineId;
        }
        $agg = (new CrmOpportunity())->queryOne(
            "SELECT
                SUM(CASE WHEN o.workflow_status = 'won' THEN 1 ELSE 0 END) AS won_count,
                SUM(CASE WHEN o.workflow_status = 'lost' THEN 1 ELSE 0 END) AS lost_count
             FROM rateb_crm_opportunities o
             WHERE o.company_id = :cid AND o.deleted_at IS NULL {$pipe}",
            $params
        );
        $won = (int) ($agg['won_count'] ?? 0);
        $lost = (int) ($agg['lost_count'] ?? 0);
        $closed = $won + $lost;
        $reasons = (new CrmOpportunityOutcome())->query(
            "SELECT COALESCE(lr.name, 'Unspecified') AS reason, COUNT(*) AS cnt, COALESCE(SUM(oo.amount),0) AS amount
             FROM rateb_crm_opportunity_outcomes oo
             LEFT JOIN rateb_crm_loss_reasons lr ON lr.id = oo.loss_reason_id
             WHERE oo.company_id = :cid AND oo.outcome = 'lost'
             GROUP BY COALESCE(lr.name, 'Unspecified')
             ORDER BY cnt DESC LIMIT 10",
            ['cid' => $companyId]
        );
        $top = [];
        foreach (is_array($reasons) ? $reasons : [] as $r) {
            $top[] = [
                'reason' => (string) ($r['reason'] ?? ''),
                'count' => (int) ($r['cnt'] ?? 0),
                'amount' => (float) ($r['amount'] ?? 0),
            ];
        }

        return [
            'won_count' => $won,
            'lost_count' => $lost,
            'win_rate' => $closed > 0 ? round(($won / $closed) * 100, 1) : 0.0,
            'top_loss_reasons' => $top,
        ];
    }

    /**
     * @return array{leads:int,opportunities:int,quotations_accepted:int,customers:int,rates:array<string,float>}
     */
    public function conversionFunnelAnalytics(): array
    {
        $rates = (new CrmReportService())->conversionRates();
        $customers = (int) (((new CrmConversion())->queryOne(
            "SELECT COUNT(*) AS c FROM rateb_crm_conversions
             WHERE company_id = :cid AND conversion_type = 'quotation_to_customer'",
            ['cid' => CrmSupport::requireCompanyId()]
        )['c'] ?? 0));

        return [
            'leads' => (int) ($rates['leads_total'] ?? 0),
            'opportunities' => (int) ($rates['opps_total'] ?? 0),
            'quotations_accepted' => (int) ($rates['quotes_accepted'] ?? 0),
            'customers' => $customers,
            'rates' => [
                'lead_conversion_rate' => (float) ($rates['lead_conversion_rate'] ?? 0),
                'opp_conversion_rate' => (float) ($rates['opp_conversion_rate'] ?? 0),
                'quote_conversion_rate' => (float) ($rates['quote_conversion_rate'] ?? 0),
            ],
        ];
    }

    /**
     * @return array{avg_days:float,median_days:float,sample_size:int,by_owner:list<array{owner_user_id:int,avg_days:float,count:int}>}
     */
    public function salesCycleAnalytics(): array
    {
        $base = (new CrmAnalyticsService())->salesCycleDuration();
        $rows = (new CrmOpportunity())->query(
            "SELECT COALESCE(owner_user_id,0) AS owner_user_id,
                    AVG(DATEDIFF(updated_at, created_at)) AS avg_days,
                    COUNT(*) AS cnt
             FROM rateb_crm_opportunities
             WHERE company_id = :cid AND deleted_at IS NULL AND workflow_status = 'won'
               AND created_at IS NOT NULL AND updated_at IS NOT NULL
             GROUP BY COALESCE(owner_user_id,0)
             ORDER BY avg_days ASC
             LIMIT 30",
            ['cid' => CrmSupport::requireCompanyId()]
        );
        $byOwner = [];
        foreach (is_array($rows) ? $rows : [] as $r) {
            $byOwner[] = [
                'owner_user_id' => (int) ($r['owner_user_id'] ?? 0),
                'avg_days' => round((float) ($r['avg_days'] ?? 0), 1),
                'count' => (int) ($r['cnt'] ?? 0),
            ];
        }

        return array_merge($base, ['by_owner' => $byOwner]);
    }
}
