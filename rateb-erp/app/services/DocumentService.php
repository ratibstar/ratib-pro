<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\SessionManager;
use Rateb\App\Core\TenantContext;

final class DocumentService
{
    /** @var array<int, string> */
    private const ALLOWED_MIMES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/webp',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    /** @return array{success:bool,path?:string,error?:string} */
    public function storeUpload(array $file, string $entityType, int $entityId, string $title = ''): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return ['success' => false, 'error' => 'Upload failed'];
        }

        $size = (int) ($file['size'] ?? 0);
        if ($size < 1 || $size > 10 * 1024 * 1024) {
            return ['success' => false, 'error' => 'File too large (max 10MB)'];
        }

        $companyId = TenantContext::companyId();
        if ($companyId === null && !TenantContext::isSuperAdmin()) {
            return ['success' => false, 'error' => 'Company context required'];
        }
        if ($companyId !== null && !(new PlanLimitService())->canUploadBytes((int) $companyId, $size)) {
            return ['success' => false, 'error' => __('storage_limit_exceeded')];
        }
        if ($companyId === null) {
            return ['success' => false, 'error' => 'Company context required'];
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file((string) ($file['tmp_name'] ?? '')) ?: '';
        if (!in_array($mime, self::ALLOWED_MIMES, true)) {
            return ['success' => false, 'error' => 'File type not allowed'];
        }

        $ext = pathinfo((string) ($file['name'] ?? 'file'), PATHINFO_EXTENSION);
        $safeName = bin2hex(random_bytes(8)) . ($ext !== '' ? '.' . preg_replace('/[^a-zA-Z0-9]/', '', $ext) : '');
        $subdir = 'company_' . $companyId . '/' . preg_replace('/[^a-z0-9_\-]/i', '_', $entityType);
        $destDir = RATEB_STORAGE_PATH . '/uploads/' . $subdir;
        if (!is_dir($destDir)) {
            @mkdir($destDir, 0755, true);
        }

        $relative = 'uploads/' . $subdir . '/' . $safeName;
        $full = RATEB_STORAGE_PATH . '/' . $relative;
        if (!move_uploaded_file((string) $file['tmp_name'], $full)) {
            return ['success' => false, 'error' => 'Could not save file'];
        }

        $db = \Rateb\App\Core\Database::connection();
        $db->prepare(
            'INSERT INTO rateb_documents (company_id, entity_type, entity_id, title, file_name, file_path, mime_type, file_size, uploaded_by)
             VALUES (:cid, :et, :eid, :title, :fn, :fp, :mime, :sz, :uid)'
        )->execute([
            'cid' => (int) $companyId,
            'et' => $entityType,
            'eid' => $entityId,
            'title' => $title !== '' ? $title : (string) ($file['name'] ?? $safeName),
            'fn' => (string) ($file['name'] ?? $safeName),
            'fp' => $relative,
            'mime' => $mime,
            'sz' => $size,
            'uid' => SessionManager::get('rateb_user_id'),
        ]);

        return ['success' => true, 'path' => $relative];
    }

    /** @return array<int, array<string, mixed>> */
    public function listForEntity(string $entityType, int $entityId, int $companyId): array
    {
        $db = \Rateb\App\Core\Database::connection();
        $stmt = $db->prepare(
            'SELECT * FROM rateb_documents WHERE company_id = :cid AND entity_type = :et AND entity_id = :eid ORDER BY id DESC'
        );
        $stmt->execute(['cid' => $companyId, 'et' => $entityType, 'eid' => $entityId]);
        return $stmt->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        if ($id < 1) {
            return null;
        }
        $db = \Rateb\App\Core\Database::connection();
        $stmt = $db->prepare('SELECT * FROM rateb_documents WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** @return array<string, mixed>|null */
    public function latestForEntity(int $companyId, string $entityType, int $entityId): ?array
    {
        if ($companyId < 1 || $entityId < 1) {
            return null;
        }
        $rows = $this->listForEntity($entityType, $entityId, $companyId);
        return $rows[0] ?? null;
    }

    public function sendDownload(int $documentId): void
    {
        $doc = $this->findById($documentId);
        if (!$doc) {
            http_response_code(404);
            echo 'Not found';
            return;
        }
        if (!$this->canDownload($doc)) {
            http_response_code(403);
            echo __('access_denied');
            return;
        }
        $relative = (string) ($doc['file_path'] ?? '');
        if ($relative === '' || strpos($relative, '..') !== false) {
            http_response_code(404);
            echo 'Not found';
            return;
        }
        $full = RATEB_STORAGE_PATH . '/' . ltrim($relative, '/');
        if (!is_file($full)) {
            http_response_code(404);
            echo 'File missing';
            return;
        }
        $mime = (string) ($doc['mime_type'] ?? 'application/octet-stream');
        $name = (string) ($doc['file_name'] ?? basename($full));
        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="' . $this->safeFilename($name) . '"');
        header('Content-Length: ' . (string) filesize($full));
        readfile($full);
        exit;
    }

    /** @param array<string, mixed> $doc */
    private function canDownload(array $doc): bool
    {
        if (!\Rateb\App\Core\Auth::check()) {
            return false;
        }
        if (TenantContext::isSuperAdmin()) {
            return true;
        }
        $sessionCompany = (int) SessionManager::get('rateb_company_id', 0);
        return $sessionCompany > 0 && $sessionCompany === (int) ($doc['company_id'] ?? 0);
    }

    private function safeFilename(string $name): string
    {
        $name = preg_replace('/[^\w\.\-]+/u', '_', $name) ?? 'file';
        return $name !== '' ? $name : 'file';
    }
}
