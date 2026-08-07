<?php

declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\CrmOpportunity;
use Rateb\App\Models\CrmStageTransition;

/** Phase 5 — Stage duration, bottlenecks, pipeline health score. */
final class CrmPipelineHealthService
{
    /**
     * Average time spent in each stage (from transition log + current open dwell).
     *
     * @return list<array{stage_id:int,stage:string,avg_duration_days:float,transition_count:int,open_count:int,expected_duration_days:?int,bottleneck:bool}>
     */
    public function stageDurationTracking(?int $pipelineId = null): array
    {
        $companyId = CrmSupport::requireCompanyId();
        $pipe = $pipelineId !== null && $pipelineId > 0
            ? $pipelineId
            : (int) (((new PipelineService())->defaultPipeline()['id'] ?? 0));
        if ($pipe < 1) {
            return [];
        }
        $stages = (new PipelineService())->stagesFor($pipe);
        $out = [];
        foreach ($stages as $stage) {
            $sid = (int) ($stage['id'] ?? 0);
            $avgRow = (new CrmStageTransition())->queryOne(
                'SELECT AVG(duration_seconds) AS avg_sec, COUNT(*) AS cnt
                 FROM rateb_crm_stage_transitions
                 WHERE company_id = :cid AND from_stage_id = :sid AND duration_seconds > 0',
                ['cid' => $companyId, 'sid' => $sid]
            );
            $openRow = (new CrmOpportunity())->queryOne(
                "SELECT COUNT(*) AS c FROM rateb_crm_opportunities
                 WHERE company_id = :cid AND stage_id = :sid AND deleted_at IS NULL AND workflow_status = 'open'",
                ['cid' => $companyId, 'sid' => $sid]
            );
            $avgDays = round(((float) ($avgRow['avg_sec'] ?? 0)) / 86400, 2);
            $expected = isset($stage['expected_duration_days']) && $stage['expected_duration_days'] !== null
                ? (int) $stage['expected_duration_days']
                : null;
            $bottleneck = $expected !== null && $expected > 0 && $avgDays > ($expected * 1.25);
            $out[] = [
                'stage_id' => $sid,
                'stage' => (string) ($stage['name'] ?? ''),
                'avg_duration_days' => $avgDays,
                'transition_count' => (int) ($avgRow['cnt'] ?? 0),
                'open_count' => (int) ($openRow['c'] ?? 0),
                'expected_duration_days' => $expected,
                'bottleneck' => $bottleneck,
            ];
        }

        return $out;
    }

    /**
     * @return list<array{stage_id:int,stage:string,avg_duration_days:float,severity:float}>
     */
    public function bottleneckAnalysis(?int $pipelineId = null): array
    {
        $rows = $this->stageDurationTracking($pipelineId);
        $bottlenecks = array_values(array_filter($rows, static fn (array $r): bool => !empty($r['bottleneck'])));
        if ($bottlenecks === []) {
            // Fallback: top stages by average duration among those with data
            usort($rows, static fn ($a, $b) => ($b['avg_duration_days'] <=> $a['avg_duration_days']));
            $bottlenecks = array_slice(array_values(array_filter(
                $rows,
                static fn (array $r): bool => $r['avg_duration_days'] > 0
            )), 0, 3);
        }
        $max = 0.0;
        foreach ($bottlenecks as $b) {
            $max = max($max, (float) $b['avg_duration_days']);
        }
        $out = [];
        foreach ($bottlenecks as $b) {
            $out[] = [
                'stage_id' => (int) $b['stage_id'],
                'stage' => (string) $b['stage'],
                'avg_duration_days' => (float) $b['avg_duration_days'],
                'severity' => $max > 0 ? round((float) $b['avg_duration_days'] / $max, 3) : 0.0,
            ];
        }

        return $out;
    }

