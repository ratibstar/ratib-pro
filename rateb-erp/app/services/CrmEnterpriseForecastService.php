<?php

declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\CrmForecastChangeLog;
use Rateb\App\Models\CrmForecastSnapshot;
use Rateb\App\Models\CrmOpportunity;
use Rateb\App\Models\CrmSalesTeamMember;

/** Phase 7 — Enterprise forecasting (monthly/quarterly, team/rep rollups). */
final class CrmEnterpriseForecastService
{
    /**
     * @return array<string, mixed>
     */
    public function compute(
        string $periodType = 'month',
        ?int $pipelineId = null,
        ?int $teamId = null,
        ?int $ownerUserId = null,
        ?string $periodKey = null
    ): array {
        $periodType = strtolower($periodType) === 'quarter' ? 'quarter' : 'month';
        $period = $periodKey !== null && $periodKey !== ''
            ? substr($periodKey, 0, 7)
            : ($periodType === 'quarter' ? $this->currentQuarterKey() : date('Y-m'));

        $base = (new CrmForecastEngineService())->compute($pipelineId, $period);
        $ownerFilter = $ownerUserId;
        $teamOwners = null;
        if ($teamId !== null && $teamId > 0) {
            $teamOwners = $this->teamUserIds($teamId);
        }

        $open = 0.0;
        $weighted = 0.0;
        $won = 0.0;
        $lost = 0.0;
        $count = 0;
        $byOwner = [];
        foreach ($base['by_owner'] as $row) {
            $uid = (int) $row['owner_user_id'];
            if ($ownerFilter !== null && $ownerFilter > 0 && $uid !== $ownerFilter) {
                continue;
            }
            if ($teamOwners !== null && !in_array($uid, $teamOwners, true)) {
                continue;
            }
            $byOwner[] = $row;
            $open += (float) $row['open_amount'];
            $weighted += (float) $row['weighted_amount'];
            $won += (float) $row['won_amount'];
            $count += (int) $row['count'];
        }
        if ($ownerFilter === null && $teamOwners === null) {
            $open = (float) $base['open_amount'];
            $weighted = (float) $base['weighted_amount'];
            $won = (float) $base['won_amount'];
            $lost = (float) $base['lost_amount'];
            $count = (int) $base['opportunity_count'];
            $byOwner = $base['by_owner'];
        } else {
            $lost = 0.0;
        }

        if ($periodType === 'quarter') {
            // Soft rollup: multiply open/weighted view with quarter coverage factor from month snapshots if any.
            $months = $this->quarterMonths($period);
            $snapBoost = $this->avgSnapshotWeighted($months, $pipelineId, $teamId, $ownerUserId);
            if ($snapBoost > 0) {
                $weighted = max($weighted, $snapBoost);
            }
        }

        $confidence = $this->confidenceScore($weighted, $won, $count, $periodType);
        $scope = 'company';
        if ($ownerUserId !== null && $ownerUserId > 0) {
            $scope = 'rep';
        } elseif ($teamId !== null && $teamId > 0) {
            $scope = 'team';
        }

        return [
            'period_key' => $period,
            'period_type' => $periodType,
            'forecast_scope' => $scope,
            'pipeline_id' => $pipelineId,
            'team_id' => $teamId,
            'owner_user_id' => $ownerUserId,
            'open_amount' => round($open, 2),
            'weighted_amount' => round($weighted, 2),
            'won_amount' => round($won, 2),
            'lost_amount' => round($lost, 2),
            'opportunity_count' => $count,
            'confidence_score' => $confidence,
            'by_owner' => $byOwner,
            'by_stage' => $base['by_stage'] ?? [],
            'team_rollup' => $this->teamForecastRollup($pipelineId, $period),
            'rep_forecast' => $byOwner,
        ];
    }

