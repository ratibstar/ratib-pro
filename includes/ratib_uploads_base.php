<?php
/**
 * Canonical filesystem root for uploads (worker documents, partner agency CVs, etc.).
 * Keep in sync with worker `api/workers/documents/upload.php` and partner CV storage.
 */
if (!function_exists('ratib_uploads_project_root')) {
    function ratib_uploads_project_root(): string
    {
        $rp = realpath(__DIR__ . DIRECTORY_SEPARATOR . '..');
        if ($rp !== false) {
            return $rp;
        }

        return dirname(__DIR__);
    }
}

if (!function_exists('ratib_uploads_can_create_tree')) {
    /**
     * True when PHP can create subdirectories under this base (stricter than is_writable alone).
     */
    function ratib_uploads_can_create_tree(string $baseDir): bool
    {
        $baseDir = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $baseDir), DIRECTORY_SEPARATOR);
        if ($baseDir === '') {
            return false;
        }
        if (!is_dir($baseDir)) {
            @mkdir($baseDir, 0777, true);
        }
        if (!is_dir($baseDir) || !@is_writable($baseDir)) {
            return false;
        }
        $probe = $baseDir . DIRECTORY_SEPARATOR . '.ratib_mk_' . str_replace('.', '', uniqid('', true));
        if (@mkdir($probe, 0777, true)) {
            @rmdir($probe);

            return true;
        }

        return false;
    }
}

if (!function_exists('ratib_uploads_can_create_worker_subtree')) {
    /**
     * True when worker paths like workers/{id}/documents/{type}/ can be created under this base.
     * Catches hosts where uploads/ is "writable" but uploads/workers/ exists root-owned (mkdir fails).
     */
    function ratib_uploads_can_create_worker_subtree(string $baseDir): bool
    {
        $baseDir = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $baseDir), DIRECTORY_SEPARATOR);
        if (!ratib_uploads_can_create_tree($baseDir)) {
            return false;
        }
        $workers = $baseDir . DIRECTORY_SEPARATOR . 'workers';
        if (!is_dir($workers)) {
            if (!@mkdir($workers, 0777, true) && !is_dir($workers)) {
                return false;
            }
        }
        if (!@is_writable($workers)) {
            @chmod($workers, 0777);
        }
        if (!is_dir($workers) || !@is_writable($workers)) {
            return false;
        }
        $probe = $workers . DIRECTORY_SEPARATOR . '.ratib_mk_' . str_replace('.', '', uniqid('', true));
        if (@mkdir($probe, 0777, true)) {
            @rmdir($probe);

            return true;
        }

        return false;
    }
}

if (!function_exists('ratib_uploads_effective_marker_file')) {
    function ratib_uploads_effective_marker_file(): string
    {
        return ratib_uploads_project_root() . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . '.ratib_effective_upload_root';
    }
}

if (!function_exists('ratib_uploads_marker_path_allowed')) {
    /**
     * @param string $abs normalized absolute path
     */
    function ratib_uploads_marker_path_allowed(string $abs, string $projectRoot): bool
    {
        $abs = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $abs), DIRECTORY_SEPARATOR);
        $projectRoot = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $projectRoot), DIRECTORY_SEPARATOR);
        $parent = dirname($projectRoot);
        $tmp = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, sys_get_temp_dir()), DIRECTORY_SEPARATOR);
        $candidates = array_filter([$projectRoot, $parent, $tmp]);
        $realAbs = realpath($abs);
        $hay = $realAbs !== false ? $realAbs : $abs;
        foreach ($candidates as $pre) {
            $preNorm = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $pre), DIRECTORY_SEPARATOR);
            if ($preNorm === '') {
                continue;
            }
            $realPre = realpath($preNorm);
            $needle = $realPre !== false ? $realPre : $preNorm;
            if ($needle !== '' && strncmp($hay, $needle, strlen($needle)) === 0) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('ratib_uploads_read_valid_marker')) {
    function ratib_uploads_read_valid_marker(): ?string
    {
        $mf = ratib_uploads_effective_marker_file();
        if (!is_readable($mf)) {
            return null;
        }
        $raw = trim((string) file_get_contents($mf));
        if ($raw === '') {
            return null;
        }
        $p = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $raw), DIRECTORY_SEPARATOR);
        $projectRoot = ratib_uploads_project_root();
        if (!ratib_uploads_marker_path_allowed($p, $projectRoot)) {
            return null;
        }
        if (!is_dir($p) || !ratib_uploads_can_create_worker_subtree($p)) {
            return null;
        }
        $rp = realpath($p);

        return $rp !== false ? $rp : $p;
    }
}

