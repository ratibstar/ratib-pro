<?php

declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\CrmFreshnessSnapshot;
use Rateb\App\Models\CrmLead;
use Rateb\App\Models\CrmOpportunity;
use Rateb\App\Models\Customer;

/** Phase 9 — Data freshness checks + automated quality scoring hook. */
final class CrmDataFreshnessService
{
    /**
     * @return array<string, mixed>
     */
    public function check(bool $persist = true): array
    {
        $companyId = CrmSupport::requireCompanyId();
        $thresholds = (new CrmGovernanceService())->setting('intelligence_thresholds', [
            'freshness_stale_days' => 30,
        ]);
        $days = max(7, (int) ($thresholds['freshness_stale_days'] ?? 30));

        $staleLeads = (int) (((new CrmLead())->queryOne(
            "SELECT COUNT(*) AS c FROM rateb_crm_leads
             WHERE company_id = :cid AND deleted_at IS NULL
               AND workflow_status NOT IN ('won','lost','archived')
               AND updated_at <= DATE_SUB(NOW(), INTERVAL {$days} DAY)",
            ['cid' => $companyId]
        )['c'] ?? 0));
        $staleOpps = (int) (((new CrmOpportunity())->queryOne(
            "SELECT COUNT(*) AS c FROM rateb_crm_opportunities
             WHERE company_id = :cid AND deleted_at IS NULL AND workflow_status = 'open'
               AND updated_at <= DATE_SUB(NOW(), INTERVAL {$days} DAY)",
            ['cid' => $companyId]
        )['c'] ?? 0));
        $staleCustomers = (int) (((new Customer())->queryOne(
            "SELECT COUNT(*) AS c FROM rateb_customers
             WHERE company_id = :cid
               AND (crm_last_interaction_at IS NULL
                    OR crm_last_interaction_at <= DATE_SUB(NOW(), INTERVAL {$days} DAY))",
            ['cid' => $companyId]
        )['c'] ?? 0));

        $quality = [];
        try {
            $scan = (new CrmDataQualityEngineService())->runScan($persist);
            $scores = (new CrmDataQualityEngineService())->computeScores($scan);
            $quality = array_merge($scan, $scores);
        } catch (\Throwable $e) {
            $quality = ['quality_score' => 0, 'completeness_score' => 0];
        }

        $penalty = min(60, ($staleLeads + $staleOpps + $staleCustomers) * 0.5);
        $freshness = max(0, min(100, 100 - $penalty));
        $result = [
            'freshness_score' => round($freshness, 2),
            'stale_leads' => $staleLeads,
            'stale_opportunities' => $staleOpps,
            'stale_customers' => $staleCustomers,
            'stale_days' => $days,
            'quality' => $quality,
            'automated_quality_score' => (float) ($quality['quality_score'] ?? 0),
        ];
        if ($persist) {
            $id = (int) (new CrmFreshnessSnapshot())->create([
                'public_uuid' => CrmSupport::uuidV4(),
                'company_id' => $companyId,
                'freshness_score' => $result['freshness_score'],
                'stale_leads' => $staleLeads,
                'stale_opportunities' => $staleOpps,
                'stale_customers' => $staleCustomers,
                'meta_json' => json_encode($result, JSON_UNESCAPED_UNICODE),
                'created_by' => CrmSupport::userId(),
            ]);
            $result['snapshot_id'] = $id;
            if (class_exists(AuditService::class)) {
                (new AuditService())->log('crm.governance.scan', 'crm_freshness_snapshot', $id, [
                    'freshness_score' => $result['freshness_score'],
                ]);
            }
        }

        return $result;
    }

    /** @return list<array<string, mixed>> */
    public function history(int $limit = 20): array
    {
        $rows = (new CrmFreshnessSnapshot())->query(
            'SELECT * FROM rateb_crm_freshness_snapshots
             WHERE company_id = :cid ORDER BY id DESC LIMIT ' . max(1, min(50, $limit)),
            ['cid' => CrmSupport::requireCompanyId()]
        );

        return is_array($rows) ? array_reverse($rows) : [];
    }
}
