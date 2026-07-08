<?php

declare(strict_types=1);

/**
 * RATEB Platform Catalog — Control Panel bridge (paths, migrations).
 */
require_once dirname(__DIR__, 3) . '/config/env/directadmin_db.php';

function control_platform_catalog_root_candidates(): array
{
    $candidates = [
        dirname(__DIR__, 3) . '/rateb-platform-catalog',
        dirname(__DIR__, 2) . '/rateb-platform-catalog',
    ];
    $docRoot = (string) ($_SERVER['DOCUMENT_ROOT'] ?? '');
    if ($docRoot !== '') {
        $candidates[] = rtrim($docRoot, '/\\') . '/rateb-platform-catalog';
        $candidates[] = dirname(rtrim($docRoot, '/\\')) . '/rateb-platform-catalog';
    }

    $unique = [];
    foreach ($candidates as $path) {
        $norm = str_replace('\\', '/', $path);
        if (!in_array($norm, $unique, true)) {
            $unique[] = $norm;
        }
    }

    return $unique;
}

function control_platform_catalog_root_path(): string
{
    foreach (control_platform_catalog_root_candidates() as $path) {
        if (is_file($path . '/app/Core/Bootstrap.php')) {
            return $path;
        }
    }

    return control_platform_catalog_root_candidates()[0] ?? (dirname(__DIR__, 3) . '/rateb-platform-catalog');
}

function control_platform_catalog_is_installed(): bool
{
    return is_file(control_platform_catalog_root_path() . '/app/Core/Bootstrap.php');
}

function control_platform_catalog_migrate_token_expected(): string
{
    $root = control_platform_catalog_root_path();
    $tokenPaths = [
        $root . '/storage/deploy-migrate-token',
        $root . '/storage/.deploy-migrate-token',
        dirname($root) . '/rateb-erp/storage/deploy-migrate-token',
        dirname($root) . '/rateb-erp/storage/.deploy-migrate-token',
    ];
    foreach ($tokenPaths as $tokenFile) {
        if (is_file($tokenFile)) {
            $token = trim((string) file_get_contents($tokenFile));
            if ($token !== '') {
                return $token;
            }
        }
    }

    if (defined('RATEB_ERP_MIGRATE_TOKEN') && (string) RATEB_ERP_MIGRATE_TOKEN !== '') {
        return (string) RATEB_ERP_MIGRATE_TOKEN;
    }
    $fromEnv = getenv('RATEB_ERP_MIGRATE_TOKEN');
    if ($fromEnv !== false && trim((string) $fromEnv) !== '') {
        return trim((string) $fromEnv);
    }
    $cpanel = getenv('CPANEL_API_TOKEN');
    if ($cpanel !== false && trim((string) $cpanel) !== '') {
        return trim((string) $cpanel);
    }

    return '';
}

function control_platform_catalog_verify_migrate_token(?string $provided = null): bool
{
    $provided = trim($provided ?? (string) ($_SERVER['HTTP_X_RATEB_MIGRATE_TOKEN'] ?? ''));
    if ($provided === '') {
        return false;
    }
    $expected = control_platform_catalog_migrate_token_expected();
    if ($expected === '') {
        return false;
    }

    return hash_equals($expected, $provided);
}

/** @return list<string> */
function control_platform_catalog_run_migrations(): array
{
    if (!defined('RATEB_ENV_NO_SESSION')) {
        define('RATEB_ENV_NO_SESSION', true);
    }
    if (!defined('RATEB_CATALOG_NO_SESSION')) {
        define('RATEB_CATALOG_NO_SESSION', true);
    }

    $root = control_platform_catalog_root_path();
    require_once $root . '/app/Core/Bootstrap.php';
    \Rateb\PlatformCatalog\Core\Bootstrap::initMinimal($root);

    if (function_exists('set_time_limit')) {
        @set_time_limit(300);
    }

    return (new \Rateb\PlatformCatalog\Application\Services\MigrationService())->runAll();
}

function control_platform_catalog_admin_url(): string
{
    $base = defined('SITE_URL') && trim((string) SITE_URL) !== ''
        ? rtrim((string) SITE_URL, '/')
        : 'https://rateb.sa';

    return $base . '/rateb-platform-catalog/admin';
}
