<?php

declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\CrmForecastSnapshot;
use Rateb\App\Models\CrmOpportunity;

/** Phase 4 — Sales forecast engine (weighted pipeline + accuracy). */
final class CrmForecastEngineService
{
    /**
     * @return array{
     *   period_key: string,
     *   open_amount: float,
     *   weighted_amount: float,
     *   won_amount: float,
     *   lost_amount: float,
     *   opportunity_count: int,
     *   by_owner: list<array{owner_user_id:int,open_amount:float,weighted_amount:float,won_amount:float,count:int}>,
     *   by_stage: list<array{stage:string,count:int,amount:float,expected_revenue:float}>
     * }
     */
    public function compute(?int $pipelineId = null, ?string $periodKey = null): array
    {
        $companyId = CrmSupport::requireCompanyId();
        $period = $periodKey !== null && $periodKey !== '' ? substr($periodKey, 0, 7) : date('Y-m');
        $params = ['cid' => $companyId];
        $pipe = '';
        if ($pipelineId !== null && $pipelineId > 0) {
            $pipe = ' AND pipeline_id = :pid';
            $params['pid'] = $pipelineId;
        }

        $agg = (new CrmOpportunity())->queryOne(
            "SELECT
                COALESCE(SUM(CASE WHEN workflow_status = 'open' THEN amount ELSE 0 END),0) AS open_amount,
                COALESCE(SUM(CASE WHEN workflow_status = 'open' THEN amount * probability_percent / 100 ELSE 0 END),0) AS weighted_amount,
                COALESCE(SUM(CASE WHEN workflow_status = 'won' THEN amount ELSE 0 END),0) AS won_amount,
                COALESCE(SUM(CASE WHEN workflow_status = 'lost' THEN amount ELSE 0 END),0) AS lost_amount,
                COUNT(*) AS opportunity_count
             FROM rateb_crm_opportunities
             WHERE company_id = :cid AND deleted_at IS NULL {$pipe}",
            $params
        );

        $byOwnerRows = (new CrmOpportunity())->query(
            "SELECT COALESCE(owner_user_id,0) AS owner_user_id,
                    COALESCE(SUM(CASE WHEN workflow_status = 'open' THEN amount ELSE 0 END),0) AS open_amount,
                    COALESCE(SUM(CASE WHEN workflow_status = 'open' THEN amount * probability_percent / 100 ELSE 0 END),0) AS weighted_amount,
                    COALESCE(SUM(CASE WHEN workflow_status = 'won' THEN amount ELSE 0 END),0) AS won_amount,
                    COUNT(*) AS cnt
             FROM rateb_crm_opportunities
             WHERE company_id = :cid AND deleted_at IS NULL {$pipe}
             GROUP BY owner_user_id
             ORDER BY weighted_amount DESC",
            $params
        );
        $byOwner = [];
        foreach (is_array($byOwnerRows) ? $byOwnerRows : [] as $r) {
            $byOwner[] = [
                'owner_user_id' => (int) ($r['owner_user_id'] ?? 0),
                'open_amount' => (float) ($r['open_amount'] ?? 0),
                'weighted_amount' => (float) ($r['weighted_amount'] ?? 0),
                'won_amount' => (float) ($r['won_amount'] ?? 0),
                'count' => (int) ($r['cnt'] ?? 0),
            ];
        }

        return [
            'period_key' => $period,
            'open_amount' => (float) ($agg['open_amount'] ?? 0),
            'weighted_amount' => (float) ($agg['weighted_amount'] ?? 0),
            'won_amount' => (float) ($agg['won_amount'] ?? 0),
            'lost_amount' => (float) ($agg['lost_amount'] ?? 0),
            'opportunity_count' => (int) ($agg['opportunity_count'] ?? 0),
            'by_owner' => $byOwner,
            'by_stage' => (new CrmReportService())->salesFunnel($pipelineId),
        ];
    }

