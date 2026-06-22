<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Application\Services\Crm;

use Ratib\ContactCenter\App\Application\Services\RccAuditService;
use Ratib\ContactCenter\App\Core\Database;
use Ratib\ContactCenter\App\Core\Events\EventBus;
use Ratib\ContactCenter\App\Core\Events\EventType;
use Ratib\ContactCenter\App\Infrastructure\Persistence\Repositories\Crm\CrmNoteRepository;

final class CustomerActivityService
{
    public function __construct(
        private readonly CrmNoteRepository $notes = new CrmNoteRepository(),
        private readonly RccAuditService $audit = new RccAuditService()
    ) {
    }

    public function addNote(int $tenantId, int $contactId, string $body, ?int $userId): int
    {
        $id = $this->notes->add($tenantId, $contactId, $body, $userId);
        $this->audit->log($tenantId, 'crm.note.add', $userId, 'contact_note', $id);
        EventBus::instance()->emit([
            'type' => EventType::CRM_NOTE_ADDED,
            'tenant_id' => $tenantId,
            'payload' => ['contact_id' => $contactId, 'note_id' => $id],
        ]);
        return $id;
    }

    /** @return list<array<string, mixed>> */
    public function listNotes(int $tenantId, int $contactId): array
    {
        return $this->notes->list($tenantId, $contactId);
    }

    /** @param array<string, mixed> $meta */
    public function uploadDocument(int $tenantId, int $contactId, string $fileName, string $mime, int $size, string $storagePath, ?int $userId, ?array $meta = null): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO rcc_contact_documents (tenant_id, contact_id, account_id, file_name, mime_type, file_size, storage_path, uploaded_by_user_id)
             VALUES (:tid, :cid, :aid, :fn, :mime, :sz, :path, :uid)'
        );
        $stmt->execute([
            'tid' => $tenantId,
            'cid' => $contactId,
            'aid' => $meta['account_id'] ?? null,
            'fn' => $fileName,
            'mime' => $mime,
            'sz' => $size,
            'path' => $storagePath,
            'uid' => $userId,
        ]);
        $id = (int) Database::connection()->lastInsertId();
        $this->audit->log($tenantId, 'crm.document.upload', $userId, 'contact_document', $id);
        EventBus::instance()->emit([
            'type' => EventType::CRM_DOCUMENT_UPLOADED,
            'tenant_id' => $tenantId,
            'payload' => ['contact_id' => $contactId, 'document_id' => $id],
        ]);
        return $id;
    }

    /** @return list<array<string, mixed>> */
    public function listDocuments(int $tenantId, int $contactId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, file_name, mime_type, file_size, created_at FROM rcc_contact_documents
             WHERE tenant_id = :tid AND contact_id = :cid ORDER BY created_at DESC'
        );
        $stmt->execute(['tid' => $tenantId, 'cid' => $contactId]);
        return $stmt->fetchAll() ?: [];
    }
}
