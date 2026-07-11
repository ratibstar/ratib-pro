<?php

declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\DmsAuditLog;
use Rateb\App\Models\DmsCategory;
use Rateb\App\Models\DmsComment;
use Rateb\App\Models\DmsDocument;
use Rateb\App\Models\DmsDocumentLink;
use Rateb\App\Models\DmsFavorite;
use Rateb\App\Models\DmsFolder;
use Rateb\App\Models\DmsLegalHold;
use Rateb\App\Models\DmsPermission;
use Rateb\App\Models\DmsRepository;
use Rateb\App\Models\DmsRetentionPolicy;
use Rateb\App\Models\DmsSearchIndex;
use Rateb\App\Models\DmsShare;
use Rateb\App\Models\DmsTag;
use Rateb\App\Models\DmsVersion;

/**
 * Phase 26A — Enterprise Document Management (DMS) Platform domain services (ONLINE).
 * Controllers must not embed business rules — call these services only.
 * Operates on rateb_dms_* — workflow_status changes via DocumentWorkflowService only.
 * Soft-links legacy_document_id only — never mutates rateb_documents or legacy upload pipeline.
 */

final class DocumentManagementEnterpriseService
{
    /** @return array<string, array<string, int>> */
    public function boardCounts(): array
    {
        $companyId = DocumentManagementSupport::requireCompanyId();
        $counts = [];
        foreach (DocumentWorkflowService::statuses(DocumentWorkflowService::ENTITY_DOCUMENT) as $st) {
            $row = (new DmsDocument())->queryOne(
                'SELECT COUNT(*) AS c FROM rateb_dms_documents'
                . ' WHERE company_id = :cid AND deleted_at IS NULL AND workflow_status = :st',
                ['cid' => $companyId, 'st' => $st]
            );
            $counts[$st] = (int) ($row['c'] ?? 0);
        }

        return ['document' => $counts];
    }
}

final class DmsRepositoryService
{
    /**
     * @return array{items: list<array<string,mixed>>, total: int}
     */
    public function list(int $limit = 25, int $offset = 0, string $search = ''): array
    {
        $companyId = DocumentManagementSupport::requireCompanyId();
        $safeLimit = max(1, min(100, $limit));
        $safeOffset = max(0, $offset);
        $params = ['cid' => $companyId];
        $where = 'company_id = :cid AND deleted_at IS NULL';
        if ($search !== '') {
            $where .= ' AND (name LIKE :q OR code LIKE :q2)';
            $like = '%' . $search . '%';
            $params['q'] = $like;
            $params['q2'] = $like;
        }
        $totalRow = (new DmsRepository())->queryOne('SELECT COUNT(*) AS c FROM rateb_dms_repositories WHERE ' . $where, $params);
        $items = (new DmsRepository())->query(
            'SELECT * FROM rateb_dms_repositories WHERE ' . $where
            . ' ORDER BY updated_at DESC, id DESC LIMIT ' . $safeLimit . ' OFFSET ' . $safeOffset,
            $params
        );

        return ['items' => is_array($items) ? $items : [], 'total' => (int) ($totalRow['c'] ?? 0)];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int, code: string}
     */
    public function create(array $input): array
    {
        $companyId = DocumentManagementSupport::requireCompanyId();
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('name_required');
        }
        $code = trim((string) ($input['code'] ?? ''));
        if ($code === '') {
            $code = DocumentManagementSupport::nextCode('rateb_dms_repositories', 'DMS-REPO', $companyId);
        }
        $id = (new DmsRepository())->create(array_merge([
            'public_uuid' => DocumentManagementSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => DocumentManagementSupport::branchId(),
            'code' => substr($code, 0, 40),
            'name' => substr($name, 0, 190),
            'name_ar' => DocumentManagementSupport::nullIfEmpty($input['name_ar'] ?? null),
            'description' => DocumentManagementSupport::nullIfEmpty($input['description'] ?? null),
            'storage_driver' => substr((string) ($input['storage_driver'] ?? 'local'), 0, 40),
            'status' => 'active',
            'notes' => DocumentManagementSupport::nullIfEmpty($input['notes'] ?? null),
            'version' => 1,
        ], DocumentManagementSupport::actorFields(true)));

        (new DocumentTimelineService())->record('repository_created', 'Repository: ' . $name, 'repository', (int) $id);