    /**
     * Persist a period snapshot for later accuracy reporting.
     *
     * @return array{id: int, period_key: string}
     */
    public function snapshot(?int $pipelineId = null, ?int $ownerUserId = null, ?string $periodKey = null): array
    {
        $companyId = CrmSupport::requireCompanyId();
        $data = $this->compute($pipelineId, $periodKey);
        $weighted = $data['weighted_amount'];
        $won = $data['won_amount'];
        if ($ownerUserId !== null && $ownerUserId > 0) {
            $weighted = 0.0;
            $won = 0.0;
            $open = 0.0;
            $count = 0;
            foreach ($data['by_owner'] as $row) {
                if ($row['owner_user_id'] === $ownerUserId) {
                    $weighted = $row['weighted_amount'];
                    $won = $row['won_amount'];
                    $open = $row['open_amount'];
                    $count = $row['count'];
                    break;
                }
            }
            $data['open_amount'] = $open;
            $data['weighted_amount'] = $weighted;
            $data['won_amount'] = $won;
            $data['opportunity_count'] = $count;
        }

        $id = (new CrmForecastSnapshot())->create([
            'public_uuid' => CrmSupport::uuidV4(),
            'company_id' => $companyId,
            'period_key' => $data['period_key'],
            'pipeline_id' => $pipelineId,
            'owner_user_id' => $ownerUserId,
            'open_amount' => $data['open_amount'],
            'weighted_amount' => $data['weighted_amount'],
            'won_amount' => $data['won_amount'],
            'lost_amount' => $data['lost_amount'],
            'opportunity_count' => $data['opportunity_count'],
            'meta_json' => json_encode(['by_stage' => $data['by_stage']], JSON_UNESCAPED_UNICODE),
            'created_by' => CrmSupport::userId(),
        ]);

        if (class_exists(AuditService::class)) {
            (new AuditService())->log('crm.forecast.snapshot', 'crm_forecast_snapshot', (int) $id, [
                'period_key' => $data['period_key'],
                'weighted_amount' => $data['weighted_amount'],
            ]);
        }

        return ['id' => (int) $id, 'period_key' => $data['period_key']];
    }

    /**
     * Compare earliest snapshot weighted vs later won for accuracy.
     *
     * @return list<array{period_key:string,forecast_weighted:float,actual_won:float,accuracy_pct:float}>
     */
    public function accuracyReport(int $limit = 12): array
    {
        $companyId = CrmSupport::requireCompanyId();
        $rows = (new CrmForecastSnapshot())->query(
            'SELECT period_key,
                    AVG(weighted_amount) AS forecast_weighted,
                    AVG(won_amount) AS actual_won
             FROM rateb_crm_forecast_snapshots
             WHERE company_id = :cid AND owner_user_id IS NULL
             GROUP BY period_key
             ORDER BY period_key DESC
             LIMIT ' . max(1, min(36, $limit)),
            ['cid' => $companyId]
        );
        $out = [];
        foreach (is_array($rows) ? $rows : [] as $r) {
            $forecast = (float) ($r['forecast_weighted'] ?? 0);
            $actual = (float) ($r['actual_won'] ?? 0);
            $accuracy = $forecast > 0 ? round(min(100, ($actual / $forecast) * 100), 1) : ($actual > 0 ? 100.0 : 0.0);
            $out[] = [
                'period_key' => (string) ($r['period_key'] ?? ''),
                'forecast_weighted' => round($forecast, 2),
                'actual_won' => round($actual, 2),
                'accuracy_pct' => $accuracy,
            ];
        }

        return $out;
    }

    /**
     * Win probability distribution for open opportunities.
     *
     * @return list<array{bucket:string,count:int,amount:float,weighted:float}>
     */
    public function winProbabilityTracking(): array
    {
        $companyId = CrmSupport::requireCompanyId();
        $rows = (new CrmOpportunity())->query(
            "SELECT
                CASE
                    WHEN probability_percent < 25 THEN '0-24'
                    WHEN probability_percent < 50 THEN '25-49'
                    WHEN probability_percent < 75 THEN '50-74'
                    ELSE '75-100'
                END AS bucket,
                COUNT(*) AS cnt,
                COALESCE(SUM(amount),0) AS amount,
                COALESCE(SUM(amount * probability_percent / 100),0) AS weighted
             FROM rateb_crm_opportunities
             WHERE company_id = :cid AND deleted_at IS NULL AND workflow_status = 'open'
             GROUP BY bucket
             ORDER BY bucket",
            ['cid' => $companyId]
        );
        $out = [];
        foreach (is_array($rows) ? $rows : [] as $r) {
            $out[] = [
                'bucket' => (string) ($r['bucket'] ?? ''),
                'count' => (int) ($r['cnt'] ?? 0),
                'amount' => (float) ($r['amount'] ?? 0),
                'weighted' => (float) ($r['weighted'] ?? 0),
            ];
        }

        return $out;
    }
}
