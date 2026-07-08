<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Support;

final class CatalogSession
{
    public static function start(): void
    {
        if (PHP_SAPI === 'cli') {
            return;
        }

        if (defined('RATEB_CATALOG_NO_SESSION') && RATEB_CATALOG_NO_SESSION) {
            return;
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            if (session_name() === 'rateb_erp') {
                return;
            }

            session_write_close();
        }

        session_name('rateb_erp');
        self::ensureSavePath();

        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');

        if (PHP_VERSION_ID >= 70300) {
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'domain' => '',
                'secure' => $secure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        } else {
            session_set_cookie_params(0, '/', '', $secure, true);
        }

        session_start();
    }

    private static function ensureSavePath(): void
    {
        foreach (self::sessionSavePathCandidates() as $dir) {
            $resolved = realpath($dir);
            if ($resolved !== false && is_dir($resolved) && is_writable($resolved)) {
                session_save_path($resolved);

                return;
            }
        }

        error_log('RATEB catalog: shared ERP session path not found — catalog may not see ERP login state');
    }

    /**
     * @return list<string>
     */
    public static function sessionSavePathCandidates(): array
    {
        $candidates = [];

        $fromEnv = getenv('RATEB_ERP_SESSION_SAVE_PATH');
        if (is_string($fromEnv) && $fromEnv !== '') {
            $candidates[] = $fromEnv;
        }

        if (defined('RATEB_ERP_SESSION_SAVE_PATH') && (string) RATEB_ERP_SESSION_SAVE_PATH !== '') {
            $candidates[] = (string) RATEB_ERP_SESSION_SAVE_PATH;
        }

        $root = defined('RATEB_CATALOG_ROOT') ? (string) RATEB_CATALOG_ROOT : dirname(__DIR__, 3);
        $candidates[] = dirname($root) . '/rateb-erp/storage/sessions';
        $candidates[] = $root . '/../rateb-erp/storage/sessions';

        $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? (string) $_SERVER['DOCUMENT_ROOT'] : '';
        if ($docRoot !== '') {
            $candidates[] = rtrim(str_replace('\\', '/', $docRoot), '/') . '/rateb-erp/storage/sessions';
        }

        return array_values(array_unique($candidates));
    }
}