        return ['id' => (int) $id, 'code' => $code];
    }
}

final class DmsFolderService
{
    /**
     * @return array{items: list<array<string,mixed>>, total: int}
     */
    public function list(int $limit = 25, int $offset = 0, ?int $repositoryId = null): array
    {
        $companyId = DocumentManagementSupport::requireCompanyId();
        $safeLimit = max(1, min(100, $limit));
        $safeOffset = max(0, $offset);
        $params = ['cid' => $companyId];
        $where = 'company_id = :cid AND deleted_at IS NULL';
        if ($repositoryId !== null && $repositoryId > 0) {
            $where .= ' AND repository_id = :rid';
            $params['rid'] = $repositoryId;
        }
        $totalRow = (new DmsFolder())->queryOne('SELECT COUNT(*) AS c FROM rateb_dms_folders WHERE ' . $where, $params);
        $items = (new DmsFolder())->query(
            'SELECT * FROM rateb_dms_folders WHERE ' . $where
            . ' ORDER BY sort_order ASC, name ASC LIMIT ' . $safeLimit . ' OFFSET ' . $safeOffset,
            $params
        );

        return ['items' => is_array($items) ? $items : [], 'total' => (int) ($totalRow['c'] ?? 0)];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int, code: string}
     */
    public function create(array $input): array
    {
        $companyId = DocumentManagementSupport::requireCompanyId();
        $repositoryId = DocumentManagementSupport::intOrNull($input['repository_id'] ?? null);
        if ($repositoryId === null) {
            throw new \InvalidArgumentException('repository_required');
        }
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('name_required');
        }
        $code = trim((string) ($input['code'] ?? ''));
        if ($code === '') {
            $code = DocumentManagementSupport::nextCode('rateb_dms_folders', 'DMS-FLD', $companyId);
        }
        $id = (new DmsFolder())->create(array_merge([
            'public_uuid' => DocumentManagementSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => DocumentManagementSupport::branchId(),
            'repository_id' => $repositoryId,
            'parent_folder_id' => DocumentManagementSupport::intOrNull($input['parent_folder_id'] ?? null),
            'code' => substr($code, 0, 40),
            'name' => substr($name, 0, 190),
            'name_ar' => DocumentManagementSupport::nullIfEmpty($input['name_ar'] ?? null),
            'path_text' => DocumentManagementSupport::nullIfEmpty($input['path_text'] ?? null),
            'sort_order' => (int) ($input['sort_order'] ?? 0),
            'status' => 'active',
            'notes' => DocumentManagementSupport::nullIfEmpty($input['notes'] ?? null),
            'version' => 1,
        ], DocumentManagementSupport::actorFields(true)));

        (new DocumentTimelineService())->record('folder_created', 'Folder: ' . $name, 'folder', (int) $id);

        return ['id' => (int) $id, 'code' => $code];
    }
}

final class DmsDocumentService
{
    /**
     * @return array{items: list<array<string,mixed>>, total: int}
     */
    public function list(int $limit = 25, int $offset = 0, string $search = '', ?string $workflowStatus = null): array
    {
        $companyId = DocumentManagementSupport::requireCompanyId();
        $safeLimit = max(1, min(100, $limit));
        $safeOffset = max(0, $offset);
        $params = ['cid' => $companyId];
        $where = 'company_id = :cid AND deleted_at IS NULL';
        if ($search !== '') {
            $where .= ' AND (title LIKE :q OR code LIKE :q2)';
            $like = '%' . $search . '%';
            $params['q'] = $like;
            $params['q2'] = $like;
        }
        if ($workflowStatus !== null && $workflowStatus !== '') {
            $where .= ' AND workflow_status = :wf';
            $params['wf'] = $workflowStatus;
        }
        $totalRow = (new DmsDocument())->queryOne('SELECT COUNT(*) AS c FROM rateb_dms_documents WHERE ' . $where, $params);
        $items = (new DmsDocument())->query(
            'SELECT * FROM rateb_dms_documents WHERE ' . $where
            . ' ORDER BY updated_at DESC, id DESC LIMIT ' . $safeLimit . ' OFFSET ' . $safeOffset,
            $params
        );

        return ['items' => is_array($items) ? $items : [], 'total' => (int) ($totalRow['c'] ?? 0)];
    }