if (!function_exists('ratib_uploads_write_marker')) {
    function ratib_uploads_write_marker(string $baseDir): void
    {
        $baseDir = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $baseDir), DIRECTORY_SEPARATOR);
        $projectRoot = ratib_uploads_project_root();
        if (!ratib_uploads_marker_path_allowed($baseDir, $projectRoot)) {
            return;
        }
        $mf = ratib_uploads_effective_marker_file();
        $dir = dirname($mf);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        if (!is_dir($dir) || !@is_writable($dir)) {
            return;
        }
        @file_put_contents($mf, $baseDir . "\n", LOCK_EX);
    }
}

if (!function_exists('ratib_uploads_candidate_base_dirs')) {
    /**
     * @return list<string>
     */
    function ratib_uploads_candidate_base_dirs(bool $appendTempFallback = true): array
    {
        $projectRoot = ratib_uploads_project_root();
        $default = $projectRoot . DIRECTORY_SEPARATOR . 'uploads';
        $parent = dirname($projectRoot);
        $out = [];
        $out[] = $default;
        if ($parent !== '' && $parent !== '.' && $parent !== $projectRoot) {
            $out[] = $parent . DIRECTORY_SEPARATOR . 'ratib_uploads';
        }
        $out[] = $projectRoot . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'ratib_uploads';
        if ($appendTempFallback) {
            $out[] = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, sys_get_temp_dir()), DIRECTORY_SEPARATOR)
                . DIRECTORY_SEPARATOR . 'ratib_uploads_' . substr(md5($projectRoot), 0, 10);
        }

        $uniq = [];
        foreach ($out as $p) {
            $p = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $p), DIRECTORY_SEPARATOR);
            if ($p === '' || isset($uniq[$p])) {
                continue;
            }
            $uniq[$p] = true;
        }

        return array_keys($uniq);
    }
}

if (!function_exists('ratib_uploads_pick_base_for_worker_document')) {
    /**
     * Find a writable root by actually creating workers/{id}/documents/{type}/ on disk.
     * Persists the first working root so ratib_uploads_base_dir() matches for reads/downloads.
     *
     * @throws RuntimeException when no root works
     */
    function ratib_uploads_pick_base_for_worker_document(int $workerId, string $docType): string
    {
        if ($workerId <= 0) {
            throw new RuntimeException('Invalid worker id');
        }
        $dt = preg_replace('/[^a-z0-9_]/', '', strtolower($docType));
        if ($dt === '') {
            throw new RuntimeException('Invalid document type');
        }
        $rel = 'workers' . DIRECTORY_SEPARATOR . $workerId . DIRECTORY_SEPARATOR . 'documents' . DIRECTORY_SEPARATOR . $dt;
        $ordered = [];
        $marker = ratib_uploads_read_valid_marker();
        if ($marker !== null) {
            $ordered[] = $marker;
        }
        foreach (ratib_uploads_candidate_base_dirs(true) as $b) {
            $ordered[] = $b;
        }
        $seen = [];
        $last = '';
        foreach ($ordered as $base) {
            $base = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $base), DIRECTORY_SEPARATOR);
            if ($base === '' || isset($seen[$base])) {
                continue;
            }
            $seen[$base] = true;
            $leaf = $base . DIRECTORY_SEPARATOR . $rel;
            try {
                ratib_uploads_ensure_dir($leaf);
                ratib_uploads_write_marker($base);
                $rp = realpath($base);

                return $rp !== false ? $rp : $base;
            } catch (RuntimeException $e) {
                if ($marker !== null && $base === $marker) {
                    @unlink(ratib_uploads_effective_marker_file());
                }
                $last = $e->getMessage();
            }
        }
        throw new RuntimeException(
            $last !== '' ? $last : 'No writable upload directory found for this worker.'
        );
    }
}

