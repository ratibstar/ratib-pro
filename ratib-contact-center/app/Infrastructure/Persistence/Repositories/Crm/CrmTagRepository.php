<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Infrastructure\Persistence\Repositories\Crm;

use Ratib\ContactCenter\App\Core\Database;

final class CrmTagRepository
{
    /** @return list<array<string, mixed>> */
    public function listForContact(int $tenantId, int $contactId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM rcc_contact_tags WHERE tenant_id = :tid AND contact_id = :cid ORDER BY tag'
        );
        $stmt->execute(['tid' => $tenantId, 'cid' => $contactId]);
        return $stmt->fetchAll() ?: [];
    }

    public function add(int $tenantId, int $contactId, string $tag, ?int $userId, ?string $color = null): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT IGNORE INTO rcc_contact_tags (tenant_id, contact_id, tag, color, created_by_user_id) VALUES (:tid, :cid, :tag, :color, :uid)'
        );
        $stmt->execute(['tid' => $tenantId, 'cid' => $contactId, 'tag' => $tag, 'color' => $color, 'uid' => $userId]);
    }

    public function remove(int $tenantId, int $contactId, string $tag): bool
    {
        $stmt = Database::connection()->prepare(
            'DELETE FROM rcc_contact_tags WHERE tenant_id = :tid AND contact_id = :cid AND tag = :tag'
        );
        $stmt->execute(['tid' => $tenantId, 'cid' => $contactId, 'tag' => $tag]);
        return $stmt->rowCount() > 0;
    }
}