    /** @return array<string, mixed>|null */
    public function get(int $id): ?array
    {
        return DocumentManagementSupport::findDocument($id, DocumentManagementSupport::requireCompanyId());
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int, code: string}
     */
    public function create(array $input): array
    {
        $companyId = DocumentManagementSupport::requireCompanyId();
        $repositoryId = DocumentManagementSupport::intOrNull($input['repository_id'] ?? null);
        if ($repositoryId === null) {
            throw new \InvalidArgumentException('repository_required');
        }
        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '') {
            throw new \InvalidArgumentException('title_required');
        }
        $code = trim((string) ($input['code'] ?? ''));
        if ($code === '') {
            $code = DocumentManagementSupport::nextCode('rateb_dms_documents', 'DMS-DOC', $companyId);
        }
        $id = (new DmsDocument())->create(array_merge([
            'public_uuid' => DocumentManagementSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => DocumentManagementSupport::branchId(),
            'repository_id' => $repositoryId,
            'folder_id' => DocumentManagementSupport::intOrNull($input['folder_id'] ?? null),
            'category_id' => DocumentManagementSupport::intOrNull($input['category_id'] ?? null),
            'code' => substr($code, 0, 40),
            'title' => substr($title, 0, 190),
            'title_ar' => DocumentManagementSupport::nullIfEmpty($input['title_ar'] ?? null),
            'document_type' => DocumentManagementSupport::nullIfEmpty($input['document_type'] ?? null),
            'mime_type' => DocumentManagementSupport::nullIfEmpty($input['mime_type'] ?? null),
            'file_extension' => DocumentManagementSupport::nullIfEmpty($input['file_extension'] ?? null),
            'current_version_no' => 1,
            'workflow_status' => 'draft',
            'status' => 'active',
            'legacy_document_id' => DocumentManagementSupport::intOrNull($input['legacy_document_id'] ?? null),
            'notes' => DocumentManagementSupport::nullIfEmpty($input['notes'] ?? null),
            'version' => 1,
        ], DocumentManagementSupport::actorFields(true)));

        (new DmsVersion())->create(array_merge([
            'public_uuid' => DocumentManagementSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => DocumentManagementSupport::branchId(),
            'document_id' => (int) $id,
            'version_no' => 1,
            'storage_path' => DocumentManagementSupport::nullIfEmpty($input['storage_path'] ?? null),
            'storage_driver' => substr((string) ($input['storage_driver'] ?? 'local'), 0, 40),
            'is_current' => 1,
            'status' => 'active',
            'version' => 1,
        ], DocumentManagementSupport::actorFields(true)));

        (new DmsSearchIndex())->create(array_merge([
            'public_uuid' => DocumentManagementSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => DocumentManagementSupport::branchId(),
            'document_id' => (int) $id,
            'title_index' => substr($title, 0, 190),
            'content_snippet' => DocumentManagementSupport::nullIfEmpty($input['content_snippet'] ?? null),
            'indexed_at' => date('Y-m-d H:i:s'),
            'status' => 'active',
            'version' => 1,
        ], DocumentManagementSupport::actorFields(true)));

        (new DocumentTimelineService())->record('document_created', 'Document: ' . $title, 'document', (int) $id);

        return ['id' => (int) $id, 'code' => $code];
    }
}

final class DmsVersionService
{
    /**
     * @return array{items: list<array<string,mixed>>, total: int}
     */
    public function listForDocument(int $documentId, int $limit = 25, int $offset = 0): array
    {
        $companyId = DocumentManagementSupport::requireCompanyId();
        DocumentManagementSupport::assertDocument($documentId, $companyId);
        $safeLimit = max(1, min(100, $limit));
        $safeOffset = max(0, $offset);
        $totalRow = (new DmsVersion())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_dms_versions WHERE company_id = :cid AND document_id = :did AND deleted_at IS NULL',
            ['cid' => $companyId, 'did' => $documentId]
        );
        $items = (new DmsVersion())->query(
            'SELECT * FROM rateb_dms_versions WHERE company_id = :cid AND document_id = :did AND deleted_at IS NULL'
            . ' ORDER BY version_no DESC LIMIT ' . $safeLimit . ' OFFSET ' . $safeOffset,
            ['cid' => $companyId, 'did' => $documentId]
        );

        return ['items' => is_array($items) ? $items : [], 'total' => (int) ($totalRow['c'] ?? 0)];
    }
}

