<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Support;

/**
 * Catalog admin session (platform_user_id) — separate cookie from ERP (rateb_erp).
 */
final class CatalogSession
{
    public const COOKIE_NAME = 'rateb_catalog';

    public static function start(): void
    {
        if (PHP_SAPI === 'cli') {
            return;
        }

        if (defined('RATEB_CATALOG_NO_SESSION') && RATEB_CATALOG_NO_SESSION) {
            return;
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            if (session_name() === self::COOKIE_NAME) {
                return;
            }

            session_write_close();
        }

        session_name(self::COOKIE_NAME);
        self::ensureCatalogSavePath();

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

    private static function ensureCatalogSavePath(): void
    {
        $root = defined('RATEB_CATALOG_ROOT') ? (string) RATEB_CATALOG_ROOT : dirname(__DIR__, 3);
        $storageRoot = defined('RATEB_PLATFORM_CATALOG_STORAGE_PATH')
            ? (string) RATEB_PLATFORM_CATALOG_STORAGE_PATH
            : $root . '/storage';
        $dir = $storageRoot . '/sessions';

        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $resolved = realpath($dir);
        if ($resolved !== false && is_dir($resolved) && is_writable($resolved)) {
            session_save_path($resolved);

            return;
        }

        error_log('RATEB catalog: unable to use catalog session storage at ' . $dir);
    }

    /**
     * @return list<string>
     */
    public static function erpSessionSavePathCandidates(): array
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
            $docRoot = rtrim(str_replace('\\', '/', $docRoot), '/');
            $candidates[] = $docRoot . '/rateb-erp/storage/sessions';
            $candidates[] = dirname($docRoot) . '/rateb-erp/storage/sessions';
        }

        return array_values(array_unique($candidates));
    }
}
