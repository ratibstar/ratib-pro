<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Application\Services\Billing;

use Ratib\ContactCenter\App\Application\Services\RccAuditService;
use Ratib\ContactCenter\App\Core\Database;
use Ratib\ContactCenter\App\Core\Events\EventBus;
use Ratib\ContactCenter\App\Core\Events\EventType;

final class UsageMeteringService
{
    public function __construct(private readonly RccAuditService $audit = new RccAuditService())
    {
    }

    public function record(int $tenantId, string $metricKey, float $quantity, ?string $date = null, string $unit = 'count'): void
    {
        $day = $date ?? date('Y-m-d');
        Database::connection()->prepare(
            'INSERT INTO rcc_usage_metrics (tenant_id, metric_key, metric_date, quantity, unit)
             VALUES (:tid, :key, :day, :qty, :unit)
             ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)'
        )->execute([
            'tid' => $tenantId,
            'key' => $metricKey,
            'day' => $day,
            'qty' => $quantity,
            'unit' => $unit,
        ]);
        EventBus::instance()->emit([
            'type' => EventType::BILLING_USAGE_RECORDED,
            'tenant_id' => $tenantId,
            'payload' => ['metric_key' => $metricKey, 'quantity' => $quantity],
        ]);
    }

    /** @return list<array<string, mixed>> */
    public function summary(int $tenantId, ?string $from = null, ?string $to = null): array
    {
        $from = $from ?? date('Y-m-01');
        $to = $to ?? date('Y-m-d');
        $stmt = Database::connection()->prepare(
            'SELECT metric_key, SUM(quantity) AS total_qty, unit
             FROM rcc_usage_metrics
             WHERE tenant_id = :tid AND metric_date BETWEEN :f AND :t
             GROUP BY metric_key, unit'
        );
        $stmt->execute(['tid' => $tenantId, 'f' => $from, 't' => $to]);
        return $stmt->fetchAll() ?: [];
    }
}
