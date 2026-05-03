<?php
/**
 * Canonical filesystem root for uploads (worker documents, partner agency CVs, etc.).
 * Keep in sync with worker `api/workers/documents/upload.php` and partner CV storage.
 */
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

        $projectRoot = realpath(__DIR__ . DIRECTORY_SEPARATOR . '..');
        if ($projectRoot === false) {
            $projectRoot = dirname(__DIR__);
        }
        $default = $projectRoot . DIRECTORY_SEPARATOR . 'uploads';
        if (!is_dir($default)) {
            @mkdir($default, 0777, true);
        }

        $parent = dirname($projectRoot);
        $candidates = [];
        $candidates[] = [$default, 'project uploads'];
        if ($parent !== '' && $parent !== '.' && $parent !== $projectRoot) {
            $candidates[] = [$parent . DIRECTORY_SEPARATOR . 'ratib_uploads', 'sibling ratib_uploads'];
        }
        $candidates[] = [
            $projectRoot . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'ratib_uploads',
            'project storage/ratib_uploads',
        ];

        foreach ($candidates as $pair) {
            $path = $pair[0];
            $label = $pair[1];
            if (!is_dir($path)) {
                @mkdir($path, 0777, true);
            }
            if (ratib_uploads_can_create_worker_subtree($path)) {
                $rp = realpath($path);
                $use = $rp !== false ? $rp : $path;
                if ($path !== $default) {
                    error_log('ratib_uploads_base_dir: using ' . $label . ' at ' . $use);
                }

                return $use;
            }
        }

        error_log(
            'ratib_uploads_base_dir: no writable worker upload root found; last tried project uploads. Set RATIB_UPLOADS_BASE to a writable path.'
        );
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
