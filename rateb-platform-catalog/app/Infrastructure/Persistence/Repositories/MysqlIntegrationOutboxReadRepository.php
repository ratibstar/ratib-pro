<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories;

use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\IntegrationOutboxReadRepositoryInterface;

final class MysqlIntegrationOutboxReadRepository extends BaseRepository implements IntegrationOutboxReadRepositoryInterface
{
    protected function table(): string
    {
        return 'integration_outbox';
    }

    public function fetchPending(int $limit): array
    {
        $limit = max(1, min(200, $limit));
        $rows = $this->fetchAll(
            'SELECT event_id, event_type, entity_type, entity_uuid, erp_company_id,
                    payload, status, attempts, next_attempt_at, created_at
             FROM integration_outbox
             WHERE status = :status AND next_attempt_at <= CURRENT_TIMESTAMP(6)
             ORDER BY next_attempt_at ASC, id ASC
             LIMIT ' . $limit,
            ['status' => 'pending']
        );

        return array_map(function (array $row): array {
            $decoded = json_decode((string) ($row['payload'] ?? '{}'), true);
            $row['payload'] = is_array($decoded) ? $decoded : [];

            return $row;
        }, $rows);
    }
}
