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
            return;
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
        $root = defined('RATEB_CATALOG_ROOT') ? (string) RATEB_CATALOG_ROOT : dirname(__DIR__, 3);
        $candidates = [
            dirname($root) . '/rateb-erp/storage/sessions',
            $root . '/../rateb-erp/storage/sessions',
        ];

        foreach ($candidates as $dir) {
            $resolved = realpath($dir);
            if ($resolved !== false && is_dir($resolved) && is_writable($resolved)) {
                session_save_path($resolved);

                return;
            }
        }
    }
}