    /**
     * Health score 0–100 based on open pipeline mix, bottlenecks, and stale stage dwell.
     *
     * @return array{score: int, grade: string, open_count: int, bottleneck_count: int, stale_count: int, factors: array<string,float>}
     */
    public function healthScore(?int $pipelineId = null): array
    {
        $companyId = CrmSupport::requireCompanyId();
        $params = ['cid' => $companyId];
        $pipeFilter = '';
        if ($pipelineId !== null && $pipelineId > 0) {
            $pipeFilter = ' AND o.pipeline_id = :pid';
            $params['pid'] = $pipelineId;
        }
        $open = (int) (((new CrmOpportunity())->queryOne(
            "SELECT COUNT(*) AS c FROM rateb_crm_opportunities o
             WHERE o.company_id = :cid AND o.deleted_at IS NULL AND o.workflow_status = 'open' {$pipeFilter}",
            $params
        )['c'] ?? 0));
        $won = (int) (((new CrmOpportunity())->queryOne(
            "SELECT COUNT(*) AS c FROM rateb_crm_opportunities o
             WHERE o.company_id = :cid AND o.deleted_at IS NULL AND o.workflow_status = 'won'
               AND o.updated_at >= DATE_SUB(NOW(), INTERVAL 90 DAY) {$pipeFilter}",
            $params
        )['c'] ?? 0));
        $lost = (int) (((new CrmOpportunity())->queryOne(
            "SELECT COUNT(*) AS c FROM rateb_crm_opportunities o
             WHERE o.company_id = :cid AND o.deleted_at IS NULL AND o.workflow_status = 'lost'
               AND o.updated_at >= DATE_SUB(NOW(), INTERVAL 90 DAY) {$pipeFilter}",
            $params
        )['c'] ?? 0));

        $durations = $this->stageDurationTracking($pipelineId);
        $bottleneckCount = count(array_filter($durations, static fn ($r) => !empty($r['bottleneck'])));
        $stale = (int) (((new CrmOpportunity())->queryOne(
            "SELECT COUNT(*) AS c FROM rateb_crm_opportunities o
             INNER JOIN rateb_crm_pipeline_stages s ON s.id = o.stage_id
             WHERE o.company_id = :cid AND o.deleted_at IS NULL AND o.workflow_status = 'open'
               AND s.expected_duration_days IS NOT NULL AND s.expected_duration_days > 0
               AND o.stage_entered_at IS NOT NULL
               AND o.stage_entered_at < DATE_SUB(NOW(), INTERVAL s.expected_duration_days DAY)
               {$pipeFilter}",
            $params
        )['c'] ?? 0));

        $closed = $won + $lost;
        $winRate = $closed > 0 ? ($won / $closed) : 0.5;
        $bottleneckPenalty = min(40.0, $bottleneckCount * 12.0);
        $stalePenalty = $open > 0 ? min(30.0, ($stale / max(1, $open)) * 30.0) : 0.0;
        $coverageBoost = min(15.0, $open > 0 ? 15.0 : 0.0);
        $score = (int) max(0, min(100, round(($winRate * 55.0) + $coverageBoost + 30.0 - $bottleneckPenalty - $stalePenalty)));

        $grade = $score >= 80 ? 'A' : ($score >= 65 ? 'B' : ($score >= 50 ? 'C' : 'D'));

        return [
            'score' => $score,
            'grade' => $grade,
            'open_count' => $open,
            'bottleneck_count' => $bottleneckCount,
            'stale_count' => $stale,
            'factors' => [
                'win_rate' => round($winRate, 3),
                'bottleneck_penalty' => $bottleneckPenalty,
                'stale_penalty' => round($stalePenalty, 2),
            ],
        ];
    }

    /**
     * Record a stage transition (called from OpportunityService::moveStage).
     *
     * @param array<string, mixed> $meta
     */
    public function recordTransition(
        int $opportunityId,
        ?int $fromStageId,
        int $toStageId,
        ?int $pipelineId,
        ?string $stageEnteredAt,
        ?int $ownerUserId,
        ?int $teamId,
        array $meta = []
    ): void {
        $duration = 0;
        if ($stageEnteredAt !== null && $stageEnteredAt !== '') {
            $start = strtotime($stageEnteredAt);
            if ($start !== false) {
                $duration = max(0, time() - $start);
            }
        }
        (new CrmStageTransition())->create([
            'public_uuid' => CrmSupport::uuidV4(),
            'company_id' => CrmSupport::requireCompanyId(),
            'opportunity_id' => $opportunityId,
            'pipeline_id' => $pipelineId,
            'from_stage_id' => $fromStageId,
            'to_stage_id' => $toStageId,
            'duration_seconds' => $duration,
            'owner_user_id' => $ownerUserId,
            'team_id' => $teamId,
            'meta_json' => $meta !== [] ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null,
            'created_by' => CrmSupport::userId(),
        ]);
    }
}