final class DmsShareService
{
    /**
     * @return array{items: list<array<string,mixed>>, total: int}
     */
    public function list(int $limit = 25, int $offset = 0): array
    {
        $companyId = DocumentManagementSupport::requireCompanyId();
        $safeLimit = max(1, min(100, $limit));
        $safeOffset = max(0, $offset);
        $totalRow = (new DmsShare())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_dms_shares WHERE company_id = :cid AND deleted_at IS NULL',
            ['cid' => $companyId]
        );
        $items = (new DmsShare())->query(
            'SELECT * FROM rateb_dms_shares WHERE company_id = :cid AND deleted_at IS NULL'
            . ' ORDER BY created_at DESC LIMIT ' . $safeLimit . ' OFFSET ' . $safeOffset,
            ['cid' => $companyId]
        );

        return ['items' => is_array($items) ? $items : [], 'total' => (int) ($totalRow['c'] ?? 0)];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int, share_token: string}
     */
    public function create(array $input): array
    {
        $companyId = DocumentManagementSupport::requireCompanyId();
        $documentId = DocumentManagementSupport::intOrNull($input['document_id'] ?? null);
        if ($documentId === null) {
            throw new \InvalidArgumentException('document_required');
        }
        DocumentManagementSupport::assertDocument($documentId, $companyId);
        $token = DocumentManagementSupport::shareToken();
        $id = (new DmsShare())->create(array_merge([
            'public_uuid' => DocumentManagementSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => DocumentManagementSupport::branchId(),
            'document_id' => $documentId,
            'share_token' => $token,
            'shared_with_user_id' => DocumentManagementSupport::intOrNull($input['shared_with_user_id'] ?? null),
            'shared_with_email' => DocumentManagementSupport::nullIfEmpty($input['shared_with_email'] ?? null),
            'permission_level' => in_array((string) ($input['permission_level'] ?? 'view'), ['view', 'download', 'edit'], true)
                ? (string) $input['permission_level'] : 'view',
            'expires_at' => DocumentManagementSupport::dateOrNull($input['expires_at'] ?? null),
            'share_status' => 'active',
            'status' => 'active',
            'notes' => DocumentManagementSupport::nullIfEmpty($input['notes'] ?? null),
            'version' => 1,
        ], DocumentManagementSupport::actorFields(true)));

        (new DocumentTimelineService())->record('share_created', 'Share for document #' . $documentId, 'document', $documentId);

        return ['id' => (int) $id, 'share_token' => $token];
    }
}

final class DmsRetentionService
{
    /**
     * @return array{items: list<array<string,mixed>>, total: int}
     */
    public function listPolicies(int $limit = 25, int $offset = 0): array
    {
        $companyId = DocumentManagementSupport::requireCompanyId();
        $safeLimit = max(1, min(100, $limit));
        $safeOffset = max(0, $offset);
        $totalRow = (new DmsRetentionPolicy())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_dms_retention_policies WHERE company_id = :cid AND deleted_at IS NULL',
            ['cid' => $companyId]
        );
        $items = (new DmsRetentionPolicy())->query(
            'SELECT * FROM rateb_dms_retention_policies WHERE company_id = :cid AND deleted_at IS NULL'
            . ' ORDER BY name ASC LIMIT ' . $safeLimit . ' OFFSET ' . $safeOffset,
            ['cid' => $companyId]
        );

        return ['items' => is_array($items) ? $items : [], 'total' => (int) ($totalRow['c'] ?? 0)];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int, code: string}
     */
    public function createPolicy(array $input): array
    {
        $companyId = DocumentManagementSupport::requireCompanyId();
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('name_required');
        }
        $code = trim((string) ($input['code'] ?? ''));
        if ($code === '') {
            $code = DocumentManagementSupport::nextCode('rateb_dms_retention_policies', 'DMS-RET', $companyId);
        }
        $id = (new DmsRetentionPolicy())->create(array_merge([
            'public_uuid' => DocumentManagementSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => DocumentManagementSupport::branchId(),
            'code' => substr($code, 0, 40),
            'name' => substr($name, 0, 190),
            'name_ar' => DocumentManagementSupport::nullIfEmpty($input['name_ar'] ?? null),
            'retention_days' => max(1, (int) ($input['retention_days'] ?? 365)),
            'action_on_expiry' => in_array((string) ($input['action_on_expiry'] ?? 'archive'), ['archive', 'delete', 'review'], true)
                ? (string) $input['action_on_expiry'] : 'archive',
            'applies_to' => substr((string) ($input['applies_to'] ?? 'document'), 0, 60),
            'status' => 'active',
            'notes' => DocumentManagementSupport::nullIfEmpty($input['notes'] ?? null),
            'version' => 1,
        ], DocumentManagementSupport::actorFields(true)));

        (new DocumentTimelineService())->record('retention_policy_created', 'Retention: ' . $name, 'retention_policy', (int) $id);

        return ['id' => (int) $id, 'code' => $code];
    }
}

