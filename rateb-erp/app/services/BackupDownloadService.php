<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\Database;
use ZipArchive;

/**
 * Serve ERP database backups in formats that bypass Windows Defender / Chrome
 * false positives on SQL dumps (raw .sql and .sql.gz from phpMyAdmin are often blocked).
 */
final class BackupDownloadService
{
    public function backupDirectory(): string
    {
        $root = defined('RATEB_ROOT') ? (string) RATEB_ROOT : dirname(__DIR__, 2);

        return rtrim(str_replace('\\', '/', $root), '/') . '/storage/backups';
    }

    /** @return array<int, string> basenames sorted newest first */
    public function listBackupBasenames(): array
    {
        $dir = $this->backupDirectory();
        $files = is_dir($dir) ? (glob($dir . '/erp-*.sql.gz') ?: []) : [];
        if ($files === []) {
            return [];
        }
        usort($files, static fn ($a, $b) => filemtime($b) <=> filemtime($a));

        return array_values(array_map('basename', $files));
    }

    public function sendBackup(string $format = 'b64', bool $fresh = false, string $basename = ''): void
    {
        $format = $this->normalizeFormat($format);
        [$path, $cleanup] = $this->resolvePayloadPath($fresh, $basename);
        if ($path === null) {
            http_response_code(404);
            header('Content-Type: text/plain; charset=UTF-8');
            echo 'Backup not found';
            exit;
        }

        $stem = $this->friendlyStem($path);

        try {
            if ($format === 'b64') {
                $this->sendAsBase64($path, $stem);
            } elseif ($format === 'zip') {
                $this->sendAsZip($path, $stem);
            } else {
                $this->sendAsGzip($path, $stem . '.sql.gz');
            }
        } finally {
            if ($cleanup && is_file($path)) {
                @unlink($path);
            }
        }
    }

    public function sendStoredBackup(string $basename, string $format = 'b64'): void
    {
        $this->sendBackup($format, false, $basename);
    }

    public function sendLatestStoredBackup(string $format = 'b64'): void
    {
        $names = $this->listBackupBasenames();
        if ($names === []) {
            $this->sendBackup($format, true);
            return;
        }

        $this->sendBackup($format, false, $names[0]);
    }

    public function sendFreshBackup(string $format = 'b64'): void
    {
        $this->sendBackup($format, true);
    }

    /** @return array{0:?string,1:bool} path and whether caller should delete temp file */
    private function resolvePayloadPath(bool $fresh, string $basename): array
    {
        if ($basename !== '') {
            $path = $this->resolveStoredPath($basename);
            return [$path, false];
        }

        if (!$fresh) {
            $names = $this->listBackupBasenames();
            if ($names !== []) {
                return [$this->resolveStoredPath($names[0]), false];
            }
        }

        return [$this->createFreshGzipPath(), true];
    }

    private function createFreshGzipPath(): ?string
    {
        if (!function_exists('exec')) {
            $this->failFreshBackup('exec() معطّل على السيرفر — استخدم File Manager أو SFTP لنسخ الملف من storage/backups.');
        }

        $dbName = Database::resolvedDatabaseName();
        $host = getenv('RATEB_ERP_DB_HOST') ?: getenv('DB_HOST') ?: 'localhost';
        $user = getenv('RATEB_ERP_DB_USER') ?: getenv('DB_USER') ?: '';
        $pass = getenv('RATEB_ERP_DB_PASS') ?: getenv('DB_PASS') ?: '';

        if ($user === '') {
            $this->failFreshBackup('بيانات الاتصال بقاعدة البيانات غير مضبوطة.');
        }

        $dir = $this->backupDirectory();
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
            $this->failFreshBackup('تعذّر إنشاء مجلد storage/backups.');
        }

        $tmp = $dir . '/.download-' . bin2hex(random_bytes(8)) . '.sql.gz';
        $cmd = sprintf(
            'mysqldump --host=%s --user=%s %s %s 2>&1 | gzip > %s',
            escapeshellarg($host),
            escapeshellarg($user),
            $pass !== '' ? '--password=' . escapeshellarg($pass) : '',
            escapeshellarg($dbName),
            escapeshellarg($tmp)
        );

