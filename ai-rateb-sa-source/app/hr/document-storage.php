<?php
declare(strict_types=1);

/** @return list<string,string> */
function hr_document_allowed_extensions(): array
{
    return [
        'pdf' => 'application/pdf',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'webp' => 'image/webp',
    ];
}

function hr_documents_storage_dir(int $companyUserId): string
{
  $root = dirname(__DIR__, 2) . '/storage/hr/' . $companyUserId;
  if (!is_dir($root)) {
      mkdir($root, 0750, true);
  }
  return $root;
}

/** @param array{error?:int,name?:string,tmp_name?:string,size?:int} $file */
function hr_document_store_upload(int $companyUserId, array $file): array
{
    $max = 5 * 1024 * 1024;
    if (!isset($file['error'], $file['tmp_name'], $file['size']) || (int) $file['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'code' => 'upload'];
    }
    if (!is_uploaded_file((string) $file['tmp_name'])) {
        return ['ok' => false, 'code' => 'upload'];
    }
    $size = (int) $file['size'];
    if ($size < 1 || $size > $max) {
        return ['ok' => false, 'code' => 'size'];
    }
    $orig = (string) ($file['name'] ?? 'document');
    $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
    $map = hr_document_allowed_extensions();
    if (!isset($map[$ext])) {
        return ['ok' => false, 'code' => 'type'];
    }
    $bytes = file_get_contents((string) $file['tmp_name']);
    if ($bytes === false || strlen($bytes) !== $size) {
        return ['ok' => false, 'code' => 'upload'];
    }
    if (preg_match('/<\?(php|=)|<script/i', $bytes)) {
        return ['ok' => false, 'code' => 'type'];
    }
    $stored = bin2hex(random_bytes(16)) . '.' . $ext;
    $dir = hr_documents_storage_dir($companyUserId);
    $full = $dir . '/' . $stored;
    if (file_put_contents($full, $bytes) === false) {
        return ['ok' => false, 'code' => 'upload'];
    }
    $relative = 'hr/' . $companyUserId . '/' . $stored;
    return ['ok' => true, 'path' => $relative, 'label' => $orig !== '' ? $orig : $stored];
}

function hr_document_unlink(?string $storagePath): void
{
    if ($storagePath === null || $storagePath === '' || str_contains($storagePath, '..')) {
        return;
    }
    $full = dirname(__DIR__, 2) . '/storage/' . ltrim(str_replace('\\', '/', $storagePath), '/');
    if (is_file($full)) {
        @unlink($full);
    }
}

function hr_document_resolve_full_path(string $storagePath): ?string
{
    if ($storagePath === '' || str_contains($storagePath, '..') || !preg_match('#^hr/\d+/[a-z0-9._-]+$#i', $storagePath)) {
        return null;
    }
    $full = dirname(__DIR__, 2) . '/storage/' . $storagePath;
    return is_file($full) ? $full : null;
}