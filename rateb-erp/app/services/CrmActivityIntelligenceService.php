<?php

declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\CrmActivity;
use Rateb\App\Models\CrmConversion;
use Rateb\App\Models\CrmOpportunity;
use Rateb\App\Models\CrmTask;

/** Phase 6 — Sales activity intelligence (rules-based analytics). */
final class CrmActivityIntelligenceService
{
    /**
     * @return array<string, mixed>
     */
    public function analyze(?int $ownerUserId = null, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $companyId = CrmSupport::requireCompanyId();
        $from = $dateFrom ?: date('Y-m-d', strtotime('-30 days'));
        $to = $dateTo ?: date('Y-m-d');
        $params = ['cid' => $companyId, 'from' => $from . ' 00:00:00', 'to' => $to . ' 23:59:59'];
        $ownerFilter = '';
        if ($ownerUserId !== null && $ownerUserId > 0) {
            $ownerFilter = ' AND owner_user_id = :uid';
            $params['uid'] = $ownerUserId;
        }

        $activityCount = (int) (((new CrmActivity())->queryOne(
            "SELECT COUNT(*) AS c FROM rateb_crm_activities
             WHERE company_id = :cid AND deleted_at IS NULL
               AND COALESCE(activity_at, created_at) BETWEEN :from AND :to {$ownerFilter}",
            $params
        )['c'] ?? 0));

        $tasks = (new CrmTask())->query(
            "SELECT id, due_at, completed_at, created_at, status
             FROM rateb_crm_tasks
             WHERE company_id = :cid AND deleted_at IS NULL
               AND created_at BETWEEN :from AND :to {$ownerFilter}",
            $params
        );
        $taskList = is_array($tasks) ? $tasks : [];
        $followUpDelays = [];
        $responseTimes = [];
        foreach ($taskList as $t) {
            if (!empty($t['due_at']) && !empty($t['completed_at'])) {
                $delay = (strtotime((string) $t['completed_at']) - strtotime((string) $t['due_at'])) / 3600;
                $followUpDelays[] = $delay;
            }
            if (!empty($t['created_at']) && !empty($t['completed_at'])) {
                $responseTimes[] = max(0, (strtotime((string) $t['completed_at']) - strtotime((string) $t['created_at'])) / 3600);
            }
        }
        $avgFollowUpDelay = $followUpDelays !== []
            ? round(array_sum($followUpDelays) / count($followUpDelays), 1)
            : 0.0;
        $avgResponseHours = $responseTimes !== []
            ? round(array_sum($responseTimes) / count($responseTimes), 1)
            : 0.0;

        $wonWithActivity = (int) (((new CrmOpportunity())->queryOne(
            "SELECT COUNT(DISTINCT o.id) AS c
             FROM rateb_crm_opportunities o
             INNER JOIN rateb_crm_activities a ON a.opportunity_id = o.id AND a.deleted_at IS NULL
             WHERE o.company_id = :cid AND o.deleted_at IS NULL AND o.workflow_status = 'won'
               AND o.updated_at BETWEEN :from AND :to"
            . ($ownerUserId !== null && $ownerUserId > 0 ? ' AND o.owner_user_id = :uid' : ''),
            $params
        )['c'] ?? 0));
        $wonTotal = (int) (((new CrmOpportunity())->queryOne(
            "SELECT COUNT(*) AS c FROM rateb_crm_opportunities
             WHERE company_id = :cid AND deleted_at IS NULL AND workflow_status = 'won'
               AND updated_at BETWEEN :from AND :to"
            . ($ownerUserId !== null && $ownerUserId > 0 ? ' AND owner_user_id = :uid' : ''),
            $params
        )['c'] ?? 0));

        $conversions = (int) (((new CrmConversion())->queryOne(
            "SELECT COUNT(*) AS c FROM rateb_crm_conversions
             WHERE company_id = :cid AND created_at BETWEEN :from AND :to",
            ['cid' => $companyId, 'from' => $params['from'], 'to' => $params['to']]
        )['c'] ?? 0));

        $effectiveness = $activityCount > 0
            ? round(($wonWithActivity / max(1, $activityCount)) * 100, 2)
            : 0.0;
        $conversionImpact = $wonTotal > 0
            ? round(($wonWithActivity / $wonTotal) * 100, 1)
            : 0.0;

        return [
            'date_from' => $from,
            'date_to' => $to,
            'owner_user_id' => $ownerUserId,
            'activity_count' => $activityCount,
            'avg_response_hours' => $avgResponseHours,
            'avg_follow_up_delay_hours' => $avgFollowUpDelay,
            'conversion_impact_pct' => $conversionImpact,
            'activity_effectiveness_pct' => $effectiveness,
            'won_with_activity' => $wonWithActivity,
            'won_total' => $wonTotal,
            'conversions' => $conversions,
            'tasks_in_period' => count($taskList),
        ];
    }
}
