<?php

declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\CrmActivity;
use Rateb\App\Models\CrmOpportunity;
use Rateb\App\Models\CrmScoreHistory;
use Rateb\App\Models\CrmTask;

/**
 * Phase 6 — Opportunity intelligence (internal rules only, no external AI).
 */
final class CrmOpportunityIntelligenceService
{
    /**
     * @return array{
     *   opportunity_id:int,
     *   intelligence_score:int,
     *   engagement_score:int,
     *   recommended_probability:float,
     *   risk_level:string,
     *   is_stale:bool,
     *   indicators:list<string>
     * }
     */
    public function score(int $opportunityId, bool $persist = true): array
    {
        $companyId = CrmSupport::requireCompanyId();
        $opp = (new CrmOpportunity())->queryOne(
            'SELECT * FROM rateb_crm_opportunities
             WHERE id = :id AND company_id = :cid AND deleted_at IS NULL LIMIT 1',
            ['id' => $opportunityId, 'cid' => $companyId]
        );
        if (!is_array($opp)) {
            throw new \RuntimeException('opportunity_not_found');
        }

        $activityCount = (int) (((new CrmActivity())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_crm_activities
             WHERE company_id = :cid AND opportunity_id = :oid AND deleted_at IS NULL
               AND COALESCE(activity_at, created_at) >= DATE_SUB(NOW(), INTERVAL 30 DAY)',
            ['cid' => $companyId, 'oid' => $opportunityId]
        )['c'] ?? 0));
        $openTasks = (int) (((new CrmTask())->queryOne(
            "SELECT COUNT(*) AS c FROM rateb_crm_tasks
             WHERE company_id = :cid AND opportunity_id = :oid AND deleted_at IS NULL AND status = 'open'",
            ['cid' => $companyId, 'oid' => $opportunityId]
        )['c'] ?? 0));

        $prob = (float) ($opp['probability_percent'] ?? 0);
        $amount = (float) ($opp['amount'] ?? 0);
        $daysSinceUpdate = 0;
        if (!empty($opp['updated_at'])) {
            $daysSinceUpdate = max(0, (int) floor((time() - strtotime((string) $opp['updated_at'])) / 86400));
        }
        $stageDays = 0;
        if (!empty($opp['stage_entered_at'])) {
            $stageDays = max(0, (int) floor((time() - strtotime((string) $opp['stage_entered_at'])) / 86400));
        }

        $engagement = min(100, ($activityCount * 12) + ($openTasks > 0 ? 8 : 0));
        if ($daysSinceUpdate <= 3) {
            $engagement = min(100, $engagement + 15);
        } elseif ($daysSinceUpdate > 14) {
            $engagement = max(0, $engagement - 25);
        }

        $intelligence = (int) round(
            min(100, max(0, ($prob * 0.45) + ($engagement * 0.35) + (min(100, $amount > 0 ? 20 : 0) * 0.20)))
        );

        $indicators = [];
        $isStale = $daysSinceUpdate >= 14 || $stageDays >= 21;
        if ($isStale) {
            $indicators[] = 'stale_opportunity';
        }
        if ($activityCount === 0) {
            $indicators[] = 'no_recent_activity';
        }
        if ($openTasks > 3) {
            $indicators[] = 'task_backlog';
        }
        if ($prob >= 70 && $engagement < 30) {
            $indicators[] = 'high_prob_low_engagement';
        }

        $risk = 'low';
        if ($isStale || count($indicators) >= 3) {
            $risk = 'high';
        } elseif (count($indicators) >= 1 || $engagement < 35) {
            $risk = 'medium';
        }

        // Recommend probability toward engagement-adjusted mid-point (rules only).
        $recommended = round(max(5, min(95, ($prob * 0.6) + ($engagement * 0.4))), 2);
        if ($risk === 'high') {
            $recommended = round(max(5, $recommended - 15), 2);
        } elseif ($risk === 'low' && $engagement >= 60) {
            $recommended = round(min(95, $recommended + 5), 2);
        }

        $result = [
            'opportunity_id' => $opportunityId,
            'intelligence_score' => $intelligence,
            'engagement_score' => $engagement,
            'recommended_probability' => $recommended,
            'risk_level' => $risk,
            'is_stale' => $isStale,
            'indicators' => $indicators,
        ];

        if ($persist) {
            $fromIntel = (string) ($opp['intelligence_score'] ?? '0');
            $fromEng = (string) ($opp['engagement_score'] ?? '0');
            (new CrmOpportunity())->update($opportunityId, [
                'intelligence_score' => $intelligence,
                'engagement_score' => $engagement,
                'risk_level' => $risk,
                'recommended_probability' => $recommended,
                'is_stale' => $isStale ? 1 : 0,
                'score_updated_at' => date('Y-m-d H:i:s'),
            ]);
            $this->logScore('opportunity', $opportunityId, 'intelligence_score', $fromIntel, (string) $intelligence, $result);
            $this->logScore('opportunity', $opportunityId, 'engagement_score', $fromEng, (string) $engagement, [
                'risk_level' => $risk,
            ]);
            if (class_exists(AuditService::class)) {
                (new AuditService())->log('crm.intelligence.score', 'crm_opportunity', $opportunityId, $result);
            }
        }

        return $result;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function refreshOpen(int $limit = 50): array
    {
        $rows = (new CrmOpportunity())->query(
            "SELECT id FROM rateb_crm_opportunities
             WHERE company_id = :cid AND deleted_at IS NULL AND workflow_status = 'open'
             ORDER BY updated_at DESC LIMIT " . max(1, min(100, $limit)),
            ['cid' => CrmSupport::requireCompanyId()]
        );
        $out = [];
        foreach (is_array($rows) ? $rows : [] as $r) {
            try {
                $out[] = $this->score((int) $r['id'], true);
            } catch (\Throwable $e) {
                // skip
            }
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function staleOpportunities(int $limit = 40): array
    {
        $rows = (new CrmOpportunity())->query(
            "SELECT id, opportunity_no, name, amount, probability_percent, intelligence_score,
                    engagement_score, risk_level, is_stale, owner_user_id, updated_at
             FROM rateb_crm_opportunities
             WHERE company_id = :cid AND deleted_at IS NULL AND workflow_status = 'open'
               AND (is_stale = 1 OR risk_level = 'high')
             ORDER BY intelligence_score ASC, updated_at ASC
             LIMIT " . max(1, min(100, $limit)),
            ['cid' => CrmSupport::requireCompanyId()]
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function logScore(
        string $entityType,
        int $entityId,
        string $scoreType,
        ?string $from,
        string $to,
        array $meta = []
    ): void {
        if ($from === $to) {
            return;
        }
        (new CrmScoreHistory())->create([
            'public_uuid' => CrmSupport::uuidV4(),
            'company_id' => CrmSupport::requireCompanyId(),
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'score_type' => substr($scoreType, 0, 40),
            'from_value' => $from,
            'to_value' => substr($to, 0, 40),
            'meta_json' => $meta !== [] ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null,
            'created_by' => CrmSupport::userId(),
        ]);
    }
}