if (!function_exists('ratib_uploads_base_dir')) {
    /**
     * Absolute path with no trailing separator.
     */
    function ratib_uploads_base_dir(): string
    {
        if (defined('RATIB_UPLOADS_BASE')) {
            $v = constant('RATIB_UPLOADS_BASE');
            if (is_string($v) && trim($v) !== '') {
                $p = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, trim($v)), DIRECTORY_SEPARATOR);
                if (!is_dir($p)) {
                    @mkdir($p, 0777, true);
                }
                if (is_dir($p) && @is_writable($p) && ratib_uploads_can_create_worker_subtree($p)) {
                    $rp = realpath($p);

                    return $rp !== false ? $rp : $p;
                }
                error_log('RATIB_UPLOADS_BASE is set but not usable for worker upload folders: ' . $p . '; using automatic fallback.');
            }
        }
        $env = getenv('RATIB_UPLOADS_BASE');
        if ($env !== false && trim((string) $env) !== '') {
            $p = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, trim((string) $env)), DIRECTORY_SEPARATOR);
            if (!is_dir($p)) {
                @mkdir($p, 0777, true);
            }
            if (is_dir($p) && @is_writable($p) && ratib_uploads_can_create_worker_subtree($p)) {
                $rp = realpath($p);

                return $rp !== false ? $rp : $p;
            }
            error_log('RATIB_UPLOADS_BASE env is set but not usable for worker upload folders: ' . $p . '; using automatic fallback.');
        }

        $marked = ratib_uploads_read_valid_marker();
        if ($marked !== null) {
            return $marked;
        }

        $projectRoot = ratib_uploads_project_root();
        $default = $projectRoot . DIRECTORY_SEPARATOR . 'uploads';
        if (!is_dir($default)) {
            @mkdir($default, 0777, true);
        }

        foreach (ratib_uploads_candidate_base_dirs(true) as $path) {
            if (!is_dir($path)) {
                @mkdir($path, 0777, true);
            }
            if (ratib_uploads_can_create_worker_subtree($path)) {
                $rp = realpath($path);
                $use = $rp !== false ? $rp : $path;
                if ($path !== $default) {
                    error_log('ratib_uploads_base_dir: using automatic candidate at ' . $use);
                }

                return $use;
            }
        }

        error_log('ratib_uploads_base_dir: no verified writable root; returning project uploads path (may still fail for uploads).');
        $rp = realpath($default);

        return $rp !== false ? $rp : $default;
    }
}

if (!function_exists('ratib_uploads_ensure_dir')) {
    /**
     * Create a directory tree under the uploads base (0777 for shared hosting).
     *
     * @throws RuntimeException when the path cannot be created or is not writable
     */
    function ratib_uploads_ensure_dir(string $absoluteDir): void
    {
        $absoluteDir = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $absoluteDir), DIRECTORY_SEPARATOR);
        if ($absoluteDir === '') {
            throw new RuntimeException('Empty upload directory path');
        }
        if (is_dir($absoluteDir)) {
            if (!@is_writable($absoluteDir)) {
                @chmod($absoluteDir, 0777);
            }
            if (!@is_writable($absoluteDir)) {
                throw new RuntimeException('Upload directory is not writable: ' . $absoluteDir);
            }

            return;
        }
        if (!@mkdir($absoluteDir, 0777, true) && !is_dir($absoluteDir)) {
            $extra = '';
            if (function_exists('error_get_last')) {
                $le = error_get_last();
                if (is_array($le) && !empty($le['message'])) {
                    $extra = ' (' . (string) $le['message'] . ')';
                }
            }
            throw new RuntimeException('Could not create upload directory: ' . $absoluteDir . $extra);
        }
        if (!@is_writable($absoluteDir)) {
            @chmod($absoluteDir, 0777);
        }
        if (!@is_writable($absoluteDir)) {
            throw new RuntimeException('Upload directory was created but is not writable: ' . $absoluteDir);
        }
    }
}
