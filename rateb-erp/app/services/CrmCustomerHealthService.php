<?php

declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\CrmActivity;
use Rateb\App\Models\CrmHealthHistory;
use Rateb\App\Models\CrmScoreHistory;
use Rateb\App\Models\Customer;

/**
 * Phase 6 — Customer health score (no Subscription/Accounting).
 */
final class CrmCustomerHealthService
{
    /**
     * @return array{
     *   customer_id:int,
     *   activity_score:int,
     *   engagement_score:int,
     *   health_score:int,
     *   health_status:string,
     *   renewal_risk:string,
     *   last_interaction_at:?string
     * }
     */
    public function compute(int $customerId, bool $persist = true): array
    {
        $companyId = CrmSupport::requireCompanyId();
        $customer = (new Customer())->queryOne(
            'SELECT id, crm_activity_score, crm_engagement_score, crm_health_score, crm_health_status,
                    crm_renewal_risk, crm_renewal_due_at, crm_last_interaction_at, crm_at_risk
             FROM rateb_customers WHERE id = :id AND company_id = :cid LIMIT 1',
            ['id' => $customerId, 'cid' => $companyId]
        );
        if (!is_array($customer)) {
            throw new \RuntimeException('customer_not_found');
        }

        $retention = (new CrmRetentionService())->refreshCustomer($customerId);
        $activityScore = (int) ($retention['activity_score'] ?? 0);
        $lastAt = $retention['last_interaction_at'] ?? null;

        $recentTypes = (int) (((new CrmActivity())->queryOne(
            "SELECT COUNT(DISTINCT activity_type) AS c FROM rateb_crm_activities
             WHERE company_id = :cid AND customer_id = :cuid AND deleted_at IS NULL
               AND COALESCE(activity_at, created_at) >= DATE_SUB(NOW(), INTERVAL 60 DAY)",
            ['cid' => $companyId, 'cuid' => $customerId]
        )['c'] ?? 0));
        $engagement = min(100, $activityScore + ($recentTypes * 8));
        if ($lastAt !== null) {
            $days = (int) floor((time() - strtotime($lastAt)) / 86400);
            if ($days > 30) {
                $engagement = max(0, $engagement - 20);
            }
        }

        $renewalRisk = 'low';
        $due = $customer['crm_renewal_due_at'] ?? null;
        if ($due !== null && $due !== '') {
            $daysToRenewal = (int) floor((strtotime((string) $due) - time()) / 86400);
            if ($daysToRenewal < 0 || ($daysToRenewal <= 30 && $engagement < 40)) {
                $renewalRisk = 'high';
            } elseif ($daysToRenewal <= 45 && $engagement < 55) {
                $renewalRisk = 'medium';
            }
        } elseif (!empty($customer['crm_at_risk']) || $engagement < 30) {
            $renewalRisk = 'medium';
        }

        $health = (int) round(max(0, min(100, ($activityScore * 0.4) + ($engagement * 0.4)
            + ($renewalRisk === 'low' ? 20 : ($renewalRisk === 'medium' ? 10 : 0)))));
        $status = 'healthy';
        if ($health < 40 || $renewalRisk === 'high') {
            $status = 'critical';
        } elseif ($health < 60 || $renewalRisk === 'medium') {
            $status = 'watch';
        }

        $result = [
            'customer_id' => $customerId,
            'activity_score' => $activityScore,
            'engagement_score' => $engagement,
            'health_score' => $health,
            'health_status' => $status,
            'renewal_risk' => $renewalRisk,
            'last_interaction_at' => $lastAt,
        ];

        if ($persist) {
            $fromHealth = (string) ($customer['crm_health_score'] ?? '0');
            (new Customer())->update($customerId, [
                'crm_engagement_score' => $engagement,
                'crm_health_score' => $health,
                'crm_health_status' => $status,
                'crm_renewal_risk' => $renewalRisk,
            ]);
            if ($fromHealth !== (string) $health) {
                (new CrmScoreHistory())->create([
                    'public_uuid' => CrmSupport::uuidV4(),
                    'company_id' => $companyId,
                    'entity_type' => 'customer',
                    'entity_id' => $customerId,
                    'score_type' => 'health_score',
                    'from_value' => $fromHealth,
                    'to_value' => (string) $health,
                    'meta_json' => json_encode($result, JSON_UNESCAPED_UNICODE),
                    'created_by' => CrmSupport::userId(),
                ]);
            }
            try {
                (new CrmHealthHistory())->create([
                    'public_uuid' => CrmSupport::uuidV4(),
                    'company_id' => $companyId,
                    'customer_id' => $customerId,
                    'activity_score' => $activityScore,
                    'engagement_score' => $engagement,
                    'health_score' => $health,
                    'health_status' => $status,
                    'renewal_risk' => $renewalRisk,
                    'meta_json' => json_encode(['last_interaction_at' => $lastAt], JSON_UNESCAPED_UNICODE),
                    'created_by' => CrmSupport::userId(),
                ]);
            } catch (\Throwable $e) {
                // table may be absent pre-migrate
            }
            if (class_exists(AuditService::class)) {
                (new AuditService())->log('crm.health.score', 'customer', $customerId, $result);
            }
        }

        return $result;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function healthHistory(int $customerId, int $limit = 40): array
    {
        try {
            $rows = (new CrmHealthHistory())->query(
                'SELECT * FROM rateb_crm_health_history
                 WHERE company_id = :cid AND customer_id = :cuid
                 ORDER BY created_at DESC, id DESC LIMIT ' . max(1, min(100, $limit)),
                ['cid' => CrmSupport::requireCompanyId(), 'cuid' => $customerId]
            );

            return is_array($rows) ? $rows : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * @return array{trend:string,points:list<array{at:string,health_score:int,renewal_risk:string}>}
     */
    public function riskTrends(int $customerId): array
    {
        $history = array_reverse($this->healthHistory($customerId, 20));
        $points = [];
        foreach ($history as $h) {
            $points[] = [
                'at' => (string) ($h['created_at'] ?? ''),
                'health_score' => (int) ($h['health_score'] ?? 0),
                'renewal_risk' => (string) ($h['renewal_risk'] ?? 'low'),
            ];
        }
        $trend = 'stable';
        if (count($points) >= 2) {
            $first = $points[0]['health_score'];
            $last = $points[count($points) - 1]['health_score'];
            if ($last < $first - 5) {
                $trend = 'worsening';
            } elseif ($last > $first + 5) {
                $trend = 'improving';
            }
        }

        return ['trend' => $trend, 'points' => $points];
    }

    /**
     * @return list<array{at:string,type:string,subject:string}>
     */
    public function engagementTimeline(int $customerId, int $limit = 40): array
    {
        $rows = (new CrmActivity())->query(
            "SELECT COALESCE(activity_at, created_at) AS at_ts, activity_type, subject
             FROM rateb_crm_activities
             WHERE company_id = :cid AND customer_id = :cuid AND deleted_at IS NULL
             ORDER BY COALESCE(activity_at, created_at) DESC
             LIMIT " . max(1, min(100, $limit)),
            ['cid' => CrmSupport::requireCompanyId(), 'cuid' => $customerId]
        );
        $out = [];
        foreach (is_array($rows) ? $rows : [] as $r) {
            $out[] = [
                'at' => (string) ($r['at_ts'] ?? ''),
                'type' => (string) ($r['activity_type'] ?? ''),
                'subject' => (string) ($r['subject'] ?? ''),
            ];
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    public function intelligence(int $customerId): array
    {
        $health = $this->compute($customerId, true);

        return [
            'health' => $health,
            'health_history' => $this->healthHistory($customerId),
            'risk_trends' => $this->riskTrends($customerId),
            'engagement_timeline' => $this->engagementTimeline($customerId),
            'retention' => (new CrmRetentionService())->summary(),
        ];
    }
}
