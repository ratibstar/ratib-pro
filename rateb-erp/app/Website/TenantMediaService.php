<?php
declare(strict_types=1);

namespace Rateb\App\Website;

use Rateb\App\Services\AuditService;
use Rateb\App\Models\CmsMedia;

/**
 * Phase WEBSITE-03 — Tenant media (upload path + public resolve by company_id).
 */
final class TenantMediaService
{
    private const MAX_BYTES = 10485760;
    /** @var list<string> */
    private const ALLOWED_MIME = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        'application/pdf', 'video/mp4', 'video/webm',
    ];

    private TenantWebsiteRepository $repo;

    public function __construct(?TenantWebsiteRepository $repo = null)
    {
        $this->repo = $repo ?? new TenantWebsiteRepository();
    }

    /** @return array{ok:bool,path?:string,id?:int,error?:string} */
    public function upload(array $file, ?int $userId = null): array
    {
        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return ['ok' => false, 'error' => 'No file uploaded'];
        }
        $size = (int) ($file['size'] ?? 0);
        if ($size < 1 || $size > self::MAX_BYTES) {
            return ['ok' => false, 'error' => 'File too large (max 10MB)'];
        }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? (string) finfo_file($finfo, $file['tmp_name']) : '';
        if ($finfo) {
            finfo_close($finfo);
        }
        if (!in_array($mime, self::ALLOWED_MIME, true)) {
            return ['ok' => false, 'error' => 'File type not allowed'];
        }
        $ext = pathinfo((string) ($file['name'] ?? 'file'), PATHINFO_EXTENSION);
        $ext = preg_replace('/[^a-zA-Z0-9]/', '', $ext) ?: 'bin';
        if (strtolower($ext) === 'svg') {
            return ['ok' => false, 'error' => 'SVG uploads are disabled for security'];
        }

        $companyId = $this->repo->companyId();
        $dir = RATEB_STORAGE_PATH . '/cms-media/' . $companyId . '/' . date('Y/m');
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            return ['ok' => false, 'error' => 'Storage unavailable'];
        }
        $name = bin2hex(random_bytes(8)) . '.' . strtolower($ext);
        $full = $dir . '/' . $name;
        if (!move_uploaded_file($file['tmp_name'], $full)) {
            return ['ok' => false, 'error' => 'Upload failed'];
        }
        $relative = 'storage/cms-media/' . $companyId . '/' . date('Y/m') . '/' . $name;
        $data = [
            'file_name' => (string) ($file['name'] ?? $name),
            'file_path' => $relative,
            'mime_type' => $mime,
            'file_size' => $size,
            'uploaded_by' => $userId,
        ];
        if ($this->repo->scoped()) {
            $data['company_id'] = $companyId;
        }
        $model = new CmsMedia();
        $id = $model->create($data);
        (new AuditService())->log('cms_media_upload', 'cms_media', $id, [
            'file' => $relative,
            'company_id' => $companyId,
        ]);

        return ['ok' => true, 'path' => $relative, 'id' => $id];
    }

    /** @return array<string, mixed>|null */
    public function findByBasename(string $file): ?array
    {
        $file = basename($file);
        if (!preg_match('/^[a-zA-Z0-9._-]+$/', $file)) {
            return null;
        }
        [$where, $params] = $this->repo->companyWhere();
        $params['like'] = '%/' . $file;

        return $this->repo->fetchOne(
            "SELECT * FROM rateb_cms_media WHERE {$where} AND file_path LIKE :like ORDER BY id DESC LIMIT 1",
            $params
        );
    }

    public function absolutePathForRow(array $row): ?string
    {
        $this->repo->assertRowCompany($row, 'media');
        $rel = str_replace('\\', '/', (string) ($row['file_path'] ?? ''));
        $rel = ltrim($rel, '/');
        if (str_starts_with($rel, 'storage/')) {
            $rel = substr($rel, strlen('storage/'));
        }
        $full = rtrim((string) RATEB_STORAGE_PATH, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
        if (!is_file($full)) {
            return null;
        }

        return $full;
    }

    public function publicUrl(string $relativePath): string
    {
        return rateb_url('site/media/' . rawurlencode(basename($relativePath)));
    }
}
