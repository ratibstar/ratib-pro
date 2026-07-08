<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories;

use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\WebhookSubscriptionReadRepositoryInterface;

final class MysqlWebhookSubscriptionReadRepository extends BaseRepository implements WebhookSubscriptionReadRepositoryInterface
{
    protected function table(): string
    {
        return 'webhook_subscriptions';
    }

    public function findActiveForEvent(string $eventType, ?int $erpCompanyId): array
    {
        $where = [
            'ws.is_active = 1',
            'ws.deleted_at IS NULL',
            'JSON_CONTAINS(ws.events, :event_json)',
        ];
        $params = ['event_json' => json_encode($eventType, JSON_UNESCAPED_UNICODE)];

        if ($erpCompanyId !== null) {
            $where[] = '(ws.erp_company_id IS NULL OR ws.erp_company_id = :erp_company_id)';
            $params['erp_company_id'] = $erpCompanyId;
        }

        $rows = $this->fetchAll(
            'SELECT ws.id, ws.uuid, ws.erp_company_id, ws.url, ws.secret_encrypted, ws.events,
                    ws.is_active, ws.created_at, ws.updated_at
             FROM webhook_subscriptions ws
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY ws.id ASC',
            $params
        );

        return array_map(fn (array $row): array => $this->decodeEvents($row), $rows);
    }

    public function findByUuid(string $uuid): ?array
    {
        $row = $this->fetchOne(
            'SELECT uuid, erp_company_id, url, secret_encrypted, events, is_active,
                    created_at, updated_at, created_by, updated_by
             FROM webhook_subscriptions
             WHERE uuid = :uuid AND deleted_at IS NULL
             LIMIT 1',
            ['uuid' => $uuid]
        );
        if ($row === null) {
            return null;
        }

        return $this->decodeEvents($row);
    }

    public function list(int $limit, int $offset): array
    {
        $limit = max(1, min(200, $limit));
        $offset = max(0, $offset);

        $rows = $this->fetchAll(
            'SELECT uuid, erp_company_id, url, events, is_active, created_at, updated_at
             FROM webhook_subscriptions
             WHERE deleted_at IS NULL
             ORDER BY id DESC
             LIMIT ' . $limit . ' OFFSET ' . $offset
        );

        return array_map(fn (array $row): array => $this->decodeEvents($row), $rows);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function decodeEvents(array $row): array
    {
        $decoded = json_decode((string) ($row['events'] ?? '[]'), true);
        $row['events'] = is_array($decoded) ? $decoded : [];

        return $row;
    }
}
