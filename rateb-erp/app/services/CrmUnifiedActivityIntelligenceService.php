<?php

declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\CrmActivity;
use Rateb\App\Models\CrmOpportunity;
use Rateb\App\Models\CrmTask;

/** Phase 9 — Unified activity intelligence (patterns, delays, engagement, rep effectiveness). */
final class CrmUnifiedActivityIntelligenceService
{
    /**
     * @return array<string, mixed>
     */
    public function analyze(?int $ownerUserId = null, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $base = (new CrmActivityIntelligenceService())->analyze($ownerUserId, $dateFrom, $dateTo);
        $patterns = $this->activityPatterns($ownerUserId, $dateFrom, $dateTo);
        $delays = $this->responseDelays($ownerUserId, $dateFrom, $dateTo);
        $engagement = $this->salesEngagement($ownerUserId, $dateFrom, $dateTo);
        $reps = $this->repEffectiveness($dateFrom, $dateTo);

        $result = array_merge($base, [
            'activity_patterns' => $patterns,
            'response_delays' => $delays,
            'sales_engagement' => $engagement,
            'rep_effectiveness' => $reps,
        ]);
        if (class_exists(AuditService::class)) {
            (new AuditService())->log('crm.intelligence.calculate', 'crm_activity_intelligence', null, [
                'owner_user_id' => $ownerUserId,
                'activity_count' => (int) ($base['activity_count'] ?? 0),
            ]);
        }

        return $result;
    }

    /**
     * @return array{by_type:array<string,int>,by_day:list<array{day:string,count:int}>}
     */
    public function activityPatterns(?int $ownerUserId = null, ?string $dateFrom = null, ?string $dateTo = null): array
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
        $byType = (new CrmActivity())->query(
            "SELECT activity_type, COUNT(*) AS c FROM rateb_crm_activities
             WHERE company_id = :cid AND deleted_at IS NULL
               AND COALESCE(activity_at, created_at) BETWEEN :from AND :to {$ownerFilter}
             GROUP BY activity_type ORDER BY c DESC",
            $params
        );
        $types = [];
        foreach (is_array($byType) ? $byType : [] as $r) {
            $types[(string) ($r['activity_type'] ?? 'other')] = (int) ($r['c'] ?? 0);
        }
        $byDay = (new CrmActivity())->query(
            "SELECT DATE(COALESCE(activity_at, created_at)) AS day, COUNT(*) AS c
             FROM rateb_crm_activities
             WHERE company_id = :cid AND deleted_at IS NULL
               AND COALESCE(activity_at, created_at) BETWEEN :from AND :to {$ownerFilter}
             GROUP BY DATE(COALESCE(activity_at, created_at))
             ORDER BY day ASC LIMIT 60",
            $params
        );
        $days = [];
        foreach (is_array($byDay) ? $byDay : [] as $r) {
            $days[] = ['day' => (string) ($r['day'] ?? ''), 'count' => (int) ($r['c'] ?? 0)];
        }

        return ['by_type' => $types, 'by_day' => $days];
    }

    /** @return array{avg_hours:float,overdue_open:int,samples:int} */
    public function responseDelays(?int $ownerUserId = null, ?string $dateFrom = null, ?string $dateTo = null): array
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
        $tasks = (new CrmTask())->query(
            "SELECT created_at, completed_at, due_at, status FROM rateb_crm_tasks
             WHERE company_id = :cid AND deleted_at IS NULL
               AND created_at BETWEEN :from AND :to {$ownerFilter}
             LIMIT 300",
            $params
        );
        $hours = [];
        $overdue = 0;
        foreach (is_array($tasks) ? $tasks : [] as $t) {
            if (!empty($t['created_at']) && !empty($t['completed_at'])) {
                $hours[] = max(0, (strtotime((string) $t['completed_at']) - strtotime((string) $t['created_at'])) / 3600);
            }
            if (($t['status'] ?? '') === 'open' && !empty($t['due_at']) && strtotime((string) $t['due_at']) < time()) {
                ++$overdue;
            }
        }

        return [
            'avg_hours' => $hours !== [] ? round(array_sum($hours) / count($hours), 1) : 0.0,
            'overdue_open' => $overdue,
            'samples' => count($hours),
        ];
    }

    /** @return array{active_opps:int,touched_opps:int,engagement_rate:float} */
    public function salesEngagement(?int $ownerUserId = null, ?string $dateFrom = null, ?string $dateTo = null): array
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
        $active = (int) (((new CrmOpportunity())->queryOne(
            "SELECT COUNT(*) AS c FROM rateb_crm_opportunities
             WHERE company_id = :cid AND deleted_at IS NULL AND workflow_status = 'open' {$ownerFilter}",
            $params
        )['c'] ?? 0));
        $touched = (int) (((new CrmOpportunity())->queryOne(
            "SELECT COUNT(DISTINCT o.id) AS c
             FROM rateb_crm_opportunities o
             INNER JOIN rateb_crm_activities a ON a.opportunity_id = o.id AND a.deleted_at IS NULL
               AND COALESCE(a.activity_at, a.created_at) BETWEEN :from AND :to
             WHERE o.company_id = :cid AND o.deleted_at IS NULL AND o.workflow_status = 'open'"
            . ($ownerUserId !== null && $ownerUserId > 0 ? ' AND o.owner_user_id = :uid' : ''),
            $params
        )['c'] ?? 0));
        $rate = $active > 0 ? round(($touched / $active) * 100, 1) : 0.0;

        return ['active_opps' => $active, 'touched_opps' => $touched, 'engagement_rate' => $rate];
    }

    /** @return list<array<string, mixed>> */
    public function repEffectiveness(?string $dateFrom = null, ?string $dateTo = null): array
    {
        try {
            $perf = (new CrmSalesPerformanceService())->repProductivity($dateFrom, $dateTo);
        } catch (\Throwable $e) {
            $perf = (new CrmAnalyticsService())->repPerformance();
        }
        if (!is_array($perf)) {
            return [];
        }
        // Normalize list shape
        if (isset($perf[0]) && is_array($perf[0])) {
            return $perf;
        }
        if (isset($perf['reps']) && is_array($perf['reps'])) {
            return $perf['reps'];
        }

        return [];
    }
}