    /**
     * @return array{id:int,period_key:string,confidence_score:float}
     */
    public function snapshot(
        string $periodType = 'month',
        ?int $pipelineId = null,
        ?int $teamId = null,
        ?int $ownerUserId = null,
        ?string $periodKey = null
    ): array {
        $companyId = CrmSupport::requireCompanyId();
        $data = $this->compute($periodType, $pipelineId, $teamId, $ownerUserId, $periodKey);
        $prev = (new CrmForecastSnapshot())->queryOne(
            'SELECT id, weighted_amount, confidence_score FROM rateb_crm_forecast_snapshots
             WHERE company_id = :cid AND period_key = :pk AND period_type = :pt
               AND COALESCE(pipeline_id,0) = :pid
               AND COALESCE(team_id,0) = :tid
               AND COALESCE(owner_user_id,0) = :uid
             ORDER BY id DESC LIMIT 1',
            [
                'cid' => $companyId,
                'pk' => $data['period_key'],
                'pt' => $data['period_type'],
                'pid' => $pipelineId ?? 0,
                'tid' => $teamId ?? 0,
                'uid' => $ownerUserId ?? 0,
            ]
        );

        $meta = json_encode([
            'scope' => $data['forecast_scope'],
            'by_owner_count' => count($data['by_owner']),
        ], JSON_UNESCAPED_UNICODE);

        try {
            $id = (new CrmForecastSnapshot())->create([
                'public_uuid' => CrmSupport::uuidV4(),
                'company_id' => $companyId,
                'period_key' => $data['period_key'],
                'period_type' => $data['period_type'],
                'pipeline_id' => $pipelineId,
                'owner_user_id' => $ownerUserId,
                'team_id' => $teamId,
                'open_amount' => $data['open_amount'],
                'weighted_amount' => $data['weighted_amount'],
                'won_amount' => $data['won_amount'],
                'lost_amount' => $data['lost_amount'],
                'opportunity_count' => $data['opportunity_count'],
                'confidence_score' => $data['confidence_score'],
                'forecast_scope' => $data['forecast_scope'],
                'meta_json' => $meta,
                'created_by' => CrmSupport::userId(),
            ]);
        } catch (\Throwable $e) {
            // Pre-migrate fallback without new columns
            $created = (new CrmForecastEngineService())->snapshot($pipelineId, $ownerUserId, $data['period_key']);

            return [
                'id' => (int) $created['id'],
                'period_key' => $created['period_key'],
                'confidence_score' => $data['confidence_score'],
            ];
        }

        $this->logChange(
            (int) $id,
            $data['period_key'],
            $data['period_type'],
            'snapshot',
            isset($prev['weighted_amount']) ? (float) $prev['weighted_amount'] : null,
            (float) $data['weighted_amount'],
            isset($prev['confidence_score']) ? (float) $prev['confidence_score'] : null,
            (float) $data['confidence_score'],
            $teamId,
            $ownerUserId,
            ['pipeline_id' => $pipelineId]
        );

        if (class_exists(AuditService::class)) {
            (new AuditService())->log('crm.forecast.change', 'crm_forecast_snapshot', (int) $id, [
                'period_key' => $data['period_key'],
                'period_type' => $data['period_type'],
                'weighted' => $data['weighted_amount'],
                'confidence' => $data['confidence_score'],
            ]);
        }

        return [
            'id' => (int) $id,
            'period_key' => $data['period_key'],
            'confidence_score' => (float) $data['confidence_score'],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function changeHistory(int $limit = 40): array
    {
        $rows = (new CrmForecastChangeLog())->query(
            'SELECT * FROM rateb_crm_forecast_change_log
             WHERE company_id = :cid ORDER BY id DESC LIMIT ' . max(1, min(100, $limit)),
            ['cid' => CrmSupport::requireCompanyId()]
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return list<array{team_id:int,weighted_amount:float,open_amount:float,member_count:int}>
     */
    public function teamForecastRollup(?int $pipelineId = null, ?string $periodKey = null): array
    {
        $companyId = CrmSupport::requireCompanyId();
        $members = (new CrmSalesTeamMember())->query(
            'SELECT team_id, user_id FROM rateb_crm_sales_team_members
             WHERE company_id = :cid AND deleted_at IS NULL',
            ['cid' => $companyId]
        );
        if (!is_array($members) || $members === []) {
            return [];
        }
        $byTeam = [];
        foreach ($members as $m) {
            $tid = (int) ($m['team_id'] ?? 0);
            $uid = (int) ($m['user_id'] ?? 0);
            if ($tid < 1 || $uid < 1) {
                continue;
            }
            $byTeam[$tid][] = $uid;
        }
        $base = (new CrmForecastEngineService())->compute($pipelineId, $periodKey);
        $ownerMap = [];
        foreach ($base['by_owner'] as $row) {
            $ownerMap[(int) $row['owner_user_id']] = $row;
        }
        $out = [];
        foreach ($byTeam as $tid => $uids) {
            $w = 0.0;
            $o = 0.0;
            foreach ($uids as $uid) {
                if (!isset($ownerMap[$uid])) {
                    continue;
                }
                $w += (float) $ownerMap[$uid]['weighted_amount'];
                $o += (float) $ownerMap[$uid]['open_amount'];
            }
            $out[] = [
                'team_id' => $tid,
                'weighted_amount' => round($w, 2),
                'open_amount' => round($o, 2),
                'member_count' => count($uids),
            ];
        }
        usort($out, static fn ($a, $b) => $b['weighted_amount'] <=> $a['weighted_amount']);

        return $out;
    }

    private function confidenceScore(float $weighted, float $won, int $count, string $periodType): float
    {
        $accuracy = (new CrmForecastEngineService())->accuracyReport(6);
        $accAvg = 50.0;
        if ($accuracy !== []) {
            $sum = 0.0;
            foreach ($accuracy as $row) {
                $sum += (float) ($row['accuracy_pct'] ?? 0);
            }
            $accAvg = $sum / count($accuracy);
        }
        $coverage = min(20.0, $count * 1.5);
        $wonBoost = $weighted > 0 ? min(15.0, ($won / max(1.0, $weighted)) * 15.0) : 0.0;
        $periodAdj = $periodType === 'quarter' ? -5.0 : 0.0;

        return round(max(0, min(100, $accAvg * 0.6 + $coverage + $wonBoost + $periodAdj + 10)), 2);
    }

    /** @return list<int> */
    private function teamUserIds(int $teamId): array
    {
        $rows = (new CrmSalesTeamMember())->query(
            'SELECT user_id FROM rateb_crm_sales_team_members
             WHERE company_id = :cid AND team_id = :tid AND deleted_at IS NULL',
            ['cid' => CrmSupport::requireCompanyId(), 'tid' => $teamId]
        );
        $ids = [];
        foreach (is_array($rows) ? $rows : [] as $r) {
            $ids[] = (int) ($r['user_id'] ?? 0);
        }

        return array_values(array_filter($ids, static fn ($i) => $i > 0));
    }

    private function currentQuarterKey(): string
    {
        $m = (int) date('n');
        $qStart = (int) (floor(($m - 1) / 3) * 3 + 1);

        return date('Y') . '-' . str_pad((string) $qStart, 2, '0', STR_PAD_LEFT);
    }

    /** @return list<string> */
    private function quarterMonths(string $periodKey): array
    {
        $y = (int) substr($periodKey, 0, 4);
        $m = (int) substr($periodKey, 5, 2);
        if ($m < 1) {
            $m = 1;
        }
        $start = (int) (floor(($m - 1) / 3) * 3 + 1);

        return [
            sprintf('%04d-%02d', $y, $start),
            sprintf('%04d-%02d', $y, $start + 1),
            sprintf('%04d-%02d', $y, $start + 2),
        ];
    }

    /** @param list<string> $months */
    private function avgSnapshotWeighted(array $months, ?int $pipelineId, ?int $teamId, ?int $ownerUserId): float
    {
        if ($months === []) {
            return 0.0;
        }
        $placeholders = [];
        $params = ['cid' => CrmSupport::requireCompanyId()];
        foreach ($months as $i => $mk) {
            $k = 'm' . $i;
            $placeholders[] = ':' . $k;
            $params[$k] = $mk;
        }
        $sql = 'SELECT AVG(weighted_amount) AS w FROM rateb_crm_forecast_snapshots
                WHERE company_id = :cid AND period_key IN (' . implode(',', $placeholders) . ')';
        if ($pipelineId !== null && $pipelineId > 0) {
            $sql .= ' AND pipeline_id = :pid';
            $params['pid'] = $pipelineId;
        }
        if ($teamId !== null && $teamId > 0) {
            $sql .= ' AND team_id = :tid';
            $params['tid'] = $teamId;
        }
        if ($ownerUserId !== null && $ownerUserId > 0) {
            $sql .= ' AND owner_user_id = :uid';
            $params['uid'] = $ownerUserId;
        }
        try {
            return (float) (((new CrmForecastSnapshot())->queryOne($sql, $params)['w'] ?? 0));
        } catch (\Throwable $e) {
            return 0.0;
        }
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function logChange(
        ?int $snapshotId,
        string $periodKey,
        string $periodType,
        string $changeType,
        ?float $fromWeighted,
        ?float $toWeighted,
        ?float $fromConf,
        ?float $toConf,
        ?int $teamId,
        ?int $ownerUserId,
        array $meta = []
    ): void {
        try {
            (new CrmForecastChangeLog())->create([
                'public_uuid' => CrmSupport::uuidV4(),
                'company_id' => CrmSupport::requireCompanyId(),
                'snapshot_id' => $snapshotId,
                'period_key' => $periodKey,
                'period_type' => $periodType,
                'change_type' => substr($changeType, 0, 40),
                'from_weighted' => $fromWeighted,
                'to_weighted' => $toWeighted,
                'from_confidence' => $fromConf,
                'to_confidence' => $toConf,
                'team_id' => $teamId,
                'owner_user_id' => $ownerUserId,
                'meta_json' => $meta !== [] ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null,
                'created_by' => CrmSupport::userId(),
            ]);
        } catch (\Throwable $e) {
            // table may be absent pre-migrate
        }
    }
}
