<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Infrastructure\Persistence\Repositories\Crm;

use Ratib\ContactCenter\App\Core\Database;

final class CrmActivityRepository
{
    public function add(
        int $tenantId,
        int $contactId,
        string $activityType,
        string $title,
        ?int $accountId = null,
        ?string $channel = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $summary = null,
        ?array $payload = null
    ): int {
        $stmt = Database::connection()->prepare(
            'INSERT INTO rcc_contact_activities
             (tenant_id, contact_id, account_id, activity_type, channel, reference_type, reference_id, title, summary, payload_json, occurred_at)
             VALUES (:tid, :cid, :aid, :type, :ch, :rtype, :rid, :title, :summary, :payload, NOW())'
        );
        $stmt->execute([
            'tid' => $tenantId,
            'cid' => $contactId,
            'aid' => $accountId,
            'type' => $activityType,
            'ch' => $channel,
            'rtype' => $referenceType,
            'rid' => $referenceId,
            'title' => $title,
            'summary' => $summary,
            'payload' => $payload !== null ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null,
        ]);
        return (int) Database::connection()->lastInsertId();
    }

    /** @return list<array<string, mixed>> */
    public function timeline(int $tenantId, int $contactId, int $limit = 100): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM rcc_contact_activities WHERE tenant_id = :tid AND contact_id = :cid
             ORDER BY occurred_at DESC LIMIT ' . max(1, min(500, $limit))
        );
        $stmt->execute(['tid' => $tenantId, 'cid' => $contactId]);
        return $stmt->fetchAll() ?: [];
    }

    /** @return list<array<string, mixed>> */
    public function interactionHistory(int $tenantId, int $contactId): array
    {
        $pdo = Database::connection();
        $items = [];

        $calls = $pdo->prepare(
            "SELECT 'call' AS kind, c.id, c.direction, c.status, c.caller_number, c.started_at AS occurred_at
             FROM rcc_calls c
             INNER JOIN rcc_contacts ct ON ct.phone_primary = c.caller_number AND ct.tenant_id = c.tenant_id
             WHERE c.tenant_id = :tid AND ct.id = :cid ORDER BY c.started_at DESC LIMIT 50"
        );
        $calls->execute(['tid' => $tenantId, 'cid' => $contactId]);
        foreach ($calls->fetchAll() ?: [] as $row) {
            $items[] = $row;
        }

        $conv = $pdo->prepare(
            'SELECT \'conversation\' AS kind, cv.id, cv.channel, cv.status, cv.subject, cv.updated_at AS occurred_at
             FROM rcc_conversations cv
             WHERE cv.tenant_id = :tid AND cv.contact_id = :cid ORDER BY cv.updated_at DESC LIMIT 50'
        );
        $conv->execute(['tid' => $tenantId, 'cid' => $contactId]);
        foreach ($conv->fetchAll() ?: [] as $row) {
            $items[] = $row;
        }

        $tickets = $pdo->prepare(
            'SELECT \'ticket\' AS kind, t.id, t.ticket_no, t.subject, t.status, t.created_at AS occurred_at
             FROM rcc_tickets t WHERE t.tenant_id = :tid AND t.contact_id = :cid ORDER BY t.created_at DESC LIMIT 50'
        );
        $tickets->execute(['tid' => $tenantId, 'cid' => $contactId]);
        foreach ($tickets->fetchAll() ?: [] as $row) {
            $items[] = $row;
        }

        usort($items, static fn ($a, $b) => strcmp((string) ($b['occurred_at'] ?? ''), (string) ($a['occurred_at'] ?? '')));
        return array_slice($items, 0, 100);
    }
}
