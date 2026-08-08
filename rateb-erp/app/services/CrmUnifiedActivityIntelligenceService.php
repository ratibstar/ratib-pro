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
        $safe = static function (callable $fn, mixed $fallback): mixed {
            try {
                return $fn();
            } catch (\Throwable $e) {
                return $fallback;
            }
        };
        $base = $safe(
            static fn () => (new CrmActivityIntelligenceService())->analyze($ownerUserId, $dateFrom, $dateTo),
            [
                'date_from' => $dateFrom ?: date('Y-m-d', strtotime('-30 days')),
                'date_to' => $dateTo ?: date('Y-m-d'),
                'owner_user_id' => $ownerUserId,
                'activity_count' => 0,
                'avg_response_hours' => 0.0,
                'avg_follow_up_delay_hours' => 0.0,
                'conversion_impact_pct' => 0.0,
                'activity_effectiveness_pct' => 0.0,
                'won_with_activity' => 0,
                'won_total' => 0,
                'conversions' => 0,
                'tasks_in_period' => 0,
            ]
        );
        $patterns = $safe(fn () => $this->activityPatterns($ownerUserId, $dateFrom, $dateTo), ['by_type' => [], 'by_day' => []]);
        $delays = $safe(fn () => $this->responseDelays($ownerUserId, $dateFrom, $dateTo), ['avg_hours' => 0.0, 'overdue_open' => 0, 'samples' => 0]);
        $engagement = $safe(fn () => $this->salesEngagement($ownerUserId, $dateFrom, $dateTo), ['active_opps' => 0, 'touched_opps' => 0, 'engagement_rate' => 0.0]);
        $reps = $safe(fn () => $this->repEffectiveness($dateFrom, $dateTo), []);

        $result = array_merge(is_array($base) ? $base : [], [
            'activity_patterns' => $patterns,
            'response_delays' => $delays,
            'sales_engagement' => $engagement,
            'rep_effectiveness' => $reps,
        ]);
        if (class_exists(AuditService::class)) {
            try {
                (new AuditService())->log('crm.intelligence.calculate', 'crm_activity_intelligence', 0, [
                    'owner_user_id' => $ownerUserId,
                    'activity_count' => (int) ($result['activity_count'] ?? 0),
                ]);
            } catch (\Throwable $e) {
                // never block the page on audit
            }
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
        // Native PDO rejects unused named params (HY093) — bind only placeholders present in each SQL.
        $activeParams = ['cid' => $companyId];
        $ownerFilter = '';
        if ($ownerUserId !== null && $ownerUserId > 0) {
            $ownerFilter = ' AND owner_user_id = :uid';
            $activeParams['uid'] = $ownerUserId;
        }
        $active = (int) (((new CrmOpportunity())->queryOne(
            "SELECT COUNT(*) AS c FROM rateb_crm_opportunities
             WHERE company_id = :cid AND deleted_at IS NULL AND workflow_status = 'open' {$ownerFilter}",
            $activeParams
        )['c'] ?? 0));

        $touchedParams = [
            'cid' => $companyId,
            'from' => $from . ' 00:00:00',
            'to' => $to . ' 23:59:59',
        ];
        $touchedOwner = '';
        if ($ownerUserId !== null && $ownerUserId > 0) {
            $touchedOwner = ' AND o.owner_user_id = :uid';
            $touchedParams['uid'] = $ownerUserId;
        }
        $touched = (int) (((new CrmOpportunity())->queryOne(
            "SELECT COUNT(DISTINCT o.id) AS c
             FROM rateb_crm_opportunities o
             INNER JOIN rateb_crm_activities a ON a.opportunity_id = o.id AND a.deleted_at IS NULL
               AND COALESCE(a.activity_at, a.created_at) BETWEEN :from AND :to
             WHERE o.company_id = :cid AND o.deleted_at IS NULL AND o.workflow_status = 'open'"
            . $touchedOwner,
            $touchedParams
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
