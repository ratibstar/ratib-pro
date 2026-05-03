<?php
/**
 * Canonical filesystem root for uploads (worker documents, partner agency CVs, etc.).
 * Keep in sync with worker `api/workers/documents/upload.php` and partner CV storage.
 */
if (!function_exists('ratib_uploads_base_dir')) {
    /**
     * Absolute path with no trailing separator.
     */
    function ratib_uploads_base_dir(): string
    {
        if (defined('RATIB_UPLOADS_BASE')) {
            $v = constant('RATIB_UPLOADS_BASE');
            if (is_string($v) && trim($v) !== '') {
                return rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, trim($v)), DIRECTORY_SEPARATOR);
            }
        }
        $env = getenv('RATIB_UPLOADS_BASE');
        if ($env !== false && trim((string) $env) !== '') {
            return rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, trim((string) $env)), DIRECTORY_SEPARATOR);
        }

        $projectRoot = realpath(__DIR__ . DIRECTORY_SEPARATOR . '..');
        if ($projectRoot === false) {
            $projectRoot = dirname(__DIR__);
        }
        $default = $projectRoot . DIRECTORY_SEPARATOR . 'uploads';

        if (!is_dir($default)) {
            return $default;
        }
        if (@is_writable($default)) {
            $rp = realpath($default);

            return $rp !== false ? $rp : $default;
        }

        $parent = dirname($projectRoot);
        if ($parent !== '' && $parent !== '.' && $parent !== $projectRoot) {
            $fallback = $parent . DIRECTORY_SEPARATOR . 'ratib_uploads';
            if (!is_dir($fallback)) {
                @mkdir($fallback, 0775, true);
            }
            if (is_dir($fallback) && @is_writable($fallback)) {
                $rp = realpath($fallback);
                $use = $rp !== false ? $rp : $fallback;
                error_log('ratib_uploads_base_dir: project uploads/ not writable; using ' . $use);

                return $use;
            }
        }

        $rp = realpath($default);

        return $rp !== false ? $rp : $default;
    }
}
