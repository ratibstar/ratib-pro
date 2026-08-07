<?php

declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\CrmActivity;
use Rateb\App\Models\Customer;

/**
 * Phase 5 — Customer retention indicators (no Subscription/Accounting logic).
 */
final class CrmRetentionService
{
    /**
     * Recompute activity score, last interaction, and at-risk flag for one customer.
     *
     * @return array{activity_score:int,last_interaction_at:?string,at_risk:bool}
     */
    public function refreshCustomer(int $customerId): array
    {
        $companyId = CrmSupport::requireCompanyId();
        $customer = (new Customer())->queryOne(
            'SELECT id, crm_renewal_due_at FROM rateb_customers WHERE id = :id AND company_id = :cid LIMIT 1',
            ['id' => $customerId, 'cid' => $companyId]
        );
        if (!is_array($customer)) {
            throw new \RuntimeException('customer_not_found');
        }

        $last = (new CrmActivity())->queryOne(
            "SELECT MAX(COALESCE(activity_at, created_at)) AS last_at
             FROM rateb_crm_activities
             WHERE company_id = :cid AND customer_id = :cuid AND deleted_at IS NULL",
            ['cid' => $companyId, 'cuid' => $customerId]
        );
        $lastAt = !empty($last['last_at']) ? (string) $last['last_at'] : null;

        $recent = (int) (((new CrmActivity())->queryOne(
            "SELECT COUNT(*) AS c FROM rateb_crm_activities
             WHERE company_id = :cid AND customer_id = :cuid AND deleted_at IS NULL
               AND COALESCE(activity_at, created_at) >= DATE_SUB(NOW(), INTERVAL 90 DAY)",
            ['cid' => $companyId, 'cuid' => $customerId]
        )['c'] ?? 0));

        $score = min(100, $recent * 8);
        if ($lastAt !== null) {
            $days = (int) floor((time() - strtotime($lastAt)) / 86400);
            if ($days <= 7) {
                $score = min(100, $score + 20);
            } elseif ($days > 45) {
                $score = max(0, $score - 25);
            }
        } else {
            $score = max(0, $score - 15);
        }

        $renewalDue = $customer['crm_renewal_due_at'] ?? null;
        $renewalSoon = $renewalDue !== null && $renewalDue !== ''
            && (string) $renewalDue <= date('Y-m-d', strtotime('+30 days'));
        $inactive = $lastAt === null || (strtotime($lastAt) < strtotime('-30 days'));
        $atRisk = ($score < 35 && $inactive) || ($renewalSoon && $score < 50);

        (new Customer())->update($customerId, [
            'crm_last_interaction_at' => $lastAt,
            'crm_activity_score' => $score,
            'crm_at_risk' => $atRisk ? 1 : 0,
        ]);

        if ($atRisk) {
            try {
                (new CrmLifecycleService())->ensureAtLeast($customerId, 'retention', 'at_risk_indicator');
            } catch (\Throwable $e) {
                // best-effort
            }
        }

        return [
            'activity_score' => $score,
            'last_interaction_at' => $lastAt,
            'at_risk' => $atRisk,
        ];
    }

    /**
     * @param array<string, mixed> $input renewal_due_at
     */
    public function setRenewal(int $customerId, array $input): void
    {
        $due = CrmSupport::nullIfEmpty($input['crm_renewal_due_at'] ?? $input['renewal_due_at'] ?? null);
        (new Customer())->update($customerId, [
            'crm_renewal_due_at' => $due,
        ]);
        if ($due !== null) {
            (new CrmLifecycleService())->ensureAtLeast($customerId, 'renewal', 'renewal_tracking');
        }
        if (class_exists(AuditService::class)) {
            (new AuditService())->log('crm.retention.renewal', 'customer', $customerId, [
                'crm_renewal_due_at' => $due,
            ]);
        }
        $this->refreshCustomer($customerId);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function atRiskCustomers(int $limit = 50): array
    {
        $safe = max(1, min(100, $limit));
        $rows = (new Customer())->query(
            "SELECT id, code, name, crm_lifecycle_stage, crm_activity_score, crm_last_interaction_at,
                    crm_renewal_due_at, crm_at_risk, crm_owner_user_id
             FROM rateb_customers
             WHERE company_id = :cid AND crm_at_risk = 1
             ORDER BY crm_activity_score ASC, crm_last_interaction_at ASC
             LIMIT {$safe}",
            ['cid' => CrmSupport::requireCompanyId()]
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function renewalsDue(int $daysAhead = 30, int $limit = 50): array
    {
        $safe = max(1, min(100, $limit));
        $horizon = date('Y-m-d', strtotime('+' . max(0, $daysAhead) . ' days'));
        $rows = (new Customer())->query(
            "SELECT id, code, name, crm_lifecycle_stage, crm_renewal_due_at, crm_owner_user_id, crm_activity_score
             FROM rateb_customers
             WHERE company_id = :cid AND crm_renewal_due_at IS NOT NULL
               AND crm_renewal_due_at <= :horizon
             ORDER BY crm_renewal_due_at ASC
             LIMIT {$safe}",
            ['cid' => CrmSupport::requireCompanyId(), 'horizon' => $horizon]
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return array{at_risk:int,renewals_due:int,avg_activity_score:float}
     */
    public function summary(): array
    {
        $companyId = CrmSupport::requireCompanyId();
        $atRisk = (int) (((new Customer())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_customers WHERE company_id = :cid AND crm_at_risk = 1',
            ['cid' => $companyId]
        )['c'] ?? 0));
        $renewals = (int) (((new Customer())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_customers
             WHERE company_id = :cid AND crm_renewal_due_at IS NOT NULL
               AND crm_renewal_due_at <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)',
            ['cid' => $companyId]
        )['c'] ?? 0));
        $avg = (float) (((new Customer())->queryOne(
            'SELECT AVG(crm_activity_score) AS a FROM rateb_customers WHERE company_id = :cid',
            ['cid' => $companyId]
        )['a'] ?? 0));

        return [
            'at_risk' => $atRisk,
            'renewals_due' => $renewals,
            'avg_activity_score' => round($avg, 1),
        ];
    }
}
