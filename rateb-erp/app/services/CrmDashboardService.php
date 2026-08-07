<?php

declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\CrmLead;
use Rateb\App\Models\CrmOpportunity;
use Rateb\App\Models\CrmQuotation;

/** Phase 2 — CRM dashboard KPI aggregation. */
final class CrmDashboardService
{
    /**
     * @return array{
     *   leads_total: int,
     *   leads_won: int,
     *   conversion_rate: float,
     *   opportunities_active: int,
     *   quotations_pending: int,
     *   pipeline_value: float
     * }
     */
    public function kpis(): array
    {
        $companyId = CrmSupport::requireCompanyId();
        $lead = new CrmLead();
        $totalRow = $lead->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_crm_leads WHERE company_id = :cid AND deleted_at IS NULL',
            ['cid' => $companyId]
        );
        $wonRow = $lead->queryOne(
            "SELECT COUNT(*) AS c FROM rateb_crm_leads WHERE company_id = :cid AND deleted_at IS NULL AND workflow_status = 'won'",
            ['cid' => $companyId]
        );
        $leadsTotal = (int) ($totalRow['c'] ?? 0);
        $leadsWon = (int) ($wonRow['c'] ?? 0);
        $rate = $leadsTotal > 0 ? round(($leadsWon / $leadsTotal) * 100, 1) : 0.0;

        $oppActive = (new CrmOpportunity())->queryOne(
            "SELECT COUNT(*) AS c FROM rateb_crm_opportunities
             WHERE company_id = :cid AND deleted_at IS NULL AND workflow_status = 'open'",
            ['cid' => $companyId]
        );
        $pipeline = (new CrmOpportunity())->queryOne(
            "SELECT COALESCE(SUM(amount),0) AS v FROM rateb_crm_opportunities
             WHERE company_id = :cid AND deleted_at IS NULL AND workflow_status = 'open'",
            ['cid' => $companyId]
        );
        $pendingQuotes = (new CrmQuotation())->queryOne(
            "SELECT COUNT(*) AS c FROM rateb_crm_quotations
             WHERE company_id = :cid AND deleted_at IS NULL AND status IN ('draft','sent')",
            ['cid' => $companyId]
        );

        return [
            'leads_total' => $leadsTotal,
            'leads_won' => $leadsWon,
            'conversion_rate' => $rate,
            'opportunities_active' => (int) ($oppActive['c'] ?? 0),
            'quotations_pending' => (int) ($pendingQuotes['c'] ?? 0),
            'pipeline_value' => (float) ($pipeline['v'] ?? 0),
        ];
    }
}