final class DmsLegalHoldService
{
    /**
     * @return array{items: list<array<string,mixed>>, total: int}
     */
    public function list(int $limit = 25, int $offset = 0): array
    {
        $companyId = DocumentManagementSupport::requireCompanyId();
        $safeLimit = max(1, min(100, $limit));
        $safeOffset = max(0, $offset);
        $totalRow = (new DmsLegalHold())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_dms_legal_holds WHERE company_id = :cid AND deleted_at IS NULL',
            ['cid' => $companyId]
        );
        $items = (new DmsLegalHold())->query(
            'SELECT * FROM rateb_dms_legal_holds WHERE company_id = :cid AND deleted_at IS NULL'
            . ' ORDER BY created_at DESC LIMIT ' . $safeLimit . ' OFFSET ' . $safeOffset,
            ['cid' => $companyId]
        );

        return ['items' => is_array($items) ? $items : [], 'total' => (int) ($totalRow['c'] ?? 0)];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int, code: string}
     */
    public function create(array $input): array
    {
        $companyId = DocumentManagementSupport::requireCompanyId();
        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '') {
            throw new \InvalidArgumentException('title_required');
        }
        $code = trim((string) ($input['code'] ?? ''));
        if ($code === '') {
            $code = DocumentManagementSupport::nextCode('rateb_dms_legal_holds', 'DMS-HOLD', $companyId);
        }
        $id = (new DmsLegalHold())->create(array_merge([
            'public_uuid' => DocumentManagementSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => DocumentManagementSupport::branchId(),
            'document_id' => DocumentManagementSupport::intOrNull($input['document_id'] ?? null),
            'folder_id' => DocumentManagementSupport::intOrNull($input['folder_id'] ?? null),
            'code' => substr($code, 0, 40),
            'title' => substr($title, 0, 190),
            'hold_reason' => DocumentManagementSupport::nullIfEmpty($input['hold_reason'] ?? null),
            'hold_status' => 'active',
            'effective_from' => DocumentManagementSupport::dateOrNull($input['effective_from'] ?? null),
            'effective_to' => DocumentManagementSupport::dateOrNull($input['effective_to'] ?? null),
            'status' => 'active',
            'notes' => DocumentManagementSupport::nullIfEmpty($input['notes'] ?? null),
            'version' => 1,
        ], DocumentManagementSupport::actorFields(true)));

        (new DocumentTimelineService())->record('legal_hold_created', 'Legal hold: ' . $title, 'legal_hold', (int) $id);

        return ['id' => (int) $id, 'code' => $code];
    }
}

final class DmsPermissionService
{
    /**
     * @return array{items: list<array<string,mixed>>, total: int}
     */
    public function list(int $limit = 25, int $offset = 0): array
    {
        $companyId = DocumentManagementSupport::requireCompanyId();
        $safeLimit = max(1, min(100, $limit));
        $safeOffset = max(0, $offset);
        $totalRow = (new DmsPermission())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_dms_permissions WHERE company_id = :cid AND deleted_at IS NULL',
            ['cid' => $companyId]
        );
        $items = (new DmsPermission())->query(
            'SELECT * FROM rateb_dms_permissions WHERE company_id = :cid AND deleted_at IS NULL'
            . ' ORDER BY created_at DESC LIMIT ' . $safeLimit . ' OFFSET ' . $safeOffset,
            ['cid' => $companyId]
        );

