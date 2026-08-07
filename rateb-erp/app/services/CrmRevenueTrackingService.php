<?php

declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\CrmRevenueEvent;

/**
 * Phase 4 — CRM revenue tracking layer (no invoice creation).
 * Flow marker: Lead → Opportunity → Quotation → Customer → Revenue event.
 */
final class CrmRevenueTrackingService
{
    /**
     * @param array<string, mixed> $links
     * @param array<string, mixed> $meta
     * @return array{id: int}
     */
    public function record(
        string $eventType,
        float $amount,
        string $currency = 'SAR',
        array $links = [],
        array $meta = []
    ): array {
        $companyId = CrmSupport::requireCompanyId();
        $period = date('Y-m');
        $id = (new CrmRevenueEvent())->create([
            'public_uuid' => CrmSupport::uuidV4(),
            'company_id' => $companyId,
            'event_type' => substr(trim($eventType), 0, 60),
            'amount' => round($amount, 2),
            'currency_code' => substr(strtoupper($currency), 0, 3) ?: 'SAR',
            'lead_id' => CrmSupport::intOrNull($links['lead_id'] ?? null),
            'opportunity_id' => CrmSupport::intOrNull($links['opportunity_id'] ?? null),
            'quotation_id' => CrmSupport::intOrNull($links['quotation_id'] ?? null),
            'customer_id' => CrmSupport::intOrNull($links['customer_id'] ?? null),
            'period_key' => $period,
            'meta_json' => $meta !== [] ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null,
            'created_by' => CrmSupport::userId(),
        ]);

        (new CrmTimelineService())->record(
            'revenue_' . substr(trim($eventType), 0, 30),
            'Revenue tracked: ' . round($amount, 2) . ' ' . $currency,
            null,
            'customer',
            CrmSupport::intOrNull($links['customer_id'] ?? null),
            [
                'lead_id' => CrmSupport::intOrNull($links['lead_id'] ?? null),
                'opportunity_id' => CrmSupport::intOrNull($links['opportunity_id'] ?? null),
                'customer_id' => CrmSupport::intOrNull($links['customer_id'] ?? null),
            ]
        );

        if (class_exists(AuditService::class)) {
            (new AuditService())->log('crm.revenue.track', 'crm_revenue_event', (int) $id, [
                'event_type' => $eventType,
                'amount' => $amount,
                'period_key' => $period,
            ]);
        }

        return ['id' => (int) $id];
    }

    /**
     * @return array{total: float, by_period: list<array{period_key:string,total:float,count:int}>, items: list<array<string,mixed>>}
     */
    public function summary(?string $periodKey = null, int $limit = 50): array
    {
        $companyId = CrmSupport::requireCompanyId();
        $params = ['cid' => $companyId];
        $where = 'company_id = :cid';
        if ($periodKey !== null && $periodKey !== '') {
            $where .= ' AND period_key = :pk';
            $params['pk'] = substr($periodKey, 0, 7);
        }
        $byPeriod = (new CrmRevenueEvent())->query(
            'SELECT period_key, COALESCE(SUM(amount),0) AS total, COUNT(*) AS cnt
             FROM rateb_crm_revenue_events WHERE ' . $where . '
             GROUP BY period_key ORDER BY period_key DESC LIMIT 24',
            $params
        );
        $items = (new CrmRevenueEvent())->query(
            'SELECT * FROM rateb_crm_revenue_events WHERE ' . $where
            . ' ORDER BY created_at DESC, id DESC LIMIT ' . max(1, min(200, $limit)),
            $params
        );
        $total = 0.0;
        $periods = [];
        foreach (is_array($byPeriod) ? $byPeriod : [] as $row) {
            $amt = (float) ($row['total'] ?? 0);
            $total += $amt;
            $periods[] = [
                'period_key' => (string) ($row['period_key'] ?? ''),
                'total' => $amt,
                'count' => (int) ($row['cnt'] ?? 0),
            ];
        }

        return [
            'total' => round($total, 2),
            'by_period' => $periods,
            'items' => is_array($items) ? $items : [],
        ];
    }
}
