<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Application\Services\Recordings;

use Ratib\ContactCenter\App\Application\Services\RccAuditService;
use Ratib\ContactCenter\App\Core\Database;
use Ratib\ContactCenter\App\Core\Events\EventBus;
use Ratib\ContactCenter\App\Core\Events\EventType;

final class RecordingService
{
    public function __construct(private readonly RccAuditService $audit = new RccAuditService())
    {
    }

    /** @param array<string, mixed> $data */
    public function ingest(int $tenantId, array $data): array
    {
        $uuid = (string) ($data['uuid'] ?? bin2hex(random_bytes(16)));
        $stmt = Database::connection()->prepare(
            'INSERT INTO rcc_recordings (tenant_id, uuid, call_id, conversation_id, contact_id, agent_id, ticket_id, channel, direction, caller_number, duration_seconds, file_path, file_size, mime_type, asterisk_uniqueid)
             VALUES (:tid, :uuid, :call, :conv, :contact, :agent, :ticket, :ch, :dir, :caller, :dur, :path, :size, :mime, :uid)'
        );
        $stmt->execute([
            'tid' => $tenantId,
            'uuid' => $uuid,
            'call' => $data['call_id'] ?? null,
            'conv' => $data['conversation_id'] ?? null,
            'contact' => $data['contact_id'] ?? null,
            'agent' => $data['agent_id'] ?? null,
            'ticket' => $data['ticket_id'] ?? null,
            'ch' => (string) ($data['channel'] ?? 'voice'),
            'dir' => (string) ($data['direction'] ?? 'inbound'),
            'caller' => $data['caller_number'] ?? null,
            'dur' => (int) ($data['duration_seconds'] ?? 0),
            'path' => (string) ($data['file_path'] ?? ''),
            'size' => (int) ($data['file_size'] ?? 0),
            'mime' => (string) ($data['mime_type'] ?? 'audio/wav'),
            'uid' => $data['asterisk_uniqueid'] ?? null,
        ]);
        $id = (int) Database::connection()->lastInsertId();
        EventBus::instance()->emit([
            'type' => EventType::RECORDING_INGESTED,
            'tenant_id' => $tenantId,
            'payload' => ['recording_id' => $id],
        ]);
        return $this->find($tenantId, $id) ?? [];
    }

    /** @return array<string, mixed>|null */
    public function find(int $tenantId, int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM rcc_recordings WHERE tenant_id = :tid AND id = :id LIMIT 1');
        $stmt->execute(['tid' => $tenantId, 'id' => $id]);
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
    }

    /** @return list<array<string, mixed>> */
    public function search(int $tenantId, ?string $query = null, ?int $contactId = null, int $limit = 50): array
    {
        $sql = 'SELECT * FROM rcc_recordings WHERE tenant_id = :tid';
        $params = ['tid' => $tenantId];
        if ($contactId !== null && $contactId > 0) {
            $sql .= ' AND contact_id = :cid';
            $params['cid'] = $contactId;
        }
        if ($query !== null && $query !== '') {
            $sql .= ' AND (caller_number LIKE :q OR uuid LIKE :q OR asterisk_uniqueid LIKE :q)';
            $params['q'] = '%' . $query . '%';
        }
        $sql .= ' ORDER BY created_at DESC LIMIT ' . max(1, min(200, $limit));
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll() ?: [];
    }

    public function linkToQa(int $tenantId, int $recordingId, int $qaReviewId, ?int $userId): void
    {
        Database::connection()->prepare(
            'INSERT INTO rcc_recording_reviews (tenant_id, recording_id, qa_review_id, reviewer_user_id)
             VALUES (:tid, :rid, :qa, :uid)'
        )->execute(['tid' => $tenantId, 'rid' => $recordingId, 'qa' => $qaReviewId, 'uid' => $userId]);
        $this->audit->log($tenantId, 'recording.link.qa', $userId, 'recording', $recordingId, ['qa_review_id' => $qaReviewId]);
    }
}
