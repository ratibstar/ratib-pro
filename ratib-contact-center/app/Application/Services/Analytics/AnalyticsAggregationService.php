<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Application\Services\Analytics;

use Ratib\ContactCenter\App\Core\Database;
use Ratib\ContactCenter\App\Core\Events\EventBus;
use Ratib\ContactCenter\App\Core\Events\EventType;

final class AnalyticsAggregationService
{
    public function aggregateDaily(int $tenantId, ?string $date = null): int
    {
        $metricDate = $date ?? gmdate('Y-m-d');
        $pdo = Database::connection();
        $metrics = [
            'calls_total' => $this->scalar($pdo, 'SELECT COUNT(*) FROM rcc_calls WHERE tenant_id = :tid AND DATE(started_at) = :d', $tenantId, $metricDate),
            'tickets_open' => $this->scalar($pdo, "SELECT COUNT(*) FROM rcc_tickets WHERE tenant_id = :tid AND status IN ('open','in_progress','pending')", $tenantId),
            'accounts_total' => $this->scalar($pdo, "SELECT COUNT(*) FROM rcc_accounts WHERE tenant_id = :tid AND status = 'active'", $tenantId),
            'contacts_total' => $this->scalar($pdo, 'SELECT COUNT(*) FROM rcc_contacts WHERE tenant_id = :tid AND deleted_at IS NULL', $tenantId),
        ];
        $count = 0;
        foreach ($metrics as $key => $value) {
            $pdo->prepare(
                'INSERT INTO rcc_metrics_daily (tenant_id, metric_date, metric_key, metric_value)
                 VALUES (:tid, :d, :key, :val)
                 ON DUPLICATE KEY UPDATE metric_value = VALUES(metric_value), updated_at = NOW()'
            )->execute(['tid' => $tenantId, 'd' => $metricDate, 'key' => $key, 'val' => $value]);
            $count++;
        }
        EventBus::instance()->emit([
            'type' => EventType::ANALYTICS_AGGREGATED,
            'tenant_id' => $tenantId,
            'payload' => ['date' => $metricDate, 'metrics' => $count],
        ]);
        return $count;
    }

    private function scalar(\PDO $pdo, string $sql, int $tenantId, ?string $date = null): float
    {
        $stmt = $pdo->prepare($sql);
        $params = ['tid' => $tenantId];
        if ($date !== null && str_contains($sql, ':d')) {
            $params['d'] = $date;
        }
        $stmt->execute($params);
        return (float) ($stmt->fetchColumn() ?: 0);
    }
}
