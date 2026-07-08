<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories;

use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\WebhookSubscriptionWriteRepositoryInterface;

final class MysqlWebhookSubscriptionWriteRepository extends BaseRepository implements WebhookSubscriptionWriteRepositoryInterface
{
    protected function table(): string
    {
        return 'webhook_subscriptions';
    }

    public function create(?int $erpCompanyId, string $url, string $secretEncrypted, array $events): string
    {
        $uuid = $this->newUuid();
        $this->writePdo->prepare(
            'INSERT INTO webhook_subscriptions
             (uuid, erp_company_id, url, secret_encrypted, events, is_active)
             VALUES (:uuid, :erp_company_id, :url, :secret_encrypted, :events, 1)'
        )->execute([
            'uuid' => $uuid,
            'erp_company_id' => $erpCompanyId,
            'url' => $url,
            'secret_encrypted' => $secretEncrypted,
            'events' => json_encode(array_values($events), JSON_UNESCAPED_UNICODE) ?: '[]',
        ]);

        return $uuid;
    }

    public function update(
        string $uuid,
        ?int $erpCompanyId,
        string $url,
        string $secretEncrypted,
        array $events,
        bool $isActive
    ): bool {
        $stmt = $this->writePdo->prepare(
            'UPDATE webhook_subscriptions
             SET erp_company_id = :erp_company_id,
                 url = :url,
                 secret_encrypted = :secret_encrypted,
                 events = :events,
                 is_active = :is_active,
                 updated_at = CURRENT_TIMESTAMP(6)
             WHERE uuid = :uuid AND deleted_at IS NULL'
        );
        $stmt->execute([
            'uuid' => $uuid,
            'erp_company_id' => $erpCompanyId,
            'url' => $url,
            'secret_encrypted' => $secretEncrypted,
            'events' => json_encode(array_values($events), JSON_UNESCAPED_UNICODE) ?: '[]',
            'is_active' => (int) $isActive,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function delete(string $uuid): bool
    {
        $stmt = $this->writePdo->prepare(
            'UPDATE webhook_subscriptions
             SET deleted_at = CURRENT_TIMESTAMP(6), is_active = 0, updated_at = CURRENT_TIMESTAMP(6)
             WHERE uuid = :uuid AND deleted_at IS NULL'
        );
        $stmt->execute(['uuid' => $uuid]);

        return $stmt->rowCount() > 0;
    }
}
