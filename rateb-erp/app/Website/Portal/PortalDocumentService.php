<?php
declare(strict_types=1);

namespace Rateb\App\Website\Portal;

use Rateb\App\Website\TenantMediaService;
use Rateb\App\Website\TenantWebsiteRepository;

/**
 * Phase WEBSITE-07 — Document center (tenant-isolated; optional ERP DocumentService link).
 */
final class PortalDocumentService
{
    private TenantWebsiteRepository $repo;

    public function __construct(?TenantWebsiteRepository $repo = null)
    {
        $this->repo = $repo ?? new TenantWebsiteRepository();
    }

    /**
     * @param array<string, mixed> $file
     * @return array{ok: bool, id?: int, error?: string}
     */
    public function upload(array $portalUser, array $file, string $category = 'attachment', string $title = ''): array
    {
        $upload = (new TenantMediaService($this->repo))->upload($file);
        if (($upload['ok'] ?? false) !== true) {
            return ['ok' => false, 'error' => (string) ($upload['error'] ?? 'upload_failed')];
        }
        $title = trim($title);
        if ($title === '') {
            $title = (string) ($file['name'] ?? 'Document');
        }
        $this->repo->execute(
            'INSERT INTO rateb_website_portal_documents
             (company_id, portal_user_id, doc_category, title, media_id, file_path, erp_document_id, mime_type, file_size)
             VALUES (:cid, :uid, :cat, :title, :mid, :path, :erp, :mime, :size)',
            [
                'cid' => $this->repo->companyId(),
                'uid' => (int) $portalUser['id'],
                'cat' => $this->normalizeCategory($category),
                'title' => $title,
                'mid' => (int) ($upload['id'] ?? 0) ?: null,
                'path' => (string) ($upload['path'] ?? ''),
                'erp' => null,
                'mime' => null,
                'size' => (int) ($file['size'] ?? 0) ?: null,
            ]
        );

        return ['ok' => true, 'id' => (int) $this->repo->lastInsertId()];
    }

    /** @return list<array<string, mixed>> */
    public function listForUser(int $portalUserId, ?string $category = null): array
    {
        [$where, $params] = $this->repo->companyWhere();
        $params['uid'] = $portalUserId;
        $sql = "SELECT * FROM rateb_website_portal_documents WHERE {$where} AND portal_user_id = :uid AND status = 'active'";
        if ($category !== null && $category !== '') {
            $sql .= ' AND doc_category = :cat';
            $params['cat'] = $category;
        }
        $sql .= ' ORDER BY id DESC LIMIT 200';

        return $this->repo->fetchAll($sql, $params);
    }

    /** @return array<string, mixed>|null */
    public function findForUser(int $id, int $portalUserId): ?array
    {
        [$where, $params] = $this->repo->companyWhere();
        $params['id'] = $id;
        $params['uid'] = $portalUserId;

        return $this->repo->fetchOne(
            "SELECT * FROM rateb_website_portal_documents WHERE {$where} AND id = :id AND portal_user_id = :uid LIMIT 1",
            $params
        );
    }

    private function normalizeCategory(string $category): string
    {
        $allowed = ['contract', 'invoice', 'visa', 'passport', 'cv', 'certificate', 'letter', 'attachment'];

        return in_array($category, $allowed, true) ? $category : 'attachment';
    }
}
