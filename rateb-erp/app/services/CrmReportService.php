<?php

declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\CrmLead;
use Rateb\App\Models\CrmOpportunity;
use Rateb\App\Models\CrmQuotation;

/** Phase 3 — CRM analytical reports (read-only). */
final class CrmReportService
{
    /**
     * @return list<array{stage: string, count: int, amount: float, expected_revenue: float}>
     */
    public function salesFunnel(?int $pipelineId = null): array
    {
        $companyId = CrmSupport::requireCompanyId();
        $params = ['cid' => $companyId];
        $pipeFilter = '';
        if ($pipelineId !== null && $pipelineId > 0) {
            $pipeFilter = ' AND o.pipeline_id = :pid';
            $params['pid'] = $pipelineId;
        }
        $rows = (new CrmOpportunity())->query(
            "SELECT COALESCE(s.name, 'Unstaged') AS stage,
                    COUNT(*) AS cnt,
                    COALESCE(SUM(o.amount),0) AS amount,
                    COALESCE(SUM(o.amount * o.probability_percent / 100),0) AS expected_revenue
             FROM rateb_crm_opportunities o
             LEFT JOIN rateb_crm_pipeline_stages s ON s.id = o.stage_id
             WHERE o.company_id = :cid AND o.deleted_at IS NULL AND o.workflow_status = 'open'
             {$pipeFilter}
             GROUP BY s.id, s.name, s.sort_order
             ORDER BY COALESCE(s.sort_order, 999), stage",
            $params
        );
        $out = [];
        foreach (is_array($rows) ? $rows : [] as $r) {
            $out[] = [
                'stage' => (string) ($r['stage'] ?? ''),
                'count' => (int) ($r['cnt'] ?? 0),
                'amount' => (float) ($r['amount'] ?? 0),
                'expected_revenue' => (float) ($r['expected_revenue'] ?? 0),
            ];
        }

        return $out;
    }

    /**
     * @return array{leads_total: int, leads_won: int, lead_conversion_rate: float, opps_total: int, opps_won: int, opp_conversion_rate: float, quotes_total: int, quotes_accepted: int, quote_conversion_rate: float}
     */
    public function conversionRates(): array
    {
        $companyId = CrmSupport::requireCompanyId();
        $lead = new CrmLead();
        $lt = (int) (($lead->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_crm_leads WHERE company_id = :cid AND deleted_at IS NULL',
            ['cid' => $companyId]
        )['c'] ?? 0));
        $lw = (int) (($lead->queryOne(
            "SELECT COUNT(*) AS c FROM rateb_crm_leads WHERE company_id = :cid AND deleted_at IS NULL AND workflow_status = 'won'",
            ['cid' => $companyId]
        )['c'] ?? 0));

