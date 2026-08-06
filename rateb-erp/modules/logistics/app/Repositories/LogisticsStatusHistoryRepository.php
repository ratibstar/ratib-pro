<?php
declare(strict_types=1);

namespace Rateb\App\Logistics\Repositories;

use Rateb\App\Core\Model;
use Rateb\App\Logistics\Models\LogisticsStatusHistory;

final class LogisticsStatusHistoryRepository extends AbstractLogisticsRepository
{
    protected function newModel(): Model
    {
        return new LogisticsStatusHistory();
    }

    /** @return array<int, array<string, mixed>> */
    public function listForEntity(int $companyId, string $entityType, int $entityId, int $limit = 100): array
    {
        $rows = $this->listForCompany($companyId, 500, 0);
        $matched = array_values(array_filter(
            $rows,
            static fn(array $row): bool => (string) ($row['entity_type'] ?? '') === $entityType
                && (int) ($row['entity_id'] ?? 0) === $entityId
        ));
        usort($matched, static function (array $a, array $b): int {
            return strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? ''));
        });

        return array_slice($matched, 0, $limit);
    }
}
