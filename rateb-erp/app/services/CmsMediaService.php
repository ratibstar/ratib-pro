<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\CmsMedia;

final class CmsMediaService
{
    private const MAX_BYTES = 10485760;
    /** @var array<int, string> */
    private const ALLOWED_MIME = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml',
        'application/pdf', 'video/mp4', 'video/webm',
    ];

    /** @return array{ok:bool,path?:string,error?:string} */
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
        $dir = RATEB_STORAGE_PATH . '/cms-media/' . date('Y/m');
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            return ['ok' => false, 'error' => 'Storage unavailable'];
        }
        $name = bin2hex(random_bytes(8)) . '.' . strtolower($ext);
        $full = $dir . '/' . $name;
        if (!move_uploaded_file($file['tmp_name'], $full)) {
            return ['ok' => false, 'error' => 'Upload failed'];
        }
        $relative = 'storage/cms-media/' . date('Y/m') . '/' . $name;
        $model = new CmsMedia();
        $id = $model->create([
            'file_name' => (string) ($file['name'] ?? $name),
            'file_path' => $relative,
            'mime_type' => $mime,
            'file_size' => $size,
            'uploaded_by' => $userId,
        ]);
        (new AuditService())->log('cms_media_upload', 'cms_media', $id, ['file' => $relative]);
        return ['ok' => true, 'path' => $relative, 'id' => $id];
    }

    public function publicUrl(string $relativePath): string
    {
        return rateb_url('site/media/' . rawurlencode(basename($relativePath)));
    }
}