        exec($cmd, $output, $code);
        if ($code !== 0 || !is_file($tmp) || (filesize($tmp) ?: 0) < 100) {
            @unlink($tmp);
            $this->failFreshBackup('فشل mysqldump على السيرفر.');
        }

        return $tmp;
    }

    private function sendAsGzip(string $path, string $downloadName): void
    {
        $this->streamBytes((string) file_get_contents($path), $downloadName, 'application/gzip');
    }

    private function sendAsZip(string $path, string $stem): void
    {
        if (!class_exists(ZipArchive::class)) {
            $this->sendAsBase64($path, $stem);
            return;
        }

        $tmpZip = $this->backupDirectory() . '/.download-' . bin2hex(random_bytes(8)) . '.zip';
        $zip = new ZipArchive();
        if ($zip->open($tmpZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $this->sendAsBase64($path, $stem);
            return;
        }

        $zip->addFile($path, 'erp-backup.dat');
        $zip->close();

        $bytes = (string) file_get_contents($tmpZip);
        @unlink($tmpZip);
        $this->streamBytes($bytes, $stem . '.zip', 'application/zip');
    }

    private function sendAsBase64(string $path, string $stem): void
    {
        $raw = (string) file_get_contents($path);
        $encoded = chunk_split(base64_encode($raw), 76, "\n");
        $innerName = $stem . '.sql.gz';
        $body = implode("\n", [
            'RATEB_ERP_BACKUP_B64',
            'inner_file=' . $innerName,
            'restore_windows=انسخ المحتوى بعد السطر الفارغ إلى https://base64.guru/converter/decode/file ثم احفظ الملف باسم ' . $innerName,
            'restore_linux=tail -n +6 FILE.txt | tr -d "\\n" | base64 -d > ' . $innerName,
            '',
            rtrim($encoded),
            '',
        ]);

        $this->streamBytes($body, $stem . '-backup.txt', 'text/plain; charset=UTF-8');
    }

    private function resolveStoredPath(string $basename): ?string
    {
        $basename = basename($basename);
        if (!preg_match('/^erp-[a-zA-Z0-9_\-\.]+\-\d{8}-\d{6}\.sql\.gz$/', $basename)) {
            return null;
        }

        $path = $this->backupDirectory() . '/' . $basename;
        if (!is_file($path)) {
            return null;
        }

        $realDir = realpath($this->backupDirectory());
        $realFile = realpath($path);
        if ($realDir === false || $realFile === false || !str_starts_with($realFile, $realDir)) {
            return null;
        }

        return $realFile;
    }

    private function friendlyStem(string $path): string
    {
        $dbName = Database::resolvedDatabaseName();
        $safeDb = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $dbName) ?: 'erp';
        $mtime = date('Ymd-His', filemtime($path) ?: time());

        return $safeDb . '-' . $mtime;
    }

    private function normalizeFormat(string $format): string
    {
        $format = strtolower(trim($format));
        if (in_array($format, ['b64', 'base64', 'txt', 'safe'], true)) {
            return 'b64';
        }
        if (in_array($format, ['zip', 'gz', 'gzip', 'sql'], true)) {
            return $format === 'sql' ? 'gzip' : ($format === 'gz' ? 'gzip' : $format);
        }

        return 'b64';
    }

    private function streamBytes(string $bytes, string $downloadName, string $contentType): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: ' . $contentType);
        header('Content-Disposition: attachment; filename="' . $this->safeFilename($downloadName) . '"');
        header('Content-Length: ' . (string) strlen($bytes));
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
        header('X-Content-Type-Options: nosniff');

        echo $bytes;
        exit;
    }

    private function safeFilename(string $name): string
    {
        $name = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $name) ?? 'backup.txt';

        return substr($name, 0, 120);
    }

    private function failFreshBackup(string $message): void
    {
        http_response_code(503);
        header('Content-Type: text/plain; charset=UTF-8');
        echo $message;
        exit;
    }
}
