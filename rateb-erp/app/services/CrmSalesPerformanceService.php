<?php

declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\CrmActivity;
use Rateb\App\Models\CrmOpportunity;
use Rateb\App\Models\CrmTask;

/** Phase 7 — Sales performance management reports. */
final class CrmSalesPerformanceService
{
    /**
     * @return array<string, mixed>
     */
    public function dashboard(?string $dateFrom = null, ?string $dateTo = null): array
    {
        return [
            'rep_productivity' => $this->repProductivity($dateFrom, $dateTo),
            'activity_effectiveness' => (new CrmActivityIntelligenceService())->analyze(null, $dateFrom, $dateTo),
            'pipeline_contribution' => $this->pipelineContribution(),
            'conversion_performance' => (new CrmRevenueIntelligenceService())->conversionFunnelAnalytics(),
            'response_sla' => $this->responseSlaTracking($dateFrom, $dateTo),
        ];
    }

    /**
     * @return list<array{owner_user_id:int,activities:int,tasks_completed:int,opps_won:int,pipeline_amount:float,productivity_score:float}>
     */
    public function repProductivity(?string $dateFrom = null, ?string $dateTo = null): array
    {
        $companyId = CrmSupport::requireCompanyId();
        $from = ($dateFrom ?: date('Y-m-d', strtotime('-30 days'))) . ' 00:00:00';
        $to = ($dateTo ?: date('Y-m-d')) . ' 23:59:59';
        $acts = (new CrmActivity())->query(
            "SELECT COALESCE(owner_user_id,0) AS owner_user_id, COUNT(*) AS cnt
             FROM rateb_crm_activities
             WHERE company_id = :cid AND deleted_at IS NULL
               AND COALESCE(activity_at, created_at) BETWEEN :from AND :to
             GROUP BY COALESCE(owner_user_id,0)",
            ['cid' => $companyId, 'from' => $from, 'to' => $to]
        );
        $tasks = (new CrmTask())->query(
            "SELECT COALESCE(owner_user_id,0) AS owner_user_id, COUNT(*) AS cnt
             FROM rateb_crm_tasks
             WHERE company_id = :cid AND deleted_at IS NULL AND status = 'done'
               AND COALESCE(completed_at, updated_at) BETWEEN :from AND :to
             GROUP BY COALESCE(owner_user_id,0)",
            ['cid' => $companyId, 'from' => $from, 'to' => $to]
        );
        $opps = (new CrmOpportunity())->query(
            "SELECT COALESCE(owner_user_id,0) AS owner_user_id,
                    SUM(CASE WHEN workflow_status = 'won' THEN 1 ELSE 0 END) AS won,
                    COALESCE(SUM(CASE WHEN workflow_status = 'open' THEN amount ELSE 0 END),0) AS pipeline_amount
             FROM rateb_crm_opportunities
             WHERE company_id = :cid AND deleted_at IS NULL
             GROUP BY COALESCE(owner_user_id,0)",
            ['cid' => $companyId]
        );
        $map = [];
        foreach (is_array($acts) ? $acts : [] as $r) {
            $uid = (int) ($r['owner_user_id'] ?? 0);
            $map[$uid] = ['owner_user_id' => $uid, 'activities' => (int) $r['cnt'], 'tasks_completed' => 0, 'opps_won' => 0, 'pipeline_amount' => 0.0];
        }
        foreach (is_array($tasks) ? $tasks : [] as $r) {
            $uid = (int) ($r['owner_user_id'] ?? 0);
            if (!isset($map[$uid])) {
                $map[$uid] = ['owner_user_id' => $uid, 'activities' => 0, 'tasks_completed' => 0, 'opps_won' => 0, 'pipeline_amount' => 0.0];
            }
            $map[$uid]['tasks_completed'] = (int) $r['cnt'];
        }
        foreach (is_array($opps) ? $opps : [] as $r) {
            $uid = (int) ($r['owner_user_id'] ?? 0);
            if (!isset($map[$uid])) {
                $map[$uid] = ['owner_user_id' => $uid, 'activities' => 0, 'tasks_completed' => 0, 'opps_won' => 0, 'pipeline_amount' => 0.0];
            }
            $map[$uid]['opps_won'] = (int) ($r['won'] ?? 0);
            $map[$uid]['pipeline_amount'] = (float) ($r['pipeline_amount'] ?? 0);
        }
        $out = [];
        foreach ($map as $row) {
            $score = min(100, ($row['activities'] * 2) + ($row['tasks_completed'] * 3) + ($row['opps_won'] * 10));
            $row['productivity_score'] = (float) $score;
            $out[] = $row;
        }
        usort($out, static fn ($a, $b) => $b['productivity_score'] <=> $a['productivity_score']);

        return $out;
    }

    /**
     * @return list<array{owner_user_id:int,open_amount:float,weighted_amount:float,contribution_pct:float}>
     */
    public function pipelineContribution(): array
    {
        $reps = (new CrmAnalyticsService())->repPerformance();
        $total = 0.0;
        foreach ($reps as $r) {
            $total += (float) $r['open_amount'];
        }
        $out = [];
        foreach ($reps as $r) {
            $open = (float) $r['open_amount'];
            $out[] = [
                'owner_user_id' => (int) $r['owner_user_id'],
                'open_amount' => $open,
                'weighted_amount' => (float) ($r['open_amount'] * max(0.01, $r['win_rate'])),
                'contribution_pct' => $total > 0 ? round(($open / $total) * 100, 1) : 0.0,
            ];
        }

        return $out;
    }

    /**
     * Response SLA: % of tasks completed on/before due_at.
     *
     * @return array{tasks_total:int,on_time:int,late:int,sla_pct:float,avg_delay_hours:float}
     */
    public function responseSlaTracking(?string $dateFrom = null, ?string $dateTo = null): array
    {
        $from = ($dateFrom ?: date('Y-m-d', strtotime('-30 days'))) . ' 00:00:00';
        $to = ($dateTo ?: date('Y-m-d')) . ' 23:59:59';
        $rows = (new CrmTask())->query(
            "SELECT due_at, completed_at, status
             FROM rateb_crm_tasks
             WHERE company_id = :cid AND deleted_at IS NULL
               AND due_at IS NOT NULL
               AND created_at BETWEEN :from AND :to
             LIMIT 500",
            ['cid' => CrmSupport::requireCompanyId(), 'from' => $from, 'to' => $to]
        );
        $total = 0;
        $onTime = 0;
        $late = 0;
        $delays = [];
        foreach (is_array($rows) ? $rows : [] as $t) {
            if (empty($t['completed_at'])) {
                if (($t['status'] ?? '') === 'open' && (string) $t['due_at'] < date('Y-m-d H:i:s')) {
                    ++$total;
                    ++$late;
                }
                continue;
            }
            ++$total;
            $diff = strtotime((string) $t['completed_at']) - strtotime((string) $t['due_at']);
            if ($diff <= 0) {
                ++$onTime;
            } else {
                ++$late;
                $delays[] = $diff / 3600;
            }
        }

        return [
            'tasks_total' => $total,
            'on_time' => $onTime,
            'late' => $late,
            'sla_pct' => $total > 0 ? round(($onTime / $total) * 100, 1) : 0.0,
            'avg_delay_hours' => $delays !== [] ? round(array_sum($delays) / count($delays), 1) : 0.0,
        ];
    }
}