        return ['items' => is_array($items) ? $items : [], 'total' => (int) ($totalRow['c'] ?? 0)];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int}
     */
    public function grant(array $input): array
    {
        $companyId = DocumentManagementSupport::requireCompanyId();
        $resourceId = DocumentManagementSupport::intOrNull($input['resource_id'] ?? null);
        $granteeId = DocumentManagementSupport::intOrNull($input['grantee_id'] ?? null);
        if ($resourceId === null || $granteeId === null) {
            throw new \InvalidArgumentException('grant_fields_required');
        }
        $id = (new DmsPermission())->create(array_merge([
            'public_uuid' => DocumentManagementSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => DocumentManagementSupport::branchId(),
            'resource_type' => in_array((string) ($input['resource_type'] ?? 'document'), ['repository', 'folder', 'document'], true)
                ? (string) $input['resource_type'] : 'document',
            'resource_id' => $resourceId,
            'grantee_type' => in_array((string) ($input['grantee_type'] ?? 'user'), ['user', 'role', 'branch'], true)
                ? (string) $input['grantee_type'] : 'user',
            'grantee_id' => $granteeId,
            'permission_slug' => substr((string) ($input['permission_slug'] ?? 'documents.view'), 0, 60),
            'status' => 'active',
            'notes' => DocumentManagementSupport::nullIfEmpty($input['notes'] ?? null),
            'version' => 1,
        ], DocumentManagementSupport::actorFields(true)));

        return ['id' => (int) $id];
    }
}

final class DmsSearchService
{
    /**
     * @return array{items: list<array<string,mixed>>, total: int}
     */
    public function search(string $query, int $limit = 25, int $offset = 0): array
    {
        $companyId = DocumentManagementSupport::requireCompanyId();
        $safeLimit = max(1, min(100, $limit));
        $safeOffset = max(0, $offset);
        $q = trim($query);
        if ($q === '') {
            return ['items' => [], 'total' => 0];
        }
        $params = ['cid' => $companyId, 'q' => '%' . $q . '%'];
        $totalRow = (new DmsSearchIndex())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_dms_search_index'
            . ' WHERE company_id = :cid AND deleted_at IS NULL AND title_index LIKE :q',
            $params
        );
        $items = (new DmsSearchIndex())->query(
            'SELECT si.*, d.code, d.title, d.workflow_status FROM rateb_dms_search_index si'
            . ' INNER JOIN rateb_dms_documents d ON d.id = si.document_id AND d.company_id = si.company_id AND d.deleted_at IS NULL'
            . ' WHERE si.company_id = :cid AND si.deleted_at IS NULL AND si.title_index LIKE :q'
            . ' ORDER BY si.indexed_at DESC LIMIT ' . $safeLimit . ' OFFSET ' . $safeOffset,
            $params
        );

        return ['items' => is_array($items) ? $items : [], 'total' => (int) ($totalRow['c'] ?? 0)];
    }
}

final class DmsFavoriteService
{
    /**
     * @return array{items: list<array<string,mixed>>, total: int}
     */
    public function listForUser(?int $userId = null, int $limit = 25, int $offset = 0): array
    {
        $companyId = DocumentManagementSupport::requireCompanyId();
        $uid = $userId ?? DocumentManagementSupport::userId() ?? 0;
        if ($uid < 1) {
            return ['items' => [], 'total' => 0];
        }
        $safeLimit = max(1, min(100, $limit));
        $safeOffset = max(0, $offset);
        $totalRow = (new DmsFavorite())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_dms_favorites WHERE company_id = :cid AND user_id = :uid AND deleted_at IS NULL',
            ['cid' => $companyId, 'uid' => $uid]
        );
        $items = (new DmsFavorite())->query(
            'SELECT f.*, d.code, d.title FROM rateb_dms_favorites f'
            . ' INNER JOIN rateb_dms_documents d ON d.id = f.document_id AND d.company_id = f.company_id AND d.deleted_at IS NULL'
            . ' WHERE f.company_id = :cid AND f.user_id = :uid AND f.deleted_at IS NULL'
            . ' ORDER BY f.created_at DESC LIMIT ' . $safeLimit . ' OFFSET ' . $safeOffset,
            ['cid' => $companyId, 'uid' => $uid]
        );