        $opp = new CrmOpportunity();
        $ot = (int) (($opp->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_crm_opportunities WHERE company_id = :cid AND deleted_at IS NULL',
            ['cid' => $companyId]
        )['c'] ?? 0));
        $ow = (int) (($opp->queryOne(
            "SELECT COUNT(*) AS c FROM rateb_crm_opportunities WHERE company_id = :cid AND deleted_at IS NULL AND workflow_status = 'won'",
            ['cid' => $companyId]
        )['c'] ?? 0));

        $q = new CrmQuotation();
        $qt = (int) (($q->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_crm_quotations WHERE company_id = :cid AND deleted_at IS NULL',
            ['cid' => $companyId]
        )['c'] ?? 0));
        $qa = (int) (($q->queryOne(
            "SELECT COUNT(*) AS c FROM rateb_crm_quotations WHERE company_id = :cid AND deleted_at IS NULL AND status = 'accepted'",
            ['cid' => $companyId]
        )['c'] ?? 0));

        $pct = static fn (int $n, int $d): float => $d > 0 ? round(($n / $d) * 100, 1) : 0.0;

        return [
            'leads_total' => $lt,
            'leads_won' => $lw,
            'lead_conversion_rate' => $pct($lw, $lt),
            'opps_total' => $ot,
            'opps_won' => $ow,
            'opp_conversion_rate' => $pct($ow, $ot),
            'quotes_total' => $qt,
            'quotes_accepted' => $qa,
            'quote_conversion_rate' => $pct($qa, $qt),
        ];
    }

    /**
     * @return list<array{source: string, count: int}>
     */
    public function leadSources(): array
    {
        $companyId = CrmSupport::requireCompanyId();
        $rows = (new CrmLead())->query(
            "SELECT COALESCE(s.name, 'Unspecified') AS source, COUNT(*) AS cnt
             FROM rateb_crm_leads l
             LEFT JOIN rateb_crm_lead_sources s ON s.id = l.source_id
             WHERE l.company_id = :cid AND l.deleted_at IS NULL
             GROUP BY s.id, s.name
             ORDER BY cnt DESC, source ASC",
            ['cid' => $companyId]
        );
        $out = [];
        foreach (is_array($rows) ? $rows : [] as $r) {
            $out[] = ['source' => (string) ($r['source'] ?? ''), 'count' => (int) ($r['cnt'] ?? 0)];
        }

        return $out;
    }

    /**
     * @return list<array{owner_user_id: int, opportunities: int, amount: float, expected_revenue: float, won: int}>
     */
    public function salesPerformance(): array
    {
        $companyId = CrmSupport::requireCompanyId();
        $rows = (new CrmOpportunity())->query(
            "SELECT COALESCE(owner_user_id, 0) AS owner_user_id,
                    COUNT(*) AS opportunities,
                    COALESCE(SUM(amount),0) AS amount,
                    COALESCE(SUM(CASE WHEN workflow_status = 'open' THEN amount * probability_percent / 100 ELSE 0 END),0) AS expected_revenue,
                    SUM(CASE WHEN workflow_status = 'won' THEN 1 ELSE 0 END) AS won
             FROM rateb_crm_opportunities
             WHERE company_id = :cid AND deleted_at IS NULL
             GROUP BY owner_user_id
             ORDER BY amount DESC",
            ['cid' => $companyId]
        );
        $out = [];
        foreach (is_array($rows) ? $rows : [] as $r) {
            $out[] = [
                'owner_user_id' => (int) ($r['owner_user_id'] ?? 0),
                'opportunities' => (int) ($r['opportunities'] ?? 0),
                'amount' => (float) ($r['amount'] ?? 0),
                'expected_revenue' => (float) ($r['expected_revenue'] ?? 0),
                'won' => (int) ($r['won'] ?? 0),
            ];
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function lostOpportunities(): array
    {
        $companyId = CrmSupport::requireCompanyId();
        $rows = (new CrmOpportunity())->query(
            "SELECT o.id, o.opportunity_no, o.name, o.amount, o.probability_percent,
                    o.loss_reason_id, o.loss_notes, o.owner_user_id, o.updated_at,
                    lr.name AS loss_reason_name,
                    ROUND(o.amount * o.probability_percent / 100, 2) AS expected_revenue
             FROM rateb_crm_opportunities o
             LEFT JOIN rateb_crm_loss_reasons lr ON lr.id = o.loss_reason_id
             WHERE o.company_id = :cid AND o.deleted_at IS NULL AND o.workflow_status = 'lost'
             ORDER BY o.updated_at DESC LIMIT 100",
            ['cid' => $companyId]
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * Weighted pipeline forecast by stage.
     *
     * @return array{total_amount: float, total_expected_revenue: float, by_stage: list<array{stage:string,count:int,amount:float,expected_revenue:float}>}
     */
    public function forecast(?int $pipelineId = null): array
    {
        $byStage = $this->salesFunnel($pipelineId);
        $totalAmount = 0.0;
        $totalExpected = 0.0;
        foreach ($byStage as $row) {
            $totalAmount += $row['amount'];
            $totalExpected += $row['expected_revenue'];
        }

        return [
            'total_amount' => round($totalAmount, 2),
            'total_expected_revenue' => round($totalExpected, 2),
            'by_stage' => $byStage,
        ];
    }
}
