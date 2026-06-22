<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Infrastructure\Persistence\Repositories\Crm;

use Ratib\ContactCenter\App\Core\Database;

final class CrmNoteRepository
{
    /** @return list<array<string, mixed>> */
    public function list(int $tenantId, int $contactId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM rcc_contact_notes WHERE tenant_id = :tid AND contact_id = :cid ORDER BY is_pinned DESC, created_at DESC'
        );
        $stmt->execute(['tid' => $tenantId, 'cid' => $contactId]);
        return $stmt->fetchAll() ?: [];
    }

    public function add(int $tenantId, int $contactId, string $body, ?int $userId, ?int $accountId = null): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO rcc_contact_notes (tenant_id, contact_id, account_id, author_user_id, body) VALUES (:tid, :cid, :aid, :uid, :body)'
        );
        $stmt->execute(['tid' => $tenantId, 'cid' => $contactId, 'aid' => $accountId, 'uid' => $userId, 'body' => $body]);
        return (int) Database::connection()->lastInsertId();
    }
}