        return ['items' => is_array($items) ? $items : [], 'total' => (int) ($totalRow['c'] ?? 0)];
    }

    public function add(int $documentId): void
    {
        $companyId = DocumentManagementSupport::requireCompanyId();
        $uid = DocumentManagementSupport::userId() ?? 0;
        if ($uid < 1) {
            throw new \RuntimeException('user_required');
        }
        DocumentManagementSupport::assertDocument($documentId, $companyId);
        $existing = (new DmsFavorite())->queryOne(
            'SELECT id FROM rateb_dms_favorites WHERE company_id = :cid AND user_id = :uid AND document_id = :did AND deleted_at IS NULL LIMIT 1',
            ['cid' => $companyId, 'uid' => $uid, 'did' => $documentId]
        );
        if (is_array($existing)) {
            return;
        }
        (new DmsFavorite())->create(array_merge([
            'public_uuid' => DocumentManagementSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => DocumentManagementSupport::branchId(),
            'user_id' => $uid,
            'document_id' => $documentId,
            'status' => 'active',
            'version' => 1,
        ], DocumentManagementSupport::actorFields(true)));
    }
}

final class DmsCategoryService
{
    /**
     * @return list<array<string, mixed>>
     */
    public function listActive(): array
    {
        $companyId = DocumentManagementSupport::requireCompanyId();
        $items = (new DmsCategory())->query(
            'SELECT * FROM rateb_dms_categories WHERE company_id = :cid AND deleted_at IS NULL AND status = :st ORDER BY name ASC',
            ['cid' => $companyId, 'st' => 'active']
        );

        return is_array($items) ? $items : [];
    }
}

final class DmsTagService
{
    /**
     * @return list<array<string, mixed>>
     */
    public function listActive(): array
    {
        $companyId = DocumentManagementSupport::requireCompanyId();
        $items = (new DmsTag())->query(
            'SELECT * FROM rateb_dms_tags WHERE company_id = :cid AND deleted_at IS NULL AND status = :st ORDER BY name ASC',
            ['cid' => $companyId, 'st' => 'active']
        );

        return is_array($items) ? $items : [];
    }
}

final class DmsCommentService
{
    /**
     * @param array<string, mixed> $input
     */
    public function create(array $input): void
    {
        $companyId = DocumentManagementSupport::requireCompanyId();
        $documentId = DocumentManagementSupport::intOrNull($input['document_id'] ?? null);
        $text = trim((string) ($input['comment_text'] ?? ''));
        if ($documentId === null || $text === '') {
            throw new \InvalidArgumentException('comment_fields_required');
        }
        DocumentManagementSupport::assertDocument($documentId, $companyId);
        (new DmsComment())->create(array_merge([
            'public_uuid' => DocumentManagementSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => DocumentManagementSupport::branchId(),
            'document_id' => $documentId,
            'comment_text' => $text,
            'status' => 'active',
            'version' => 1,
        ], DocumentManagementSupport::actorFields(true)));
    }
}

final class DmsDocumentLinkService
{
    /**
     * @param array<string, mixed> $input
     */
    public function link(array $input): void
    {
        $companyId = DocumentManagementSupport::requireCompanyId();
        $documentId = DocumentManagementSupport::intOrNull($input['document_id'] ?? null);
        $entityId = DocumentManagementSupport::intOrNull($input['linked_entity_id'] ?? null);
        $entityType = trim((string) ($input['linked_entity_type'] ?? ''));
        if ($documentId === null || $entityId === null || $entityType === '') {
            throw new \InvalidArgumentException('link_fields_required');
        }
        DocumentManagementSupport::assertDocument($documentId, $companyId);
        (new DmsDocumentLink())->create(array_merge([
            'public_uuid' => DocumentManagementSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => DocumentManagementSupport::branchId(),
            'document_id' => $documentId,
            'linked_entity_type' => substr($entityType, 0, 60),
            'linked_entity_id' => $entityId,
            'link_role' => DocumentManagementSupport::nullIfEmpty($input['link_role'] ?? null),
            'status' => 'active',
            'version' => 1,
        ], DocumentManagementSupport::actorFields(true)));
    }
}

final class DmsAuditLogService
{
    public function log(string $actionType, ?int $documentId = null, ?string $detail = null): void
    {
        $companyId = DocumentManagementSupport::requireCompanyId();
        (new DmsAuditLog())->create(array_merge([
            'public_uuid' => DocumentManagementSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => DocumentManagementSupport::branchId(),
            'document_id' => $documentId,
            'action_type' => substr($actionType, 0, 60),
            'actor_user_id' => DocumentManagementSupport::userId(),
            'detail_text' => DocumentManagementSupport::nullIfEmpty($detail),
            'status' => 'active',
            'version' => 1,
        ], DocumentManagementSupport::actorFields(true)));
    }
}
